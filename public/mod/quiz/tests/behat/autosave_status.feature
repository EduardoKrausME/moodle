@mod @mod_quiz
Feature: Reliable auto-save status during quiz attempts
  As a student
  I need to know whether the server has acknowledged my latest answers
  So a pending or failed save is not presented as completed

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
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | quiz1    |
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

  @javascript
  Scenario: Confirmation follows server acknowledgement of changed answers
    Given the following config values are set as admin:
      | config         | value | plugin |
      | autosaveperiod | 1     | quiz   |
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    Then "#mod-quiz-autosave-status[role='status'][aria-live='polite']" "css_element" should exist
    And "#mod-quiz-autosave-status[data-save-state='ready']" "css_element" should exist
    And I click on "True" "radio" in the "First question" "question"
    And I wait "4" seconds
    And "#mod-quiz-autosave-status[data-save-state='saved']" "css_element" should exist
    And I should see "Answers saved." in "#mod-quiz-autosave-status" "css_element"
    And I press "Next page"
    And I should see "Second question"

  @javascript
  Scenario: New changes are not presented as already saved
    Given the following config values are set as admin:
      | config         | value | plugin |
      | autosaveperiod | 1     | quiz   |
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I click on "True" "radio" in the "First question" "question"
    And I wait "4" seconds
    Then "#mod-quiz-autosave-status[data-save-state='saved']" "css_element" should exist
    And I click on "False" "radio" in the "First question" "question"
    And "#mod-quiz-autosave-status[data-save-state='pending']" "css_element" should exist
    And I wait "4" seconds
    And "#mod-quiz-autosave-status[data-save-state='saved']" "css_element" should exist

  @javascript
  Scenario: An administrator can disable auto-save without a false saved indicator
    Given the following config values are set as admin:
      | config         | value | plugin |
      | autosaveperiod | 0     | quiz   |
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    Then "#mod-quiz-autosave-status[data-save-state='off']" "css_element" should exist
    And I should see "Automatic saving is disabled." in "#mod-quiz-autosave-status" "css_element"
    And I click on "True" "radio" in the "First question" "question"
    And "#mod-quiz-autosave-status[data-save-state='saved']" "css_element" should not exist
    And I press "Next page"
    And I should see "Second question"

  Scenario: Disabled JavaScript does not claim that answers were saved
    Given the following config values are set as admin:
      | config         | value | plugin |
      | autosaveperiod | 1     | quiz   |
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    Then "#mod-quiz-autosave-status[data-save-state='ready']" "css_element" should exist
    And I should see "Warning: these links will not save your answers." in "#quiznojswarning" "css_element"
    And "Next page" "button" should exist
