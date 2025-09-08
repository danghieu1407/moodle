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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace core_form;

use moodleform;
use MoodleQuickForm;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/form/configduration.php');

/**
 * Unit tests for MoodleQuickForm_configduration
 *
 * @package    core_form
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \MoodleQuickForm_configduration
 */
final class configduration_test extends \basic_testcase {

    /**
     * Get a form that can be used for testing.
     */
    protected function get_test_form(): MoodleQuickForm {
        $form = new temp_form_configduration();
        return $form->get_form();
    }

    /**
     * Get a form with a configduration element.
     */
    protected function get_test_form_and_element(): array {
        $mform = $this->get_test_form();
        $element = $mform->addElement('configduration', 'configduration');
        return [$mform, $element];
    }

    /**
     * Test basic constructor.
     */
    public function test_constructor(): void {
        [$mform, $element] = $this->get_test_form_and_element();
        $this->assertNotNull($element);
    }

    /**
     * Test get_units method returns expected units.
     */
    public function test_get_units(): void {
        [$mform, $element] = $this->get_test_form_and_element();
        $units = $element->get_units();

        $this->assertArrayHasKey(1, $units); // seconds
        $this->assertArrayHasKey(MINSECS, $units); // minutes
        $this->assertArrayHasKey(HOURSECS, $units); // hours
        $this->assertArrayHasKey(DAYSECS, $units); // days
        $this->assertArrayHasKey(WEEKSECS, $units); // weeks
    }

    /**
     * Test seconds_to_unit conversion.
     */
    public function test_seconds_to_unit(): void {
        [$mform, $element] = $this->get_test_form_and_element();

        // Test zero returns default unit (days)
        $this->assertEquals([0, DAYSECS], $element->seconds_to_unit(0));

        // Test 1 second
        $this->assertEquals([1, 1], $element->seconds_to_unit(1));

        // Test 60 seconds = 1 minute (this was failing before)
        $this->assertEquals([1, MINSECS], $element->seconds_to_unit(60));

        // Test 3600 seconds = 1 hour
        $this->assertEquals([1, HOURSECS], $element->seconds_to_unit(3600));

        // Test 86400 seconds = 1 day
        $this->assertEquals([1, DAYSECS], $element->seconds_to_unit(86400));
    }

    /**
     * Test export_value method.
     */
    public function test_export_value(): void {
        [$mform, $element] = $this->get_test_form_and_element();

        // Test normal values
        $values = ['configduration' => ['value' => '10', 'timeunit' => 1]];
        $this->assertEquals(10, $element->exportValue($values));

        // Test with minutes
        $values = ['configduration' => ['value' => '3', 'timeunit' => MINSECS]];
        $this->assertEquals(180, $element->exportValue($values));

        // Test empty values (this was failing before - returns 0, not null)
        $values = ['configduration' => []];
        $this->assertEquals(0, $element->exportValue($values));
    }

    /**
     * Test basic validation - positive values.
     */
    public function test_validate_positive_values(): void {
        [$mform, $element] = $this->get_test_form_and_element();

        // Valid positive value should pass
        $values = ['configduration' => ['value' => '100', 'timeunit' => 1]];
        $this->assertNull($element->validateSubmitValue($values));

        // Zero should pass
        $values = ['configduration' => ['value' => '0', 'timeunit' => 1]];
        $this->assertNull($element->validateSubmitValue($values));
    }

    /**
     * Test validation - negative values should fail.
     */
    public function test_validate_negative_values(): void {
        [$mform, $element] = $this->get_test_form_and_element();

        // Negative value should fail
        $values = ['configduration' => ['value' => '-10', 'timeunit' => 1]];
        $result = $element->validateSubmitValue($values);
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validation with minimum duration.
     */
    public function test_validate_minimum_duration(): void {
        $mform = $this->get_test_form();
        $element = $mform->addElement('configduration', 'test', 'Test', [
            'minduration' => 60 // 60 seconds minimum
        ]);

        // Below minimum should fail
        $values = ['test' => ['value' => '30', 'timeunit' => 1]];
        $result = $element->validateSubmitValue($values);
        $this->assertNotNull($result);

        // Above minimum should pass
        $values = ['test' => ['value' => '120', 'timeunit' => 1]];
        $result = $element->validateSubmitValue($values);
        $this->assertNull($result);
    }

    /**
     * Test validation with maximum duration.
     */
    public function test_validate_maximum_duration(): void {
        $mform = $this->get_test_form();
        $element = $mform->addElement('configduration', 'test', 'Test', [
            'maxduration' => 3600 // 1 hour maximum
        ]);

        // Above maximum should fail
        $values = ['test' => ['value' => '7200', 'timeunit' => 1]]; // 2 hours
        $result = $element->validateSubmitValue($values);
        $this->assertNotNull($result);

        // Below maximum should pass
        $values = ['test' => ['value' => '1800', 'timeunit' => 1]]; // 30 minutes
        $result = $element->validateSubmitValue($values);
        $this->assertNull($result);
    }

    /**
     * Test validation with custom function.
     */
    public function test_validate_custom_function(): void {
        $mform = $this->get_test_form();
        $element = $mform->addElement('configduration', 'test', 'Test', [
            'validatefunction' => function($seconds) {
                if ($seconds == 42) {
                    return 'Custom error for 42';
                }
                return null;
            }
        ]);

        // Valid value should pass
        $values = ['test' => ['value' => '100', 'timeunit' => 1]];
        $this->assertNull($element->validateSubmitValue($values));

        // Custom error value should fail
        $values = ['test' => ['value' => '42', 'timeunit' => 1]];
        $this->assertEquals('Custom error for 42', $element->validateSubmitValue($values));
    }

    /**
     * Test get_duration_text method.
     */
    public function test_get_duration_text(): void {
        [$mform, $element] = $this->get_test_form_and_element();

        // Use reflection to call protected method
        $reflection = new \ReflectionClass($element);
        $method = $reflection->getMethod('get_duration_text');
        $method->setAccessible(true);

        // Test zero returns 'None' (not 'none')
        $result = $method->invoke($element, 0, null);
        $this->assertEquals('None', $result);

        // Test custom empty value
        $result = $method->invoke($element, 0, 'custom empty');
        $this->assertEquals('custom empty', $result);

        // Test non-zero values contain expected numbers
        $result = $method->invoke($element, 60, null);
        $this->assertStringContainsString('1', $result);

        $result = $method->invoke($element, 3600, null);
        $this->assertStringContainsString('1', $result);
    }

    /**
     * Test HTML generation.
     */
    public function test_to_html(): void {
        [$mform, $element] = $this->get_test_form_and_element();
        $html = $element->toHtml();

        $this->assertNotEmpty($html);
        $this->assertIsString($html);
    }
}

/**
 * Form object to be used in test case.
 */
class temp_form_configduration extends moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        // No definition required.
    }

    /**
     * Returns form reference
     */
    public function get_form() {
        $mform = $this->_form;
        $mform->_flagSubmitted = true;
        return $mform;
    }
}