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

namespace local_integritycase\local;

/**
 * The stages a case moves through, and which moves staff may make.
 *
 * The student never sees a case while staff look into it. It becomes visible the first time it
 * reaches a stage that asks something of the student or tells them a decision.
 *
 * @package    local_integritycase
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class workflow {
    /** @var string Opened, nobody has looked yet. */
    public const REPORTED = 'reported';
    /** @var string Staff are looking into it. */
    public const INVESTIGATING = 'investigating';
    /** @var string The student has been asked for their side. */
    public const AWAITING = 'awaitingresponse';
    /** @var string An outcome has been recorded. */
    public const DECIDED = 'decided';
    /** @var string The student appealed the decision. */
    public const APPEALED = 'appealed';
    /** @var string Finished. */
    public const CLOSED = 'closed';

    /** @var array<string, string[]> Moves staff may make from each stage. Appeals are the student's. */
    private const MOVES = [
        self::REPORTED => [self::INVESTIGATING, self::CLOSED],
        self::INVESTIGATING => [self::AWAITING, self::DECIDED, self::CLOSED],
        self::AWAITING => [self::INVESTIGATING, self::DECIDED, self::CLOSED],
        self::DECIDED => [self::INVESTIGATING, self::CLOSED],
        self::APPEALED => [self::DECIDED, self::CLOSED],
        self::CLOSED => [self::INVESTIGATING],
    ];

    /** @var string[] Possible decisions. */
    private const OUTCOMES = ['nocase', 'warning', 'resubmit', 'markreduced', 'referred'];

    /**
     * All stages, in order.
     *
     * @return string[]
     */
    public static function stages(): array {
        return array_keys(self::MOVES);
    }

    /**
     * Stages staff can move a case to from this one.
     *
     * @param string $stage
     * @return string[]
     */
    public static function next_stages(string $stage): array {
        return self::MOVES[$stage] ?? [];
    }

    /**
     * Whether staff can move a case between these stages.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function can_move(string $from, string $to): bool {
        return in_array($to, self::next_stages($from), true);
    }

    /**
     * Whether reaching this stage makes the case visible to the student.
     *
     * @param string $stage
     * @return bool
     */
    public static function reveals(string $stage): bool {
        return $stage === self::AWAITING || $stage === self::DECIDED;
    }

    /**
     * Possible decisions.
     *
     * @return string[]
     */
    public static function outcomes(): array {
        return self::OUTCOMES;
    }

    /**
     * Whether this is a known decision.
     *
     * @param string $outcome
     * @return bool
     */
    public static function is_outcome(string $outcome): bool {
        return in_array($outcome, self::OUTCOMES, true);
    }
}
