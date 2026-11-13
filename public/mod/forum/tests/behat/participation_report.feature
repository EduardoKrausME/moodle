@mod @mod_forum @javascript
Feature: Teachers can see safe participation metrics for their forum
  In order to understand forum involvement without grading students automatically
  As a teacher
  I can open a participation dashboard and the native participant summary

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
      | student2 | Student   | Two      | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity | name             | course | idnumber | allowsolutions |
      | forum    | Discussion forum | C1     | forum1   | 1              |
    And the following "mod_forum > discussions" exist:
      | user     | forum  | name           | message           |
      | student1 | forum1 | First question | How can I do it?  |
    And the following "mod_forum > posts" exist:
      | user     | parentsubject  | subject   | message          |
      | student2 | First question | An answer | Use this method |

  Scenario: Teacher can open aggregates and the original detailed report
    Given I am on the "Discussion forum" "forum activity" page logged in as teacher1
    And "Forum participation" "link" should exist
    When I follow "Forum participation"
    Then I should see "Discussions started"
    And I should see "Replies published"
    And I should see "Unique participants"
    And I should see "Answer coverage"
    And "Download aggregated CSV" "link" should exist
    And "Participant summary" "link" should exist
    When I follow "Participant summary"
    Then I should see "Forum summary report"

  Scenario: Student cannot access teacher-only participation navigation
    Given I am on the "Discussion forum" "forum activity" page logged in as student1
    Then "Forum participation" "link" should not exist
