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
 * @package    qtype_hvp
 * @copyright 2022 onwards SysBind  {@link http://sysbind.co.il}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the information to backup hvp questions.
 *
 */
class backup_qtype_hvp_plugin extends backup_qtype_plugin {

    /**
     * Returns the qtype information to attach to question element.
     */
    protected function define_question_plugin_structure() {
        global $CFG;
        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element(null, '../../qtype', 'hvp');

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect the visible container ASAP.
        $plugin->add_child($pluginwrapper);
        // Allow user to override library backup.
        $backuplibraries = !(isset($CFG->qtype_hvp_backup_libraries) && $CFG->qtype_hvp_backup_libraries === '0');

        // Exclude hvp libraries step for local 'imports'.
        if ($backuplibraries && backup_controller_dbops::backup_includes_files($this->task->get_backupid())) {
            $pluginwrapper->add_child($this->define_libs_n_deps());
        }


        $hvp = $this->define_question_hvp_structure();

        // Now the own qtype tree.
        $pluginwrapper->add_child($hvp);

        // Set source to populate the data.
        $hvp->set_source_sql('
          SELECT h.id,
                 h.question,
                 hl.machine_name,
                 hl.major_version,
                 hl.minor_version,
                 h.json_content,
                 h.title,
                 h.embed_type,
                 h.disable,
                 h.content_type,
                 h.slug,
                 h.timecreated,
                 h.timemodified,
                 h.authors,
                 h.source,
                 h.year_from,
                 h.year_to,
                 h.license_version,
                 h.changes,
                 h.license_extras,
                 h.author_comments,
                 h.license,
                 h.completionpass
          FROM {qtype_hvp} h
              JOIN {qtype_hvp_libraries} hl ON hl.id = h.main_library_id
              WHERE h.question = ?', array(backup::VAR_PARENTID));

        // Return root element.
        return $plugin;
    }

    protected function define_question_hvp_structure() {
        // Define each element separated.
        $hvp = new backup_nested_element('hvp', array('id'), array(
            'question',
            'title',
            'machine_name',
            'major_version',
            'minor_version',
            'intro',
            'introformat',
            'json_content',
            'embed_type',
            'disable',
            'content_type',
            'source',
            'year_from',
            'year_to',
            'license_version',
            'changes',
            'license_extras',
            'author_comments',
            'slug',
            'timecreated',
            'timemodified',
            'authors',
            'license',
            'completionpass'
        ));

        return $hvp;
    }

    protected function define_question_library_structure() {
        return new backup_nested_element('library', array('id'), array(
            'title',
            'machine_name',
            'major_version',
            'minor_version',
            'patch_version',
            'runnable',
            'fullscreen',
            'embed_types',
            'preloaded_js',
            'preloaded_css',
            'drop_library_css',
            'semantics',
            'restricted',
            'tutorial_url',
            'add_to',
            'metadata'
        ));
    }

    protected function define_libs_n_deps() {
        $libraries = new backup_nested_element('hvp_libraries');

        $library = $this->define_question_library_structure();

        // Library translations.
        $translations = new backup_nested_element('translations');
        $translation = new backup_nested_element('translation', array(
            'language_code'
        ), array(
            'language_json'
        ));

        // Library dependencies.
        $dependencies = new backup_nested_element('dependencies');
        $dependency = new backup_nested_element('dependency', array(
            'required_library_id'
        ), array(
            'dependency_type'
        ));

        // Build the tree.
        $libraries->add_child($library);

        $library->add_child($translations);
        $translations->add_child($translation);

        $library->add_child($dependencies);
        $dependencies->add_child($dependency);

        // Define sources.

        $library->set_source_table('qtype_hvp_libraries', array());

        $translation->set_source_table('qtype_hvp_libs_languages', array('library_id' => backup::VAR_PARENTID));

        $dependency->set_source_table('qtype_hvp_libs_libraries', array('library_id' => backup::VAR_PARENTID));

        // Define file annotations.
        $context = \context_system::instance();
        $library->annotate_files('qtype_hvp', 'libraries', null, $context->id);

        return $libraries;
    }

    /**
     * Returns one array with filearea => mappingname elements for the qtype.
     *
     * Used by {@link get_components_and_fileareas} to know about all the qtype
     * files to be processed both in backup and restore.
     */
    public static function get_qtype_fileareas() {
        return array(
            'correctfeedback' => 'question_created',
            'partiallycorrectfeedback' => 'question_created',
            'incorrectfeedback' => 'question_created');
    }
}
