@mod @mod_assign
Feature: A learner can review and confirm their saved assignment draft
  In order to understand when work is actually sent for grading
  As a student
  I need distinct saved-draft and final-submission states

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | One | student1@example.com |
      | student2 | Student | Two | student2@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
      | student2 | C1 | student |

  @javascript
  Scenario: Draft is not submitted until the learner reviews the content and confirms
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Final submission required |
      | submissiondrafts | 1 |
      | requiresubmissionstatement | 1 |
      | assignsubmission_onlinetext_enabled | 1 |
    And I am on the "Final submission required" Activity page logged in as student1
    When I press "Add submission"
    Then I should see "Saving changes stores a draft" in the ".assign-submission-savehint" "css_element"
    When I set the field "Online text" to "My final assignment answer"
    And I press "Save changes"
    Then I should see "Draft saved - ready to confirm" in the ".assign-submission-workflow" "css_element"
    And I should see "Your draft is saved but not yet submitted for grading" in the ".assign-submission-workflow" "css_element"
    When I press "Submit assignment"
    Then I should see "Review your saved work" in the ".assign-confirm-review" "css_element"
    And I should see "My final assignment answer" in the ".assign-confirm-review" "css_element"
    And I should see "Selecting Continue submits this saved work for grading"
    And I should see "This submission is my own work"
    When I press "Continue"
    Then I should see "You are required to agree to this statement before you can submit"
    When I set the field "submissionstatement" to "1"
    And I press "Continue"
    Then I should see "Submitted for grading" in the ".assign-submission-workflow" "css_element"
    And "Submit assignment" "button" should not exist

  @javascript
  Scenario: Direct saving does not claim a draft is awaiting confirmation
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Direct submission |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Direct submission" Activity page logged in as student1
    And I press "Add submission"
    Then I should see "does not use a separate final confirmation" in the ".assign-submission-savehint" "css_element"
    When I set the field "Online text" to "Submitted directly"
    And I press "Save changes"
    Then I should see "Submitted for grading" in the ".assign-submission-workflow" "css_element"
    And ".assign-submission-workflow-steps" "css_element" should not exist

  @javascript
  Scenario: Submission review remains available when the statement is not required
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Review without declaration |
      | submissiondrafts | 1 |
      | requiresubmissionstatement | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Review without declaration" Activity page logged in as student1
    And I press "Add submission"
    And I set the field "Online text" to "Answer to review"
    And I press "Save changes"
    And I press "Submit assignment"
    Then ".assign-confirm-review" "css_element" should exist
    And I should see "Answer to review" in the ".assign-confirm-review" "css_element"
    And I should not see "You are required to agree to this statement"
    When I press "Continue"
    Then I should see "Submitted for grading" in the ".assign-submission-workflow" "css_element"

  @javascript
  Scenario: A group requiring individual confirmations does not imply everyone is finished
    Given the following "groups" exist:
      | name | course | idnumber |
      | Group 1 | C1 | G1 |
    And the following "group members" exist:
      | user | group |
      | student1 | G1 |
      | student2 | G1 |
    And the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Group review |
      | teamsubmission | 1 |
      | requireallteammemberssubmit | 1 |
      | submissiondrafts | 1 |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Group review" Activity page logged in as student1
    And I press "Add submission"
    And I set the field "Online text" to "Shared group work"
    And I press "Save changes"
    Then I should see "This group assignment may require other group members" in the ".assign-submission-workflow" "css_element"
    When I press "Submit assignment"
    Then I should see "This group assignment may require other group members" in the ".assign-confirm-groupnote" "css_element"
    And I should see "Shared group work" in the ".assign-confirm-review" "css_element"
