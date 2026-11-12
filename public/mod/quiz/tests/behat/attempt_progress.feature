@mod @mod_quiz
Feature: Question progress uses confirmed question-engine states
  As a student
  I want to see my answered, pending and flagged question counts
  Without seeing uncommitted changes as confirmed progress

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student  | Student   | One      | student@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | student | C1     | student |
    And the following "activities" exist:
      | activity | name   | course | idnumber | navmethod  |
      | quiz     | Quiz 1 | C1     | quiz1    | free       |
      | quiz     | Quiz 2 | C1     | quiz2    | sequential |
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
      | TF2      | 2    |
    And quiz "Quiz 2" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 2    |

  @javascript
  Scenario: Initial progress is based on the server state and outside the collapsed map
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    Then "#mod-quiz-progress" "css_element" should exist
    And "#mod-quiz-progress [role='progressbar'][aria-valuenow='0'][aria-valuemax='2']" "css_element" should exist
    And I should see "Pending" in "#mod-quiz-progress" "css_element"
    And I click on ".mod-quiz-navigation-summary" "css_element"
    And ".mod-quiz-navigation-details[open]" "css_element" should not exist
    And "#mod-quiz-progress" "css_element" should be visible

  @javascript
  Scenario: Responding increases progress only after native autosave confirms
    Given the following config values are set as admin:
      | config         | value | plugin |
      | autosaveperiod | 1     | quiz   |
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I click on "True" "radio" in the "First question" "question"
    And I wait "4" seconds
    Then "#mod-quiz-progress [role='progressbar'][aria-valuenow='1'][aria-valuemax='2']" "css_element" should exist
    And the "aria-valuetext" attribute of "#mod-quiz-progress [role='progressbar']" "css_element" should contain "1 of 2 questions answered"
    And "#mod-quiz-autosave-status[data-save-state='saved']" "css_element" should exist

  @javascript
  Scenario: Flags are independent of response completion
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I click on "Flag question" "button" in the "First question" "question"
    Then "a#quiznavbutton1.flagged" "css_element" should exist
    And I should see "Flagged" in "#mod-quiz-progress" "css_element"
    And "#mod-quiz-progress [role='progressbar'][aria-valuenow='0']" "css_element" should exist

  @javascript
  Scenario: Sequential navigation keeps blocked questions unavailable
    Given I am on the "Quiz 2" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    Then "#mod-quiz-progress [role='progressbar'][aria-valuemax='2']" "css_element" should exist
    And "a#quiznavbutton2" "css_element" should not exist
    And "span#quiznavbutton2[aria-disabled='true']" "css_element" should exist
    And "#mod-quiz-progress" "css_element" should exist
