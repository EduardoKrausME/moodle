@mod @mod_assign
Feature: Assignment edit form has section navigation while retaining native Moodle fields
  In order to configure assignments more easily
  As a teacher
  I need section shortcuts without changing submission and grading settings

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | One | teacher1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Settings navigation assignment |
      | assignsubmission_onlinetext_enabled | 1 |

  @javascript
  Scenario: Teacher can jump to existing native form sections
    When I am on the "Settings navigation assignment" "assign activity editing" page logged in as teacher1
    Then "[data-region='assign-settings-navigation']" "css_element" should be visible
    And "[data-region='assign-settings-navigation'] a[href='#id_general']" "css_element" should exist
    And "[data-region='assign-settings-navigation'] a[href='#id_availability']" "css_element" should exist
    And "[data-region='assign-settings-navigation'] a[href='#id_submissiontypes']" "css_element" should exist
    And "[data-region='assign-settings-navigation'] a[href='#id_feedbacktypes']" "css_element" should exist
    And "[data-region='assign-settings-navigation'] a[href='#id_submissionsettings']" "css_element" should exist
    And "[data-region='assign-settings-navigation'] a[href='#id_groupsubmissionsettings']" "css_element" should exist
    And "[data-region='assign-settings-navigation'] a[href='#id_notifications']" "css_element" should exist
    And "[data-region='assign-settings-navigation'] a[href='#id_modstandardgrade']" "css_element" should exist
    And "[data-region='assign-settings-navigation'] a[href='#id_modstandardelshdr']" "css_element" should exist
    And I click on "[data-region='assign-settings-navigation'] a[href='#id_groupsubmissionsettings']" "css_element"
    And the "teamsubmission" "select" should be visible
    And the "submissiondrafts" "select" should exist

  @javascript
  Scenario: Changing a native submission setting still persists
    Given I am on the "Settings navigation assignment" "assign activity editing" page logged in as teacher1
    And I click on "[data-region='assign-settings-navigation'] a[href='#id_submissionsettings']" "css_element"
    And I set the field "submissiondrafts" to "Yes"
    When I press "Save and display"
    And I am on the "Settings navigation assignment" "assign activity editing" page
    Then the field "submissiondrafts" matches value "1"

  Scenario: Core settings remain present without JavaScript
    When I am on the "Settings navigation assignment" "assign activity editing" page logged in as teacher1
    Then the "name" "text_field" should exist
    And the "submissiondrafts" "select" should exist
    And the "teamsubmission" "select" should exist
