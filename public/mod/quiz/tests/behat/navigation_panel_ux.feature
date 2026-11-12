@mod @mod_quiz
Feature: Accessible and responsive quiz question navigation
  As a student
  I need a question map that is clear on small screens and accessible by keyboard
  Without bypassing quiz navigation restrictions or losing my answers

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
  Scenario: The native question map can be collapsed and expanded
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    Then ".mod-quiz-navigation-details[open]" "css_element" should exist
    And "a#quiznavbutton1[aria-current='page']" "css_element" should exist
    And "a#quiznavbutton2" "css_element" should exist
    And "a#quiznavbutton1 .questionstate" "css_element" should exist
    And "a.endtestlink" "css_element" should exist
    And I click on ".mod-quiz-navigation-summary" "css_element"
    And "a#quiznavbutton2" "css_element" should not be visible
    And "a.endtestlink" "css_element" should be visible
    And I click on ".mod-quiz-navigation-summary" "css_element"
    And "a#quiznavbutton2" "css_element" should be visible

  @javascript
  Scenario: Free navigation continues through the native question form
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I click on "True" "radio" in the "First question" "question"
    And I click on "a#quiznavbutton2" "css_element"
    Then I should see "Second question"
    And "a#quiznavbutton2[aria-current='page']" "css_element" should exist
    And "a#quiznavbutton1" "css_element" should exist
    And I press "Finish attempt ..."
    And I should see "Summary of attempt"

  @javascript
  Scenario: Sequential navigation exposes blocked questions only as non-links
    Given I am on the "Quiz 2" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    Then "span#quiznavbutton2[aria-disabled='true']" "css_element" should exist
    And "a#quiznavbutton2" "css_element" should not exist
    And "Previous page" "button" should not exist
    And I press "Next page"
    And I should see "Second question"
    And "Previous page" "button" should not exist

  @javascript
  Scenario: Flagging a question keeps the native accessible flag label
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I click on "Flag question" "button" in the "First question" "question"
    Then "a#quiznavbutton1.flagged" "css_element" should exist
    And "a#quiznavbutton1 .flagstate[aria-live='polite']" "css_element" should exist
    And "Question 1 This page Flagged" "link" should exist

  Scenario: The question map is expanded and links remain usable without JavaScript
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    Then ".mod-quiz-navigation-details[open]" "css_element" should exist
    And "a#quiznavbutton2" "css_element" should exist
    And "a.endtestlink" "css_element" should exist
    And I should see "Warning: these links will not save your answers." in "#quiznojswarning" "css_element"

  @javascript
  Scenario: Section headings remain in their original order
    Given quiz "Quiz 1" contains the following sections:
      | heading   | firstslot | shuffle |
      | Section 1 | 1         | 0       |
      | Section 2 | 2         | 0       |
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    Then I should see "Section 1" in the "Quiz navigation" "block"
    And I should see "Section 2" in the "Quiz navigation" "block"
    And "a#quiznavbutton1" "css_element" should exist
    And "a#quiznavbutton2" "css_element" should exist

  @javascript
  Scenario: Show-all review does not mark every question link as the current page
    Given user "student" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | False    |
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    And I follow "Review"
    And I follow "Show all questions on one page"
    Then ".mod-quiz-navigation-details[open]" "css_element" should exist
    And "a.qnbutton[aria-current='page']" "css_element" should not exist
