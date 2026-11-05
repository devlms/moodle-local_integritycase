@local @local_integritycase
Feature: Handle an academic integrity case from report to appeal
  In order to deal with suspected misconduct fairly
  As a teacher
  I need to investigate privately, ask the student for their side, decide, and handle an appeal

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Tess      | Teacher  |
      | student1 | Sam       | Student  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  @javascript @accessibility
  Scenario: A case is hidden while staff investigate, then the student responds and appeals
    Given I am on the "C1" "local_integritycase > Cases" page logged in as "teacher1"
    And I click on "Open a case" "link"
    And I set the following fields to these values:
      | Student        | Sam Student                        |
      | What was found | Two paragraphs match a 2019 essay. |
    And I press "Open a case"
    Then I should see "The student cannot see this case yet."
    And the page should meet accessibility standards
    And I set the field "Private note" to "Same wording as a past submission."
    And I press "Add private note"
    And I log out

    When I am on the "C1" "local_integritycase > Cases" page logged in as "student1"
    Then I should see "No cases yet."
    And I log out

    When I am on the "C1" "local_integritycase > Cases" page logged in as "teacher1"
    And I follow "Sam Student"
    And I set the field "Stage" to "Investigating"
    And I press "Move the case"
    Then I should see "Investigating" in the "[data-region=stage]" "css_element"
    And I set the field "Stage" to "Awaiting student response"
    And I set the field "Note" to "Please explain how you wrote section 2."
    And I press "Move the case"
    And I log out

    When I am on the "C1" "local_integritycase > Cases" page logged in as "student1"
    And I follow "Awaiting student response"
    Then I should see "Please explain how you wrote section 2."
    And I should see "Two paragraphs match a 2019 essay."
    And I should not see "Same wording as a past submission."
    And I set the field "Your response" to "I used my own notes from 2019."
    And I press "Send your response"
    Then I should see "I used my own notes from 2019."
    And I log out

    When I am on the "C1" "local_integritycase > Cases" page logged in as "teacher1"
    And I follow "Sam Student"
    Then I should see "I used my own notes from 2019."
    And I am on the "C1" "local_integritycase > Cases" page
    And the page should meet accessibility standards
    And I follow "Sam Student"
    And I set the field "Stage" to "Decided"
    And I set the field "Outcome" to "educational warning"
    And I press "Move the case"
    Then I should see "educational warning" in the "[data-region=outcome]" "css_element"
    And I log out

    When I am on the "C1" "local_integritycase > Cases" page logged in as "student1"
    And I follow "Decided"
    And I set the field "Why you are appealing" to "The matching text is my own earlier work."
    And I press "Appeal the decision"
    Then I should see "Appealed" in the "[data-region=stage]" "css_element"
