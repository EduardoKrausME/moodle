@mod @mod_quiz
Feature: Accessible and responsive countdown timer
  As a student
  I need to understand when my quiz is nearing its time limit
  Without changing the server-controlled submission rules

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
      | activity | name         | course | idnumber | timelimit |
      | quiz     | Timed quiz   | C1     | timed    | 600       |
      | quiz     | Short quiz   | C1     | short    | 60        |
      | quiz     | Untimed quiz | C1     | untimed  | 0         |
    And the following "question categories" exist:
      | contextlevel    | reference | name           |
      | Activity module | timed     | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext   |
      | Test questions   | truefalse | TF1  | First question |
    And quiz "Timed quiz" contains the following questions:
      | question | page |
      | TF1      | 1    |
    And quiz "Short quiz" contains the following questions:
      | question | page |
      | TF1      | 1    |
    And quiz "Untimed quiz" contains the following questions:
      | question | page |
      | TF1      | 1    |

  @javascript
  Scenario: A normal timer is labelled and can be hidden and shown by keyboard
    Given I am on the "Timed quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I press "Start attempt"
    Then "#quiz-timer[data-time-state='normal'][role='timer']" "css_element" should exist
    And "#quiz-timer-announcement[aria-live='polite']" "css_element" should exist
    And the "aria-expanded" attribute of "#toggle-timer" "css_element" should contain "true"
    And I click on "#toggle-timer" "css_element"
    And the "aria-expanded" attribute of "#toggle-timer" "css_element" should contain "false"
    And I click on "#toggle-timer" "css_element"
    And the "aria-expanded" attribute of "#toggle-timer" "css_element" should contain "true"

  @javascript
  Scenario: A short timed quiz is immediately critical and cannot be hidden
    Given I am on the "Short quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I press "Start attempt"
    Then "#quiz-timer[data-time-state='critical']" "css_element" should exist
    And "#toggle-timer[disabled]" "css_element" should exist
    And the "aria-expanded" attribute of "#toggle-timer" "css_element" should contain "true"
    And I should see "Less than 100 seconds remain." in "#quiz-timer" "css_element"

  @javascript
  Scenario: An untimed quiz does not initialise a countdown
    Given I am on the "Untimed quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    Then "#quiz-timer[data-time-state='critical']" "css_element" should not exist
    And "#quiz-timer[data-time-state='expired']" "css_element" should not exist
    And I should see "First question"

  @javascript
  Scenario: A timed quiz retains native navigation and autosave elements
    Given I am on the "Timed quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I press "Start attempt"
    Then "#quiz-timer" "css_element" should exist
    And "#mod-quiz-autosave-status" "css_element" should exist
    And ".mod-quiz-navigation-details" "css_element" should exist
    And I should see "First question"
