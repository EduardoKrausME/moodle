<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.

/**
 * Regression tests for informational, unpersisted quiz settings guidance.
 *
 * @package mod_quiz
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/mod_form.php');

/**
 * Test the guidance HTML without changing or submitting native form fields.
 */
final class mod_quiz_settings_guidance_test extends advanced_testcase {
    /**
     * All supported groups must emit translated read-only elements.
     */
    public function test_guidance_regions_are_localised_and_contain_no_inputs(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $form = (new ReflectionClass(mod_quiz_mod_form::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(mod_quiz_mod_form::class, 'add_settings_guidance');

        foreach (['timing', 'grading', 'layout', 'behaviour', 'review', 'restrictions', 'feedback'] as $area) {
            $collector = new class {
                /** @var array Captured form elements. */
                public array $elements = [];

                public function addElement(string $type, string $content): void {
                    $this->elements[] = [$type, $content];
                }
            };
            $method->invoke($form, $collector, $area);
            $this->assertCount(1, $collector->elements);
            [$type, $html] = $collector->elements[0];
            $this->assertSame('html', $type);
            $this->assertStringContainsString('data-region="quiz-settings-guidance"', $html);
            $this->assertStringContainsString('data-guidance="' . $area . '"', $html);
            $this->assertStringContainsString('data-message-base=', $html);
            $this->assertStringContainsString('aria-live="polite"', $html);
            $this->assertStringContainsString(get_string('settingsguidanceheading', 'quiz'), $html);
            $this->assertStringNotContainsString('<input', $html);
            $this->assertStringNotContainsString('<form', $html);
        }
    }

    /**
     * The guidance helper accepts only its documented groups.
     */
    public function test_unknown_guidance_area_is_rejected(): void {
        $this->resetAfterTest();
        $form = (new ReflectionClass(mod_quiz_mod_form::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(mod_quiz_mod_form::class, 'add_settings_guidance');
        $collector = new class {
            public function addElement(string $type, string $content): void {
            }
        };
        $this->expectException(coding_exception::class);
        $method->invoke($form, $collector, 'not_a_settings_area');
    }

    /**
     * Public translations must not contain form passwords or user-specific details.
     */
    public function test_security_guidance_is_generic(): void {
        $this->resetAfterTest();
        $this->assertStringContainsString('password',
            strtolower(get_string('settingsguidancerestrictionspassword', 'quiz')));
        $this->assertStringNotContainsString('{$a}',
            get_string('settingsguidancerestrictionsbase', 'quiz'));
    }
}
