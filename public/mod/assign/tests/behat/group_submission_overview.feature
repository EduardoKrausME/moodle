@mod @mod_assign @assign_ux_15 @assign_ux_15_2
Feature: Group submissions show the current team and its confirmation progress
  In order to understand the status of our shared work
  As a learner
  I need confirmation counts and useful group membership guidance

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | One | student1@example.com |
      | student2 | Student | Two | student2@example.com |
      | student3 | Student | Three | student3@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
      | student2 | C1 | student |
      | student3 | C1 | student |

  @javascript
  Scenario: Real confirmations progress from zero to the shared final submission
    Given the following "groups" exist:
      | name | course | idnumber |
      | Project group | C1 | G1 |
    And the following "group members" exist:
      | user | group |
      | student1 | G1 |
      | student2 | G1 |
    And the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Shared project |
      | teamsubmission | 1 |
      | submissiondrafts | 1 |
      | requireallteammemberssubmit | 1 |
      | preventsubmissionnotingroup | 1 |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Shared project" Activity page logged in as student1
    Then I should see "Project group" in the ".assign-group-overview" "css_element"
    And I should see "0 of 2 members confirmed" in the ".assign-group-overview" "css_element"
    And "progress[aria-label='Individual confirmations for the current group attempt']" "css_element" should exist
    And I should not see "Student Two" in the ".assign-group-overview" "css_element"
    When I press "Add submission"
    And I set the field "Online text" to "Group draft"
    And I press "Save changes"
    Then I should see "Shared draft saved" in the ".assign-group-overview" "css_element"
    And I should see "0 of 2 members confirmed" in the ".assign-group-overview" "css_element"
    When I press "Submit assignment"
    And I press "Continue"
    Then I should see "Waiting for teammates" in the ".assign-group-overview" "css_element"
    And I should see "1 of 2 members confirmed" in the ".assign-group-overview" "css_element"
    And I should see "Your confirmation for this attempt has been registered" in the ".assign-group-overview" "css_element"
    When I am on the "Shared project" Activity page logged in as student2
    Then I should see "1 of 2 members confirmed" in the ".assign-group-overview" "css_element"
    And I should see "Your confirmation for this attempt has not been registered" in the ".assign-group-overview" "css_element"
    When I press "Submit assignment"
    And I press "Continue"
    Then I should see "Group work submitted" in the ".assign-group-overview" "css_element"
    And I should see "2 of 2 members confirmed" in the ".assign-group-overview" "css_element"
    When I am on the "Shared project" Activity page logged in as student1
    Then I should see "Group work submitted" in the ".assign-group-overview" "css_element"

  Scenario: Without requiring each confirmation the interface never shows an individual progress bar
    Given the following "groups" exist:
      | name | course | idnumber |
      | Project group | C1 | G1 |
    And the following "group members" exist:
      | user | group |
      | student1 | G1 |
      | student2 | G1 |
    And the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Team direct submission |
      | teamsubmission | 1 |
      | submissiondrafts | 0 |
      | requireallteammemberssubmit | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Team direct submission" Activity page logged in as student1
    Then I should see "Project group" in the ".assign-group-overview" "css_element"
    And I should see "Individual confirmation is not required from every member" in the ".assign-group-overview" "css_element"
    And ".assign-group-overview-meter" "css_element" should not exist
    When I press "Add submission"
    And I set the field "Online text" to "Team submission"
    And I press "Save changes"
    Then I should see "Group work submitted" in the ".assign-group-overview" "css_element"

  Scenario: Membership errors are explicit and never expose a confirmation action
    Given the following "groups" exist:
      | name | course | idnumber |
      | First group | C1 | G1 |
      | Second group | C1 | G2 |
    And the following "group members" exist:
      | user | group |
      | student1 | G1 |
      | student1 | G2 |
    And the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Group required |
      | teamsubmission | 1 |
      | submissiondrafts | 1 |
      | requireallteammemberssubmit | 1 |
      | preventsubmissionnotingroup | 1 |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Group required" Activity page logged in as student1
    Then I should see "Multiple eligible groups" in the ".assign-group-overview" "css_element"
    And I should not see "members confirmed" in the ".assign-group-overview" "css_element"
    And "Submit assignment" "button" should not exist
    When I am on the "Group required" Activity page logged in as student2
    Then I should see "No valid group" in the ".assign-group-overview" "css_element"
    And "Add submission" "button" should not exist

  Scenario: A permitted default group is labelled without inventing an individual confirmation requirement
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Default team project |
      | teamsubmission | 1 |
      | submissiondrafts | 0 |
      | requireallteammemberssubmit | 0 |
      | preventsubmissionnotingroup | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Default team project" Activity page logged in as student1
    Then I should see "Default group" in the ".assign-group-overview" "css_element"
    And ".assign-group-overview-meter" "css_element" should not exist
    And "Add submission" "button" should exist

  Scenario: Configured grouping determines which of a learner's groups is eligible
    Given the following "groups" exist:
      | name | course | idnumber |
      | Eligible group | C1 | G1 |
      | Other group | C1 | G2 |
    And the following "group members" exist:
      | user | group |
      | student1 | G1 |
      | student1 | G2 |
      | student2 | G1 |
    And the following "groupings" exist:
      | name | course | idnumber |
      | Assignment grouping | C1 | GG1 |
    And the following "grouping groups" exist:
      | grouping | group |
      | GG1 | G1 |
    And the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Grouped team project |
      | teamsubmission | 1 |
      | submissiondrafts | 1 |
      | requireallteammemberssubmit | 1 |
      | preventsubmissionnotingroup | 1 |
      | teamsubmissiongroupingid | GG1 |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Grouped team project" Activity page logged in as student1
    Then I should see "Eligible group" in the ".assign-group-overview" "css_element"
    And I should not see "Other group" in the ".assign-group-overview" "css_element"
    And I should see "0 of 2 members confirmed" in the ".assign-group-overview" "css_element"
    And "Add submission" "button" should exist
