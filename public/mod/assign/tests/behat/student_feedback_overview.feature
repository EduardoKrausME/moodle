@mod @mod_assign @assign_ux_15 @assign_ux_15_2
Feature: Student feedback overview shows only authorised grade and plugin information
  In order to understand my assignment result
  As a learner
  I need an accessible summary that honours gradebook and marking workflow visibility

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | One | teacher1@example.com |
      | student1 | Student | One | student1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |

  Scenario: A student with no published feedback sees no grade or plugin output
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Unmarked assignment |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Unmarked assignment" Activity page logged in as student1
    Then ".assign-student-feedback" "css_element" should exist
    And I should see "Feedback is not available yet." in the ".assign-student-feedback" "css_element"
    And ".assign-student-feedback-grade" "css_element" should not exist
    And ".assign-student-feedback-plugin" "css_element" should not exist

  @javascript
  Scenario: A released grade and feedback comments use native formatted output
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Marked assignment |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignfeedback_comments_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Marked assignment | student1 | Initial submission |
    And I am on the "Marked assignment" Activity page logged in as teacher1
    And I go to "Student One" "Marked assignment" activity advanced grading page
    And I set the field "Grade out of 100" to "85"
    And I set the field "Feedback comments" to "Please review the calculations."
    And I press "Save changes"
    And I log out
    When I am on the "Marked assignment" Activity page logged in as student1
    Then ".assign-student-feedback-grade" "css_element" should exist
    And I should see "85" in the ".assign-student-feedback-grade" "css_element"
    And I should see "Please review the calculations." in the ".assign-student-feedback-plugin" "css_element"
    And ".assign-student-feedback-criteria" "css_element" should not exist
    And ".feedbacktable" "css_element" should not exist

  @javascript
  Scenario: An unreleased marking workflow does not disclose the grade or comments
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Pending release |
      | markingworkflow | 1 |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignfeedback_comments_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Pending release | student1 | Initial submission |
    And I am on the "Pending release" Activity page logged in as teacher1
    And I go to "Student One" "Pending release" activity advanced grading page
    And I set the field "Grade out of 100" to "90"
    And I set the field "Feedback comments" to "Private until release"
    And I press "Save changes"
    And I log out
    When I am on the "Pending release" Activity page logged in as student1
    Then ".assign-student-feedback" "css_element" should exist
    And ".assign-student-feedback-grade" "css_element" should not exist
    And ".assign-student-feedback-plugin" "css_element" should not exist
    And I should not see "Private until release"

  @javascript
  Scenario: Hide grader does not reveal the teacher identity in the student panel
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Anonymous feedback |
      | hidegrader | 1 |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignfeedback_comments_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Anonymous feedback | student1 | Initial submission |
    And I am on the "Anonymous feedback" Activity page logged in as teacher1
    And I go to "Student One" "Anonymous feedback" activity advanced grading page
    And I set the field "Grade out of 100" to "75"
    And I set the field "Feedback comments" to "Review the methodology."
    And I press "Save changes"
    And I log out
    When I am on the "Anonymous feedback" Activity page logged in as student1
    Then ".assign-student-feedback-grade" "css_element" should exist
    And I should see "Review the methodology." in the ".assign-student-feedback-plugin" "css_element"
    And ".assign-student-feedback-grader" "css_element" should not exist
    And I should not see "Graded by" in the ".assign-student-feedback" "css_element"

  @javascript @_file_upload
  Scenario: Feedback files retain their native download links
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Feedback files assignment |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignfeedback_file_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Feedback files assignment | student1 | Initial submission |
    And I am on the "Feedback files assignment" Activity page logged in as teacher1
    And I go to "Student One" "Feedback files assignment" activity advanced grading page
    And I set the field "Grade out of 100" to "60"
    And I upload "lib/tests/fixtures/empty.txt" file to "Feedback files" filemanager
    And I press "Save changes"
    And I log out
    When I am on the "Feedback files assignment" Activity page logged in as student1
    Then ".assign-student-feedback" "css_element" should exist
    And I should see "empty.txt" in the ".assign-student-feedback-plugin" "css_element"
    And "empty.txt" "link" should exist in the ".assign-student-feedback-plugin" "css_element"
