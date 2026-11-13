@mod @mod_forum @javascript
Feature: Accepted answers are discoverable in native forum discussion lists
  In order to locate helpful replies without changing their original position
  As a forum participant
  I can filter by visible accepted solutions and navigate to a valid answer

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
      | forum    | Solutions forum  | C1     | forum1   | 1              |
      | forum    | Ordinary forum   | C1     | forum2   | 0              |
    And the following "mod_forum > discussions" exist:
      | user     | forum  | name              | message                |
      | student1 | forum1 | Answered question | How do I solve it?     |
      | student1 | forum1 | Pending question  | Still waiting for help |
      | student1 | forum2 | Ordinary question | Nothing configured     |
    And the following "mod_forum > posts" exist:
      | user     | parentsubject    | subject       | message            |
      | student2 | Answered question | Correct reply | The right method |

  Scenario: Accepted solution link and filters only include matching discussions
    Given I am on the "Solutions forum" "forum activity" page logged in as teacher1
    Then I should see "No accepted solution yet"
    When I follow "Answered question"
    And I click on "Mark as solution" "button"
    Then I should see "Accepted solution"
    And "View accepted reply" "link" should exist
    When I am on the "Solutions forum" "forum activity" page
    Then "View accepted reply" "link" should exist
    When I follow "With accepted solution"
    Then I should see "Answered question"
    And I should not see "Pending question"
    When I follow "View accepted reply"
    Then I should see "The right method"

  Scenario: Unaccepted filter only returns discussions without accepted answers
    Given I am on the "Solutions forum" "forum activity" page logged in as teacher1
    When I follow "Without accepted solution"
    Then I should see "Pending question"
    And I should see "Answered question"

  Scenario: Forums without the option do not expose accepted solution filters
    Given I am on the "Ordinary forum" "forum activity" page logged in as teacher1
    Then "With accepted solution" "link" should not exist
    And "No accepted solution yet" "text" should not exist
