@mod @mod_quiz @javascript
Feature: Contextual guidance for native quiz settings
  As a teacher configuring a quiz
  I see explanatory messages about the consequences of the settings
  Without any changes to native form controls or submission behaviour

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher  | Teacher   | One      |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And the following "activities" exist:
      | activity | course | section | name   |
      | quiz     | C1     | 1       | Quiz 1 |

  Scenario: Existing quiz settings form shows all seven explanations
    Given I am on the "Quiz 1" "quiz activity editing" page logged in as teacher
    And I expand all fieldsets
    Then "[data-guidance='timing'][role='note']" "css_element" should exist
    And "[data-guidance='grading'][role='note']" "css_element" should exist
    And "[data-guidance='layout'][role='note']" "css_element" should exist
    And "[data-guidance='behaviour'][role='note']" "css_element" should exist
    And "[data-guidance='review'][role='note']" "css_element" should exist
    And "[data-guidance='restrictions'][role='note']" "css_element" should exist
    And "[data-guidance='feedback'][role='note']" "css_element" should exist
    And "[data-region='quiz-settings-guidance-message'][aria-live='polite']" "css_element" should exist

  Scenario: Navigation summary reacts to the native sequential setting
    Given I am on the "Quiz 1" "quiz activity editing" page logged in as teacher
    And I expand all fieldsets
    When I set the field "Navigation method" to "Sequential"
    Then I should see "Sequential navigation prevents returning freely" in "[data-guidance='layout']" "css_element"
    And I set the field "Navigation method" to "Free"
    And I should see "Free navigation permits moving" in "[data-guidance='layout']" "css_element"

  Scenario: Single attempt help preserves native grading dependencies
    Given I am on the "Quiz 1" "quiz activity editing" page logged in as teacher
    And I expand all fieldsets
    When I set the field "Attempts allowed" to "1"
    Then I should see "allows one attempt by default" in "[data-guidance='grading']" "css_element"
    And the "Grading method" "field" should be disabled
    And I set the field "Attempts allowed" to "2"
    And I should see "permits multiple attempts" in "[data-guidance='grading']" "css_element"
    And the "Grading method" "field" should be enabled

  Scenario: Date guidance explains the difference between due and closing times
    Given I am on the "Quiz 1" "quiz activity editing" page logged in as teacher
    And I expand all fieldsets
    When I set the field "duedate[enabled]" to "1"
    Then I should see "does not by itself close the quiz" in "[data-guidance='timing']" "css_element"
    And I set the field "timeclose[enabled]" to "1"
    And I should see "A closing time is enabled" in "[data-guidance='timing']" "css_element"
