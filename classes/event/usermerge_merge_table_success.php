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
 * Contains the usermerge_merge_table_success class.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   tool_merge2users
 */

namespace tool_merge2users\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Trigger this event whenever a merge of a table has been successful.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_merge2users
 *
 * @property-read array $other {
 *      Extra information about the merge process
 *
 *          - string    table:  The name of the table (without prefix)
 * }
 */
class usermerge_merge_table_success extends usermerge_merge {

    /**
     * function get_explanation
     *
     * @return string The explanation
     */
    public static function get_explanation() {
        return 'These events will be triggered everytime a merge of user data for one particular table succeeded';
    }

    /**
     * function get_name
     *
     * @return string The name
     */
    public static function get_name() {
        return 'Succeeded to merge user data in table';
    }

    /**
     * function get_description
     *
     * @return string The description
     */
    public function get_description() {
        return 'Successfully merged table '.$this->other['table'];
    }
}