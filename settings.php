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
 * Administration settings definitions for the hvp module.
 *
 * @package    qtype
 * @subpackage hvp
 * @copyright 2022 onwards SysBind  {@link http://sysbind.co.il}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Make sure we are called from an internal Moodle site.
defined('MOODLE_INTERNAL') || die();

$settings = null;

if ($hassiteconfig) {
    /** @var admin_root $ADMIN */
    $ADMIN->add('qtypesettings', new admin_category('qtype_hvp_category', get_string('pluginname', 'qtype_hvp')));
    $settingspage = new admin_settingpage('hvpsettings', get_string('hvpsettings', 'qtype_hvp'));
    if ($ADMIN->fulltree) {
        $settingspage->add(new admin_setting_configcheckbox('qtype_hvp/saveeachinteraction',
            get_string('saveeachinteraction', 'qtype_hvp'),
            get_string('saveeachinteraction_help', 'qtype_hvp'), 1));

        $settingspage->add(new admin_setting_configtext('qtype_hvp/statements',
            get_string('statements', 'qtype_hvp'),
            get_string('configstatements', 'qtype_hvp'),
            'interacted,completed,answered'));

        // Use H5P Hub.
        $settingspage->add(
            new admin_setting_configcheckbox(
                'qtype_hvp/hub_is_enabled',
                get_string('enablehublabel', 'qtype_hvp'),
                get_string('disablehubdescription', 'qtype_hvp'),
                1
            )
        );
        $choices = array(
            H5PDisplayOptionBehaviour::NEVER_SHOW => get_string('displayoptiondownloadnever', 'qtype_hvp'),
            H5PDisplayOptionBehaviour::ALWAYS_SHOW => get_string('displayoptiondownloadalways', 'qtype_hvp'),
            H5PDisplayOptionBehaviour::CONTROLLED_BY_PERMISSIONS => get_string('displayoptiondownloadpermission', 'qtype_hvp'),
            H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_ON => get_string('displayoptionauthoron', 'qtype_hvp'),
            H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_OFF => get_string('displayoptionauthoroff', 'qtype_hvp')
        );

        $embedchoices = array(
            H5PDisplayOptionBehaviour::NEVER_SHOW => get_string('displayoptionnevershow', 'qtype_hvp'),
            H5PDisplayOptionBehaviour::ALWAYS_SHOW => get_string('displayoptionalwaysshow', 'qtype_hvp'),
            H5PDisplayOptionBehaviour::CONTROLLED_BY_PERMISSIONS => get_string('displayoptionpermissionsembed', 'qtype_hvp'),
            H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_ON => get_string('displayoptionauthoron', 'qtype_hvp'),
            H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_OFF => get_string('displayoptionauthoroff', 'qtype_hvp')
        );
        $settingspage->add(new admin_setting_heading('qtype_hvp/display_options', get_string('displayoptions', 'qtype_hvp'), ''));
        $settingspage->add(new admin_setting_configcheckbox('qtype_hvp/frame', get_string('enableframe', 'qtype_hvp'), '', 1));
        $settingspage->add(new admin_setting_configselect('qtype_hvp/export', get_string('enabledownload', 'qtype_hvp'), '',
            H5PDisplayOptionBehaviour::ALWAYS_SHOW, $choices));
        $settingspage->add(new admin_setting_configselect('qtype_hvp/embed', get_string('enableembed', 'qtype_hvp'), '',
            H5PDisplayOptionBehaviour::ALWAYS_SHOW, $embedchoices));
        $settingspage->add(new admin_setting_configcheckbox('qtype_hvp/copyright', get_string('enablecopyright', 'qtype_hvp'), '', 1));
        $settingspage->add(new admin_setting_configcheckbox('qtype_hvp/icon', get_string('enableabout', 'qtype_hvp'), '', 1));
    }
    $ADMIN->add('qtype_hvp_category', $settingspage);
    $ADMIN->add('qtype_hvp_category',
        new admin_externalpage(
            'qtype_hvp_libraries',
            get_string('librariessettings', 'qtype_hvp'),
            new moodle_url('/question/type/hvp/library_list.php')));
}
