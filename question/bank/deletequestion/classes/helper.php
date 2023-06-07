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

namespace qbank_deletequestion;

/**
 * Class helper of qbank_deletequestion.
 *
 * @package qbank_deletequestion
 * @copyright 2023 The Open University
 * @since Moodle 4.2
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Get the confirmation message of delete question.
     *
     * @param array $questionids List of id questions.
     * @param bool $deleteallversions Delete all question version or not.
     * @return string $message Confirmation of delete.
     */
    public static function get_delete_confirmation_message(array $questionids, bool $deleteallversions): string {
        global $DB;
        $questionnames = '';
        $inuse = false;
        $hasmutipleversions = false;
        $questionversions = [];
        if ($deleteallversions) {
            $listofquestions = \question_bank::get_all_versions_of_questions($questionids);
            foreach ($listofquestions as $questionbankentry) {
                if (count($questionbankentry) > 1 && !$hasmutipleversions) {
                    $hasmutipleversions = true;
                }
                // Flip the array to list question by question id. [ qid => qversion ].
                $questionversions += array_flip($questionbankentry);
            }
            $questionids = call_user_func_array('array_merge', $listofquestions);
        }
        [$questionsql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $questions = $DB->get_records_select('question', 'id ' . $questionsql, $params,
            'name ASC', 'id, name');
        foreach ($questions as $question) {
            if (questions_in_use([$question->id])) {
                $questionnames .= '* ';
                $inuse = true;
            }
            if (isset($questionversions[$question->id])) {
                $questionnames .= $question->name . ' v' . $questionversions[$question->id] .'<br />';
            } else {
                $questionnames .= $question->name . '<br />';
            }
        }
        if ($inuse) {
            $questionnames .= '<br />'.get_string('questionsinuse', 'question');
        }
        $message = get_string('deleteselectedquestioncheck', 'question', $questionnames);
        if ($deleteallversions) {
            if ($hasmutipleversions) {
                $message = get_string('deletequestionsallversioncheck', 'question', $questionnames);
            } else {
                $message = get_string('deletequestionscheck', 'question', $questionnames);
            }
        }

        return $message;
    }

    /**
     * Delete questions has (single/multiple) version.
     *
     * @param array $questionids List of questionid.
     * @param bool $deleteallversions Delete all question version or not.
     */
    public static function delete_questions(array $questionids, bool $deleteallversions): void {
        if ($deleteallversions) {
            // Get all the question id from multidimensional array.
            $listofquestions = \question_bank::get_all_versions_of_questions($questionids);
            $questionids = call_user_func_array('array_merge', $listofquestions);
        }
        foreach ($questionids as $questionid) {
            $questionid = (int) $questionid;
            question_require_capability_on($questionid, 'edit');
            question_delete_question($questionid);
        }
    }
}
