@mod @mod_forum @javascript
Feature: Teachers can find questions requiring attention without exposing restricted forum posts
  In order to respond to learners who are still waiting for help
  As a teacher
  I need an authorised cross-forum queue of unanswered questions

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
      | forum    | Questions forum  | C1     | forum1   | 1              |
      | forum    | Ordinary forum   | C1     | forum2   | 0              |
    And the following "mod_forum > discussions" exist:
      | user     | forum  | name                  | message               |
      | student1 | forum1 | Pending question      | Need help here        |
      | student1 | forum1 | Answered question     | Question with a reply |
      | student1 | forum2 | Second forum question | Waiting in this forum |
    And the following "mod_forum > posts" exist:
      | user     | parentsubject  | subject      | message                 |
      | student2 | Answered question | Reply text | Public answer is here |

  @forum_ux_14
  Scenario: A teacher can filter unresolved questions and open the native forum
    Given I am on the "Questions forum" "forum activity" page logged in as teacher1
    When I follow "Questions requiring attention"
    Then I should see "Pending question"
    And I should see "Answered question"
    And I should see "Second forum question"
    When I set the following fields to these values:
      | Status | No replies |
    And I press "Apply filters"
    Then I should see "Pending question"
    And I should not see "Answered question"
    When I follow "Pending question"
    Then I should see "Need help here"

  Scenario: A student cannot open the teacher queue using normal forum navigation
    Given I am on the "Questions forum" "forum activity" page logged in as student1
    Then "Questions requiring attention" "link" should not exist

  @accessibility
  Scenario: Teacher question queue meets basic accessibility checks
    Given I am on the "Questions forum" "forum activity" page logged in as teacher1
    When I follow "Questions requiring attention"
    Then the page should meet accessibility standards with "wcag143" extra tests
