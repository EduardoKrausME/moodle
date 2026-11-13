@mod @mod_quiz
Feature: Responsive presentation of the quiz question editor
  As a teacher editing a quiz
  I need clearer sections, page markers and question controls
  Without changing native permissions, numbering, random questions or editing actions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | T1        | Teacher1 | teacher1@example.com |
      | student1 | S1        | Student1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name   | intro       | course | idnumber |
      | quiz     | Quiz 1 | Layout test | C1     | quiz1    |
    And the following "question categories" exist:
      | contextlevel    | reference | name           |
      | Activity module | quiz1     | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name    | questiontext      |
      | Test questions   | truefalse | First Q | First question    |
      | Test questions   | truefalse | Next Q  | Second question   |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | First Q  | 1    |
      | Next Q   | 2    |

  @javascript
  Scenario: Editor preserves its native controls inside the new visual hierarchy
    Given I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    Then ".mod-quiz-edit-overview" "css_element" should exist
    And ".mod-quiz-edit-section-heading" "css_element" should exist
    And "#page-1.mod-quiz-edit-page" "css_element" should exist
    And "#page-2.mod-quiz-edit-page" "css_element" should exist
    And ".mod-quiz-edit-slot" "css_element" should exist
    And ".mod-quiz-edit-question__content" "css_element" should exist
    And ".mod-quiz-edit-slot[aria-labelledby^='mod-quiz-edit-question-name-']" "css_element" should exist
    And "h5.mod-quiz-edit-question__name" "css_element" should exist
    And ".mod-quiz-edit-question__metadata .mod-quiz-edit-question__kind" "css_element" should exist
    And ".mod-quiz-edit-question__version-control .mod-quiz-edit-question__version-label" "css_element" should exist
    And ".mod-quiz-edit-question__actions .mod-quiz-edit-question__mark-label" "css_element" should exist
    And ".mod-quiz-edit-question__actions a.preview" "css_element" should exist
    And ".mod-quiz-edit-question__actions a.editing_delete" "css_element" should exist
    And ".mod-quiz-edit-question__actions" "css_element" should exist
    And ".mod-quiz-edit-slot .version-selection[data-action='mod_quiz-select_slot']" "css_element" should exist
    And ".mod-quiz-edit-slot .editing_move[data-action='move']" "css_element" should exist
    And ".mod-quiz-edit-slot .page_split_join[data-action]" "css_element" should exist

  @javascript
  Scenario: Original page menus and section editing remain accessible
    Given I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    When I open the "Page 1" add to quiz menu
    Then I should see "a new question"
    And ".mod-quiz-edit-section-heading .editing_section" "css_element" should exist
    And ".mod-quiz-edit-section-heading .instanceshufflequestions" "css_element" should exist
    And "#repaginatecommand" "css_element" should exist
    And "#selectmultiplecommand" "css_element" should exist

  @javascript
  Scenario: Random question keeps its native configuration without a fixed version selection
    Given I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    When I open the "Page 2" add to quiz menu
    And I follow "a random question"
    And I press "Add random question"
    Then ".mod-quiz-edit-question--random" "css_element" should exist
    And I should see "Random question" in ".mod-quiz-edit-question__kind" "css_element"
    And ".mod-quiz-edit-question--random .version-selection" "css_element" should not exist
    And ".mod-quiz-edit-question--random .mod-quiz-edit-question__kind" "css_element" should exist
    And ".mod-quiz-edit-slot .mod_quiz_random_qbank_link" "css_element" should exist

  @javascript
  Scenario: Layout stays readable while structural edits are locked by attempts
    Given user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | False    |
    When I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    Then I should see "The question structure is locked because attempts exist."
    And ".mod-quiz-edit-section-heading" "css_element" should exist
    And "#selectmultiplecommand[disabled]" "css_element" should exist
    And "#inputmaxgrade" "css_element" should exist
    And ".mod-quiz-edit-slot .editing_move" "css_element" should not exist
    And ".mod-quiz-edit-slot .editing_delete" "css_element" should not exist
    And ".mod-quiz-edit-slot .mod-quiz-edit-question__mark-label" "css_element" should exist
    And ".mod-quiz-edit-slot .mod-quiz-edit-question__version-control" "css_element" should exist

  Scenario: Rendering is readable without JavaScript
    Given I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    Then ".mod-quiz-edit-section-heading" "css_element" should exist
    And "#page-1.mod-quiz-edit-page" "css_element" should exist
    And ".mod-quiz-edit-slot" "css_element" should exist
    And ".mod-quiz-edit-question__name" "css_element" should exist
    And I should see "First Q"

  @accessibility @javascript
  Scenario: Updated quiz editor meets accessibility standards
    Given I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    Then I should see "First Q"
    And ".mod-quiz-edit-question__actions[role='group']" "css_element" should exist
    And the page should meet accessibility standards

  @javascript
  Scenario: The visual hierarchy preserves native multiple selection controls
    Given I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    When I click on "Select multiple items" "button"
    Then ".mod-quiz-edit-slot .select-multiple-checkbox" "css_element" should exist
    And "#selectmultipledeletecommand[disabled]" "css_element" should exist
    And "#selectmultiplecancelcommand" "css_element" should exist

  @javascript
  Scenario: Question details remain present with a mobile viewport
    Given I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    When I change window size to "small"
    Then "h5.mod-quiz-edit-question__name" "css_element" should exist
    And ".mod-quiz-edit-question__version-control .version-selection" "css_element" should exist
    And ".mod-quiz-edit-question__actions .editing_maxmark" "css_element" should exist
    And ".mod-quiz-edit-slot .editing_move" "css_element" should exist
