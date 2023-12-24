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
 * Question type class for the hvp question type.
 *
 * @package    qtype
 * @subpackage hvp
 * @copyright 2022 onwards SysBind  {@link http://sysbind.co.il}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_hvp\framework;
use qtype_hvp_library\H5PCore as H5PCore;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/hvp/question.php');
require_once($CFG->dirroot . '/question/type/hvp/classes/framework.php');

/**
 * The hvp question type class.
 *
 */
class qtype_hvp extends question_type {

    protected function patch_filenames($hvpid) {
        global $DB;

        $jsoncontent = $DB->get_field('qtype_hvp', 'json_content', ['id' => $hvpid]);

        $content = json_decode($jsoncontent);

        $this->patch_content_filenames($content);

        $jsoncontent = json_encode($content);

        $DB->set_field('qtype_hvp', 'json_content', $jsoncontent, ['id' => $hvpid]);
    }

    protected function patch_content_filenames(&$content) {
        foreach ($content as $property => &$value) {
            if (($property == 'path') && is_string($value) && (substr($value, -4) == '#tmp')) {
                $value = substr($value, 0, -4);
            }

            if (is_object($value) || is_array($value)) {
                $this->patch_content_filenames($value);
            }
        }
    }

    public function export_to_xml($question, qformat_xml $format, $extra = null) {
        $content = $question->options->hvp;
        $expout = '';
        if (!empty($content)) {
            $expout .= "    <title>{$content['title']}</title>\n";
            $expout .= "    <params>" . $format->writetext($content['params'], 1) . "</params>\n";
            $expout .= "    <embed_type>{$content['embedType']}</embed_type>\n";
            $expout .= "    <h5plibrary>" .
                "{$content['library']['name']} {$content['library']['majorVersion']}.{$content['library']['minorVersion']}" .
                "</h5plibrary>\n";
            $expout .= "    <metadata>\n";
            foreach ($content['metadata'] as $key => $value) {
                $expout .= "        <$key>$value</$key>\n";
            }
            $expout .= "    </metadata>\n";
            $expout .= "    <disable>{$content['disable']}</disable>\n";
            $expout .= "    <slug>{$content['slug']}</slug>\n";
        }
        return $expout;
    }

    public function save_question_options($question) {
        if ($question->h5paction === 'upload') {
            $question->uploaded = true;
            $h5pstorage = framework::instance('storage');
            $h5pstorage->savePackage((array) $question);
            $hvpid = $h5pstorage->contentId;
        } else {
            $core = framework::instance();
            $editor = framework::instance('editor');
            $question->question = $question->id;
            $question->id = null;
            $question->library = H5PCore::libraryFromString($question->h5plibrary);

            $question->library['libraryId'] = $core->h5pF->getLibraryId($question->library['machineName'],
                $question->library['majorVersion'],
                $question->library['minorVersion']);
            $core->saveContent((array) $question);
            $params = json_decode($question->params);
            $editor->processParameters($question, $question->library, $params);
        }
        $question->id = $question->question;
        parent::save_question_options($question);
    }

    public function get_question_options($question) {
        if (!parent::get_question_options($question)) {
            return false;
        }
        $core = framework::instance();
        $question->options->hvp = $core->loadContent($question->id);
    }

    public function import_from_xml($data, $question, qformat_xml $format, $extra = null) {
        if (!array_key_exists('@', $data)) {
            return false;
        }
        if (!array_key_exists('type', $data['@'])) {
            return false;
        }
        if ($data['@']['type'] == 'hvp') {
            $qo = $format->import_headers($data);
            $qo->qtype = 'hvp';
            $qo->title = $format->getpath($data, array('#', 'title', 0, '#'), '', true);
            $qo->embed_type = $format->getpath($data, array('#', 'embed_type', 0, '#'), 'div');
            $qo->params = $format->getpath($data, array('#', 'params', 0, '#', 'text', 0, '#'), '', true);
            $qo->h5plibrary = $format->getpath($data, array('#', 'h5plibrary', 0, '#'), '');
            $qo->h5paction = 'create';
            $qo->metadata = [
                'license' => $format->getpath($data, ['#', 'metadata', 0, '#', 'license', 0, '#'], '', true),
                'title' => $format->getpath($data, ['#', 'metadata', 0, '#', 'title', 0, '#'], '', true),
                'defaultLanguage' => $format->getpath($data, ['#', 'metadata', 0, '#', 'defaultLanguage', 0, '#'], '', true),
            ];
            return $qo;
        }
        return false;
    }
}
