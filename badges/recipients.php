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
 * Badge awards information
 *
 * @package    core
 * @subpackage badges
 * @copyright  2012 onwards Totara Learning Solutions Ltd {@link http://www.totaralms.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Yuliya Bozhko <yuliya.bozhko@totaralms.com>
 */

use core_badges\reportbuilder\local\systemreports\recipients;
use core_reportbuilder\system_report_factory;
use core_reportbuilder\local\report\base;

require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/badgeslib.php');

$badgeid = required_param('id', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

require_login();

if (empty($CFG->enablebadges)) {
    throw new \moodle_exception('badgesdisabled', 'badges');
}

$badge = new badge($badgeid);
$context = $badge->get_context();
$navurl = new moodle_url('/badges/index.php', array('type' => $badge->type));

// Check if user has capability to view badge recipients
require_capability('moodle/badges:viewawarded', $context);

if ($badge->type == BADGE_TYPE_COURSE) {
    if (empty($CFG->badges_allowcoursebadges)) {
        throw new \moodle_exception('coursebadgesdisabled', 'badges');
    }
    require_login($badge->courseid);
    $course = get_course($badge->courseid);
    $heading = format_string($course->fullname, true, ['context' => $context]);
    $navurl = new moodle_url('/badges/index.php', array('type' => $badge->type, 'id' => $badge->courseid));
    $PAGE->set_pagelayout('standard');
    navigation_node::override_active_url($navurl);
} else {
    $PAGE->set_pagelayout('admin');
    $heading = get_string('administrationsite');
    navigation_node::override_active_url($navurl, true);
}

$PAGE->set_context($context);
$PAGE->set_url('/badges/recipients.php', ['id' => $badgeid]);
$PAGE->set_heading($heading);
$PAGE->set_title($badge->name);
$PAGE->navbar->add($badge->name);

/** @var core_badges_renderer $output */
$output = $PAGE->get_renderer('core', 'badges');

// Create the report instance
$report = system_report_factory::create(
    recipients::class,
    $context,
    '',
    '',
    0,
    ['badgeid' => $badge->id]
);

// Enable download buttons for the report
$report->set_downloadable(true);

// Handle downloads if requested
if (!empty($download)) {
    // For system reports, we need to ensure the report can be downloaded
    if ($report->get_type() === base::TYPE_SYSTEM_REPORT) {
        if (!$report->can_be_downloaded()) {
            throw new \moodle_exception('nopermissiontoviewreport', 'core_reportbuilder');
        }
        
        // Combine original report parameters with 'download' parameter
        $parameters = ['badgeid' => $badge->id, 'download' => $download];
        
        // Create a new instance of the report with download parameters
        $downloadreport = system_report_factory::create(
            recipients::class,
            $context,
            '',
            '',
            0,
            $parameters
        );
        
        $downloadreport->download();
    }
}

echo $output->header();

$actionbar = new \core_badges\output\recipients_action_bar($badge, $PAGE);
echo $output->render_tertiary_navigation($actionbar);

echo $OUTPUT->heading(print_badge_image($badge, $context, 'small') . ' ' . $badge->name);
echo $output->print_badge_status_box($badge);

echo $report->output();

echo $output->footer();
