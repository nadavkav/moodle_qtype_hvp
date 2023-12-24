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
 * renderer for hvp question
 *
 * @package    qtype
 * @subpackage hvp
 * @copyright 2022 onwards SysBind  {@link http://sysbind.co.il}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(   $CFG->dirroot . '/question/type/rendererbase.php');

class qtype_hvp_renderer extends qtype_renderer {

    private ?\qtype_hvp\view_assets $view = null;

    public function formulation_and_controls(question_attempt $qa,
                                             question_display_options $options) {
        if ($options->feedback) {
            return $this->gen_report_html($qa);
        }

        $this->view    = new \qtype_hvp\view_assets($qa, $this->page->context, false);
        $content = $this->view->getcontent();
        $question = $qa->get_question();
        $response = $qa->get_last_qt_var('answer', '');
        $inputname = $qa->get_qt_field_name('answer');
        $answer = [
            'type' => 'hidden',
            'name' => $inputname,
            'value' => $qa->get_database_id(),
            'id' => $inputname . 'ans',
        ];


        $this->view->validatecontent();
        $result = '';
        $result .= html_writer::tag('input', null, $answer);
        $result .= $this->view->outputview();
        return $result;
    }

    public function head_code(question_attempt $qa) {
        global $DB, $PAGE;

        // Set up view assets.
        $this->view    = new \qtype_hvp\view_assets($qa, $this->page->context, true);
        $this->view->addassetstopage();
        $qa->get_question()->qtype->find_standard_scripts();

        $qaid = $qa->get_database_id();

        if ($qaid) {
            // this only includes css required for reporting:
            $this->gen_report_html($qa, true); // we have no indication if this is a feedback / review, so we include the css's
        }
    }


    /**
     * Alter parameters of H5P content after it has been filtered through
     * semantics. This is useful for adapting the content to the current context.
     *
     * @param object $parameters The content parameters for the library
     * @param string $name The machine readable name of the library
     * @param int $majorversion Major version of the library
     * @param int $minorversion Minor version of the library
     */
    public function hvp_alter_filtered_parameters(&$parameters, $name, $majorversion, $minorversion) {
    }

    /**
     * Alter semantics before they are processed. This is useful for changing
     * how the editor looks and how content parameters are filtered.
     *
     * @param object $semantics Semantics as object
     * @param string $name Machine name of library
     * @param int $majorversion Major version of library
     * @param int $minorversion Minor version of library
     */
    public function hvp_alter_semantics(&$semantics, $name, $majorversion, $minorversion) {
    }

    /**
     * Alter which scripts are loaded for H5P. Useful for adding your
     * own custom scripts or replacing existing ones.
     *
     * @param object $scripts List of JavaScripts that will be loaded
     * @param array $libraries Array of libraries indexed by the library's machineName
     * @param string $embedtype Possible values: div, iframe, external, editor
     */
    public function hvp_alter_scripts(&$scripts, $libraries, $embedtype) {
    }

    /**
     * Alter which stylesheets are loaded for H5P. This is useful for adding
     * your own custom styles or replacing existing ones.
     *
     * @param object $scripts List of stylesheets that will be loaded
     * @param array $libraries Array of libraries indexed by the library's machineName
     * @param string $embedtype Possible values: div, iframe, external, editor
     */
    public function hvp_alter_styles(&$scripts, $libraries, $embedtype) {
    }

    protected function gen_report_html(question_attempt $qa, bool $only_css = false) {
        global $DB, $PAGE;

        $userid = $DB->get_field_sql("SELECT userid FROM {question_attempt_steps} WHERE questionattemptid = ? LIMIT 1", [$qa->get_database_id()]);

	error_log("QTYPE HVP: < SELECT x.*, i.grademax FROM {qtype_hvp_xapi_results} x JOIN {grade_items} i ON i.iteminstance = ".$qa->get_question_id() . " WHERE x.user_id = " . $userid . " AND x.question_attempt_id = " . $qa->get_database_id() . " AND i.itemtype = 'mod' AND i.itemmodule = 'quiz'");

            // We have to get grades from gradebook as well.
        $xapiresults = $DB->get_records_sql("
    SELECT x.*, i.grademax
    FROM {qtype_hvp_xapi_results} x
    LEFT JOIN {grade_items} i ON i.iteminstance = ? AND i.itemtype = 'mod' AND i.itemmodule = 'quiz'
    WHERE x.user_id = ?
    AND x.question_attempt_id = ?"
    , [$qa->get_question_id(), $userid, $qa->get_database_id()]
            );

        $reporter   = H5PReport::getInstance();

        // Assemble our question tree.
        $basequestion = null;

        // Find base question.
        foreach ($xapiresults as $question) {

         error_log("QTYPE HVP: xapi result " . $question->id );
            if ($question->parent_id === null) {
                // This is the root of our tree.
                $basequestion = $question;

                if (isset($question->raw_score) && isset($question->grademax) && isset($question->max_score)) {
                    $scaledscoreperscore   = $question->max_score ? ($question->grademax / $question->max_score) : 0;
                    $question->score_scale = round($scaledscoreperscore, 2);
                    $totalrawscore         = $question->raw_score;
                    $totalmaxscore         = $question->max_score;
                    if ($question->max_score && $question->raw_score === $question->max_score) {
                        $totalscaledscore = round($question->grademax, 2);
                    } else {
                        $totalscaledscore = round($question->score_scale * $question->raw_score, 2);
                    }
                }
                //break; take last with no parent
            }
        }

         error_log("QTYPE HVP: checking zapi results " );
        if ($basequestion == null) {
            return;
        }

         error_log("QTYPE HVP: ITRATING zapi results " );
        foreach ($xapiresults as $question) {
            if ($question->parent_id === null) {
                // Already processed.
                continue;
            } else if (isset($xapiresults[$question->parent_id])) {
                // Add to parent.
                $xapiresults[$question->parent_id]->children[] = $question;
            }

            // Set scores.
            if (!isset($question->raw_score)) {
                $question->raw_score = 0;
            }
            if (isset($question->raw_score) && isset($question->grademax) && isset($question->max_score)) {
                $question->scaled_score_per_score = $scaledscoreperscore;
                $question->parent_max_score = $totalmaxscore;
                $question->score_scale = round($question->raw_score * $scaledscoreperscore, 2);
            }

            // Set score labels.
            $question->score_label            = get_string('reportingscorelabel', 'hvp');
            $question->scaled_score_label     = get_string('reportingscaledscorelabel', 'hvp');
            $question->score_delimiter        = get_string('reportingscoredelimiter', 'hvp');
            $question->scaled_score_delimiter = get_string('reportingscaledscoredelimiter', 'hvp');
            $question->questions_remaining_label = get_string('reportingquestionsremaininglabel', 'hvp');
        }

        $reporthtml = $reporter->generateReport($basequestion, null, count($xapiresults) <= 1);
         error_log("QTYPE HVP: report html " . $reporthtml);

        $basepath = \qtype_hvp\view_assets::getsiteroot();
        if ($only_css) {
            $styles     = $reporter->getStylesUsed();
            foreach ($styles as $style) {
                $PAGE->requires->css(new moodle_url($basepath . '/question/type/hvp/reporting/' . $style));
            }
            return;
        }
        $scripts    = $reporter->getScriptsUsed();

        foreach ($scripts as $script) {
            $PAGE->requires->js(new moodle_url($basepath . '/question/type/hvp/reporting/' . $script));
        }

        $PAGE->requires->js(new moodle_url($basepath . '/question/type/hvp/library/js/jquery.js'), true);

        return $reporthtml;
    }
}
