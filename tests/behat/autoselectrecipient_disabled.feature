@mod @mod_dialogue @javascript
Feature: Recipient is not auto-selected when the setting is disabled
  As a student
  I want to choose the recipient from the autocomplete selector
  So that I explicitly pick who I want to start the dialogue with

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
      | mod/dialogue:receive  | Prohibit   | student        | Course       | C1        |

  Scenario: Recipient autocomplete selector is shown with the only possible recipient as an option
    Given I am on the "Test Dialogue" "dialogue activity" page logged in as student1
    When I click on "Create" "link"
    And I open the autocomplete suggestions list
    Then "Teacher 1" "autocomplete_suggestions" should exist
