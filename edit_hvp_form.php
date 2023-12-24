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
 * Defines the editing form for the essay question type.
 *
 * @package    qtype
 * @subpackage hvp
 * @copyright 2022 onwards SysBind  {@link http://sysbind.co.il}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/hvp/locallib.php');

/**
 * HVP question type editing form.
 *
 */
class qtype_hvp_edit_form extends question_edit_form {

    /**
     * Add question-type specific form fields.
     *
     * @param object $mform the form being built.
     * @throws coding_exception
     * @throws dml_exception|moodle_exception
     */
    protected function definition_inner($mform) {
        global $DB, $COURSE;

        $h5pmetadatatitle = '';
        if (property_exists($this->question, 'id')) {
            $h5pquestionrec = $DB->get_record('qtype_hvp',
                ['question' => $this->question->id]);

            $h5pmetadatatitle = $DB->get_field('qtype_hvp', 'title', ['question' => $this->question->id]);
        }

        // Action.
        $h5paction = array();
        $h5paction[] = $mform->createElement('radio', 'h5paction', '', get_string('upload', 'qtype_hvp'), 'upload');
        $h5paction[] = $mform->createElement('radio', 'h5paction', '', get_string('create', 'qtype_hvp'), 'create');
        $mform->addGroup($h5paction, 'h5pactiongroup', get_string('action', 'qtype_hvp'), array('<br/>'), false);
        $mform->setDefault('h5paction', 'create');


        // Editor Placeholder.
        $h5peditor = [];
        $h5peditor[] = $mform->createElement('html',
            '<div class="h5p-editor">' . get_string('javascriptloading', 'qtype_hvp') . '</div>');
        $mform->addGroup($h5peditor, 'h5peditor', get_string('editor', 'qtype_hvp'));
        $mform->addElement('filepicker', 'h5pfile', get_string('h5pfile', 'qtype_hvp'), null,
            array('maxbytes' => $COURSE->maxbytes, 'accepted_types' => '*'));

        // Hidden fields.
        $mform->addElement('hidden', 'h5plibrary');
        $mform->setType('h5plibrary', PARAM_RAW);
        $mform->addElement('hidden', 'h5pparams');
        $mform->setType('h5pparams', PARAM_RAW);
        $mform->addElement('hidden', 'h5pmaxscore', '');
        $mform->setType('h5pmaxscore', PARAM_INT);
        $mform->addElement('hidden', 'metadatatitle');
        $mform->setType('metadatatitle', PARAM_RAW);

    }

    public function data_preprocessing($question) {
        $content = null;
        $core = \qtype_hvp\framework::instance();

        if (!empty($question->id)) {
            $content = $core->loadContent($question->id);
        }
        $draftitemid = file_get_submitted_draft_itemid('h5pfile');
        file_prepare_draft_area($draftitemid, $this->context->id, 'qtype_hvp', 'package', 0);
        $question->h5pfile = $draftitemid;
        $question->h5plibrary = $content === null ? 0 : \qtype_hvp_library\H5PCore::libraryToString($content['library']);
        $params = ($content === null ? '{}' : $core->filterParameters($content));
        $maincontentdata = array('params' => json_decode($params));
        if (isset($content['metadata'])) {
            $maincontentdata['metadata'] = $content['metadata'];
        }
        $question->h5pparams = json_encode($maincontentdata, true);
        $mformid = $this->_form->getAttribute('id');
        \hvp_add_editor_assets($question, $mformid);
        return $question;
    }

    public function data_postprocessing($data) {
        if (isset($data->h5pparams)) {
            // Remove metadata wrapper from form data.
            $params = json_decode($data->h5pparams);
            if ($params !== null) {
                $data->params = json_encode($params->params);
                if (isset($params->metadata)) {
                    $data->metadata = $params->metadata;
                }
            }
            // Cleanup.
            unset($data->h5pparams);
        }
        if (isset($data->h5paction)  && $data->h5paction === 'upload') {
            if (empty($data->metadata)) {
                $data->metadata = new stdClass();
            }

            if (empty($data->metadata->title)) {
                // Fix for legacy content upload to work.
                // Fetch title from h5p.json or use a default string if not available.
                $h5pvalidator = \qtype_hvp\framework::instance('validator');
                $data->metadata->title = empty($h5pvalidator->h5pC->mainJsonData['title'])
                    ? 'Uploaded Content'
                    : $h5pvalidator->h5pC->mainJsonData['title'];
            }
            $data->name = $data->metadata->title; // Sort of a hack,
            // but there is no JavaScript that sets the value when there is no editor...
        }
    }

    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            $this->data_postprocessing($data);
        }
        return $data;
    }

    /**
     * Validate new H5P
     *
     * @param $data
     * @param $errors
     * @throws coding_exception
     */
    private function validate_created(&$data, &$errors) {
        // Validate library and params used in editor.
        $core = \qtype_hvp\framework::instance();

        // Get library array from string.
        $library = \qtype_hvp_library\H5PCore::libraryFromString($data['h5plibrary']);

        if (!$library) {
            $errors['h5peditor'] = get_string('librarynotselected', 'qtype_hvp');
        } else {
            // Check that library exists.
            $library['libraryId'] = $core->h5pF->getLibraryId($library['machineName'],
                $library['majorVersion'],
                $library['minorVersion']);
            if (!$library['libraryId']) {
                $errors['h5peditor'] = get_string('nosuchlibrary', 'qtype_hvp');
            } else {
                $data['h5plibrary'] = $library;

                if ($core->h5pF->libraryHasUpgrade($library)) {
                    // We do not allow storing old content due to security concerns.
                    $errors['h5peditor'] = get_string('anunexpectedsave', 'qtype_hvp');
                } else {
                    // Verify that parameters are valid.
                    if (empty($data['h5pparams'])) {
                        $errors['h5peditor'] = get_string('noparameters', 'qtype_hvp');
                    } else {
                        $params = json_decode($data['h5pparams']);
                        if ($params === null) {
                            $errors['h5peditor'] = get_string('invalidparameters', 'qtype_hvp');
                        } else {
                            $data['h5pparams'] = $params;
                        }
                    }
                }
            }
        }
    }

    private function validate_upload(&$data, &$errors) {
        return $errors;
    }

    public function validation($fromform, $files) {
        $errors = parent::validation($fromform, $files);

        if ($fromform['h5paction'] === 'upload') {
            // Validate uploaded H5P file.
            unset($errors['name']); // Will be set in data_postprocessing().
            $this->validate_upload($fromform, $errors);
        } else {
            $this->validate_created($fromform, $errors);
        }
        return $errors;
    }

    public function qtype() {
        return 'hvp';
    }
}
