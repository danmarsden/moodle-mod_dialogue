@mod @mod_dialogue @javascript
Feature: Auto-select recipient is not triggered when multiple recipients exist
  As a student
  I want to see the autocomplete selector when there are multiple possible recipients
  So that I can choose which teacher to start the dialogue with

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | teacher2 | Teacher   | 2        | teacher2@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name          | course | idnumber  |
      | dialogue | Test Dialogue | C1     | dialogue1 |
    And the following "permission overrides" exist:
      | capability            | permission | role           | contextlevel | reference |
      | mod/dialogue:receive  | Allow      | editingteacher | Course       | C1        |
      | mod/dialogue:receive  | Prohibit   | student        | Course       | C1        |

  Scenario: Autocomplete selector is shown when multiple recipients are available
    Given I am on the "Test Dialogue" "dialogue activity" page logged in as student1
    When I click on "Create" "link"
    And I open the autocomplete suggestions list
    Then "Teacher 1" "autocomplete_suggestions" should exist
    And "Teacher 2" "autocomplete_suggestions" should exist
