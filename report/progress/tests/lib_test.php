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

namespace report_progress;

/**
 * Class for testing lib report progress.
 *
 * @package report_progress
 * @copyright 2025 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 * @covers ::report_progress_myprofile_navigation
 * @covers ::report_progress_can_access_user_report
 */
final class lib_test extends \advanced_testcase {

    /**
     * Test report_progress_myprofile_navigation function.
     */
    public function test_report_progress_myprofile_navigation(): void {
        $this->resetAfterTest();
        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $tree = new \core_user\output\myprofile\tree();

        // Call the function with empty course.
        $result = report_progress_myprofile_navigation($tree, $user, true, null);
        $this->assertFalse($result);

        $result = report_progress_myprofile_navigation($tree, $user, true, $course);
        // Verify the results.
        $this->assertTrue($result);
    }

    /**
     * Test report_progress_can_access_user_report function.
     */
    public function test_report_progress_can_access_user_report(): void {
        global $DB;

        $this->resetAfterTest();

        // Create course with completion enabled and reports visible.
        $course = $this->getDataGenerator()->create_course([
            'enablecompletion' => 1,
            'showreports' => 1
        ]);

        // Create and enroll users.
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);

        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);

        // Test teacher access.
        $this->setUser($teacher);
        $this->assertTrue(report_progress_can_access_user_report($student, $course));

        // Test student access to their own report.
        $this->setUser($student);
        $this->assertTrue(report_progress_can_access_user_report($student, $course));
    }
}
