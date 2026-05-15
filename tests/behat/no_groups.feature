@mod @mod_dialogue @javascript
Feature: Students can start conversations with teachers from any group when groups are disabled
	As a student
	I need to see all available teachers in the open with selector
	So that I can start a conversation even when users belong to different groups

	Background:
		Given the following "users" exist:
			| username | firstname | lastname | email                |
			| teacher1 | Teacher   | 1        | teacher1@example.com |
			| teacher2 | Teacher   | 2        | teacher2@example.com |
			| student1 | Student   | 1        | student1@example.com |
			| student2 | Student   | 2        | student2@example.com |
		And the following "courses" exist:
			| fullname | shortname |
			| Course 1 | C1        |
		And the following "course enrolments" exist:
			| user     | course | role           |
			| teacher1 | C1     | editingteacher |
			| teacher2 | C1     | editingteacher |
			| student1 | C1     | student        |
			| student2 | C1     | student        |
		And the following "groups" exist:
			| name    | course | idnumber |
			| Class 1 | C1     | class1   |
			| Class 2 | C1     | class2   |
		And the following "group members" exist:
			| user     | group  |
			| teacher1 | class1 |
			| student1 | class1 |
			| teacher2 | class2 |
			| student2 | class2 |
		And the following "activities" exist:
			| activity | name          | course | idnumber  |
			| dialogue | Test Dialogue | C1     | dialogue1 |

	Scenario: Student can see teachers from all groups in the open with selector
		Given I am on the "Test Dialogue" "dialogue activity" page logged in as student1
		When I click on "Create" "link"
		And I open the autocomplete suggestions list
		Then I should see "Teacher 1"
		And I should see "Teacher 2"
