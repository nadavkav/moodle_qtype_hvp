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
 * Responsible for displaying the content upgrade page
 *
 * @package    qtype_hvp
 * @copyright  2023 onwards SysBind  {@link http://sysbind.co.il}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../../config.php");
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/question/type/hvp/locallib.php');

use \qtype_hvp_library\H5PCore as H5PCore;

// No guest autologin.
require_login(0, false);

$libraryid = required_param('library_id', PARAM_INT);
$pageurl = new moodle_url('/question/type/hvp/upgrade_content_page.php', array('library_id' => $libraryid));
$PAGE->set_url($pageurl);
admin_externalpage_setup('qtype_hvp_libraries');
$PAGE->set_title("{$SITE->shortname}: " . get_string('upgrade', 'qtype_hvp'));

// Inform moodle which menu entry currently is active!
$core = \qtype_hvp\framework::instance();
global $DB;
$results = $DB->get_records_sql('SELECT hl2.id as id, hl2.machine_name as name, hl2.title, hl2.major_version,
                                        hl2.minor_version, hl2.patch_version
                                       FROM {qtype_hvp_libraries} hl1
                                       JOIN {qtype_hvp_libraries} hl2 ON hl1.machine_name = hl2.machine_name
                                      WHERE hl1.id = ?
                                   ORDER BY hl2.title ASC, hl2.major_version ASC, hl2.minor_version ASC', array($libraryid));
$versions = array();
foreach ($results as $result) {
    $versions[$result->id] = $result;
}
$library = $versions[$libraryid];
$upgrades = $core->getUpgrades($library, $versions);

$PAGE->set_heading(get_string('upgradeheading', 'qtype_hvp', $library->title . ' (' .  H5PCore::libraryVersion($library) . ')'));

// Get num of contents that can be upgraded.
$numcontents = $core->h5pF->getNumContent($libraryid);
if (count($versions) < 2) {
    echo $OUTPUT->header();
    echo get_string('upgradenoavailableupgrades', 'qtype_hvp');
} else if ($numcontents === 0) {
    echo $OUTPUT->header();
    echo get_string('upgradenothingtodo', 'qtype_hvp');
} else {
    $settings = array(
        'libraryInfo' => array(
            'message' => get_string('upgrademessage', 'qtype_hvp', $numcontents),
            'inProgress' => get_string('upgradeinprogress', 'qtype_hvp'),
            'error' => get_string('upgradeerror', 'qtype_hvp'),
            'errorData' => get_string('upgradeerrordata', 'qtype_hvp'),
            'errorScript' => get_string('upgradeerrorscript', 'qtype_hvp'),
            'errorContent' => get_string('upgradeerrorcontent', 'qtype_hvp'),
            'errorParamsBroken' => get_string('upgradeerrorparamsbroken', 'qtype_hvp'),
            'errorLibrary' => get_string('upgradeerrormissinglibrary', 'qtype_hvp'),
            'errorTooHighVersion' => get_string('upgradeerrortoohighversion', 'qtype_hvp'),
            'errorNotSupported' => get_string('upgradeerrornotsupported', 'qtype_hvp'),
            'done' => get_string('upgradedone', 'qtype_hvp', $numcontents) .
                      ' <a href="' . (new moodle_url('/question/type/hvp/library_list.php'))->out(false) . '">' .
                      get_string('upgradereturn', 'qtype_hvp') . '</a>',
            'library' => array(
                'name' => $library->name,
                'version' => $library->major_version . '.' . $library->minor_version,
            ),
            'libraryBaseUrl' => (new moodle_url('/question/type/hvp/ajax.php',
                                 array('action' => 'getlibrarydataforupgrade')))->out(false) . '&library=',
            'scriptBaseUrl' => (new moodle_url('/question/type/hvp/library/js'))->out(false),
            'buster' => hvp_get_cache_buster(),
            'versions' => $upgrades,
            'contents' => $numcontents,
            'buttonLabel' => get_string('upgradebuttonlabel', 'qtype_hvp'),
            'infoUrl' => (new moodle_url('/question/type/hvp/ajax.php', array('action' => 'libraryupgradeprogress',
                          'library_id' => $libraryid)))->out(false),
            'total' => $numcontents,
            'token' => H5PCore::createToken('contentupgrade')
        )
    );

    // Add JavaScripts.
    $liburl = \qtype_hvp\view_assets::getsiteroot() . '/question/type/hvp/library/';
    hvp_admin_add_generic_css_and_js($PAGE, $liburl, $settings);
    $PAGE->requires->js(new moodle_url($liburl . 'js/h5p-version.js' . hvp_get_cache_buster()), true);
    $PAGE->requires->js(new moodle_url($liburl . 'js/h5p-content-upgrade.js' . hvp_get_cache_buster()), true);
    echo $OUTPUT->header();
    echo '<div id="h5p-admin-container">' . get_string('enablejavascript', 'qtype_hvp') . '</div>';
}

echo $OUTPUT->footer();
