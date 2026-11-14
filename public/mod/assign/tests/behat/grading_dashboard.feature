@mod @mod_assign @assign_ux_15 @assign_ux_15_2
Feature: Teachers see accurate, permission-aware progress for current assignment attempts
  In order to identify submissions still requiring attention
  As a teacher
  I need a dashboard which uses the native assignment grading filters

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | One | teacher1@example.com |
      | student1 | Student | One | student1@example.com |
      | student2 | Student | Two | student2@example.com |
      | student3 | Student | Three | student3@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
      | student3 | C1 | student |

  @javascript
  Scenario: The teacher sees submitted and ungraded current attempts and can use native filters
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Assignment progress |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Assignment progress | student1 | Answer one |
      | Assignment progress | student2 | Answer two |
    And I am on the "Assignment progress" Activity page logged in as teacher1
    Then ".assign-grading-dashboard" "css_element" should exist
    And I should see "3" in the ".assign-grading-dashboard-card--participants" "css_element"
    And I should see "2" in the ".assign-grading-dashboard-card--submitted" "css_element"
    And I should see "2" in the ".assign-grading-dashboard-card--pending" "css_element"
    And I should see "0" in the ".assign-grading-dashboard-card--graded" "css_element"
    And ".assign-grading-dashboard-card--drafts .assign-grading-dashboard-na" "css_element" should exist
    And I should see "2 of 3 Participants submitted" in the ".assign-grading-dashboard-progress" "css_element"
    When I click on "View Needs grading" "link"
    Then I should see "Student One" in the "Student One" "table_row"
    And I should see "Student Two" in the "Student Two" "table_row"
    And "Student Three" "table_row" should not exist

  @javascript
  Scenario: A graded current attempt is no longer pending
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Partially graded |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Partially graded | student1 | First answer |
      | Partially graded | student2 | Second answer |
    And I am on the "Partially graded" Activity page logged in as teacher1
    And I go to "Student One" "Partially graded" activity advanced grading page
    And I set the field "Grade out of 100" to "75"
    And I press "Save changes"
    When I am on the "Partially graded" Activity page logged in as teacher1
    Then I should see "1" in the ".assign-grading-dashboard-card--pending" "css_element"
    And I should see "1" in the ".assign-grading-dashboard-card--graded" "css_element"
    And I should see "2" in the ".assign-grading-dashboard-card--submitted" "css_element"

  Scenario: Students cannot see the instructor dashboard
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Private metrics |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Private metrics" Activity page logged in as student1
    Then ".assign-grading-dashboard" "css_element" should not exist
    And ".assign-landing-overview" "css_element" should exist

  @javascript
  Scenario: Submitted group work is counted once even with multiple members
    Given the following "groups" exist:
      | name | course | idnumber |
      | Group A | C1 | G1 |
      | Group B | C1 | G2 |
    And the following "group members" exist:
      | user | group |
      | student1 | G1 |
      | student2 | G1 |
      | student3 | G2 |
    And the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Team progress |
      | teamsubmission | 1 |
      | submissiondrafts | 0 |
      | requireallteammemberssubmit | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Team progress | student1 | Group A submission |
    When I am on the "Team progress" Activity page logged in as teacher1
    Then ".assign-grading-dashboard" "css_element" should exist
    And I should see "2" in the ".assign-grading-dashboard-card--participants" "css_element"
    And I should see "1" in the ".assign-grading-dashboard-card--submitted" "css_element"
    And I should see "1" in the ".assign-grading-dashboard-card--pending" "css_element"
    And ".assign-grading-dashboard-card--pending a" "css_element" should not exist
    And I should see "These counts represent teams" in the ".assign-grading-dashboard" "css_element"

  @javascript
  Scenario: Default group work remains visible as a team when no other group is assigned
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Default team progress |
      | teamsubmission | 1 |
      | submissiondrafts | 0 |
      | requireallteammemberssubmit | 0 |
      | preventsubmissionnotingroup | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Default team progress | student1 | Default group submission |
    When I am on the "Default team progress" Activity page logged in as teacher1
    Then I should see "1" in the ".assign-grading-dashboard-card--participants" "css_element"
    And I should see "1" in the ".assign-grading-dashboard-card--submitted" "css_element"
    And I should see "1" in the ".assign-grading-dashboard-card--pending" "css_element"

  Scenario: Feedback-only assignments do not report artificial completed grades
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Feedback-only progress |
      | grade | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Feedback-only progress" Activity page logged in as teacher1
    Then ".assign-grading-dashboard" "css_element" should exist
    And ".assign-grading-dashboard-card--graded .assign-grading-dashboard-na" "css_element" should exist
    And ".assign-grading-dashboard-card--pending .assign-grading-dashboard-na" "css_element" should exist

  Scenario: Native grading details remain accessible without JavaScript
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Accessible progress |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Accessible progress" Activity page logged in as teacher1
    Then ".assign-grading-dashboard" "css_element" should exist
    When I click on "Show original grading summary" "text"
    Then I should see "Number of participants" in the ".gradingsummarytable" "css_element"

  @javascript
  Scenario: A saved draft is not counted as a final submission
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Draft progress |
      | submissiondrafts | 1 |
      | assignsubmission_onlinetext_enabled | 1 |
    And I am on the "Draft progress" Activity page logged in as student1
    And I press "Add submission"
    And I set the field "Online text" to "Work in progress"
    And I press "Save changes"
    When I am on the "Draft progress" Activity page logged in as teacher1
    Then I should see "1" in the ".assign-grading-dashboard-card--drafts" "css_element"
    And I should see "0" in the ".assign-grading-dashboard-card--submitted" "css_element"
    And I should see "0" in the ".assign-grading-dashboard-card--graded" "css_element"

  @javascript
  Scenario: Reopening creates a new current attempt without reusing an old grade
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Reopened progress |
      | submissiondrafts | 0 |
      | maxattempts | 3 |
      | attemptreopenmethod | manual |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Reopened progress | student1 | First attempt |
    And I am on the "Reopened progress" Activity page logged in as teacher1
    And I go to "Student One" "Reopened progress" activity advanced grading page
    And I set the field "Grade out of 100" to "80"
    And I set the field "Allow another attempt" to "1"
    And I press "Save changes"
    When I am on the "Reopened progress" Activity page logged in as teacher1
    Then I should see "0" in the ".assign-grading-dashboard-card--submitted" "css_element"
    And I should see "0" in the ".assign-grading-dashboard-card--graded" "css_element"
    And I should see "0" in the ".assign-grading-dashboard-card--pending" "css_element"

  @javascript
  Scenario: An assessment in workflow review is not described as released
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Workflow progress |
      | submissiondrafts | 0 |
      | markingworkflow | 1 |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Workflow progress | student1 | Answer to review |
    And I am on the "Workflow progress" Activity page logged in as teacher1
    And I go to "Student One" "Workflow progress" activity advanced grading page
    And I set the field "Grade out of 100" to "65"
    And I set the field "Marking workflow state" to "In review"
    And I press "Save changes"
    When I am on the "Workflow progress" Activity page logged in as teacher1
    Then I should see "1" in the ".assign-grading-dashboard-card--graded" "css_element"
    And I should see "0" in the ".assign-grading-dashboard-card--pending" "css_element"
    And I should see "may not yet be released to students" in the ".assign-grading-dashboard" "css_element"
