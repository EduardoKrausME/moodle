@mod @mod_assign @javascript
Feature: Teachers can understand exactly which assignment submissions bulk actions affect
  In order to avoid processing unintended participants
  As a teacher
  I need page-scoped selection counts and explicit native confirmations

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
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
    And the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Bulk grading review |
      | submissiondrafts | 1 |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Bulk grading review | student1 | Essay One |
      | Bulk grading review | student2 | Essay Two |
      | Bulk grading review | student3 | Essay Three |

  Scenario: Native Select all stays available with an explicit current-page description
    Given I am on the "Bulk grading review" Activity page logged in as teacher1
    When I navigate to "Submissions" in current page administration
    Then "Select all" "checkbox" should exist
    And "#submissions [name='selectall'][aria-describedby='assign-bulk-selectall-scope']" "css_element" should exist
    And I should see "Select all participants on this page" in the "#submissions" "css_element"

  Scenario: A partial selection gives the header an accessible mixed state
    Given I am on the "Bulk grading review" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    When I click on "Select Student One" "checkbox"
    Then "#submissions [name='selectall'][aria-checked='mixed']" "css_element" should exist
    And I should see "Selection applies to this page only." in the "sticky-footer" "region"
    And I should see "Clear selection" in the "sticky-footer" "region"

  Scenario: Select all applies to the visible page and supports native cancellation
    Given I am on the "Bulk grading review" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    When I click on "Select all" "checkbox"
    Then "#submissions [name='selectall'][aria-checked='true']" "css_element" should exist
    And I should see "Selection applies to this page only." in the "sticky-footer" "region"
    When I click on "Clear selection" "button" in the "sticky-footer" "region"
    Then "#submissions [name='selectall'][aria-checked='false']" "css_element" should exist

  Scenario: Native lock confirmation shows the selected count and page scope
    Given I am on the "Bulk grading review" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Select all" "checkbox"
    When I click on "Lock" "button" in the "sticky-footer" "region"
    Then I should see "3 participants selected on this page" in the "Lock submissions" "dialogue"
    And I should see "The action applies only to the selected participants on this page." in the "Lock submissions" "dialogue"
    When I click on "Cancel" "button" in the "Lock submissions" "dialogue"
    Then I should see "Essay One"
    And I should see "Essay Two"

  Scenario: Native delete operation explains the selection before deleting submissions
    Given the following "role capability" exists:
      | role | editingteacher |
      | mod/assign:editothersubmission | allow |
    And I am on the "Bulk grading review" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Select all" "checkbox"
    When I click on "Delete" "button" in the "sticky-footer" "region"
    Then I should see "3 participants selected on this page" in the "Remove submission" "dialogue"
    And I should see "The action applies only to the selected participants on this page." in the "Remove submission" "dialogue"
    When I click on "Cancel" "button" in the "Remove submission" "dialogue"
    Then I should see "Essay One"
    And I should see "Essay Three"

  Scenario: A teacher without submission deletion capability cannot use bulk delete
    Given I am on the "Bulk grading review" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    When I click on "Select all" "checkbox"
    Then I should not see "Delete" in the "sticky-footer" "region"

  Scenario: Bulk selection remains usable on a narrow screen
    Given I am on the "Bulk grading review" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I change window size to "small"
    When I click on "Select all" "checkbox" in the "#submissions" "css_element"
    Then "[data-region='assign-bulk-page-scope']" "css_element" should be visible
    And I should see "Clear selection" in the "sticky-footer" "region"
    And "#submissions.assign-responsive-grading-table" "css_element" should exist

  Scenario: The bulk action confirmation is not displayed to the student
    Given I am on the "Bulk grading review" Activity page logged in as student1
    Then "[data-type='bulkactions']" "css_element" should not exist
    And "[data-region='assign-bulk-confirm']" "css_element" should not exist

  Scenario: The native bulk workflow can still open its second-step form
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Bulk workflow review |
      | markingworkflow | 1 |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Bulk workflow review | student1 | Workflow One |
    And I am on the "Bulk workflow review" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Select all" "checkbox"
    When I click on "Change marking state" "button" in the "sticky-footer" "region"
    Then I should see "1 participants selected on this page" in the "Set marking workflow state" "dialogue"
    When I click on "Change marking state" "button" in the "Set marking workflow state" "dialogue"
    Then I should see "Marking workflow state"
