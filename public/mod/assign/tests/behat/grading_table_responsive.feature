@mod @mod_assign
Feature: Native submissions table remains readable and fully functional on narrow screens
  In order to manage submissions on a computer or mobile device
  As a teacher
  I need responsive access to all original assignment grading controls and columns

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | One | teacher1@example.com |
      | student1 | Student | One | student1@example.com |
      | student2 | Student | Two | student2@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |

  @javascript
  Scenario: The same semantic submissions table supports narrow screens and native selection
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Responsive submissions |
      | duedate | ##+2 days## |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Responsive submissions | student1 | First answer |
    And I am on the "Responsive submissions" Activity page logged in as teacher1
    When I navigate to "Submissions" in current page administration
    And I change window size to "small"
    Then "#submissions.assign-responsive-grading-table" "css_element" should exist
    And ".assign-grading-table-scroll[role='region'][tabindex='0']" "css_element" should exist
    And "#submissions thead th.assign-grading-identity" "css_element" should exist
    And "#submissions thead th.assign-grading-status" "css_element" should exist
    And "#submissions thead th.assign-grading-date" "css_element" should exist
    And "#submissions thead th.assign-grading-grade" "css_element" should exist
    And I should see "Student One" in the "#submissions" "css_element"
    And I should see "Submitted for grading" in the "Student One" "table_row"
    And "selectall" "checkbox" should exist
    And I click on "Select all" "checkbox" in the "#submissions" "css_element"
    And I should see "Download" in the "sticky-footer" "region"

  @javascript
  Scenario: Submission and grading actions remain available in the original row
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Responsive row actions |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Responsive row actions | student1 | A submitted answer |
    And I am on the "Responsive row actions" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    When I change window size to "small"
    And I click on "Grade actions" "actionmenu" in the "Student One" "table_row"
    And I choose "Grade" in the open action menu
    Then I should see "A submitted answer"

  @javascript
  Scenario: Quick grading keeps its single native form and sticky save action
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Quick grading on mobile |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Quick grading on mobile | student1 | Submission to grade |
    And I am on the "Quick grading on mobile" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Quick grading" "checkbox"
    When I change window size to "small"
    Then "table#submissions" "css_element" should exist
    And "#submissions input[name='selectedusers']" "css_element" should exist
    And "Save" "button" should exist in the "sticky-footer" "region"
    And ".assign-grading-table-scroll" "css_element" should exist

  @javascript
  Scenario: Group submissions and plugin columns are still present on a narrow screen
    Given the following "groups" exist:
      | name | course | idnumber |
      | Group One | C1 | G1 |
    And the following "group members" exist:
      | user | group |
      | student1 | G1 |
      | student2 | G1 |
    And the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Mobile group submissions |
      | teamsubmission | 1 |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Mobile group submissions | student1 | Our team answer |
    And I am on the "Mobile group submissions" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    When I change window size to "small"
    Then "#submissions .assign-grading-team" "css_element" should exist
    And "Our team answer" "text" should exist in the "#submissions" "css_element"
    And "Group One" "text" should exist in the "#submissions" "css_element"

  @javascript
  Scenario: Column visibility and table preference controls remain native
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Responsive table preferences |
      | assignsubmission_onlinetext_enabled | 1 |
    And I am on the "Responsive table preferences" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I change window size to "small"
    When I click on "Hide Status" "link"
    Then "#submissions.assign-responsive-grading-table" "css_element" should exist
    And "Show Status" "link" should exist
    And "Reset table preferences" "link" should exist

  Scenario: Students never receive the instructor's submissions table
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Private submissions |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Private submissions" Activity page logged in as student1
    Then "#submissions.assign-responsive-grading-table" "css_element" should not exist
