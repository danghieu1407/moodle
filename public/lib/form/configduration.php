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
 * @package   core_form
 * @copyright 2024 Matt Porritt <matt.porritt@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;
require_once($CFG->libdir . '/form/group.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/form/text.php');

/**
 * Config duration element
 *
 * HTML class for a duration setting similar to admin_setting_configduration.
 * The values returned to PHP is the duration in seconds (an int).
 *
 * @package   core_form
 * @category  form
 * @copyright 2024 Matt Porritt <matt.porritt@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class MoodleQuickForm_configduration extends MoodleQuickForm_group {
    /**
     * Control the field names for form elements
     * @var array
     */
    protected $_options = [
        'defaultunit' => 86400, // Default to days
        'minduration' => 0,
        'maxduration' => null,
        'validatefunction' => null
    ];

    /** @var array associative array of time units (weeks, days, hours, minutes, seconds) */
    private $_units = null;

    /**
     * constructor
     *
     * @param ?string $elementName Element's name
     * @param mixed $elementLabel Label(s) for an element
     * @param array $options Options to control the element's display. Recognised values are
     *      'defaultunit' => seconds - the default unit to display when the time is blank. If not specified, days is used.
     *      'minduration' => minimum allowed duration in seconds (default 0)
     *      'maxduration' => maximum allowed duration in seconds (default null = no limit)
     *      'validatefunction' => callable function for custom validation
     * @param mixed $attributes Either a typical HTML attribute string or an associative array
     */
    public function __construct($elementName = null, $elementLabel = null,
                                $options = [], $attributes = null) {
        parent::__construct($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_appendName = true;
        $this->_type = 'configduration';

        // Set the options
        if (!is_array($options)) {
            $options = [];
        }

        if (isset($options['defaultunit'])) {
            $this->_options['defaultunit'] = (int)$options['defaultunit'];
        }

        if (isset($options['minduration'])) {
            $this->_options['minduration'] = (int)$options['minduration'];
        }

        if (isset($options['maxduration'])) {
            $this->_options['maxduration'] = $options['maxduration'] === null ? null : (int)$options['maxduration'];
        }

        if (isset($options['validatefunction'])) {
            $this->_options['validatefunction'] = $options['validatefunction'];
        }
    }

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     *
     * @deprecated since Moodle 3.1
     */
    public function MoodleQuickForm_configduration($elementName = null, $elementLabel = null,
                                                   $options = [], $attributes = null) {
        debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
        self::__construct($elementName, $elementLabel, $options, $attributes);
    }

    /**
     * Returns time associative array of unit length.
     *
     * @return array unit length in seconds => string unit name.
     */
    public function get_units() {
        if (is_null($this->_units)) {
            $this->_units = [
                WEEKSECS => get_string('weeks'),
                DAYSECS => get_string('days'),
                HOURSECS => get_string('hours'),
                MINSECS => get_string('minutes'),
                1 => get_string('seconds'),
            ];
        }
        return $this->_units;
    }

    /**
     * Get the units to be used for this field, filtered by max duration if set.
     *
     * @return array number of seconds => lang string.
     */
    protected function get_units_used() {
        $units = $this->get_units();

        if ($this->_options['maxduration']) {
            $units = array_filter($units, function($unit) {
                return $unit <= $this->_options['maxduration'];
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
     * @return array associative array ($number => $unit)
     */
    public function seconds_to_unit($seconds) {
        if (empty($seconds)) {
            return [0, $this->_options['defaultunit']];
        }

        $parsed = $this->parse_seconds($seconds);
        return [$parsed['v'], $parsed['u']];
    }

    /**
     * Override of standard quickforms method to create this element.
     */
    function _createElements() {
        $attributes = $this->getAttributesForFormElement();
        if (!isset($attributes['size'])) {
            $attributes['size'] = 4;
        }

        $this->_elements = [];

        // Create the number input field
        $number = $this->createFormElement('text', 'value',
            get_string('time', 'form'), $attributes, true);
        $number->set_force_ltr(true);
        $this->_elements[] = $number;

        unset($attributes['size']);

        // Create the unit select field
        $this->_elements[] = $this->createFormElement('select', 'timeunit',
            get_string('timeunit', 'form'), $this->get_units_used(), $attributes, true);

        foreach ($this->_elements as $element) {
            if (method_exists($element, 'setHiddenLabel')) {
                $element->setHiddenLabel(true);
            }
        }
    }

    /**
     * Called by HTML_QuickForm whenever form event is made on this element
     *
     * @param string $event Name of event
     * @param mixed $arg event arguments
     * @param MoodleQuickForm $caller calling object
     * @return bool
     */
    function onQuickFormEvent($event, $arg, &$caller) {
        $this->setMoodleForm($caller);
        switch ($event) {
            case 'updateValue':
                // constant values override both default and submitted ones
                // default values are overriden by submitted
                $value = $this->_findValue($caller->_constantValues);
                if (null === $value) {
                    // if no boxes were checked, then there is no value in the array
                    // yet we don't want to display default value in this case
                    if ($caller->isSubmitted() && !$caller->is_new_repeat($this->getName())) {
                        $value = $this->_findValue($caller->_submitValues);
                    } else {
                        $value = $this->_findValue($caller->_defaultValues);
                    }
                }
                if (!is_array($value)) {
                    list($number, $unit) = $this->seconds_to_unit($value);
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

    /**
     * Validate the submitted value.
     *
     * @param array $values Submitted values
     * @return string|null Error message or null if valid
     */
    #[\Override]
    public function validateSubmitValue($values) {
        $seconds = $this->exportValue($values);

        if ($seconds === null) {
            return null; // No value submitted
        }

        // Check minimum duration
        if ($seconds < $this->_options['minduration']) {
            return get_string(
                'configduration_low',
                'admin',
                $this->get_duration_text($this->_options['minduration'], get_string('numseconds', 'core', 0))
            );
        }

        // Check maximum duration
        if ($this->_options['maxduration'] && $seconds > $this->_options['maxduration']) {
            return get_string('configduration_high', 'admin', $this->get_duration_text($this->_options['maxduration']));
        }

        // Check for negative values
        if ($seconds < 0) {
            return get_string('err_positiveduration', 'core_form');
        }

        // Call custom validation function if provided
        if ($this->_options['validatefunction']) {
            $error = call_user_func($this->_options['validatefunction'], $seconds);
            if ($error) {
                return $error;
            }
        }

        return null;
    }

    /**
     * Converts seconds to some more user friendly string.
     *
     * @param int $seconds
     * @param null|string $emptyvalue The value to use when the duration is empty
     * @return string
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
            case (60*60*24*7):
                return get_string('numweeks', '', $data['v']);
            case (60*60*24):
                return get_string('numdays', '', $data['v']);
            case (60*60):
                return get_string('numhours', '', $data['v']);
            case (60):
                return get_string('numminutes', '', $data['v']);
            default:
                return get_string('numseconds', '', $data['v']*$data['u']);
        }
    }

    /**
     * Returns HTML for configduration form element.
     *
     * @return string
     */
    function toHtml() {
        include_once('HTML/QuickForm/Renderer/Default.php');
        $renderer = new HTML_QuickForm_Renderer_Default();
        $renderer->setElementTemplate('{element}');
        parent::accept($renderer);
        return $renderer->toHtml();
    }

    /**
     * Accepts a renderer
     *
     * @param HTML_QuickForm_Renderer $renderer An HTML_QuickForm_Renderer object
     * @param bool $required Whether a group is required
     * @param ?string $error An error message associated with a group
     */
    function accept(&$renderer, $required = false, $error = null) {
        $renderer->renderElement($this, $required, $error);
    }

    /**
     * Output a timestamp. Give it the name of the group.
     * Override of standard quickforms method.
     *
     * @param  array $submitValues
     * @param  bool  $assoc  whether to return the value as associative array
     * @return array field name => value. The value is the time interval in seconds.
     */
    function exportValue(&$submitValues, $assoc = false) {
        // Get the values from all the child elements.
        $valuearray = [];
        foreach ($this->_elements as $element) {
            $thisexport = $element->exportValue($submitValues[$this->getName()], true);
            if (!is_null($thisexport)) {
                $valuearray += $thisexport;
            }
        }

        // Convert the value to an integer number of seconds.
        if (empty($valuearray)) {
            return null;
        }

        return $this->_prepareValue(
            (int) round($valuearray['value'] * $valuearray['timeunit']), $assoc);
    }
}