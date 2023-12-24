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
 *
 * @package    qtype_hvp
 * @copyright  5762 (2022) SysBind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once('autoloader.php');
/* @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the newmodule's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 *
 * @return true|false Success
 */
function qtype_hvp_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options = array()) {
    global $DB;

    switch ($filearea) {
        default:
            return false; // Invalid file area.

        case 'libraries':
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                return false; // Invalid context.
            }

            // Check permissions.
            if (!has_capability('qtype/hvp:getcachedassets', $context)) {
                return false;
            }

            $itemid = 0;
            break;
        case 'cachedassets':
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                return false; // Invalid context.
            }

            // Check permissions.
            if (!has_capability('qtype/hvp:getcachedassets', $context)) {
                return false;
            }

            $options['cacheability'] = 'public';
            $options['immutable'] = true;

            $itemid = 0;
            break;

        case 'content':
            // Check permissions.
            if (!has_capability('qtype/hvp:view', $context)) {
                return false;
            }

            $itemid = array_shift($args);
            break;

        case 'exports':
            if ($context->contextlevel != CONTEXT_COURSE) {
                return false; // Invalid context.
            }

            // Allow download if valid temporary hash.
            $ishub = false;
            $hub = optional_param('hub', null, PARAM_RAW);
            if ($hub) {
                list($time, $hash) = explode('.', $hub, 2);
                $time = hvp_base64_decode($time);
                $hash = hvp_base64_decode($hash);

                $data = $time . ':' . get_config('qtype_hvp', 'site_uuid');
                $signature = hash_hmac('SHA512', $data, get_config('qtype_hvp', 'hub_secret'), true);

                if ($time < (time() - 43200) || !hash_equals($signature, $hash)) {
                    // No valid hash.
                    return false;
                }
                $ishub = true;
            } else if (!has_capability('qtype/hvp:view', $context)) {
                // No permission.
                return false;
            }
            // Note that the getexport permission is checked after loading the content.

            // Get core.
            $h5pinterface = \qtype_hvp\framework::instance('interface');
            $h5pcore = \qtype_hvp\framework::instance('core');

            $matches = array();

            // Get content id from filename.
            if (!preg_match('/(\d*).h5p$/', $args[0], $matches)) {
                // Did not find any content ID.
                return false;
            }

            $contentid = $matches[1];
            $content = $h5pinterface->loadContentById($contentid);
            $displayoptions = $h5pcore->getDisplayOptionsForView($content['disable'], $context->instanceid);

            // Check permissions.
            if (!$displayoptions['export'] && !$ishub) {
                return false;
            }

            $itemid = 0;

            break;

        case 'editor':
            $cap = ($context->contextlevel === CONTEXT_COURSE ? 'addinstance' : 'manage');

            // Check permissions.
            if (!has_capability("qtype/hvp:$cap", $context)) {
                return false;
            }

            $itemid = 0;
            break;
    } //switch

    $filename = array_pop($args);
    $filepath = (!$args ? '/' : '/' .implode('/', $args) . '/');

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'qtype_hvp', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // No such file.
    }

    // Totara: use allowxss option to prevent application/x-javascript mimetype
    // from being converted to application/x-forcedownload.
    $options['allowxss'] = '1';

    send_stored_file($file, 86400, 0, $forcedownload, $options);

    return true;
}


 /* Moodle core API */

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function qtype_hvp_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;

        default:
            return null;
    }
}


/**
 * URL compatible base64 decoding.
 *
 * @param string $string
 * @return string
 */
function qtype_hvp_base64_decode($string) {
    $r = strlen($string) % 4;
    if ($r) {
        $l = 4 - $r;
        $string .= str_repeat('=', $l);
    }
    return base64_decode(strtr($string, '-_', '+/'));
}
