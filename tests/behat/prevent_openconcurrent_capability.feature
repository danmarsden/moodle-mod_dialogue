@mod @mod_dialogue @javascript
Feature: Prevent student from opening concurrent conversations
  As an administrator
  I want to prevent students from opening multiple conversations at the same time
  So that students can only have one open conversation in a dialogue activity

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
      | capability                     | permission | role    | contextlevel    | reference |
      | mod/dialogue:openconcurrent    | Prevent    | student | Activity module | dialogue1 |

  Scenario: Student cannot create a second conversation when openconcurrent is prevented
    Given I am on the "Test Dialogue" "dialogue activity" page logged in as student1
    # Create the first conversation.
    When I click on "Create" "link"
    And I open the autocomplete suggestions list
    And I click on "Teacher 1" item in the autocomplete list
    And I set the field "Subject" to "First conversation"
    And I set the field "Message" to "Hello teacher, this is my first message"
    And I press "Send"
    Then I should see "Conversation has been opened"
    And I should see "First conversation"
    # The Create button should no longer be visible.
    And "Create" "link" should not exist
