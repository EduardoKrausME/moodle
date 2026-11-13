@mod @mod_quiz @javascript
Feature: Modern question bank and random question selection
  As a teacher editing a quiz
  I should see the correct bank and filters in accessible dialogs
  Without changing the native question selection or random-question services

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email          |
      | teacher1 | Teacher   | One      | t1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name    | intro            | course | idnumber |
      | quiz     | Quiz 1  | Question chooser | C1     | quiz1    |
      | qbank    | Qbank 1 | Shared bank      | C1     | qbank1   |
    And the following "question categories" exist:
      | contextlevel    | reference | name                 |
      | Activity module | quiz1     | Questions Category 1 |
      | Activity module | qbank1    | Shared questions     |
    And the following "questions" exist:
      | questioncategory     | qtype | name            | user     | questiontext | tags |
      | Questions Category 1 | essay | Essay one       | teacher1 | An essay     | foo  |
      | Questions Category 1 | essay | Essay two       | teacher1 | Another one  | bar  |
      | Shared questions     | essay | Shared question | teacher1 | Shared text  | foo  |

  Scenario: Native question bank dialog identifies the source and retains the question list
    Given I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    When I open the "last" add to quiz menu
    And I follow "from question bank"
    Then ".mod-quiz-selector-qbank" "css_element" should exist
    And ".mod-quiz-selector-bank" "css_element" should exist
    And "button[data-action='switch-question-bank']" "css_element" should exist
    And "form#questionsubmit" "css_element" should exist
    And I should see "Essay one" in the "categoryquestions" "table"
    And I should see "Essay two" in the "categoryquestions" "table"

  Scenario: Random question dialog explains candidates while retaining native filters
    Given I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    When I open the "last" add to quiz menu
    And I follow "a random question"
    Then ".mod-quiz-selector-dialog" "css_element" should exist
    And "a[role='tab'][aria-controls]" "css_element" should exist
    And "#menurandomcount[name='randomcount']" "css_element" should exist
    And "[data-region='quiz-selection-status'][aria-live='polite']" "css_element" should exist
    And I should see "The matching questions are candidates for random selection"
    And I apply question bank filter "Category" with value "Questions Category 1"
    And I apply question bank filter "Tag" with value "foo"
    And I should see "Essay one"
    And I should not see "Essay two"

  Scenario: Random question addition still uses its existing service and page destination
    Given I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    When I open the "last" add to quiz menu
    And I follow "a random question"
    And I apply question bank filter "Category" with value "Questions Category 1"
    And I apply question bank filter "Tag" with value "foo"
    And I select "1" from the "randomcount" singleselect
    And I press "Add random question"
    Then I should see "Random (Questions Category 1) based on filter condition with tags: foo" on quiz page "1"
    And ".mod-quiz-edit-question--random" "css_element" should exist

  Scenario: Shared bank switch preserves bank identity and native filters
    Given I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    When I open the "last" add to quiz menu
    And I follow "from question bank"
    And I click on "Switch bank" "button"
    And I click on "Qbank 1" "link" in the "Select question bank" "dialogue"
    Then ".mod-quiz-selector-bank" "css_element" should exist
    And I should see "Shared question" in the "categoryquestions" "table"
    And I should not see "Essay one" in the "categoryquestions" "table"

  @accessibility
  Scenario: Updated random selection dialog retains accessible keyboard navigation
    Given I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    When I open the "last" add to quiz menu
    And I follow "a random question"
    Then "a[id^='existing-category-tab-'][aria-controls]" "css_element" should exist
    And "[role='tabpanel'][aria-labelledby]" "css_element" should exist
    And "label[for='menurandomcount']" "css_element" should exist
    And the page should meet accessibility standards
