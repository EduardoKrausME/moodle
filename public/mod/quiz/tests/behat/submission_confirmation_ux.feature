@mod @mod_quiz
Feature: Final quiz submission is clear and remains secure
  As a student
  I want to review and explicitly confirm before finishing
  Without changing the native Moodle submission rules

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
      | activity | name            | course | idnumber | navmethod  |
      | quiz     | Free quiz       | C1     | quiz1    | free       |
      | quiz     | Sequential quiz | C1     | quiz2    | sequential |
    And the following "question categories" exist:
      | contextlevel    | reference | name           |
      | Activity module | quiz1     | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext    |
      | Test questions   | truefalse | TF1  | First question  |
      | Test questions   | truefalse | TF2  | Second question |
    And quiz "Free quiz" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 2    |
    And quiz "Sequential quiz" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 2    |

  @javascript
  Scenario: Submission modal shows real progress and warns of pending answers
    Given I am on the "Free quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I follow "Finish attempt ..."
    And I press "Submit all and finish"
    Then I should see "Once you submit your answers, you won’t be able to change them." in the "Submit all your answers and finish?" "dialogue"
    And I should see "2" in ".quiz-submission-confirmation-count-pending" "css_element"
    And I should see "0" in ".quiz-submission-confirmation-count-answered" "css_element"
    And I should see "Questions without a response: 2" in the "Submit all your answers and finish?" "dialogue"

  @javascript
  Scenario: Saved answer changes counts without losing flagged question count
    Given I am on the "Free quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I click on "True" "radio" in the "First question" "question"
    And I click on "Flag question" "button" in the "First question" "question"
    And I follow "Finish attempt ..."
    And I press "Submit all and finish"
    Then I should see "1" in ".quiz-submission-confirmation-count-answered" "css_element"
    And I should see "1" in ".quiz-submission-confirmation-count-pending" "css_element"
    And I should see "1" in ".quiz-submission-confirmation-count-flagged" "css_element"

  @javascript
  Scenario: Cancel closes the dialog without submitting
    Given I am on the "Free quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I follow "Finish attempt ..."
    And I press "Submit all and finish"
    And I click on "Cancel" "button" in the "Submit all your answers and finish?" "dialogue"
    Then I should see "Summary of attempt"
    And "#frm-finishattempt" "css_element" should exist
    And I should not see "Attempt submitted."
    And I press "Submit all and finish"
    And I should see "Once you submit your answers, you won’t be able to change them." in the "Submit all your answers and finish?" "dialogue"

  @javascript
  Scenario: Sequential navigation does not suggest going back
    Given I am on the "Sequential quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I press "Next page"
    And I follow "Finish attempt ..."
    And I press "Submit all and finish"
    Then I should see "2" in ".quiz-submission-confirmation-count-pending" "css_element"
    And I should see "Sequential navigation does not permit returning to earlier questions." in the "Submit all your answers and finish?" "dialogue"
    And I should not see "Questions without a response: 2" in the "Submit all your answers and finish?" "dialogue"

  @javascript
  Scenario: Confirm uses original Moodle submission and shows review
    Given I am on the "Free quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I follow "Finish attempt ..."
    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
    Then I should see "Attempt submitted." in the ".alert-success" "css_element"

  Scenario: Submission continues to work without JavaScript
    Given I am on the "Free quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I follow "Finish attempt ..."
    Then "#frm-finishattempt" "css_element" should exist
    And I press "Submit all and finish"
    And I should see "Attempt submitted." in the ".alert-success" "css_element"
