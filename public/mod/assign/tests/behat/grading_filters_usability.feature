@mod @mod_assign
Feature: Teachers understand and remove native assignment grading filters without losing other criteria
  In order to find submissions without unexpectedly changing my view
  As a teacher
  I need a visible summary of the effective grading filters

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
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
    And the following "groups" exist:
      | name | course | idnumber |
      | Group One | C1 | G1 |
      | Group Two | C1 | G2 |
    And the following "group members" exist:
      | user | group |
      | student1 | G1 |
      | student2 | G2 |
    And the following "activity" exists:
      | activity | assign |
      | course | C1 |
      | name | Grading filters |
      | submissiondrafts | 0 |
      | assignsubmission_onlinetext_enabled | 1 |
    And the following "mod_assign > submissions" exist:
      | assign | user | onlinetext |
      | Grading filters | student1 | Work for review |

  @javascript
  Scenario: A status filter appears as a removable item and the native clear link still works
    Given I am on the "Grading filters" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    Then ".assign-grading-active-filters" "css_element" should not exist
    When I set the field "Status" to "Submitted"
    Then ".assign-grading-filter-chip[data-filter-key='status']" "css_element" should exist
    And I should see "Status: Submitted" in the ".assign-grading-active-filters" "css_element"
    And "Clear all" "link" should exist in the ".tertiary-navigation" "css_element"
    When I click on ".assign-grading-filter-chip[data-filter-key='status']" "css_element"
    Then ".assign-grading-filter-chip[data-filter-key='status']" "css_element" should not exist
    And the field "Status" matches value "All"

  @javascript
  Scenario: Changing status and removing it keeps the selected activity group
    Given I am on the "Grading filters" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    When I select "Group One" from the "Separate groups" singleselect
    Then I should see "Group: Group One" in the ".assign-grading-active-filters" "css_element"
    When I set the field "Status" to "Submitted"
    Then ".assign-grading-filter-chip[data-filter-key='group']" "css_element" should exist
    And ".assign-grading-filter-chip[data-filter-key='status']" "css_element" should exist
    When I click on ".assign-grading-filter-chip[data-filter-key='status']" "css_element"
    Then ".assign-grading-filter-chip[data-filter-key='status']" "css_element" should not exist
    And ".assign-grading-filter-chip[data-filter-key='group']" "css_element" should exist
    And I should see "Student One" in the "#submissions" "css_element"
    And I should not see "Student Two" in the "#submissions" "css_element"

  @javascript
  Scenario: Searching for a student does not clear a previously selected status
    Given I am on the "Grading filters" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I set the field "Status" to "Submitted"
    When I set the field "Search users" to "Student One"
    And I wait until "View all results (1)" "option_role" exists
    And I click on "View all results (1)" "option_role"
    And I wait until the page is ready
    Then ".assign-grading-filter-chip[data-filter-key='user']" "css_element" should exist
    And ".assign-grading-filter-chip[data-filter-key='status']" "css_element" should exist
    When I click on ".assign-grading-filter-chip[data-filter-key='user']" "css_element"
    Then ".assign-grading-filter-chip[data-filter-key='user']" "css_element" should not exist
    And ".assign-grading-filter-chip[data-filter-key='status']" "css_element" should exist

  @javascript
  Scenario: Clearing all filters also clears search selections
    Given I am on the "Grading filters" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    When I set the field "Status" to "Submitted"
    And I set the field "Search users" to "Student One"
    And I wait until "View all results (1)" "option_role" exists
    And I click on "View all results (1)" "option_role"
    Then ".assign-grading-filter-chip[data-filter-key='user']" "css_element" should exist
    When I click on "Clear all" "link" in the ".tertiary-navigation" "css_element"
    Then ".assign-grading-active-filters" "css_element" should not exist
    And I should see "Student One" in the "#submissions" "css_element"
    And I should see "Student Two" in the "#submissions" "css_element"

  @javascript
  Scenario: Initials are displayed separately from the selected status
    Given I am on the "Grading filters" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    When I click on "Filter by name" "combobox"
    And I select "O" in the "Last name" "core_course > initials bar"
    And I press "Apply"
    Then ".assign-grading-filter-chip[data-filter-key='tilast']" "css_element" should exist
    And I should see "Last name initial: O" in the ".assign-grading-active-filters" "css_element"
    When I click on ".assign-grading-filter-chip[data-filter-key='tilast']" "css_element"
    Then ".assign-grading-filter-chip[data-filter-key='tilast']" "css_element" should not exist

  @javascript
  Scenario: Marking workflow can be removed without resetting group and status
    Given I am on the "Grading filters" "assign activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I set the field "Use marking workflow" to "Yes"
    And I press "Save and display"
    And I navigate to "Submissions" in current page administration
    And I change window size to "large"
    And I set the field "Status" to "Submitted"
    And I click on "Advanced" "button" in the ".tertiary-navigation" "css_element"
    And I set the field "Marking state" in the ".extrafilters .dropdown-menu" "css_element" to "In marking"
    When I click on "Apply" "button" in the ".extrafilters .dropdown-menu" "css_element"
    Then ".assign-grading-filter-chip[data-filter-key='workflowfilter']" "css_element" should exist
    And ".assign-grading-filter-chip[data-filter-key='status']" "css_element" should exist
    When I click on ".assign-grading-filter-chip[data-filter-key='workflowfilter']" "css_element"
    Then ".assign-grading-filter-chip[data-filter-key='workflowfilter']" "css_element" should not exist
    And ".assign-grading-filter-chip[data-filter-key='status']" "css_element" should exist

  @javascript
  Scenario: Marker selection remains visible and can be reset independently
    Given I am on the "Grading filters" "assign activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I set the field "Use marking workflow" to "Yes"
    And I set the field "Use marking allocation" to "Yes"
    And I press "Save and display"
    And I navigate to "Submissions" in current page administration
    And I change window size to "large"
    And I click on "Advanced" "button" in the ".tertiary-navigation" "css_element"
    And I set the field "Marker" in the ".extrafilters .dropdown-menu" "css_element" to "No marker"
    When I click on "Apply" "button" in the ".extrafilters .dropdown-menu" "css_element"
    Then ".assign-grading-filter-chip[data-filter-key='markingallocationfilter']" "css_element" should exist
    And I should see "Marker: No marker" in the ".assign-grading-active-filters" "css_element"
    When I click on ".assign-grading-filter-chip[data-filter-key='markingallocationfilter']" "css_element"
    Then ".assign-grading-filter-chip[data-filter-key='markingallocationfilter']" "css_element" should not exist

  @javascript
  Scenario: Small screens show removable filters without replacing the native submissions table
    Given I am on the "Grading filters" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I set the field "Status" to "Submitted"
    When I change window size to "small"
    Then ".assign-grading-filters-toolbar" "css_element" should exist
    And ".assign-grading-active-filters" "css_element" should exist
    And "#submissions.assign-responsive-grading-table" "css_element" should exist

  Scenario: A student cannot access grading filters or the teacher's submissions table
    When I am on the "Grading filters" Activity page logged in as student1
    Then ".assign-grading-active-filters" "css_element" should not exist
    And "#submissions.assign-responsive-grading-table" "css_element" should not exist
