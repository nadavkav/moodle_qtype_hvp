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
 * qtype_hvp remove_old_auth_tokens.php description here.
 *
 * @package    qtype_hvp
 * @copyright  2023 avi <avi@sysbind.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_hvp\task;

use mod_hvp\mobile_auth;

class remove_old_auth_tokens extends \core\task\scheduled_task {

    /**
     * @inheritDoc
     */
    public function get_name() {
        return get_string('removeoldmobileauthentries', 'qtype_hvp');
    }

    /**
     * @inheritDoc
     */
    public function execute() {
        global $DB;
        require_once(__DIR__ . '/../../autoloader.php');
        $deletethreshold = time() - mobile_auth::VALID_TIME;
        $DB->delete_records_select('qtype_hvp_auth', 'created_at < :threshold', ['threshold' => $deletethreshold]);
    }
}
