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
 * hvp question definition class.
 *
 * @package   qtype_hvp
 * @copyright 2022 onwards SysBind  {@link http://sysbind.co.il}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/questionbase.php');

/**
 * Represents a hvp question.
 */
class qtype_hvp_question extends question_graded_automatically {

    /**
     * @var question_attempt attemptobj
     */
    protected $attemptobj;


    public function get_expected_data() {
        return array('answer' => PARAM_INT);
    }

    public function get_correct_response() {
        return null;
    }

    public function is_complete_response(array $response) :  bool {
        global $DB;
        $questionusage = $DB->get_record('question_attempts', ['id' => $response['answer']]);
        $this->attemptobj = new question_attempt($this, $questionusage->questionusageid);
        return true;
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        return false;
    }

    public function summarise_response(array $response) {
        return "RESPONSE SUMMARY";
    }

    public function get_validation_error(array $response) {
        if ($this->is_complete_response($response)) {
            return '';
        }
        return get_string('pleaseananswerallparts', 'qtype_hvp');
    }

    public function grade_response(array $response) {
        $records = $this->get_records_from_xapi_results();
        if (empty($records)) {
            return [0, question_state::$gradedwrong];
        }
        $max = max(array_keys($records));

        if ($records[$max]->max_score == $records[$max]->raw_score) {
            return [1, question_state::$gradedright];
        }

        if ($records[$max]->max_score > $records[$max]->raw_score && $records[$max]->raw_score != 0) {
            return [($records[$max]->raw_score / $records[$max]->max_score), question_state::$gradedpartial];
        }

        if ($records[$max]->raw_score == 0) {
            return [0, question_state::$gradedwrong];
        }

        return false;
    }

    /**
     * Return all current question attempts from xapi table.
     */
    public function get_records_from_xapi_results() {
        global $DB, $USER;

        // Required course module id.
        $cmid = required_param('cmid', PARAM_INT);
        // Question usage id.
        $quid = $this->attemptobj->get_usage_id();
        // Question attempt id.
        $qaid = $DB->get_field('question_attempts', 'id',
            [
                'questionusageid' => $quid, 'questionid' => $this->id
            ]
        );
        return $DB->get_records('qtype_hvp_xapi_results',
            [
                'user_id' => $USER->id,
                'cm_id' => $cmid,
                'question_attempt_id' => $qaid,
            ]
        );
    }
}
