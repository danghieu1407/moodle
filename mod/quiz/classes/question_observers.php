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

namespace mod_quiz;
use core\event\question_deleted;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Question observers class.
 *
 * @package    mod_quiz
 * @copyright  2024 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_observers {

    /**
     * Question delete observer.
     *
     * @param question_deleted $event The core_question deleted event.
     */
    public static function question_deleted(question_deleted $event): void {
        global $DB;

        $questionid = $event->get_data()['objectid'];
        $sql = 'SELECT qa.uniqueid
                  FROM {quiz_attempts} qa
                  JOIN {question_attempts} qna ON qa.uniqueid = qna.questionusageid
                 WHERE qna.questionid = :questionid';
        $uniqueidrecord = $DB->get_record_sql($sql, ['questionid' => $questionid]);

        // Only delete the question of which have in progress attempt.
        $DB->delete_records('question_attempts', ['questionid' => $questionid]);

        // When deleting a question with multiple versions, each version has a different uniqueid.
        // Only delete the records that belong to the current progress attempt.
        if ($uniqueidrecord) {
            $DB->delete_records('question_usages', ['id' => $uniqueidrecord->uniqueid]);
            $DB->delete_records('quiz_attempts', ['uniqueid' => $uniqueidrecord->uniqueid]);
        }
    }
}
