@mod @mod_dialogue @javascript
Feature: Sort conversation messages
  In order to read conversation messages in my preferred order
  As a student
  I need to be able to sort messages by oldest or latest

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name            | course | idnumber   |
      | dialogue | Test Dialogue   | C1     | dialogue1  |

  Scenario: Messages are sorted by oldest first by default
    Given I am on the "Test Dialogue" "dialogue activity" page logged in as student1
    When I click on "Create" "link"
    And I open the autocomplete suggestions list
    And I click on "Teacher One" item in the autocomplete list
    And I set the field "Subject" to "Test subject"
    And I set the field "Message" to "My first message"
    And I press "Send"
    And I click on "Test subject" "text"
    And I set the field "Message" to "My second message"
    And I press "Send"
    And I click on "Test subject" "text"
    And I set the field "Message" to "My third message"
    And I press "Send"
    And I click on "Test subject" "text"
    Then "My first message" "text" should appear before "My second message" "text"
    And "My second message" "text" should appear before "My third message" "text"

  Scenario: Messages can be sorted by latest first
    Given I am on the "Test Dialogue" "dialogue activity" page logged in as student1
    When I click on "Create" "link"
    And I open the autocomplete suggestions list
    And I click on "Teacher One" item in the autocomplete list
    And I set the field "Subject" to "Test subject"
    And I set the field "Message" to "My first message"
    And I press "Send"
    And I click on "Test subject" "text"
    And I set the field "Message" to "My second message"
    And I press "Send"
    And I click on "Test subject" "text"
    And I set the field "Message" to "My third message"
    And I press "Send"
    And I click on "Test subject" "text"
    And I click on "//button[starts-with(normalize-space(.), 'Sorted by:')]" "xpath_element"
    And I click on "Latest" "link" in the ".dropdown-group" "css_element"
    Then "My third message" "text" should appear before "My second message" "text"
    And "My second message" "text" should appear before "My first message" "text"

  Scenario: Sort order preference persists across page visits
    Given I am on the "Test Dialogue" "dialogue activity" page logged in as student1
    When I click on "Create" "link"
    And I open the autocomplete suggestions list
    And I click on "Teacher One" item in the autocomplete list
    And I set the field "Subject" to "Test subject"
    And I set the field "Message" to "My first message"
    And I press "Send"
    And I click on "Test subject" "text"
    And I set the field "Message" to "My second message"
    And I press "Send"
    And I click on "Test subject" "text"
    And I set the field "Message" to "My third message"
    And I press "Send"
    And I click on "Test subject" "text"
    And I click on "//button[starts-with(normalize-space(.), 'Sorted by:')]" "xpath_element"
    And I click on "Latest" "link" in the ".dropdown-group" "css_element"
    And I am on the "Test Dialogue" "dialogue activity" page
    And I click on "Test subject" "text"
    Then I should see "Sorted by: Latest" in the "//button[starts-with(normalize-space(.), 'Sorted by:')]" "xpath_element"
    And "My third message" "text" should appear before "My second message" "text"
    And "My second message" "text" should appear before "My first message" "text"
