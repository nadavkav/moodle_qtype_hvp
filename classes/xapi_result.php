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
 * The qtype_hvp content user data.
 *
 * @package   qtype_hvp
 * @copyright 2022 onwards SysBind  {@link http://sysbind.co.il}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

namespace qtype_hvp;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/hvp/library/h5p.classes.php');
require_once($CFG->dirroot . '/question/type/hvp/reporting/h5p-report-xapi-data.class.php');

/**
 * Class xapi_result handles xapi results and corresponding db operations.
 *
 * @package qtype_hvp
 */
class xapi_result {

    /**
     * Handle xapi results endpoint
     */
    public static function handle_ajax() {
        global $DB;
        // Validate token.
        if (!self::validate_token()) {
            $core = framework::instance();
            \qtype_hvp_library\H5PCore::ajaxError($core->h5pF->t('Invalid security token.'),
                'INVALID_TOKEN');
            return;
        }

        $modulename = self::get_module_name();
        $cm = get_coursemodule_from_id($modulename, required_param('contextId', PARAM_INT));

        if (!$cm) {
            \qtype_hvp_library\H5PCore::ajaxError('No such content');
            http_response_code(404);
            return;
        }

        $xapiresult = required_param('xAPIResult', PARAM_RAW);

        // Validate.
        $context = \context_module::instance($cm->id);
        if (!has_capability('qtype/hvp:saveresults', $context)) {
            \qtype_hvp_library\H5PCore::ajaxError(get_string('nopermissiontosaveresult', 'qtype_hvp'));
            return;
        }

        $xapijson = json_decode($xapiresult);

        // Get all question in this attempt.
        // Question usage id.
        $quid = (Array)$xapijson->statement->object->questionusageid;
        $questionsrecords = self::get_records($quid);

        if (!$xapijson) {
            \qtype_hvp_library\H5PCore::ajaxError('Invalid json in xAPI data.');
            return;
        }

        if (!self::validate_xapi_data($xapijson)) {
            \qtype_hvp_library\H5PCore::ajaxError('Invalid xAPI data.');
            return;
        }

        // Get question attempt id with this current question record.
        // Then store results.
        $qaid = (Array)$xapijson->statement->object->moreinfo->questionattemptid;
        self::store_xapi_data(reset($qaid), $xapijson);

        // Successfully inserted xAPI result.
        \qtype_hvp_library\H5PCore::ajaxSuccess();
    }

    /**
     * Validate xAPI results token
     *
     * @return bool True if token was valid
     */
    private static function validate_token() {
        $token = required_param('token', PARAM_ALPHANUM);
        return \qtype_hvp_library\H5PCore::validToken('xapiresult', $token);
    }


    /**
     * Validate xAPI data
     *
     * @param object $xapidata xAPI data
     *
     * @return bool True if valid data
     */
    private static function validate_xapi_data($xapidata) {
        $xapidata = new \H5PReportXAPIData($xapidata);
        return $xapidata->validateData();
    }

    /**
     * Store xAPI result(s)
     *
     * @param int $contentid Content id
     * @param object $xapidata xAPI data
     * @param int $parentid Parent id
     */
    private static function store_xapi_data($contentid, $xapidata, $parentid = null) {
        global $DB, $USER;

        $cmid = (Array)$xapidata->statement->object->moreinfo->cmid;

        $xapidata = new \H5PReportXAPIData($xapidata, $parentid);
        $insertedid = $DB->insert_record('qtype_hvp_xapi_results', (object) [
                'question_attempt_id' => $contentid,
                'user_id' => $USER->id,
                'parent_id' => $xapidata->getParentID(),
                'interaction_type' => $xapidata->getInteractionType(),
                'description' => $xapidata->getDescription(),
                'correct_responses_pattern' => $xapidata->getCorrectResponsesPattern(),
                'response' => $xapidata->getResponse(),
                'additionals' => $xapidata->getAdditionals(),
                'raw_score' => $xapidata->getScoreRaw(),
                'max_score' => $xapidata->getScoreMax(),
                'cm_id' => reset($cmid),
        ]);

        // Save sub content statements data.
        if ($xapidata->isCompound()) {
            foreach ($xapidata->getChildren($contentid) as $child) {
                self::store_xapi_data($contentid, $child, $insertedid);
            }
        }
    }

    /**
     * Get all questions records.
     *
     * @param int $contentid Content id
     */
    private static function get_records($quid) {
        global $DB;

        return $DB->get_recordset_sql("
SELECT
    quba.id AS qubaid,
    quba.contextid,
    quba.component,
    quba.preferredbehaviour,
    qa.id AS questionattemptid,
    qa.questionusageid,
    qa.slot,
    qa.behaviour,
    qa.questionid,
    qa.variant,
    qa.maxmark,
    qa.minfraction,
    qa.maxfraction,
    qa.flagged,
    qa.questionsummary,
    qa.rightanswer,
    qa.responsesummary,
    qa.timemodified,
    qas.id AS attemptstepid,
    qas.sequencenumber,
    qas.state,
    qas.fraction,
    qas.timecreated,
    qas.userid,
    qasd.name,
    qasd.value

FROM      {question_usages}            quba
LEFT JOIN {question_attempts}          qa   ON qa.questionusageid    = quba.id
LEFT JOIN {question_attempt_steps}     qas  ON qas.questionattemptid = qa.id
LEFT JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid    = qas.id

WHERE
    quba.id = :qubaid

ORDER BY
    qa.slot,
    qas.sequencenumber
    ", ['qubaid' => reset($quid)]);
    }

    /**
     * Get module name.
     */
    private static function get_module_name(): string {
        global $DB;

        $coursemoduleid = required_param('contextId', PARAM_INT);
        return $DB->get_field('modules', 'name', ['id' =>
                        $DB->get_field('course_modules', 'module', ['id' => $coursemoduleid])]
        );
    }
}
