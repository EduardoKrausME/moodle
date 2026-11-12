@mod @mod_assign
Feature: Assignment availability overview uses viewer-specific deadlines
  In order to plan submissions correctly
  As a student
  I need the displayed dates and countdown to reflect the native rules

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | One | student1@example.com |
      | student2 | Student | Two | student2@example.com |
      | teacher1 | Teacher | One | teacher1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
      | student2 | C1 | student |
      | teacher1 | C1 | editingteacher |

  Scenario: Future opening does not enable submissions
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Future availability |
      | allowsubmissionsfromdate | ##+2 days## |
      | duedate | ##+5 days## |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Future availability" Activity page logged in as student1
    Then ".assign-availability" "css_element" should exist
    And I should see "Not yet available" in the ".assign-availability" "css_element"
    And ".assign-availability-timeline time" "css_element" should exist
    And "Add submission" "button" should not exist

  Scenario: Late submissions remain possible until the cut-off
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Late but open |
      | duedate | ##-1 days## |
      | cutoffdate | ##+2 days## |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Late but open" Activity page logged in as student1
    Then I should see "Late submissions still accepted" in the ".assign-availability" "css_element"
    And I should see "The due date has passed" in the ".assign-availability" "css_element"
    And "Add submission" "button" should exist

  Scenario: Group overrides are shown only for the affected student
    Given the following "groups" exist:
      | name | course | idnumber |
      | Group 1 | C1 | G1 |
    And the following "group members" exist:
      | user | group |
      | student1 | G1 |
    And the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Overridden group assignment |
      | duedate | ##1 Jan 2028 08:00## |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > group overrides" exist:
      | assignment | group | duedate |
      | Overridden group assignment | G1 | ##1 Jan 2030 08:00## |
    When I am on the "Overridden group assignment" Activity page logged in as student1
    Then I should see "2030" in the ".assign-availability" "css_element"
    And I am on the "Overridden group assignment" Activity page logged in as student2
    And I should see "2028" in the ".assign-availability" "css_element"
    And I should not see "2030" in the ".assign-availability" "css_element"

  Scenario: Personal overrides take precedence for the affected student
    Given the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Personal deadline assignment |
      | duedate | ##1 Jan 2028 08:00## |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > user overrides" exist:
      | assignment | user | duedate |
      | Personal deadline assignment | student1 | ##1 Jan 2030 08:00## |
    When I am on the "Personal deadline assignment" Activity page logged in as student1
    Then I should see "2030" in the ".assign-availability" "css_element"
    And I am on the "Personal deadline assignment" Activity page logged in as student2
    And I should not see "2030" in the ".assign-availability" "css_element"

  Scenario: A timed assignment explains when its timer starts
    Given the following config values are set as admin:
      | config | value | plugin |
      | enabletimelimit | 1 | assign |
    And the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Timed availability |
      | timelimit | 3600 |
      | assignsubmission_onlinetext_enabled | 1 |
    When I am on the "Timed availability" Activity page logged in as student1
    Then ".assign-availability-limit" "css_element" should exist
    And I should see "only starts after you confirm" in the ".assign-availability" "css_element"
    And "#mod_assign_timelimit_block" "css_element" should not exist
