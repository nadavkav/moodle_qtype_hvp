@qtype @qtype_hvp
Feature: Test creating a HVP question
  As a teacher
  In order to test my students
  I need to be able to create a HVP question

  Background:
    Given the following "users" exist:
      | username |
      | teacher  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |

  @javascript
  Scenario: Create a HVP question
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "HVP" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "HVP"
    And I set the field "Question text" to "Please do this HVP Challenge"
    And I click the "h5p-blanks" li
    When I press "id_submitbutton"
    Then I should see "HVP"
