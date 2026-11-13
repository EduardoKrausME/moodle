@mod @mod_assign @javascript
Feature: The assignment grader uses native accessible mobile tabs and keeps the desktop split view
  In order to review submissions without losing feedback and grade changes
  As a teacher
  I need one native grading form with responsive preview and assessment panes

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
      | name | Responsive grading station |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignfeedback_comments_enabled | 1 |
      | assignfeedback_editpdf_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Responsive grading station | student1 | A submission to review |
      | Responsive grading station | student2 | Another submission to review |

  Scenario: Desktop retains both native panels and a labelled keyboard resizer
    Given I go to "Student One" "Responsive grading station" activity advanced grading page logged in as teacher1
    When I change window size to "large"
    Then "[data-region='review-panel']" "css_element" should exist
    And "[data-region='grade-panel']" "css_element" should exist
    And "[data-region='resizer'][role='separator'][aria-orientation='vertical']" "css_element" should exist
    And "[data-region='resizer'][aria-valuenow='70']" "css_element" should exist
    And "[data-region='grading-mobile-tabs']" "css_element" should not be visible
    And "[data-region='grade-panel'] form.gradeform" "css_element" should exist

  Scenario: Mobile tabs switch the visible panels without duplicating grading forms
    Given I go to "Student One" "Responsive grading station" activity advanced grading page logged in as teacher1
    When I change window size to "small"
    Then "[data-region='grading-mobile-tabs'][role='tablist']" "css_element" should be visible
    And "[data-region='grading-mobile-tab-grade'][aria-selected='true']" "css_element" should be visible
    And "[data-region='grade-panel'][aria-hidden='false']" "css_element" should exist
    And "[data-region='review-panel'][aria-hidden='true']" "css_element" should exist
    When I click on "Submission preview" "button"
    Then "[data-region='grading-mobile-tab-review'][aria-selected='true']" "css_element" should exist
    And "[data-region='review-panel'][aria-hidden='false']" "css_element" should be visible
    And "[data-region='grade-panel'][aria-hidden='true']" "css_element" should exist
    When I click on "Assessment" "button"
    Then "[data-region='grade-panel'][aria-hidden='false']" "css_element" should be visible
    And "[data-region='grade-panel'] form.gradeform" "css_element" should exist

  Scenario: Unsaved numeric grade survives mobile tab changes
    Given I go to "Student One" "Responsive grading station" activity advanced grading page logged in as teacher1
    And I change window size to "small"
    And I set the field "Grade out of 100" to "72"
    When I click on "Submission preview" "button"
    And I click on "Assessment" "button"
    Then the field "Grade out of 100" matches value "72"
    And "[data-region='grade-panel'] form.gradeform" "css_element" should exist

  Scenario: Desktop layout controls preserve the native form and restore both panes
    Given I go to "Student One" "Responsive grading station" activity advanced grading page logged in as teacher1
    And I change window size to "large"
    When I click on ".collapse-review-panel" "css_element"
    Then "[data-region='review-panel'][aria-hidden='true']" "css_element" should exist
    And "[data-region='grade-panel'] form.gradeform" "css_element" should exist
    When I click on ".collapse-none" "css_element"
    Then "[data-region='review-panel'][aria-hidden='false']" "css_element" should exist
    And "[data-region='resizer'][aria-valuemin='8'][aria-valuemax='92']" "css_element" should exist

  Scenario: The native save action remains available after switching mobile tabs
    Given I go to "Student One" "Responsive grading station" activity advanced grading page logged in as teacher1
    And I change window size to "small"
    And I set the field "Grade out of 100" to "65"
    And I click on "Submission preview" "button"
    And I click on "Assessment" "button"
    When I press "Save changes"
    Then I should see "65" in the "[data-region='grade-panel']" "css_element"

  Scenario: A different layout for desktop does not remove mobile's assessment tab
    Given I go to "Student One" "Responsive grading station" activity advanced grading page logged in as teacher1
    And I change window size to "large"
    And I click on ".collapse-grade-panel" "css_element"
    When I change window size to "small"
    Then "[data-region='grading-mobile-tab-grade'][aria-selected='true']" "css_element" should exist
    And "[data-region='grade-panel'][aria-hidden='false']" "css_element" should exist
    When I change window size to "large"
    Then "[data-region='review-panel'][aria-hidden='false']" "css_element" should exist

  Scenario: Without a supported preview plugin no empty mobile preview tab is created
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Text-only grading station |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignfeedback_comments_enabled | 1 |
      | assignfeedback_editpdf_enabled | 0 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Text-only grading station | student1 | Text only |
    And I go to "Student One" "Text-only grading station" activity advanced grading page logged in as teacher1
    When I change window size to "small"
    Then "[data-region='grading-mobile-tabs']" "css_element" should not exist
    And "[data-region='grade-panel'] form.gradeform" "css_element" should exist
    And "[data-region='resizer']" "css_element" should not exist

  Scenario: Blind marking does not reveal student names in the responsive workspace
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Anonymous grading station |
      | blindmarking | 1 |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignfeedback_editpdf_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Anonymous grading station | student1 | Anonymous review |
    And I am on the "Anonymous grading station" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Grade actions" "actionmenu" in the "Participant" "table_row"
    And I choose "Grade" in the open action menu
    When I change window size to "small"
    Then "[data-region='grading-mobile-tabs']" "css_element" should exist
    And I should not see "Student One" in the "[data-region='grading-navigation-panel']" "css_element"
    And I should not see "Student One" in the "[data-region='grading-mobile-tabs']" "css_element"
