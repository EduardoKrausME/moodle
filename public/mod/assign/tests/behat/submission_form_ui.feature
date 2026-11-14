@mod @mod_assign @assign_ux_15 @assign_ux_15_1
Feature: Native assignment submission controls have clear responsive guidance
  In order to submit my work safely
  As a student
  I need file and online text guidance while keeping Moodle's native controls

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

  @javascript @_file_upload
  Scenario: File-only submission shows real configured limits and retains native upload
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Files only |
      | submissiondrafts | 0 |
      | assignsubmission_file_enabled | 1 |
      | assignsubmission_file_maxfiles | 2 |
      | assignsubmission_file_maxsizebytes | 1000000 |
      | assignsubmission_file_filetypes | .txt,.csv |
      | assignsubmission_onlinetext_enabled | 0 |
    And I am on the "Files only" Activity page logged in as student1
    When I press "Add submission"
    Then ".assign-submission-intro" "css_element" should exist
    And ".assign-submission-guidance-file" "css_element" should exist
    And I should see "Maximum files: 2" in the ".assign-submission-guidance-file" "css_element"
    And I should see "Accepted file types: .txt,.csv" in the ".assign-submission-guidance-file" "css_element"
    And ".assign-submission-guidance-text" "css_element" should not exist
    And I upload "lib/tests/fixtures/empty.txt" file to "File submissions" filemanager
    And I press "Save changes"
    Then "empty.txt" "link" should exist

  @javascript
  Scenario: Online text submission shows word limits and preserves validation and saving
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Online only |
      | submissiondrafts | 0 |
      | assignsubmission_file_enabled | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignsubmission_onlinetext_wordlimit_enabled | 1 |
      | assignsubmission_onlinetext_wordlimit | 6 |
    And I am on the "Online only" Activity page logged in as student1
    When I press "Add submission"
    Then ".assign-submission-guidance-text" "css_element" should exist
    And I should see "Word limit: 6" in the ".assign-submission-guidance-text" "css_element"
    And ".assign-submission-guidance-file" "css_element" should not exist
    And I set the field "Online text" to "one two three four five six seven"
    And I press "Save changes"
    And I should see "The word limit for this assignment is 6 words"
    And I set the field "Online text" to "one two three"
    And I press "Save changes"
    Then I should see "Submitted for grading"

  @javascript
  Scenario: Group submission retains draft status and both native plugin controls
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
      | name | Group submission |
      | teamsubmission | 1 |
      | submissiondrafts | 1 |
      | assignsubmission_file_enabled | 1 |
      | assignsubmission_file_maxfiles | 2 |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Group submission" Activity page logged in as student1
    And I press "Add submission"
    Then I should see "Saving changes keeps your work as a draft" in the ".assign-submission-intro" "css_element"
    And I should see "This is a group submission" in the ".assign-submission-intro" "css_element"
    And ".assign-submission-guidance-file" "css_element" should exist
    And ".assign-submission-guidance-text" "css_element" should exist
    And "Save changes" "button" should exist

  Scenario: Native form guidance remains visible without JavaScript
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | No script submission |
      | assignsubmission_onlinetext_enabled | 1 |
      | assignsubmission_file_enabled | 0 |
    When I am on the "No script submission" Activity page logged in as student1
    And I press "Add submission"
    Then ".assign-submission-intro" "css_element" should exist
    And ".assign-submission-guidance-text" "css_element" should exist
    And "Save changes" "button" should exist
