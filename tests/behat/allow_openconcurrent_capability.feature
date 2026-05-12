@mod @mod_dialogue @javascript
Feature: Student creates multiple conversations in a Dialogue activity
  As a student
  I need to create conversations with my teacher
  So that I can communicate within the dialogue activity

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

  Scenario: Student creates two conversations with a teacher
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
    # Create the second conversation.
    When I click on "Create" "link"
    And I open the autocomplete suggestions list
    And I click on "Teacher 1" item in the autocomplete list
    And I set the field "Subject" to "Second conversation"
    And I set the field "Message" to "Hello teacher, this is my second message"
    And I press "Send"
    Then I should see "Conversation has been opened"
    And I should see "First conversation" in the ".conversation-list" "css_element"
    And I should see "Second conversation" in the ".conversation-list" "css_element"
