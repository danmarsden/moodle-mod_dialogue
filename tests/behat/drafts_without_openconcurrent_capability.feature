@mod @mod_dialogue @javascript
Feature: Prevent student from sending a second draft when concurrent conversations are not allowed
  As an administrator
  I want to prevent students from opening multiple conversations at the same time
  So students can't send a second draft when they already have an open conversation in a dialogue activity

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

  Scenario: Student cannot send a second draft when openconcurrent is prevented
    Given I am on the "Test Dialogue" "dialogue activity" page logged in as student1
    # Create the first draft.
    When I click on "Create" "link"
    And I open the autocomplete suggestions list
    And I click on "Teacher 1" item in the autocomplete list
    And I set the field "Subject" to "First draft"
    And I set the field "Message" to "This is my first draft"
    And I press "Save draft"
    # Create the second draft.
    And I click on "Create" "link"
    And I open the autocomplete suggestions list
    And I click on "Teacher 1" item in the autocomplete list
    And I set the field "Subject" to "Second draft"
    And I set the field "Message" to "This is my second draft"
    And I press "Save draft"
    # Send the first draft.
    And I click on "First draft" "table_row"
    And I press "Send"
    # Make sure user can't send the second draft.
    And I click on "Drafts" "link"
    And I click on "Second draft" "table_row"
    Then "Save draft" "button" should exist in the "#id_actionssection" "css_element"
    And "Send" "button" should not exist in the "#id_actionssection" "css_element"
