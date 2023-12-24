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
 * Behat qtype_hvp-related steps definitions.
 *
 * @package    qtype
 * @subpackage hvp
 * @copyright 2022 onwards SysBind  {@link http://sysbind.co.il}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Behat custom step definitions and partial named selectors for qtype_hvp.
 *
 */
class behat_qtype_hvp extends behat_base {
    /**
     * @When I click the :arg1 li
     */
    public function iclicktheli($arg1) {
        $page = $this->getSession()->getPage();

        $findname = $page->find("css", $arg1);
        if (!$findname) {
            throw new Exception($arg1 . " could not be found");
        } else {
            $findname->click();
        }
    }

}
