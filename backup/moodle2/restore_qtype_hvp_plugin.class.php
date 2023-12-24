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
 * restore plugin class that provides the necessary information
 * needed to restore one hvp qtype plugin
 *
 */
class restore_qtype_hvp_plugin extends restore_qtype_plugin {

    /**
     * Returns the paths to be handled by the plugin at question level
     */
    protected function define_question_plugin_structure() {
        $paths = array();

        // This qtype uses question_answers, add them.
        $this->add_question_question_answers($paths);

        // Add own qtype stuff.
        $elepath = $this->get_pathfor('/'); // We used get_recommended_name() so this works.

        // Restore libraries first.
        $paths[] = new restore_path_element('hvp_library', $elepath . '/hvp_libraries/library');

        // Add translations.
        $paths[] = new restore_path_element('hvp_library_translation',
                                            $elepath . '/hvp_libraries/library/translations/translation');
        // ... and dependencies.
        $paths[] = new restore_path_element('hvp_library_dependency',
                                            $elepath . '/hvp_libraries/library/dependencies/dependency');

        $paths[] = new restore_path_element('hvp', $elepath . '/hvp');

        return $paths; // And we return the interesting paths.
    }

    /**
     * Process the qtype/hvp element
     */
    public function process_hvp($data) {
        global $DB;

        static $maxfileid = 0;

        $data = (object)$data;
        $oldid = $data->id;
        $data->main_library_id = self::get_library_id($data);

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, we need to create its question_hvp too.
        if ($questioncreated) {
            // Adjust some columns.
            $data->question = $newquestionid;
            // Insert record.
            $newitemid = $DB->insert_record('qtype_hvp', $data);
            // Create mapping.
            $this->set_mapping('question_hvp', $oldid, $newitemid);

            if ($maxfileid == 0) {
                // Add files for intro field.
                $this->add_related_files('qtype_hvp', 'intro', null);

                // Add hvp related files.
                $maxfileid = $DB->get_field_sql("SELECT MAX(id) FROM {files}");

                $this->add_related_files('qtype_hvp', 'content', null);
            }

            $newfiles = $DB->get_records_select('files', 'id > ? AND component=? AND filearea=? AND itemid=?',
                                            [$maxfileid, 'qtype_hvp', 'content', $oldid]);
            // Fix item ids to be question id.
            foreach ($newfiles as $file) {
                $file->itemid = $newquestionid;
                $file->pathnamehash = file_storage::get_pathname_hash($file->contextid,
                                                                      'qtype_hvp',
                                                                      'content',
                                                                      $newquestionid,
                                                                      $file->filepath,
                                                                      $file->filename);
                $DB->update_record('files', $file);
            }
        }
    }

    /**
     * Process and insert library record.
     *
     * @param $data
     *
     * @throws dml_exception
     * @throws restore_step_exception
     */
    public function process_hvp_library($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        unset($data->id);

        $libraryid = self::get_library_id($data);
        if (!$libraryid) {
            // There is no updating of libraries. If an older patch version exists
            // on the site that one will be used instead of the new one in the backup.
            // This is due to the default behavior when files are restored in Moodle.

            // Restore library.
            $libraryid = $DB->insert_record('qtype_hvp_libraries', $data);

            // Update libraries cache.
            self::get_library_id($data, $libraryid);
        }

        // Keep track of libraries for translations and dependencies.
        $this->set_mapping('hvp_library', $oldid, $libraryid);

        // Update any dependencies that require this library.
        $this->update_missing_dependencies($oldid, $libraryid);

    }



    /**
     * Process and inserts translations for library.
     *
     * @param $data
     *
     * @throws dml_exception
     */
    public function process_hvp_library_translation($data) {
        global $DB;

        $data = (object) $data;
        $data->library_id = $this->get_new_parentid('hvp_library');

        // Check that translations doesn't exists.
        $translation = $DB->get_record_sql(
            'SELECT id
               FROM {qtype_hvp_libs_languages}
              WHERE library_id = ?
                AND language_code = ?',
              array($data->library_id,
                    $data->language_code)
        );

        if (empty($translation)) {
            // Only restore translations if library has been restored.
            $DB->insert_record('qtype_hvp_libs_languages', $data);
        }
    }

    /**
     * Process and inserts library dependencies.
     *
     * @param $data
     *
     * @throws dml_exception
     */
    public function process_hvp_library_dependency($data) {
        global $DB;

        $data             = (object) $data;
        $data->library_id = $this->get_new_parentid('hvp_library');

        $newlibraryid = $this->get_mappingid('hvp_library', $data->required_library_id);
        if ($newlibraryid) {
            $data->required_library_id = $newlibraryid;

            // Check that the dependency doesn't exists.
            $dependency = $DB->get_record_sql(
                'SELECT id
                 FROM {qtype_hvp_libs_libraries}
                WHERE library_id = ?
                  AND required_library_id = ?',
                [
                    $data->library_id,
                    $data->required_library_id,
                ]
            );
            if (empty($dependency)) {
                $DB->insert_record('qtype_hvp_libs_libraries', $data);
            }
        } else {
            // The required dependency hasn't been restored yet. We need to add this dependency later.
            $this->update_missing_dependencies($data->required_library_id, null, $data);
        }
        // Add files for libraries.
        $context = \context_system::instance();
        $this->add_related_files('qtype_hvp', 'libraries', null, $context->id);
    }


    /**
     * Keep track of missing dependencies since libraries aren't inserted
     * in any special order
     *
     * @param $oldid
     * @param $newid
     * @param null $setmissing
     *
     * @throws dml_exception
     */
    private function update_missing_dependencies($oldid, $newid, $setmissing = null) {
        static $missingdeps;
        global $DB;

        if (is_null($missingdeps)) {
            $missingdeps = array();
        }

        if ($setmissing !== null) {
            $missingdeps[$oldid][] = $setmissing;
        } else if (isset($missingdeps[$oldid])) {
            foreach ($missingdeps[$oldid] as $missingdep) {
                $missingdep->required_library_id = $newid;

                // Check that the dependency doesn't exists.
                $dependency = $DB->get_record_sql(
                    'SELECT id
                       FROM {qtype_hvp_libs_libraries}
                      WHERE library_id = ?
                        AND required_library_id = ?',
                      array($missingdep->library_id,
                            $missingdep->required_library_id)
                );
                if (empty($dependency)) {
                    $DB->insert_record('qtype_hvp_libs_libraries', $missingdep);
                }
            }
            unset($missingdeps[$oldid]);
        }
    }

    /**
     * Cache to reduce queries.
     *
     * @param $library
     * @param null $set
     *
     * @return mixed
     * @throws dml_exception
     */
    public static function get_library_id(&$library, $set = null) {
        static $keytoid;
        global $DB;

        $key = $library->machine_name . ' ' . $library->major_version . '.' . $library->minor_version;
        if (is_null($keytoid)) {
            $keytoid = array();
        }
        if ($set !== null) {
            $keytoid[$key] = $set;
        } else if (!isset($keytoid[$key])) {
            $lib = $DB->get_record_sql(
                'SELECT id
                   FROM {qtype_hvp_libraries}
                  WHERE machine_name = ?
                    AND major_version = ?
                    AND minor_version = ?',
                  array($library->machine_name,
                        $library->major_version,
                        $library->minor_version)
            );

            // Non existing = false.
            $keytoid[$key] = (empty($lib) ? false : $lib->id);
        }

        return $keytoid[$key];
    }
}
