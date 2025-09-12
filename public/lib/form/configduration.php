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

/**
 * Config duration form element.
 *
 * Contains class to create a duration element similar to admin_setting_configduration
 * but for use in forms.
 *
 * @package core_form
 * @copyright 2025 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/form/group.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/form/text.php');

/**
 * Config duration element.
 *
 * HTML class for a duration setting similar to admin_setting_configduration.
 * The values returned to PHP is the duration in seconds (an int).
 *
 * @package   core_form
 * @copyright 2025 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class MoodleQuickForm_configduration extends MoodleQuickForm_group {
    /**
     * Control the field names for form elements.
     * @var array
     */
    protected $options = [
        'defaultunit' => DAYSECS,
        'defaultvalue' => null,
        'minduration' => 2,
        'maxduration' => null,
        'validatefunction' => null,
    ];

    /** @var array associative array of time units (weeks, days, hours, minutes, seconds). */
    private $units = null;

    /**
     * Constructor.
     *
     * @param ?string $elementname Element's name.
     * @param mixed $elementlabel Label(s) for an element.
     * @param array $options Options to control the element's display. Recognised values are
     *    'defaultunit' => seconds - the default unit to display when the time is blank. If not specified, days is used.
     *    'defaultvalue' => number - the default numeric value to display with defaultunit.
     *    'minduration' => minimum allowed duration in seconds (default 2).
     *    'maxduration' => maximum allowed duration in seconds (default null = no limit).
     *    'validatefunction' => callable function for custom validation.
     * @param mixed $attributes Either a typical HTML attribute string or an associative array.
     */
    public function __construct(
        $elementname = null,
        $elementlabel = null,
        $options = [],
        $attributes = null
    ) {
        parent::__construct($elementname, $elementlabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_appendName = true;
        $this->_type = 'configduration';

        // Set the options.
        if (!is_array($options)) {
            $options = [];
        }

        if (isset($options['defaultunit'])) {
            $this->options['defaultunit'] = (int)$options['defaultunit'];
        }

        if (isset($options['defaultvalue'])) {
            $this->options['defaultvalue'] = $options['defaultvalue'] === null ? null : (int)$options['defaultvalue'];
        }

        if (isset($options['minduration'])) {
            $this->options['minduration'] = (int)$options['minduration'];
        }

        if (isset($options['maxduration'])) {
            $this->options['maxduration'] = $options['maxduration'] === null ? null : (int)$options['maxduration'];
        }

        if (isset($options['validatefunction'])) {
            $this->options['validatefunction'] = $options['validatefunction'];
        }
    }

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     * @param ?string $elementname Element's name.
     * @param mixed $elementlabel Label(s) for an element.
     * @param array $options Options to control the element's display. Recognised values are
     *    'defaultunit' => seconds - the default unit to display when the time is blank. If not specified, days is used.
     *    'defaultvalue' => number - the default numeric value to display with defaultunit.
     *    'minduration' => minimum allowed duration in seconds (default 2).
     *    'maxduration' => maximum allowed duration in seconds (default null = no limit).
     *    'validatefunction' => callable function for custom validation.
     * @param mixed $attributes Either a typical HTML attribute string or an associative array.
     * @deprecated since Moodle 3.1
     */
    public function MoodleQuickForm_configduration(
        $elementname = null,
        $elementlabel = null,
        $options = [],
        $attributes = null
    ) {
        debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
        self::__construct($elementname, $elementlabel, $options, $attributes);
    }

    /**
     * Returns time associative array of unit length.
     *
     * @return array unit length in seconds => string unit name.
     */
    public function get_units() {
        if (is_null($this->units)) {
            $this->units = [
                WEEKSECS => get_string('weeks'),
                DAYSECS => get_string('days'),
                HOURSECS => get_string('hours'),
                MINSECS => get_string('minutes'),
                1 => get_string('seconds'),
            ];
        }
        return $this->units;
    }

    /**
     * Get the units to be used for this field, filtered by max duration if set.
     *
     * @return array number of seconds => lang string.
     */
    protected function get_units_used() {
        $units = $this->get_units();

        if ($this->options['maxduration']) {
            $units = array_filter($units, function ($unit) {
                return $unit <= $this->options['maxduration'];
            }, ARRAY_FILTER_USE_KEY);
        }

        return $units;
    }

    /**
     * Finds suitable units for given duration.
     *
     * @param int $seconds
     * @return array
     */
    protected function parse_seconds($seconds) {
        foreach ($this->get_units() as $unit => $unused) {
            if ($seconds % $unit === 0) {
                return ['v' => (int)($seconds / $unit), 'u' => $unit];
            }
        }
        return ['v' => (int)$seconds, 'u' => 1];
    }

    /**
     * Converts seconds to the best possible time unit.
     *
     * @param int $seconds an amount of time in seconds.
     * @return array associative array ($number => $unit).
     */
    public function seconds_to_unit($seconds) {
        if (empty($seconds)) {
            if ($this->options['defaultvalue'] !== null) {
                // Use defaultvalue as the number and defaultunit as the unit.
                return [$this->options['defaultvalue'], $this->options['defaultunit']];
            }
            return [0, $this->options['defaultunit']];
        }

        $parsed = $this->parse_seconds($seconds);
        return [$parsed['v'], $parsed['u']];
    }

    /**
     * Get the default value in seconds.
     *
     * @return int|null Default value in seconds or null if not set.
     */
    public function get_default_value() {
        if ($this->options['defaultvalue'] !== null) {
            return $this->options['defaultvalue'] * $this->options['defaultunit'];
        }
        return null;
    }

    /**
     * Set the default value in seconds.
     *
     * @param int|null $seconds Default value in seconds or null to unset.
     */
    public function set_default_value($seconds) {
        $this->options['defaultvalue'] = $seconds === null ? null : (int)$seconds;
    }

    #[\Override]
    public function _createElements() {
        $attributes = $this->getAttributesForFormElement();
        if (!isset($attributes['size'])) {
            $attributes['size'] = 4;
        }

        $this->_elements = [];

        // Create the number input field.
        $number = $this->createFormElement(
            'text',
            'value',
            get_string('time', 'form'),
            $attributes,
            true
        );
        $number->set_force_ltr(true);
        $this->_elements[] = $number;

        unset($attributes['size']);

        // Create the unit select field.
        $this->_elements[] = $this->createFormElement(
            'select',
            'timeunit',
            get_string('timeunit', 'form'),
            $this->get_units_used(),
            $attributes,
            true
        );

        foreach ($this->_elements as $element) {
            if (method_exists($element, 'setHiddenLabel')) {
                $element->setHiddenLabel(true);
            }
        }
    }

    #[\Override]
    public function onQuickFormEvent($event, $arg, &$caller) {
        $this->setMoodleForm($caller);
        switch ($event) {
            case 'updateValue':
                // Constant values override both default and submitted ones.
                // Default values are overriden by submitted.
                $value = $this->_findValue($caller->_constantValues);
                if (null === $value) {
                    // If no boxes were checked, then there is no value in the array.
                    // yet we don't want to display default value in this case.
                    if ($caller->isSubmitted() && !$caller->is_new_repeat($this->getName())) {
                        $value = $this->_findValue($caller->_submitValues);
                    } else {
                        $value = $this->_findValue($caller->_defaultValues);
                        // If no explicit default value found and we have a defaultvalue option, use it.
                        if ($value === null && $this->options['defaultvalue'] !== null) {
                            $value = $this->options['defaultvalue'];
                        }
                    }
                }
                if (!is_array($value)) {
                    [$number, $unit] = $this->seconds_to_unit($value);
                    $value = ['value' => $number, 'timeunit' => $unit];
                }
                if (null !== $value) {
                    $this->setValue($value);
                }
                break;

            case 'createElement':
                $caller->setType($arg[0] . '[value]', PARAM_INT);
                return parent::onQuickFormEvent($event, $arg, $caller);

            default:
                return parent::onQuickFormEvent($event, $arg, $caller);
        }
    }

    #[\Override]
    public function validateSubmitValue($values) {
        $seconds = $this->exportValue($values);

        if ($seconds === null) {
            return null;
        }

        // Check minimum duration.
        if ($seconds < $this->options['minduration']) {
            return get_string(
                'configduration_low',
                'admin',
                $this->get_duration_text($this->options['minduration'], get_string('numseconds', 'core', 0))
            );
        }

        // Check maximum duration.
        if ($this->options['maxduration'] && $seconds > $this->options['maxduration']) {
            return get_string('configduration_high', 'admin', $this->get_duration_text($this->options['maxduration']));
        }

        // Check for negative values.
        if ($seconds < 0) {
            return get_string('err_positiveduration', 'core_form');
        }

        // Call custom validation function if provided.
        if ($this->options['validatefunction']) {
            $error = call_user_func($this->options['validatefunction'], $seconds);
            if ($error) {
                return $error;
            }
        }

        return null;
    }

    /**
     * Converts seconds to some more user-friendly string.
     *
     * @param int $seconds The duration in seconds.
     * @param null|string $emptyvalue The value to use when the duration is empty.
     * @return string The duration formatted.
     */
    protected function get_duration_text(int $seconds, ?string $emptyvalue = null): string {
        if (empty($seconds)) {
            if ($emptyvalue !== null) {
                return $emptyvalue;
            }
            return get_string('none');
        }

        $data = $this->parse_seconds($seconds);
        switch ($data['u']) {
            case WEEKSECS:
                return get_string('numweeks', '', $data['v']);
            case DAYSECS:
                return get_string('numdays', '', $data['v']);
            case HOURSECS:
                return get_string('numhours', '', $data['v']);
            case MINSECS:
                return get_string('numminutes', '', $data['v']);
            default:
                return get_string('numseconds', '', $data['v'] * $data['u']);
        }
    }

    #[\Override]
    public function toHtml() {
        include_once('HTML/QuickForm/Renderer/Default.php');
        $renderer = new HTML_QuickForm_Renderer_Default();
        $renderer->setElementTemplate('{element}');
        parent::accept($renderer);
        return $renderer->toHtml();
    }

    #[\Override]
    public function accept(&$renderer, $required = false, $error = null) {
        $renderer->renderElement($this, $required, $error);
    }

    #[\Override]
    public function exportValue(&$submitvalues, $assoc = false) {
        // Get the values from all the child elements.
        $valuearray = [];
        foreach ($this->_elements as $element) {
            $thisexport = $element->exportValue($submitvalues[$this->getName()], true);
            if (!is_null($thisexport)) {
                $valuearray += $thisexport;
            }
        }

        // Convert the value to an integer number of seconds.
        if (empty($valuearray)) {
            return null;
        }

        return $this->_prepareValue((int) round($valuearray['value'] * $valuearray['timeunit']), $assoc);
    }
}
