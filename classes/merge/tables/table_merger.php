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
 * Contains the abstract table_merger class.
 *
 * @package   tool_merge2users
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_merge2users\merge\tables;

defined('MOODLE_INTERNAL') || die();

/**
 * Class table_merger
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_merge2users
 * TODO: Remove this class if only one class extends it.
 */
abstract class table_merger {

    /** @var int $baseuserid A reference to user.id of the base user */
    protected $baseuserid;

    /** @var int $mergeuserid A reference to user.id of the user to be merged */
    protected $mergeuserid;

    /**
     * Return the sql queries that should be executed per table. The return array should look like this:
     *
     * First dimension: key=tablename (without prefix), value=array (Tables)
     * Second dimension: key=numeric, value=array (Queries)
     * Third dimension: key=sql|params value=string|array (One particular query)
     *
     * @return array The queries per table that will be executed in the given order. Return an empty array if there are no queries
     * to be executed.
     */
    public abstract function get_sql_and_parameters();

    /**
     * table_merger constructor.
     *
     * @param $baseuserid A reference to user.id of the base user
     * @param $mergeuserid A reference to user.id of the user to be merged
     */
    public function __construct($baseuserid, $mergeuserid) {
        $this->baseuserid = $baseuserid;
        $this->mergeuserid = $mergeuserid;
    }
}