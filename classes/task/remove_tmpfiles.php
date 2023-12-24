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
 * qtype_hvp remove_tmpfiles.php description here.
 *
 * @package    qtype_hvp
 * @copyright  2023 avi <avi@sysbind.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_hvp\task;

class remove_tmpfiles extends \core\task\scheduled_task {

    /**
     * @inheritDoc
     */
    public function get_name() {
        return get_string('removetmpfiles', 'qtype_hvp');
    }

    /**
     * @inheritDoc
     */
    public function execute() {
        global $DB;
        $tmpfiles = $DB->get_records_sql("SELECT f.id FROM {qtype_hvp_tmpfiles} tf JOIN {files} f ON f.id = tf.id 
            WHERE f.timecreated < ?", [time() - 86400]);
        if (empty($tmpfiles)) {
            return;
        }

        $fs = get_file_storage();
        foreach ($tmpfiles as $tmpfile) {
            $file = $fs->get_file_by_id($tmpfile->id);
            $file->delete();
            $DB->delete_records('qtype_hvp_tmpfiles', ['id' => $tmpfile->id]);
        }
    }
}
