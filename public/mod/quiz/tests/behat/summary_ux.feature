@mod @mod_quiz
Feature: Accessible summary of saved quiz question progress before submission
  As a student
  I need to review the saved question states before submitting
  Without bypassing native quiz navigation or changing final confirmation

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
      | activity | name            | course | idnumber   | navmethod  |
      | quiz     | Free quiz       | C1     | freequiz   | free       |
      | quiz     | Sequential quiz | C1     | seqquiz    | sequential |
    And the following "question categories" exist:
      | contextlevel    | reference | name           |
      | Activity module | freequiz  | Test questions |
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
  Scenario: A fresh attempt displays pending questions without modifying the finish form
    Given I am on the "Free quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I follow "Finish attempt ..."
    Then "#mod-quiz-summary-progress" "css_element" should exist
    And "#mod-quiz-summary-progress [role='progressbar'][aria-valuenow='0'][aria-valuemax='2']" "css_element" should exist
    And I should see "2" in "#mod-quiz-summary-progress [data-summary-count='pending']" "css_element"
    And ".quizsummaryofattempt tr.quizsummary1.mod-quiz-summary-pending" "css_element" should exist
    And ".quizsummaryofattempt tr.quizsummary2.mod-quiz-summary-pending" "css_element" should exist
    And ".quizsummaryofattempt tr.quizsummary2 a" "css_element" should exist
    And "#frm-finishattempt" "css_element" should exist
    And I should see "Submit all and finish"

  @javascript
  Scenario: Saved answers and flags are shown separately on the summary page
    Given I am on the "Free quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I click on "True" "radio" in the "First question" "question"
    And I click on "Flag question" "button" in the "First question" "question"
    And "a#quiznavbutton1.flagged" "css_element" should exist
    And I follow "Finish attempt ..."
    Then "#mod-quiz-summary-progress [role='progressbar'][aria-valuenow='1'][aria-valuemax='2']" "css_element" should exist
    And I should see "1" in "#mod-quiz-summary-progress [data-summary-count='answered']" "css_element"
    And I should see "1" in "#mod-quiz-summary-progress [data-summary-count='pending']" "css_element"
    And I should see "1" in "#mod-quiz-summary-progress [data-summary-count='flagged']" "css_element"
    And ".quizsummaryofattempt tr.quizsummary1.mod-quiz-summary-answered.mod-quiz-summary-flagged" "css_element" should exist
    And ".quizsummaryofattempt tr.quizsummary2.mod-quiz-summary-pending" "css_element" should exist
    And I should see "Flagged" in ".quizsummaryofattempt tr.quizsummary1" "css_element"

  @javascript
  Scenario: Sequential navigation is described without offering prohibited question links
    Given I am on the "Sequential quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I press "Next page"
    And I follow "Finish attempt ..."
    Then "#mod-quiz-summary-progress [data-summary-notice='restricted']" "css_element" should exist
    And ".quizsummaryofattempt tr.quizsummary1 a" "css_element" should not exist
    And ".quizsummaryofattempt tr.quizsummary2 a" "css_element" should not exist
    And I should see "Submit all and finish"

  @javascript
  Scenario: Native final confirmation and submission remain unchanged
    Given I am on the "Free quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I follow "Finish attempt ..."
    And I press "Submit all and finish"
    Then I should see "Once you submit your answers, you won’t be able to change them." in the "Submit all your answers and finish?" "dialogue"
    And I should see "Questions without a response: 2" in the "Submit all your answers and finish?" "dialogue"
    And I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
    And I should see "Attempt submitted." in the ".alert-success" "css_element"

  Scenario: The summary still renders without JavaScript
    Given I am on the "Free quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I follow "Finish attempt ..."
    Then "#mod-quiz-summary-progress" "css_element" should exist
    And ".quizsummaryofattempt tr.quizsummary1.mod-quiz-summary-pending" "css_element" should exist
    And "#frm-finishattempt" "css_element" should exist
