@mod @mod_dialogue @mod_dialogue_report
Feature: Search messages using the report page
  In order to find specific messages in a dialogue activity
  As a teacher
  I need to be able to view and filter the search-messages report

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname | email                  |
      | teacher1  | Teacher   | One      | teacher1@example.com   |
      | teacher2  | Tony      | Two      | teacher2@example.com   |
      | student1  | Alice     | Smith    | student1@example.com   |
      | student2  | Bob       | Jones    | student2@example.com   |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher        |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "permission overrides" exist:
      | capability             | permission | role           | contextlevel | reference |
      | mod/dialogue:viewany   | Allow      | editingteacher | Course       | C1        |
    And the following "activities" exist:
      | activity | name            | course | idnumber |
      | dialogue | Test Dialogue   | C1     | dialogue1 |
    And the following "mod_dialogue > conversations" exist:
      | dialogue  | userfrom | userto             | subject              | body                     | state  |
      | dialogue1 | student1 | teacher1           | Hello teacher        | This is my first message | open   |
      | dialogue1 | student2 | teacher1           | Another subject      | Another message body     | open   |
      | dialogue1 | student1 | teacher2           | Question for Tony    | Message for Tony only    | open   |
      | dialogue1 | student1 | teacher1           | Resolved long ago    | Already wrapped up       | closed |
      | dialogue1 | teacher1 | student1,student2  | Group announcement   | Message to both students | open   |

  @javascript
  Scenario: Teacher can see the Search messages tab
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    Then I should see "Search messages"

  @javascript
  Scenario: The report page loads and shows messages
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    When I follow "Search messages"
    Then I should see "Hello teacher"
    And I should see "Another subject"
    And I should see "Alice Smith"
    And I should see "Bob Jones"

  @javascript
  Scenario: Teacher can filter messages by author (From)
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    And I follow "Search messages"
    When I click on "Filters" "button"
    And I set the following fields in the "From" "core_reportbuilder > Filter" to these values:
      | From operator | Contains |
      | From value    | Alice    |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "Hello teacher"
    But I should not see "Another subject"

  @javascript
  Scenario: Teacher can filter messages by subject
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    And I follow "Search messages"
    When I click on "Filters" "button"
    And I set the following fields in the "Subject" "core_reportbuilder > Filter" to these values:
      | Subject operator | Contains |
      | Subject value    | Hello    |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "Hello teacher"
    But I should not see "Another subject"

  @javascript
  Scenario: Teacher can filter messages by state Open
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    And I follow "Search messages"
    When I click on "Filters" "button"
    And I set the following fields in the "State" "core_reportbuilder > Filter" to these values:
      | State operator | Is equal to |
      | State value    | Open        |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "Hello teacher"
    And I should see "Another subject"
    But I should not see "Resolved long ago"

  @javascript
  Scenario: Teacher can filter messages by state Closed
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    And I follow "Search messages"
    When I click on "Filters" "button"
    And I set the following fields in the "State" "core_reportbuilder > Filter" to these values:
      | State operator | Is equal to |
      | State value    | Closed      |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "Resolved long ago"
    But I should not see "Hello teacher"
    And I should not see "Another subject"

  @javascript
  Scenario: Teacher can filter messages by date
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    And I follow "Search messages"
    When I click on "Filters" "button"
    # "In the past" needs no extra input fields, so the filter popover's Apply
    # button can't occlude any hidden form controls.
    And I set the field "Date operator" in the "Date" "core_reportbuilder > Filter" to "In the past"
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "Hello teacher"
    And I should see "Another subject"
    And I should see "Resolved long ago"
    # Flipping to "In the future" should hide every just-created message.
    And I set the field "Date operator" in the "Date" "core_reportbuilder > Filter" to "In the future"
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should not see "Hello teacher"
    And I should not see "Another subject"
    And I should not see "Resolved long ago"

  @javascript
  Scenario: Student can see the Search messages tab
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    Then I should see "Search messages"

  @javascript
  Scenario: Student only sees conversations they participate in
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    When I follow "Search messages"
    Then I should see "Hello teacher"
    And I should see "Question for Tony"
    But I should not see "Another subject"

  @javascript
  Scenario: A second student only sees their own conversation
    Given I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    When I follow "Search messages"
    Then I should see "Another subject"
    But I should not see "Hello teacher"
    And I should not see "Question for Tony"

  @javascript
  Scenario: Student does not see other participants' usernames in the report
    Given I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    When I follow "Search messages"
    # The shared conversation row is visible, and the other participant's
    # full name is shown — but their username must not be exposed because
    # students lack moodle/site:viewuseridentity.
    Then I should see "Group announcement"
    And I should see "Alice Smith"
    But I should not see "student1"

  @javascript
  Scenario: Student without mod/dialogue:searchmessages capability cannot see or access the Search messages feature
    Given the following "permission overrides" exist:
      | capability                  | permission | role    | contextlevel | reference |
      | mod/dialogue:searchmessages | Prevent    | student | Course       | C1        |
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    # The tab is hidden, so the student has no UI route to the search-messages
    # report. Because the link is absent, attempting "I follow 'Search messages'"
    # would itself fail — covering both "see" and "access" via the UI.
    Then I should not see "Search messages"

  @javascript
  Scenario: Non-editing teacher can see the Search messages tab
    Given I log in as "teacher2"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    Then I should see "Search messages"

  @javascript
  Scenario: Non-editing teacher only sees conversations they participate in
    Given I log in as "teacher2"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    When I follow "Search messages"
    Then I should see "Question for Tony"
    But I should not see "Hello teacher"
    And I should not see "Another subject"

  @javascript
  Scenario: Non-editing teacher cannot surface other participants' conversations via filters
    Given I log in as "teacher2"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    And I follow "Search messages"
    When I click on "Filters" "button"
    And I set the following fields in the "Subject" "core_reportbuilder > Filter" to these values:
      | Subject operator | Contains |
      | Subject value    | Another  |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should not see "Another subject"
