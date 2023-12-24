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
 * The qtype_hvp event logger
 *
 * @package    qtype_hvp
 * @copyleft  5762 (2022) SysBind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_hvp;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/hvp/library/h5p-event-base.class.php');

class event extends \H5PEventBase {
    private $user;

     /**
      * @inheritdoc
      */
    public function __construct($type, $subtype = null, $contentid = null,
        $contenttitle = null, $libraryname = null, $libraryversion = null) {
        global $USER;

        // Track the who initiated the event.
        $this->user = $USER->id;

        parent::__construct($type, $subtype, $contentid, $contenttitle, $libraryname, $libraryversion);
    }

    /**
     * Store the event.
     *
     * @return int Event ID
     */
    protected function save() {
        global $DB;

        // Get data in array format without null values.
        $data = $this->getDataArray();

        // Add user.
        $data['user_id'] = $this->user;

        return $DB->insert_record('qtype_hvp_events', $data);
    }

    /**
     * @inheritdoc
     */
    // @codingStandardsIgnoreLine
    protected function saveStats() {
        global $DB;
        $type = $this->type . ' ' . $this->sub_type;

        // Grab current counter to check if it exists.
        $id = $DB->get_field_sql(
            "SELECT id
               FROM {qtype_hvp_counters}
              WHERE type = ?
                AND library_name = ?
                AND library_version = ?",
            array($type, $this->library_name, $this->library_version)
        );

        if ($id === false) {
            // No counter found, insert new one.
            $DB->insert_record('qtype_hvp_counters', array(
                'type' => $type,
                'library_name' => $this->library_name,
                'library_version' => $this->library_version,
                'num' => 1
            ));
        } else {
            // Update num+1.
            $DB->execute(
                "UPDATE {qtype_hvp_counters}
                    SET num = num + 1
                  WHERE id = ?",
                array($id)
            );
        }
    }
}
