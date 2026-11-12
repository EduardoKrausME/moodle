@mod @mod_quiz
Feature: Responsive quiz attempt interface
  As a student
  I need to read and answer questions on different screen sizes
  Without changing the native quiz navigation or submission behaviour

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student  | Student   | One      | student@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student  | C1     | student |
    And the following "activities" exist:
      | activity | name   | course | idnumber | navmethod  |
      | quiz     | Quiz 1 | C1     | quiz1    | free       |
      | quiz     | Quiz 2 | C1     | quiz2    | sequential |
      | quiz     | Quiz 3 | C1     | quiz3    | free       |
    And the following "question categories" exist:
      | contextlevel    | reference | name           |
      | Activity module | quiz1     | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext    |
      | Test questions   | truefalse | TF1  | First question  |
      | Test questions   | truefalse | TF2  | Second question |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 1    |
    And quiz "Quiz 2" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 2    |
    And quiz "Quiz 3" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 2    |

  @javascript
  Scenario: Multiple questions preserve their native answer controls and submission
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    Then I should see "First question" in the ".mod-quiz-attempt-content" "css_element"
    And I should see "Second question" in the ".mod-quiz-attempt-content" "css_element"
    And the "role" attribute of ".mod-quiz-attempt-navigation" "css_element" should contain "group"
    And the "aria-label" attribute of ".mod-quiz-attempt-navigation" "css_element" should contain "Quiz navigation"
    And I click on "True" "radio" in the "First question" "question"
    And I click on "False" "radio" in the "Second question" "question"
    And I press "Finish attempt ..."
    And I should see "Summary of attempt"

  @javascript
  Scenario: Sequential navigation retains forward-only movement
    Given I am on the "Quiz 2" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    Then "Previous page" "button" should not exist
    And I should see "First question" in the ".mod-quiz-attempt-content" "css_element"
    And I press "Next page"
    And I should see "Second question" in the ".mod-quiz-attempt-content" "css_element"
    And "Previous page" "button" should not exist
    And I press "Finish attempt ..."
    And I should see "Summary of attempt"

  @javascript
  Scenario: Free navigation retains previous and next page controls
    Given I am on the "Quiz 3" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    Then "Previous page" "button" should not exist
    And I should see "First question" in the ".mod-quiz-attempt-content" "css_element"
    And I press "Next page"
    And I should see "Second question" in the ".mod-quiz-attempt-content" "css_element"
    And "Previous page" "button" should exist
    And I press "Previous page"
    And I should see "First question" in the ".mod-quiz-attempt-content" "css_element"

  Scenario: Question controls and form remain available without JavaScript
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    Then I should see "First question" in the ".mod-quiz-attempt-content" "css_element"
    And I should see "Second question" in the ".mod-quiz-attempt-content" "css_element"
    And "Finish attempt ..." "button" should exist
