@mod @mod_quiz
Feature: The contextual quiz review respects information visibility
  As a student reviewing an attempt
  I should only see contextual information the review settings permit
  Without losing the native question renderer or navigation

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
      | activity | name              | intro  | course | idnumber | correctnessimmediately | maxmarksimmediately | marksimmediately | specificfeedbackimmediately | generalfeedbackimmediately | rightanswerimmediately | overallfeedbackimmediately |
      | quiz     | Full review       | Full   | C1     | full     | 1                      | 1                   | 1                | 1                           | 1                          | 1                      | 1                          |
      | quiz     | Restricted review | Hidden | C1     | hidden   | 0                      | 0                   | 0                | 0                           | 0                          | 0                      | 0                          |
      | quiz     | Maximum only      | Max    | C1     | max      | 0                      | 1                   | 0                | 0                           | 0                          | 0                      | 0                          |
    And the following "question categories" exist:
      | contextlevel    | reference | name           |
      | Activity module | full      | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext    |
      | Test questions   | truefalse | TF1  | First question  |
      | Test questions   | truefalse | TF2  | Second question |
    And quiz "Full review" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 2    |
    And quiz "Restricted review" contains the following questions:
      | question | page |
      | TF1      | 1    |
    And quiz "Maximum only" contains the following questions:
      | question | page |
      | TF1      | 1    |

  @javascript
  Scenario: Full review has contextual labels and preserves native question markup
    Given user "student" has attempted "Full review" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | False    |
    When I am on the "Full review" "mod_quiz > View" page logged in as "student"
    And I follow "Review"
    Then ".mod-quiz-review-context" "css_element" should exist
    And ".mod-quiz-review-context-question" "css_element" should exist
    And ".mod-quiz-review-context-state" "css_element" should exist
    And I should see "Correction status" in ".mod-quiz-review-context" "css_element"
    And I should see "Specific feedback" in ".mod-quiz-review-context" "css_element"
    And ".que .info" "css_element" should exist

  @javascript
  Scenario: Hidden correctness, feedback, marks and correct answers stay hidden
    Given user "student" has attempted "Restricted review" with responses:
      | slot | response |
      | 1    | True     |
    When I am on the "Restricted review" "mod_quiz > View" page logged in as "student"
    And I follow "Review"
    Then ".mod-quiz-review-context-state" "css_element" should not exist
    And I should not see "Correction status"
    And I should not see "Earned and maximum marks"
    And I should not see "Correct answer"
    And I should not see "Specific feedback"
    And ".que .info" "css_element" should exist

  @javascript
  Scenario: Maximum marks may be visible without earned marks
    Given user "student" has attempted "Maximum only" with responses:
      | slot | response |
      | 1    | True     |
    When I am on the "Maximum only" "mod_quiz > View" page logged in as "student"
    And I follow "Review"
    Then I should see "Maximum possible marks" in ".mod-quiz-review-context" "css_element"
    And I should not see "Earned and maximum marks"
    And ".mod-quiz-review-context-state" "css_element" should not exist

  @javascript
  Scenario: Review navigation between individual and all question pages remains native
    Given user "student" has attempted "Full review" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | False    |
    When I am on the "Full review" "mod_quiz > View" page logged in as "student"
    And I follow "Review"
    And I follow "Show one page at a time"
    Then ".mod-quiz-review-question-section" "css_element" should exist
    And I follow "Next page"
    And ".mod-quiz-review-question-section" "css_element" should exist
    And I follow "Show all questions on one page"
    And ".mod-quiz-review-question-section" "css_element" should exist
    And I follow "Finish review"
    And I should see "Full review"

  Scenario: Context is available without JavaScript
    Given user "student" has attempted "Full review" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | False    |
    When I am on the "Full review" "mod_quiz > View" page logged in as "student"
    And I follow "Review"
    Then ".mod-quiz-review-context" "css_element" should exist
    And ".que .info" "css_element" should exist
