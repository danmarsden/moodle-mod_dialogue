@mod @mod_dialogue @javascript
Feature: Auto-select recipient when only one is available
  As a student
  I want the only possible recipient to be selected automatically
  So that I don't need to pick the recipient manually when there is just one

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
      | activity | name          | course | idnumber  | autoselectrecipient |
      | dialogue | Test Dialogue | C1     | dialogue1 | 1                   |
    And the following "permission overrides" exist:
      | capability            | permission | role           | contextlevel | reference |
      | mod/dialogue:receive  | Allow      | editingteacher | Course       | C1        |
      | mod/dialogue:receive  | Prohibit   | student        | Course       | C1        |

  Scenario: Recipient is auto-selected when only one possible recipient exists
    Given I am on the "Test Dialogue" "dialogue activity" page logged in as student1
    When I click on "Create" "link"
    Then I should see "Teacher 1" in the "Open with" "fieldset"
    And "useridsselected" "field" should not exist

  Scenario: Student creates a conversation without manually selecting the auto-selected recipient
    Given I am on the "Test Dialogue" "dialogue activity" page logged in as student1
    When I click on "Create" "link"
    And I set the field "Subject" to "Auto-selected recipient test"
    And I set the field "Message" to "Hello teacher, the recipient was auto-selected"
    And I press "Send"
    Then I should see "Conversation has been opened"
    And I should see "Auto-selected recipient test"
    And I should see "Teacher 1" in the ".participant" "css_element"
