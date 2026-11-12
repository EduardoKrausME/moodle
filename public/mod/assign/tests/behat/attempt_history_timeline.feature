@mod @mod_assign
Feature: Students can review their assignment attempts in a chronological timeline
  In order to understand which attempt is active and consult earlier work
  As an authorised learner
  I need a read-only timeline that preserves the native submission actions

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

  Scenario: One existing submission is shown as the current attempt
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Single submission |
      | assignsubmission_onlinetext_enabled | 1 |
      | submissiondrafts | 0 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Single submission | student1 | My only submitted work |
    When I am on the "Single submission" Activity page logged in as student1
    Then ".assign-attempt-timeline" "css_element" should exist
    And I should see "Attempt 1" in the ".assign-attempt-timeline" "css_element"
    And I should see "Current attempt" in the ".assign-attempt-timeline" "css_element"
    And I should see "Submitted for grading" in the ".assign-attempt-timeline" "css_element"
    And I should not see "Review this previous attempt" in the ".assign-attempt-timeline" "css_element"

  @javascript
  Scenario: A manually reopened attempt appears before the submitted previous attempt
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Reopened written work |
      | assignsubmission_onlinetext_enabled | 1 |
      | submissiondrafts | 0 |
      | maxattempts | 3 |
      | attemptreopenmethod | manual |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Reopened written work | student1 | First attempt content |
    And I am on the "Reopened written work" Activity page logged in as teacher1
    And I change window size to "large"
    And I go to "Student One" "Reopened written work" activity advanced grading page
    And I change window size to "medium"
    And I set the following fields to these values:
      | Allow another attempt | 1 |
    And I press "Save changes"
    And I log out
    When I am on the "Reopened written work" Activity page logged in as student1
    Then I should see "Attempt 2" in the ".assign-attempt-timeline" "css_element"
    And I should see "Attempt 1" in the ".assign-attempt-timeline" "css_element"
    And I should see "Reopened" in the ".assign-attempt-timeline" "css_element"
    And I should see "Current attempt" in the ".assign-attempt-timeline" "css_element"
    And "details.assign-attempt-timeline-details" "css_element" should exist
    When I click on "Review this previous attempt" "text"
    Then I should see "First attempt content" in the ".assign-attempt-timeline-detail-content" "css_element"
    And I should see "Add a new attempt based on previous submission"

  Scenario: Team attempt timeline does not expose another group's work
    Given the following "groups" exist:
      | name | course | idnumber |
      | Team A | C1 | G1 |
      | Team B | C1 | G2 |
    And the following "group members" exist:
      | user | group |
      | student1 | G1 |
      | student2 | G1 |
      | student3 | G2 |
    And the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Team history |
      | assignsubmission_onlinetext_enabled | 1 |
      | teamsubmission | 1 |
      | submissiondrafts | 0 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Team history | student1 | Team A private work |
      | Team history | student3 | Team B private work |
    When I am on the "Team history" Activity page logged in as student2
    Then ".assign-attempt-timeline" "css_element" should exist
    And I should see "Current attempt" in the ".assign-attempt-timeline" "css_element"
    And I should see "Team A private work"
    And I should not see "Team B private work"

  Scenario: Current extension deadline is displayed without claiming an extra submission attempt
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Extended written work |
      | assignsubmission_onlinetext_enabled | 1 |
      | duedate | ## 2034-01-01 ## |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Extended written work | student1 | Existing answer |
    And the following "mod_assign > extensions" exist:
      | assign | user | extensionduedate |
      | Extended written work | student1 | ## 2034-02-02 ## |
    When I am on the "Extended written work" Activity page logged in as student1
    Then I should see "Extension due date" in the ".assign-attempt-timeline" "css_element"
    And I should see "Attempt 1" in the ".assign-attempt-timeline" "css_element"
    And I should not see "Attempt 2" in the ".assign-attempt-timeline" "css_element"
