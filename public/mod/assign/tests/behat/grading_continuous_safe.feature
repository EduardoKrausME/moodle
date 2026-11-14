@mod @mod_assign @javascript @assign_ux_15 @assign_ux_15_3
Feature: Assignment grading saves before navigating and never loses unconfirmed edits
  In order to grade successive participants without accidental data loss
  As a teacher
  I need a safe native save and navigation flow with clear feedback

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
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
    And the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Safe continuous grading |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignfeedback_comments_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Safe continuous grading | student1 | First submission |
      | Safe continuous grading | student2 | Second submission |

  Scenario: Saving a grade confirms the result without switching participants
    Given I am on the "Safe continuous grading" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Grade actions" "actionmenu" in the "Student One" "table_row"
    And I choose "Grade" in the open action menu
    And I should see "1 of 2"
    And I set the field "Grade out of 100" to "71"
    When I press "Save changes"
    Then I should see "Changes saved" in the "[data-region='grading-save-status']" "css_element"
    And I should see "1 of 2"
    And the field "Grade out of 100" matches value "71"

  Scenario: Save and next advances only after the current participant is saved
    Given I am on the "Safe continuous grading" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Grade actions" "actionmenu" in the "Student One" "table_row"
    And I choose "Grade" in the open action menu
    And I set the field "Grade out of 100" to "76"
    When I click on "[name='saveandshownext']" "css_element"
    Then I should see "2 of 2"
    And I should see "Student Two" in the "[data-region='user-info']" "css_element"

  Scenario: The last filtered participant does not wrap to the first after save and next
    Given I am on the "Safe continuous grading" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Grade actions" "actionmenu" in the "Student Two" "table_row"
    And I choose "Grade" in the open action menu
    And I should see "2 of 2"
    And I set the field "Grade out of 100" to "73"
    When I click on "[name='saveandshownext']" "css_element"
    Then I should see "2 of 2"
    And I should see "End of the current filtered list" in the "[data-region='grading-save-status']" "css_element"

  Scenario: Cancelling navigation preserves an unsaved grade and the current participant
    Given I am on the "Safe continuous grading" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Grade actions" "actionmenu" in the "Student One" "table_row"
    And I choose "Grade" in the open action menu
    And I set the field "Grade out of 100" to "88"
    When I click on "[data-action='next-user']" "css_element"
    Then I should see "Discard changes" in the "Unsaved changes" "dialogue"
    When I click on "Cancel" "button" in the "Unsaved changes" "dialogue"
    Then I should see "1 of 2"
    And the field "Grade out of 100" matches value "88"

  Scenario: Explicitly discarding changes allows navigation without saving the old grade
    Given I am on the "Safe continuous grading" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Grade actions" "actionmenu" in the "Student One" "table_row"
    And I choose "Grade" in the open action menu
    And I set the field "Grade out of 100" to "88"
    When I click on "[data-action='next-user']" "css_element"
    And I click on "Discard changes" "button" in the "Unsaved changes" "dialogue"
    Then I should see "2 of 2"
    And I should see "Student Two" in the "[data-region='user-info']" "css_element"

  Scenario: Save and continue from the unsaved dialog saves before opening the next participant
    Given I am on the "Safe continuous grading" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Grade actions" "actionmenu" in the "Student One" "table_row"
    And I choose "Grade" in the open action menu
    And I set the field "Grade out of 100" to "91"
    When I click on "[data-action='next-user']" "css_element"
    And I click on "Save and continue" "button" in the "Unsaved changes" "dialogue"
    Then I should see "2 of 2"
    And I should see "Student Two" in the "[data-region='user-info']" "css_element"

  Scenario: The mobile grading tabs do not save or discard pending edits
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Safe mobile grading |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignfeedback_editpdf_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Safe mobile grading | student1 | Review this file |
    And I am on the "Safe mobile grading" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Grade actions" "actionmenu" in the "Student One" "table_row"
    And I choose "Grade" in the open action menu
    And I change window size to "small"
    And I set the field "Grade out of 100" to "63"
    When I click on "Submission preview" "button"
    And I click on "Assessment" "button"
    Then the field "Grade out of 100" matches value "63"
    And "[data-region='grading-save-status'][role='status'][aria-live='polite']" "css_element" should exist

  Scenario: Blind marking keeps the native anonymous participant selector
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Anonymous continuous grading |
      | blindmarking | 1 |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Anonymous continuous grading | student1 | Confidential work |
    And I am on the "Anonymous continuous grading" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Grade actions" "actionmenu" in the "Participant" "table_row"
    And I choose "Grade" in the open action menu
    Then "[data-region='grading-save-status'][role='status']" "css_element" should exist
    And I should not see "Student One" in the "[data-region='user-info']" "css_element"
