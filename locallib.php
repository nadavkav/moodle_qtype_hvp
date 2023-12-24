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
 * Internal library of functions for module hvp
 *
 * All the hvp specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    qtype_hvp
 * @copyright 2022 onwards SysBind  {@link http://sysbind.co.il}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


use qtype_hvp_library\H5PCore as H5PCore;
use qtype_hvp_library\H5PHubEndpoints as H5PHubEndpoints;
use qtype_hvp\editor\H5peditor as H5peditor;

defined('MOODLE_INTERNAL') || die();

require_once('autoloader.php');
/**
 * Get array with settings for hvp core
 *
 * @param \context_course|\context_module $context [$context]
 * @return array Settings
 * @throws coding_exception|dml_exception
 */
function hvp_get_core_settings($context) {
    global $USER, $CFG;

    $systemcontext = \context_system::instance();
    $basepath = \qtype_hvp\view_assets::getsiteroot() . '/';

    // Check permissions and generate ajax paths.
    $ajaxpaths = array();
    $savefreq = false;
    $ajaxpath = "{$basepath}question/type/hvp/ajax.php?contextId={$context->instanceid}&token=";
    if ($context->contextlevel == CONTEXT_MODULE && has_capability('qtype/hvp:saveresults', $context)) {
        $ajaxpaths['setFinished'] = $ajaxpath . H5PCore::createToken('result') . '&action=set_finished';
        $ajaxpaths['xAPIResult'] = $ajaxpath . H5PCore::createToken('xapiresult') . '&action=xapiresult';
    }
    if (has_capability('qtype/hvp:savecontentuserdata', $context)) {
        $ajaxpaths['contentUserData'] = $ajaxpath . H5PCore::createToken('contentuserdata') .
            '&action=contents_user_data&content_id=:contentId&data_type=:dataType&sub_content_id=:subContentId';

        if (get_config('qtype_hvp', 'enable_save_content_state')) {
            $savefreq = get_config('qtype_hvp', 'content_state_frequency');
        }
    }

    $contentlang = get_config('qtype_hvp', 'contentlang');
    $saveeachinteraction = get_config('qtype_hvp', 'saveeachinteraction');
    $statements = get_config('qtype_hvp', 'statements');

    $core = \qtype_hvp\framework::instance('core');

    $settings = array(
        'baseUrl' => $basepath,
        'url' => "{$basepath}pluginfile.php/{$context->instanceid}/qtype_hvp",
        // NOTE: Separate context from content URL !
        'urlLibraries' => "{$basepath}pluginfile.php/{$systemcontext->id}/qtype_hvp/libraries",
        'postUserStatistics' => true,
        'ajax' => $ajaxpaths,
        'saveFreq' => $savefreq,
        'siteUrl' => $CFG->wwwroot,
        'l10n' => array('H5P' => $core->getLocalization()),
        'user' => array(
            'name' => $USER->firstname . ' ' . $USER->lastname,
            'mail' => $USER->email
        ),
        'hubIsEnabled' => get_config('qtype_hvp', 'hub_is_enabled') ? true : false,
        'reportingIsEnabled' => true,
        'crossorigin' => isset($CFG->qtype_hvp_crossorigin) ? $CFG->qtype_hvp_crossorigin : null,
        'crossoriginRegex' => isset($CFG->qtype_hvp_crossoriginRegex) ? $CFG->qtype_hvp_crossoriginRegex : null,
        'crossoriginCacheBuster' => isset($CFG->qtype_hvp_crossoriginCacheBuster) ? $CFG->qtype_hvp_crossoriginCacheBuster : null,
        'libraryConfig' => $core->h5pF->getLibraryConfig(),
        'pluginCacheBuster' => hvp_get_cache_buster(),
        'libraryUrl' => $basepath . 'question/type/hvp/library/js',
        'contentlang' => $contentlang,
        'saveeachinteraction' => $saveeachinteraction,
        'statements' => $statements
    );

    return $settings;
}

/**
 * Add required assets for displaying the editor.
 *
 * @param stdClass $question The question object
 * @param null $mformid Id of Moodle form
 *
 * @throws dml_exception
 * @throws moodle_exception
 */
function hvp_add_editor_assets($question = null, $mformid = null) {
    global $PAGE, $CFG;

    $contextid = $question->contextid;
    $context = \context::instance_by_id($contextid);

    $settings = \hvp_get_core_assets($context, true);

    // Use jQuery and styles from core.
    $assets = array(
        'css' => $settings['core']['styles'],
        'js' => $settings['core']['scripts']
    );

    // Use relative URL to support both http and https.
    $url = \qtype_hvp\view_assets::getsiteroot() . '/question/type/hvp/';
    $url = '/' . preg_replace('/^[^:]+:\/\/[^\/]+\//', '', $url);

    // Make sure files are reloaded for each plugin update.
    $cachebuster = \hvp_get_cache_buster();

    // Add editor styles.
    foreach (H5peditor::$styles as $style) {
        $assets['css'][] = $url . 'editor/' . $style . $cachebuster;
    }

    // Add editor JavaScript.
    foreach (H5peditor::$scripts as $script) {
        // We do not want the creator of the iframe inside the iframe.
        if ($script !== 'scripts/h5peditor-editor.js') {
            $assets['js'][] = $url . 'editor/' . $script . $cachebuster;
        }
    }

    // Add JavaScript with library framework integration (editor part).
    $PAGE->requires->js(new moodle_url('/question/type/hvp/editor/scripts/h5peditor-editor.js' . $cachebuster), true);
    $PAGE->requires->js(new moodle_url('/question/type/hvp/editor/scripts/h5peditor-init.js' . $cachebuster), true);
    $PAGE->requires->js(new moodle_url('/question/type/hvp/editor.js' . $cachebuster), true);

    // Add translations.
    $language = \qtype_hvp\framework::get_language();
    $languagescript = "editor/language/{$language}.js";
    if (!file_exists("{$CFG->dirroot}/question/type/hvp/{$languagescript}")) {
        $languagescript = 'editor/language/en.js';
    }
    $PAGE->requires->js(new moodle_url('/question/type/hvp/' . $languagescript . $cachebuster), true);

    // Add JavaScript settings.
    $root = \qtype_hvp\view_assets::getsiteroot();
    $filespathbase = "{$root}/pluginfile.php/{$context->id}/qtype_hvp/";
    $contentvalidator = \qtype_hvp\framework::instance('contentvalidator');
    $editorajaxtoken = H5PCore::createToken('editorajax');

    $interface = \qtype_hvp\framework::instance('interface');
    $siteuuid = $interface->getOption('site_uuid', null);
    $secret   = $interface->getOption('hub_secret', null);
    $enablecontenthub = !empty($siteuuid) && !empty($secret);
    $basepath = \qtype_hvp\view_assets::getsiteroot() . '/';

    $settings['editor'] = array(
      'filesPath' => $filespathbase . 'editor',
      'fileIcon' => array(
        'path' => $url . 'editor/images/binary-file.png',
        'width' => 50,
        'height' => 50,
      ),
      'ajaxPath' => "${basepath}question/type/hvp/ajax.php?contextId={$context->id}&token={$editorajaxtoken}&action=",
      'libraryUrl' => $url . 'editor/',
      'copyrightSemantics' => $contentvalidator->getCopyrightSemantics(),
      'metadataSemantics' => $contentvalidator->getMetadataSemantics(),
      'assets' => $assets,
      // @codingStandardsIgnoreLine
      'apiVersion' => H5PCore::$coreApi,
      'language' => $language,
      'formId' => $mformid,
      'hub' => [
        'contentSearchUrl' => H5PHubEndpoints::createURL(H5PHubEndpoints::CONTENT) . '/search',
      ],
      'enableContentHub' => $enablecontenthub,
    );

    $PAGE->requires->data_for_js('H5PIntegration', $settings, true);
}

/**
 * Get assets (scripts and styles) for hvp core.
 *
 * @param \context_course|\context_module $context
 * @return array
 * @throws moodle_exception
 */
function hvp_get_core_assets($context, bool $dorequire) {
    global $PAGE;

    // Get core settings.
    $settings = \hvp_get_core_settings($context);
    $settings['core'] = array(
        'styles' => array(),
        'scripts' => array()
    );
    $settings['loadedJs'] = array();
    $settings['loadedCss'] = array();

    // Make sure files are reloaded for each plugin update.
    $cachebuster = \hvp_get_cache_buster();

    // Use relative URL to support both http and https.
    $liburl = \qtype_hvp\view_assets::getsiteroot() . '/question/type/hvp/library/';
    $relpath = '/' . preg_replace('/^[^:]+:\/\/[^\/]+\//', '', $liburl);

    // Add core stylesheets.
    foreach (H5PCore::$styles as $style) {
        $settings['core']['styles'][] = $relpath . $style . $cachebuster;
        if ($dorequire) {
            $PAGE->requires->css(new moodle_url($liburl . $style . $cachebuster));
        }
    }
    // Add core JavaScript.
    foreach (H5PCore::$scripts as $script) {
        $settings['core']['scripts'][] = $relpath . $script . $cachebuster;
        if ($dorequire) {
            $PAGE->requires->js(new moodle_url($liburl . $script . $cachebuster), true);
        }
    }

    return $settings;
}

/**
 * Get a query string with the plugin version number to include at the end
 * of URLs. This is used to force the browser to reload the asset when the
 * plugin is updated.
 *
 * @return string
 * @throws dml_exception
 */
function hvp_get_cache_buster(): string {
    return '?ver=' . get_config('qtype_hvp', 'version');
}

/**
 * Add core JS and CSS to page.
 *
 * @param moodle_page $page
 * @param moodle_url|string $liburl
 * @param array|null $settings
 * @throws \coding_exception
 * @throws dml_exception|moodle_exception
 */
function hvp_admin_add_generic_css_and_js($page, $liburl, $settings = null) {
    // @codingStandardsIgnoreLine
    foreach (H5PCore::$adminScripts as $script) {
        $page->requires->js(new moodle_url($liburl . $script . hvp_get_cache_buster()), true);
    }

    if ($settings === null) {
        $settings = array();
    }

    $settings['containerSelector'] = '#h5p-admin-container';
    $settings['l10n'] = array(
        'NA' => get_string('notapplicable', 'hvp'),
        'viewLibrary' => '',
        'deleteLibrary' => '',
        'upgradeLibrary' => get_string('upgradelibrarycontent', 'hvp')
    );

    $page->requires->data_for_js('H5PAdminIntegration', $settings, true);
    $page->requires->css(new moodle_url($liburl . 'styles/h5p.css' . hvp_get_cache_buster()));
    $page->requires->css(new moodle_url($liburl . 'styles/h5p-admin.css' . hvp_get_cache_buster()));

    // Add settings.
    $page->requires->data_for_js('h5p', hvp_get_core_settings(\context_system::instance()), true);
}

/**
 * Handle content upgrade progress
 *
 * @method hvp_content_upgrade_progress
 * @param $libraryid
 * @return object An object including the json content for the H5P instances
 *                (maximum 40) that should be upgraded.
 * @throws coding_exception
 * @throws dml_exception
 */
function hvp_content_upgrade_progress($libraryid) {
    global $DB;

    $tolibraryid = filter_input(INPUT_POST, 'libraryId');

    // Verify security token.
    if (!H5PCore::validToken('contentupgrade', required_param('token', PARAM_RAW))) {
        print get_string('upgradeinvalidtoken', 'qtype_hvp');
        return;
    }

    // Get the library we're upgrading to.
    $tolibrary = $DB->get_record('qtype_hvp_libraries', array(
        'id' => $tolibraryid
    ));
    if (!$tolibrary) {
        print get_string('upgradelibrarymissing', 'qtype_hvp');
        return;
    }

    // Prepare response.
    $out = new stdClass();
    $out->params = array();
    $out->token = H5PCore::createToken('contentupgrade');
    $out->metadata = array();

    // Prepare our interface.
    $interface = \qtype_hvp\framework::instance('interface');

    // Get updated params.
    $params = filter_input(INPUT_POST, 'params');
    if ($params !== null) {
        // Update params.
        $params = json_decode($params);
        foreach ($params as $id => $param) {
            $upgraded = json_decode($param);
            $metadata = isset($upgraded->metadata) ? $upgraded->metadata : array();

            $fields = array_merge(\H5PMetadata::toDBArray($metadata, false, false), array(
                'id' => $id,
                'main_library_id' => $tolibrary->id,
                'json_content' => json_encode($upgraded->params),
                'filtered' => ''
            ));

            $DB->update_record('qtype_hvp', $fields);

            // Log content upgrade successful.
            new \qtype_hvp\event(
                'content', 'upgrade',
                $id, $DB->get_field_sql("SELECT title FROM {qtype_hvp} WHERE id = ?", array($id)),
                $tolibrary->machine_name, $tolibrary->major_version . '.' . $tolibrary->minor_version
            );
        }
    }

    // Determine if any content has been skipped during the process.
    $skipped = filter_input(INPUT_POST, 'skipped');
    if ($skipped !== null) {
        $out->skipped = json_decode($skipped);
        // Clean up input, only numbers.
        foreach ($out->skipped as $i => $id) {
            $out->skipped[$i] = intval($id);
        }
        $skipped = implode(',', $out->skipped);
    } else {
        $out->skipped = array();
    }

    // Get number of contents for this library.
    $out->left = $interface->getNumContent($libraryid, $skipped);

    if ($out->left) {
        $skipquery = empty($skipped) ? '' : " AND id NOT IN ($skipped)";

        // Find the 40 first contents using this library version and add to params.
        $results = $DB->get_records_sql(
            "SELECT id, json_content as params, title, authors, source, year_from, year_to,
                    license, license_version, changes, license_extras, author_comments, default_language,
                    a11y_title
                   FROM {qtype_hvp}
                  WHERE main_library_id = ?
                    {$skipquery}
               ORDER BY title ASC", array($libraryid), 0 , 40
        );

        foreach ($results as $content) {
            $out->params[$content->id] = '{"params":' . $content->params .
                ',"metadata":' . \H5PMetadata::toJSON($content) . '}';
        }
    }

    return $out;
}

/**
 * Gets the information needed when content is upgraded
 *
 * @method hvp_get_library_upgrade_info
 * @param string $name
 * @param int $major
 * @param int $minor
 * @return object Library metadata including name, version, semantics and path
 *                to upgrade script
 * @throws dml_exception
 */
function hvp_get_library_upgrade_info($name, $major, $minor): object {
    $library = (object) array(
        'name' => $name,
        'version' => (object) array(
            'major' => $major,
            'minor' => $minor
        )
    );

    $core = \qtype_hvp\framework::instance();

    $library->semantics = $core->loadLibrarySemantics($library->name, $library->version->major, $library->version->minor);

    $context = \context_system::instance();
    $libraryfoldername = "{$library->name}-{$library->version->major}.{$library->version->minor}";
    if (\qtype_hvp\file_storage::fileExists($context->id, 'libraries', '/' . $libraryfoldername . '/', 'upgrades.js')) {
        $basepath = \qtype_hvp\view_assets::getsiteroot() . '/';
        $library->upgradesScript = "{$basepath}pluginfile.php/{$context->id}/qtype_hvp/libraries/{$libraryfoldername}/upgrades.js";
    }

    return $library;
}

/**
 * Restrict access to a given content type.
 *
 * @param $libraryid
 * @param bool $restrict
 * @throws dml_exception
 */
function hvp_restrict_library($libraryid, $restrict) {
    global $DB;
    $DB->update_record('qtype_hvp_libraries', (object) array(
        'id' => $libraryid,
        'restricted' => $restrict ? 1 : 0
    ));
}
