@mod @mod_assign @assign_ux_15 @assign_ux_15_1
Feature: Assignment landing page overview
  In order to understand what to do next without losing existing assignment functionality
  As a student or a teacher
  I need a clear assignment overview that respects my permissions

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | One | student1@example.com |
      | teacher1 | Teacher | One | teacher1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
      | teacher1 | C1 | editingteacher |

  @javascript
  Scenario: Student sees the overview and can submit with the native action
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Overview assignment |
      | intro | Read the activity description before submitting |
      | duedate | ##+2 days## |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Overview assignment" Activity page logged in as student1
    Then ".assign-landing-overview" "css_element" should exist
    And I should see "Read the activity description before submitting"
    And I should see "Submission status" in the ".assign-landing-overview" "css_element"
    And I should see "Due date" in the ".assign-landing-overview" "css_element"
    And ".assign-landing-overview-grade" "css_element" should not exist
    And "Add submission" "button" should exist
    When I press "Add submission"
    And I set the field "Online text" to "My assignment answer"
    And I press "Save changes"
    Then ".assign-landing-overview" "css_element" should exist
    And I should see "Submitted for grading" in the ".assign-landing-overview" "css_element"
    And I should see "My assignment answer"

  @javascript
  Scenario: Teacher retains the native grading summary and submission actions
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Overview assignment |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Overview assignment" Activity page logged in as teacher1
    Then ".assign-landing .gradingsummary" "css_element" should exist
    And ".assign-grading-dashboard" "css_element" should exist
    And I should see "Grading summary" in the ".assign-landing" "css_element"
    When I click on "Show original grading summary" "text"
    Then I should see "Number of participants" in the ".assign-landing" "css_element"
