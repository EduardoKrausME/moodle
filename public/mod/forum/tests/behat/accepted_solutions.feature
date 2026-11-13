@mod @mod_forum @javascript
Feature: Accepted solutions respect discussion authorship and forum settings
  In order to identify a verified answer without changing forum posts
  As a discussion starter or teacher
  I can accept and remove a visible reply when the forum enables solutions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
      | student2 | Student   | Two      | student2@example.com |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name             | course | idnumber | allowsolutions |
      | forum    | Solutions forum  | C1     | forum1   | 1              |
      | forum    | Ordinary forum   | C1     | forum2   | 0              |
    And the following "mod_forum > discussions" exist:
      | user     | forum  | name                | message             |
      | student1 | forum1 | Help with an issue  | How can I fix this? |
      | student1 | forum2 | An ordinary topic   | Standard forum post |
    And the following "mod_forum > posts" exist:
      | user     | parentsubject      | subject           | message          |
      | student2 | Help with an issue | Verified answer   | Try this method. |

  @forum_ux_14
  Scenario: The starter can accept and remove a reply
    When I am on the "Solutions forum" "forum activity" page logged in as student1
    And I follow "Help with an issue"
    Then "Mark as solution" "button" should exist
    When I click on "Mark as solution" "button"
    Then "View accepted reply" "link" should exist
    And "Remove accepted solution" "button" should exist
    When I click on "Remove accepted solution" "button"
    Then "Mark as solution" "button" should exist
    And "View accepted reply" "link" should not exist

  Scenario: A reply author cannot accept the solution for someone else's discussion
    When I am on the "Solutions forum" "forum activity" page logged in as student2
    And I follow "Help with an issue"
    Then "Mark as solution" "button" should not exist
    And "Remove accepted solution" "button" should not exist

  Scenario: Teachers can manage an accepted reply
    When I am on the "Solutions forum" "forum activity" page logged in as teacher1
    And I follow "Help with an issue"
    Then "Mark as solution" "button" should exist
    When I click on "Mark as solution" "button"
    Then "Remove accepted solution" "button" should exist

  Scenario: Disabled forums do not expose accepted-solution actions
    When I am on the "Ordinary forum" "forum activity" page logged in as teacher1
    And I follow "An ordinary topic"
    Then "Mark as solution" "button" should not exist
    And "View accepted reply" "link" should not exist
