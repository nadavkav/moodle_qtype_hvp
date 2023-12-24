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
 * qtype_hvp look_for_updates.php description here.
 *
 * @package    qtype_hvp
 * @copyright  2023 avi <avi@sysbind.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_hvp\task;

class look_for_updates extends \core\task\scheduled_task {

    /**
     * @inheritDoc
     */
    public function get_name() {
        return get_string('lookforupdates', 'qtype_hvp');
    }

    /**
     * @inheritDoc
     */
    public function execute() {
        if (get_config('qtype_hvp', 'hub_is_enabled')
            || get_config('qtype_hvp', 'send_usage_statistics')) {
            $core = \qtype_hvp\framework::instance();
            $core->fetchLibrariesMetadata();
        }
    }
}
