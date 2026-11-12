@mod @mod_quiz
Feature: Quiz entry-page overview
  In order to understand the rules before starting a quiz
  As a student
  I need a clear summary without changing existing quiz access restrictions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student  | Student   | One      | student@example.com |
      | teacher  | Teacher   | One      | teacher@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student  | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name    | course | idnumber |
      | qbank    | Qbank 1 | C1     | qbank1   |
    And the following "question categories" exist:
      | contextlevel    | reference | name           |
      | Activity module | qbank1    | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext    |
      | Test questions   | truefalse | TF1  | First question  |

  Scenario: Available quiz shows the existing start button and key settings
    Given the following "activities" exist:
      | activity | name   | course | attempts | timelimit | navmethod |
      | quiz     | Quiz 1 | C1     | 3        | 1800      | free      |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    Then I should see "Quiz at a glance" in the ".mod-quiz-overview" "css_element"
    And I should see "Ready to start" in the ".mod-quiz-overview" "css_element"
    And I should see "Attempts allowed" in the ".mod-quiz-overview" "css_element"
    And I should see "Attempts made" in the ".mod-quiz-overview" "css_element"
    And I should see "Time limit" in the ".mod-quiz-overview" "css_element"
    And I should see "Navigation method" in the ".mod-quiz-overview" "css_element"
    And "Attempt quiz" "button" should exist

  Scenario: Quiz opening in the future does not offer an attempt
    Given the following "activities" exist:
      | activity | name   | course | timeopen     |
      | quiz     | Quiz 1 | C1     | ##tomorrow## |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    Then I should see "Opens later" in the ".mod-quiz-overview" "css_element"
    And I should see "Quiz opens" in the ".mod-quiz-overview" "css_element"
    And "Attempt quiz" "button" should not exist

  Scenario: A single completed attempt cannot be restarted
    Given the following "activities" exist:
      | activity | name   | course | attempts |
      | quiz     | Quiz 1 | C1     | 1        |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    And user "student" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    Then I should see "No more attempts available" in the ".mod-quiz-overview" "css_element"
    And "Attempt quiz" "button" should not exist

  Scenario: An empty quiz retains the teacher's add-question action
    Given the following "activities" exist:
      | activity | name   | course |
      | quiz     | Quiz 1 | C1     |
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "teacher"
    Then I should see "No questions have been added yet" in the ".mod-quiz-overview" "css_element"
    And "Add question" "link" should exist

  @javascript
  Scenario: An unfinished attempt retains the native continue action
    Given the following "activities" exist:
      | activity | name   | course |
      | quiz     | Quiz 1 | C1     |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    And I press "Attempt quiz"
    And I am on the "Quiz 1" "mod_quiz > View" page
    Then I should see "In progress" in the ".mod-quiz-overview" "css_element"
    And "Continue your attempt" "button" should exist
