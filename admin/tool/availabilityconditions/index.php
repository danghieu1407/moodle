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
 * Provides an overview of installed availability conditions.
 *
 * You can also enable/disable them from this screen.
 *
 * @package tool_availabilityconditions
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('manageavailability');

// Get sorted list of all availability condition plugins.
$plugins = array();
foreach (core_component::get_plugin_list('availability') as $plugin => $plugindir) {
    if (get_string_manager()->string_exists('pluginname', 'availability_' . $plugin)) {
        $strpluginname = get_string('pluginname', 'availability_' . $plugin);
    } else {
        $strpluginname = $plugin;
    }
    $plugins[$plugin] = $strpluginname;
}
core_collator::asort($plugins);

// Do plugin actions.
$pageurl = new moodle_url('/' . $CFG->admin . '/tool/availabilityconditions/');
$classavailability = \core_plugin_manager::resolve_plugininfo_class('availability');
if (($plugin = optional_param('plugin', '', PARAM_PLUGIN))) {
    require_sesskey();
    if (!array_key_exists($plugin, $plugins)) {
        throw new \moodle_exception('invalidcomponent', 'error', $pageurl);
    }
    $action = optional_param('action', '', PARAM_ALPHA);
    switch ($action) {
        case 'hide' :
            $classavailability::enable_plugin($plugin, false);
            break;
        case 'show' :
            $classavailability::enable_plugin($plugin, true);
            break;
    }
    $displaymode = optional_param('displaymode', '', PARAM_ALPHA);
    switch ($displaymode) {
        case 'hide' :
            $classavailability::update_display_mode($plugin, false);
            break;
        case 'show' :
            $classavailability::update_display_mode($plugin, true);
            break;
    }

    // Always redirect back after an action.
    redirect($pageurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageplugins', 'availability'));

// Show a table of installed availability conditions.
$table = new flexible_table('availabilityconditions_administration_table');
$table->define_columns(['name', 'version', 'enable', 'defaultdisplaymode']);
$table->define_headers([
    get_string('plugin'),
    get_string('version'),
    get_string('enabled', 'tool_availabilityconditions') . '/' . get_string('disabled', 'tool_availabilityconditions'),
    get_string('defaultdisplaymode', 'tool_availabilityconditions'),
]);
$table->define_baseurl($PAGE->url);
$table->set_attribute('id', 'availabilityconditions');
$table->set_attribute('class', 'admintable generaltable');
$table->setup();

$enabledlist = core\plugininfo\availability::get_enabled_plugins();
foreach ($plugins as $plugin => $name) {

    // Get version or ? if unknown.
    $version = get_config('availability_' . $plugin);
    if (!empty($version->version)) {
        $version = $version->version;
    } else {
        $version = '?';
    }

    // Get enabled status and use to grey out name if necessary.
    $enabled = in_array($plugin, $enabledlist);
    if ($enabled) {
        $enabledaction = 'hide';
        $enabledstr = get_string('hide');
        $class = '';
    } else {
        $enabledaction = 'show';
        $enabledstr = get_string('show');
        $class = 'dimmed_text';
    }

    // Make enable control. This is a POST request (using a form control rather
    // than just a link) because it makes a database change.
    $paramsenablecontrol = ['sesskey' => sesskey(), 'plugin' => $plugin, 'action' => $enabledaction];
    $urlenablecontrol = new moodle_url('/' . $CFG->admin . '/tool/availabilityconditions/', $paramsenablecontrol);
    $enablecontrol = html_writer::link($urlenablecontrol, $OUTPUT->pix_icon('t/' . $enabledaction, $enabledstr),
        ['class' => 'enable-control-' . $plugin]);

    // Get config for display mode.
    $displaymode = !!get_config('availability_' . $plugin, 'defaultdisplaymode') ? 'show' : 'hide';

    // Display mode POST request.
    $paramsdisplaymode = ['sesskey' => sesskey(), 'plugin' => $plugin, 'displaymode' => $displaymode];
    $urldisplaymode = new moodle_url('/' . $CFG->admin . '/tool/availabilityconditions/', $paramsdisplaymode);
    $enabledisplaymode = html_writer::link($urldisplaymode, $OUTPUT->pix_icon('t/' . $displaymode,
        get_string($displaymode)), ['class' => 'display-mode-' . $plugin]);

    $table->add_data([$name, $version, $enablecontrol, $enabledisplaymode], $class);
}

$table->print_html();

echo $OUTPUT->footer();
