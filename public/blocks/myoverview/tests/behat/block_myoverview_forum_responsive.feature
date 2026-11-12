@block @block_myoverview @mod_forum @javascript
Feature: Accessible recent forum discussions in My courses
  In order to read recent conversations without unnecessary navigation on a mobile device
  As an enrolled student
  I can expand and collapse the forum summary in my course overview

  Background:
    Given the following config values are set as admin:
      | enablemycourses | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | One | student1@example.com |
      | teacher1 | Teacher | One | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Programming PHP | PHP | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | PHP | student |
      | teacher1 | PHP | editingteacher |
    And the following "activities" exist:
      | activity | name | course | idnumber | showinmycourses |
      | forum | Questions and answers | PHP | forum1 | 1 |
    And the following "mod_forum > discussions" exist:
      | user | forum | name | message |
      | teacher1 | forum1 | PDO connection problem | Check the database host |

  Scenario: A course summary begins collapsed and can be expanded with an accessible control
    Given I am on the "My courses" page logged in as "student1"
    Then "[data-region='forum-discussions-content']" "css_element" should not be visible
    And the "aria-expanded" attribute of "[data-action='toggle-forum-discussions']" "css_element" should contain "false"
    When I click on "[data-action='toggle-forum-discussions']" "css_element" in the "Course overview" "block"
    Then the "aria-expanded" attribute of "[data-action='toggle-forum-discussions']" "css_element" should contain "true"
    And I should see "PDO connection problem" in the "[data-region='forum-discussions-content']" "css_element"
    And I should see "Collapse" in the "[data-action='toggle-forum-discussions']" "css_element"
    When I click on "[data-action='toggle-forum-discussions']" "css_element" in the "Course overview" "block"
    Then "[data-region='forum-discussions-content']" "css_element" should not be visible
    And the "aria-expanded" attribute of "[data-action='toggle-forum-discussions']" "css_element" should contain "false"

  Scenario: The link to the unified feed remains available when the summary is collapsed
    Given I am on the "My courses" page logged in as "student1"
    Then "[data-action='open-recent-feed']" "css_element" should be visible
    When I click on "[data-action='open-recent-feed']" "css_element" in the "Course overview" "block"
    Then I should see "Recent discussions" in the "page-header" "region"
    And "[data-action='feed-all']" "css_element" should be visible
    And "[data-action='feed-unread']" "css_element" should be visible
    And "[data-region='feed-course']" "css_element" should be visible
