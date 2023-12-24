<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or qtypeify
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
 * Responsible for displaying the library list page
 *
 * @package    qtype_hvp
 * @copyright  2023 onwards SysBind  {@link http://sysbind.co.il}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use qtype_hvp_library\H5PCore as H5PCore;

require_once("../../../config.php");
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/question/type/hvp/locallib.php');

// No guest autologin.
require_login(0, false);

$pageurl = new moodle_url('/question/type/hvp/library_list.php');
$PAGE->set_url($pageurl);


// Inform moodle which menu entry currently is active!
admin_externalpage_setup('qtype_hvp_libraries');

$PAGE->set_title("{$SITE->shortname}: " . get_string('librariessettings', 'qtype_hvp'));

// Create upload libraries form.
$uploadform = new \qtype_hvp\upload_libraries_form();
if ($formdata = $uploadform->get_data()) {
    // Handle submitted valid form.
    $h5pstorage = \qtype_hvp\framework::instance('storage');
    $h5pstorage->savePackage(null, null, true);
}

$core = \qtype_hvp\framework::instance();

$hubon = $core->h5pF->getOption('hub_is_enabled', true);
if ($hubon) {
    // Create content type cache form.
    $ctcacheform = new \qtype_hvp\content_type_cache_form();

    // On form submit.
    if ($ctcacheform->get_data()) {
        // Update cache and reload page.
        $core->updateContentTypeCache();
        redirect($pageurl);
    }
}

$numnotfiltered = $core->h5pF->getNumNotFiltered();
$libraries = $core->h5pF->loadLibraries();

// Add settings for each library.
$settings = array();
$i = 0;
foreach ($libraries as $versions) {
    foreach ($versions as $library) {
        $usage = $core->h5pF->getLibraryUsage($library->id, $numnotfiltered ? true : false);
        if ($library->runnable) {
            $upgrades = $core->getUpgrades($library, $versions);
            $upgradeurl = empty($upgrades) ? false : (new moodle_url('/question/type/hvp/upgrade_content_page.php', array(
                'library_id' => $library->id
            )))->out(false);

            $restricted = (isset($library->restricted) && $library->restricted == 1 ? true : false);
            $restrictedurl = (new moodle_url('/question/type/hvp/ajax.php', array(
                'action' => 'restrict_library',
                'token' => H5PCore::createToken('library_' . $library->id),
                'restrict' => ($restricted ? 0 : 1),
                'library_id' => $library->id
            )))->out(false);
        } else {
            $upgradeurl = null;
            $restricted = null;
            $restrictedurl = null;
        }

        $settings['libraryList']['listData'][] = array(
            'title' => $library->title . ' (' . H5PCore::libraryVersion($library) . ')',
            'restricted' => $restricted,
            'restrictedUrl' => $restrictedurl,
            'numContent' => $core->h5pF->getNumContent($library->id),
            'numContentDependencies' => $usage['content'] === -1 ? '' : $usage['content'],
            'numLibraryDependencies' => $usage['libraries'],
            'upgradeUrl' => $upgradeurl,
            'detailsUrl' => null, // Not implemented in Moodle.
            'deleteUrl' => null // Not implemented in Moodle.
        );

        $i++;
    }
}

// All translations are made server side.
$settings['libraryList']['listHeaders'] = array(
    get_string('librarylisttitle', 'qtype_hvp'),
    get_string('librarylistrestricted', 'qtype_hvp'),
    get_string('librarylistinstances', 'qtype_hvp'),
    get_string('librarylistinstancedependencies', 'qtype_hvp'),
    get_string('librarylistlibrarydependencies', 'qtype_hvp'),
    get_string('librarylistactions', 'qtype_hvp')
);

// Add js.
$liburl = \qtype_hvp\view_assets::getsiteroot() . '/question/type/hvp/library/';

hvp_admin_add_generic_css_and_js($PAGE, $liburl, $settings);
$PAGE->requires->js(new moodle_url($liburl . 'js/h5p-library-list.js' . hvp_get_cache_buster()), true);

// RENDER PAGE OUTPUT.
echo $OUTPUT->header();

// Print any messages.
\qtype_hvp\framework::printMessages('info', \qtype_hvp\framework::messages('info'));
\qtype_hvp\framework::printMessages('error', \qtype_hvp\framework::messages('error'));

// Page Header.
echo '<h2>' . get_string('libraries', 'qtype_hvp') . '</h2>';

if ($hubon) {
    // Content type cache form.
    echo '<h3>' . get_string('contenttypecacheheader', 'qtype_hvp') . '</h3>';
    $ctcacheform->display();
}

// Upload Form.
echo '<h3 class="h5p-admin-header">' . get_string('uploadlibraries', 'qtype_hvp') . '</h3>';
$uploadform->display();

// Installed Libraries List.
echo '<h3 class="h5p-admin-header">' . get_string('installedlibraries', 'qtype_hvp')  . '</h3>';
echo '<div id="h5p-admin-container"></div>';

echo $OUTPUT->footer();
