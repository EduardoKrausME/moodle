@mod @mod_assign @javascript
Feature: Native grading instruments have a consistent accessible assignment grading workspace
  In order to assess submissions without losing native grading contracts
  As a teacher
  I need clear direct, rubric, guide and multi-marker presentation

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | One | teacher1@example.com |
      | teacher2 | Teacher | Two | teacher2@example.com |
      | student1 | Student | One | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | teacher2 | C1 | editingteacher |
      | student1 | C1 | student |

  Scenario: Direct grading keeps its native grade and feedback fields
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Direct assessment |
      | grade[modgrade_type] | point |
      | grade[modgrade_point] | 100 |
      | assignfeedback_comments_enabled | 1 |
    When I go to "Student One" "Direct assessment" activity advanced grading page logged in as teacher1
    Then "[data-region='assign-assessment-summary'][data-assessment-mode='simple']" "css_element" should exist
    And I should see "Direct grading" in the "[data-region='assign-assessment-summary']" "css_element"
    And "Grade out of 100" "field" should exist
    And "[data-region='assign-assessment-criteria-progress']" "css_element" should not exist

  Scenario: Native rubric displays a progress summary without duplicating grade inputs
    Given the following "activities" exist:
      | activity | name | intro | course | grade | advancedgradingmethod_submissions |
      | assign | Rubric assessment | Rubric | C1 | 100 | rubric |
    And I am on the "Course 1" course page logged in as teacher1
    And I go to "Rubric assessment" advanced grading definition page
    And I set the following fields to these values:
      | Name | Assignment rubric |
      | Description | Assessment criteria |
    And I define the following rubric:
      | Criterion 1 | Incomplete | 0 | Acceptable | 5 | Excellent | 10 |
      | Criterion 2 | Incomplete | 0 | Acceptable | 5 | Excellent | 10 |
    And I press "Save rubric and make it ready"
    When I go to "Student One" "Rubric assessment" activity advanced grading page
    Then "[data-region='assign-assessment-summary'][data-assessment-mode='rubric']" "css_element" should exist
    And I should see "0 of 2 criteria selected" in the "[data-region='assign-assessment-summary']" "css_element"
    And ".gradingform_rubric.evaluate" "css_element" should exist
    When I grade by filling the rubric with:
      | Criterion 1 | 5 |  |
      | Criterion 2 | 10 |  |
    Then I should see "2 of 2 criteria selected" in the "[data-region='assign-assessment-summary']" "css_element"
    And ".gradingform_rubric.evaluate" "css_element" should exist

  Scenario: Native marking guide keeps its criterion scores and comments
    Given the following "activities" exist:
      | activity | course | name | advancedgradingmethod_submissions |
      | assign | C1 | Guide assessment | guide |
    And I am on the "Course 1" course page logged in as teacher1
    And I go to "Guide assessment" advanced grading definition page
    And I set the following fields to these values:
      | Name | Assignment guide |
      | Description | Guide criteria |
    And I define the following marking guide:
      | Criterion name | Description for students | Description for markers | Maximum score |
      | Criterion A | Explanation A | Marker guidance A | 70 |
      | Criterion B | Explanation B | Marker guidance B | 30 |
    And I press "Save marking guide and make it ready"
    When I go to "Student One" "Guide assessment" activity advanced grading page
    Then "[data-region='assign-assessment-summary'][data-assessment-mode='guide']" "css_element" should exist
    And I should see "0 of 2 criteria have scores" in the "[data-region='assign-assessment-summary']" "css_element"
    When I grade by filling the marking guide with:
      | Criterion A | 60 | Clear analysis |
      | Criterion B | 25 | Some mistakes |
    Then I should see "2 of 2 criteria have scores" in the "[data-region='assign-assessment-summary']" "css_element"

  Scenario: Multiple marking preserves native individual marking fields and workflow
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | idnumber | A1 |
      | name | Multiple marking assessment |
      | markingworkflow | 1 |
      | markingallocation | 1 |
      | markercount | 2 |
      | multimarkmethod | maximum |
      | grade[modgrade_type] | point |
      | grade[modgrade_point] | 100 |
    And the following "mod_assign > marker_allocations" exist:
      | assign | user | marker |
      | Multiple marking assessment | student1 | teacher1 |
      | Multiple marking assessment | student1 | teacher2 |
    When I go to "Student One" "Multiple marking assessment" activity advanced marking page logged in as teacher1
    Then "[data-region='assign-assessment-summary'][data-assessment-mode='marker']" "css_element" should exist
    And "Mark out of 100" "field" should exist
    And I should see "Individual marking" in the "[data-region='assign-assessment-summary']" "css_element"

  Scenario: A marking workflow shows its native state without unlocking a released grade
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Workflow assessment |
      | markingworkflow | 1 |
      | grade[modgrade_type] | point |
      | grade[modgrade_point] | 100 |
    When I go to "Student One" "Workflow assessment" activity advanced grading page logged in as teacher1
    Then "[data-region='assign-assessment-summary']" "css_element" should exist
    And "Marking workflow state" "field" should exist
    And "[data-region='grade-panel'] form.gradeform" "css_element" should exist

  Scenario: The responsive assessment station does not duplicate its native form
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Mobile assessment |
      | assignfeedback_editpdf_enabled | 1 |
      | grade[modgrade_type] | point |
      | grade[modgrade_point] | 100 |
    And I go to "Student One" "Mobile assessment" activity advanced grading page logged in as teacher1
    And I change window size to "small"
    Then "[data-region='grade-panel'] form.gradeform" "css_element" should exist
    And "[data-region='assign-assessment-summary']" "css_element" should exist
    When I click on "Submission preview" "button"
    And I click on "Assessment" "button"
    Then "[data-region='grade-panel'] form.gradeform" "css_element" should exist
    And "[data-region='assign-assessment-summary']" "css_element" should exist

  Scenario: A manager's multiple-marker overview never reveals marker identities
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Private marker overview |
      | markingworkflow | 1 |
      | markingallocation | 1 |
      | markercount | 2 |
      | grade[modgrade_type] | point |
      | grade[modgrade_point] | 100 |
    And the following "mod_assign > marker_allocations" exist:
      | assign | user | marker |
      | Private marker overview | student1 | teacher1 |
      | Private marker overview | student1 | teacher2 |
    When I go to "Student One" "Private marker overview" activity advanced grading page logged in as teacher1
    Then "[data-region='assign-assessment-summary'][data-assessment-mode='multiple']" "css_element" should exist
    And I should see "2 expected marks" in the "[data-region='assign-assessment-summary']" "css_element"
    And I should not see "Teacher One" in the "[data-region='assign-assessment-summary']" "css_element"
    And I should not see "Teacher Two" in the "[data-region='assign-assessment-summary']" "css_element"
