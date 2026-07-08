@mod @mod_dialogue @javascript
Feature: Auto-select recipient skips myself
  As a teacher
  I don't want to be able to select myself as a recipient
  So the only other possible recipient is auto-selected

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name          | course | idnumber  |
      | dialogue | Test Dialogue | C1     | dialogue1 |
    And the following "permission overrides" exist:
      | capability            | permission | role           | contextlevel | reference |
      | mod/dialogue:receive  | Allow      | editingteacher | Course       | C1        |
      | mod/dialogue:receive  | Allow      | student        | Course       | C1        |

  Scenario: I'm not listed as a recipient, so the only other possible recipient is auto-selected
    Given I am on the "Test Dialogue" "dialogue activity" page logged in as teacher1
    When I click on "Create" "link"
    Then I should not see "Teacher 1" in the "Open with" "fieldset"
    And I should see "Student 1" in the "Open with" "fieldset"
    And "useridsselected" "field" should not exist
