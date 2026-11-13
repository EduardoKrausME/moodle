@mod @mod_quiz
Feature: Responsive, permission-aware history of quiz attempts
  As a student viewing a quiz
  I need to identify recent attempts and review availability
  Without exposing grades or bypassing the native review rules

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
      | activity | name            | course | idnumber | maxmarksimmediately | marksimmediately | maxmarksopen | marksopen | maxmarksclosed | marksclosed | attemptimmediately | attemptopen | attemptclosed |
      | quiz     | History quiz    | C1     | quiz1    | 1                   | 1                | 1            | 1         | 1              | 1           | 1                  | 1           | 1             |
      | quiz     | Hidden grades   | C1     | hidden   | 0                   | 0                | 0            | 0         | 0              | 0           | 1                  | 1           | 1             |
      | quiz     | Review disabled | C1     | disabled | 0                   | 0                | 0            | 0         | 0              | 0           | 0                  | 0           | 0             |
    And the following "question categories" exist:
      | contextlevel    | reference | name           |
      | Activity module | quiz1     | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext    |
      | Test questions   | truefalse | TF1  | First question  |
    And quiz "History quiz" contains the following questions:
      | question | page |
      | TF1      | 1    |
    And quiz "Hidden grades" contains the following questions:
      | question | page |
      | TF1      | 1    |
    And quiz "Review disabled" contains the following questions:
      | question | page |
      | TF1      | 1    |

  @javascript
  Scenario: Multiple attempts retain numbering, order, status and native review action
    Given user "student" has attempted "History quiz" with responses:
      | slot | response |
      | 1    | True     |
    And user "student" has attempted "History quiz" with responses:
      | slot | response |
      | 1    | False    |
    When I am on the "History quiz" "mod_quiz > View" page logged in as "student"
    Then ".mod-quiz-attempt-history" "css_element" should exist
    And I should see "Finished" in the "Attempt 1" "list_item"
    And I should see "Finished" in the "Attempt 2" "list_item"
    And I should see "Most recent attempt" in the "Attempt 2" "list_item"
    And I should not see "Most recent attempt" in the "Attempt 1" "list_item"
    And ".mod-quiz-attempt-history__card--finished" "css_element" should exist
    And I should see "Started" in the "Attempt 2" "list_item"
    And I should see "Completed" in the "Attempt 2" "list_item"
    And I should see "Duration" in the "Attempt 2" "list_item"
    And I should see "Review" in the "Attempt 2" "list_item"

  @javascript
  Scenario: Hidden grades are not printed inside the attempt history
    Given user "student" has attempted "Hidden grades" with responses:
      | slot | response |
      | 1    | True     |
    When I am on the "Hidden grades" "mod_quiz > View" page logged in as "student"
    Then ".mod-quiz-attempt-history__card" "css_element" should exist
    And I should not see "Grade" in the "Attempt 1" "list_item"
    And I should not see "Marks" in the "Attempt 1" "list_item"
    And I should see "Finished" in the "Attempt 1" "list_item"

  @javascript
  Scenario: Review permission is unchanged and never adds a manual review URL
    Given user "student" has attempted "Review disabled" with responses:
      | slot | response |
      | 1    | False    |
    When I am on the "Review disabled" "mod_quiz > View" page logged in as "student"
    Then ".mod-quiz-attempt-history__card" "css_element" should exist
    And ".mod-quiz-attempt-history__actions a[href*='review.php']" "css_element" should not exist
    And I should see "Finished" in the "Attempt 1" "list_item"

  @javascript
  Scenario: In-progress attempt has a neutral status without a review shortcut
    Given I am on the "History quiz" "mod_quiz > View" page logged in as "student"
    When I press "Attempt quiz"
    And I am on the "History quiz" "mod_quiz > View" page
    Then ".mod-quiz-attempt-history__card--inprogress" "css_element" should exist
    And I should see "In progress" in the "Attempt 1" "list_item"
    And ".mod-quiz-attempt-history__actions a[href*='review.php']" "css_element" should not exist
    And ".mod-quiz-attempt-history__actions" "css_element" should not exist

  Scenario: The attempt history remains readable without JavaScript
    Given user "student" has attempted "History quiz" with responses:
      | slot | response |
      | 1    | True     |
    When I am on the "History quiz" "mod_quiz > View" page logged in as "student"
    Then ".mod-quiz-attempt-history__card" "css_element" should exist
    And "table.quizreviewsummary" "css_element" should exist
    And I should see "Started" in the "Attempt 1" "list_item"
    And I should see "Finished" in the "Attempt 1" "list_item"
