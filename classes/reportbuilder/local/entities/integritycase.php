<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_integritycase\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\{date, select};
use core_reportbuilder\local\report\{column, filter};
use lang_string;
use local_integritycase\local\case_manager;
use local_integritycase\local\workflow;

/**
 * Report builder entity for integrity cases.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class integritycase extends base {
    /**
     * Tables used by the entity.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['local_integritycase'];
    }

    /**
     * Entity title.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entitycase', 'local_integritycase');
    }

    /**
     * Add the columns, filters and conditions.
     *
     * @return base
     */
    public function initialise(): base {
        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }
        foreach ($this->get_all_filters() as $filter) {
            $this->add_filter($filter)->add_condition($filter);
        }
        return $this;
    }

    /**
     * Columns.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $alias = $this->get_table_alias('local_integritycase');
        $columns = [];
        $columns[] = (new column('stage', new lang_string('stage', 'local_integritycase'), $this->get_entity_name()))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.stage")
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $stage): string => $stage === null ? '' : case_manager::stage_name($stage));
        $columns[] = (new column('outcome', new lang_string('outcome', 'local_integritycase'), $this->get_entity_name()))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.outcome")
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $outcome): string => $outcome && workflow::is_outcome($outcome)
                ? get_string("outcome:$outcome", 'local_integritycase') : '');
        foreach (['timecreated', 'timemodified', 'timeclosed'] as $name) {
            $field = $name;
            $columns[] = (new column($name, new lang_string(
                $name === 'timeclosed' ? 'timeclosed' : $name,
                $name === 'timeclosed' ? 'local_integritycase' : 'core_reportbuilder'
            ), $this->get_entity_name()))
                ->add_joins($this->get_joins())
                ->set_type(column::TYPE_TIMESTAMP)
                ->add_field("{$alias}.{$field}")
                ->set_is_sortable(true)
                ->add_callback(static fn(?int $value): string => $value ? userdate($value) : '');
        }
        $columns[] = (new column(
            'studentvisible',
            new lang_string('studentvisible', 'local_integritycase'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$alias}.studentvisible")
            ->set_is_sortable(true)
            ->add_callback([\core_reportbuilder\local\helpers\format::class, 'boolean_as_text']);
        return $columns;
    }

    /**
     * Filters, also used as conditions.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $alias = $this->get_table_alias('local_integritycase');
        $filters = [];
        $filters[] = (new filter(
            select::class,
            'stage',
            new lang_string('stage', 'local_integritycase'),
            $this->get_entity_name(),
            "{$alias}.stage"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static function (): array {
                $options = [];
                foreach (workflow::stages() as $stage) {
                    $options[$stage] = case_manager::stage_name($stage);
                }
                return $options;
            });
        $filters[] = (new filter(
            select::class,
            'outcome',
            new lang_string('outcome', 'local_integritycase'),
            $this->get_entity_name(),
            "{$alias}.outcome"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static function (): array {
                $options = [];
                foreach (workflow::outcomes() as $outcome) {
                    $options[$outcome] = get_string("outcome:$outcome", 'local_integritycase');
                }
                return $options;
            });
        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$alias}.timecreated"
        ))
            ->add_joins($this->get_joins());
        return $filters;
    }
}
