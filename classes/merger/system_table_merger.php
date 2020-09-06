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
 * Contains the system_table_merger class.
 *
 * @package   tool_merge2users
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_merge2users\merger;

defined('MOODLE_INTERNAL') || die();

use admin_settingpage;
use tool_merge2users\merge_table;

// TODO: Add a PHPUnit test that checks if there exists a function for every table in /lib/db/install.xml .

/**
 * A merger that provides the sql to merge the data of all core tables.
 *
 * @package tool_merge2users
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class system_table_merger {
    /** @var int $baseuserid A reference to user.id of the base user */
    protected $baseuserid;

    /** @var int $mergeuserid A reference to user.id of the user to be merged */
    protected $mergeuserid;

    /**
     * system_table_merger constructor.
     *
     * @param int $baseuserid A reference to user.id of the base user
     * @param int $mergeuserid A reference to user.id of the user to be merged
     */
    public function __construct($baseuserid, $mergeuserid) {
        $this->baseuserid = $baseuserid;
        $this->mergeuserid = $mergeuserid;
    }

    /**
     * Displays the options for all core tables.
     *
     * @param admin_settingpage $settingpage The admin settings page the options will be added to.
     */
    public static function deliver_merge_options(&$settingpage) {
        $settingpage->add(new \admin_setting_configcheckbox('delete_merge_user', 'Delete the user that got merged',
                'The record in mdl_user for this user will be deleted', 1));
    }

    /**
     * Gets the sql and parameters from all functions that are declared like 'deliver_'.tablename.'_queries' and
     * returns it in an array.
     *
     * @return array An array of queries per table.
     * @throws \coding_exception
     */
    public function get_queries_per_table() {
        global $DB;

        $queriespertable = array();

        $functions = get_class_methods($this);
        $prefix = 'deliver_';
        $postfix = '_queries';
        // Filter out all functions that do not start with $prefix and end with $postfix.
        $deliverfunctions = array_filter($functions, function($function) use ($prefix, $postfix) {
            return (substr($function, 0, strlen($prefix)) === $prefix &&
                    substr($function, strlen($function) - strlen($postfix), strlen($postfix)) === $postfix);
        });
        sort($deliverfunctions);

        $tables = $DB->get_tables();
        foreach ($deliverfunctions as $deliverfunction) {
            $tablename = substr(substr($deliverfunction, 0, -strlen($postfix)), strlen($prefix));
            // TODO Implement this as a phpunit test instead of executing it
            // TODO in production everytime (We know which tables are installed by default).
            if (!in_array($tablename, $tables)) {
                throw new \coding_exception('The methodname is wrong',
                        'The methodname '.$deliverfunction.' holds a tablename that does not exist.');
            }

            $queries = $this->$deliverfunction();
            if (!empty($queries)) {
                $queriespertable[$tablename] = $queries;
            }

        }

        return $queriespertable;
    }

    /**
     * Delivers the queries to merge two datasets in the user table.
     *
     * @return array
     * @throws \dml_exception If the configuration could not be read
     */
    private function deliver_user_queries() {
        $config = get_config('tool_merge2users', 'delete_merge_user');
        $queries = array();
        if ($config == 1) {
            $query = new \stdClass();
            $query->sql = 'DELETE from {user} WHERE id=:mergeuserid';
            $query->params = array('mergeuserid' => $this->mergeuserid);
            $queries[] = $query;
        }

        return $queries;
    }

    /**
     * Delivers the queries to merge two datasets in the user_enrolments table.
     *
     * @return array An array of objects
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function deliver_user_enrolments_queries() {
        $merger = new generic_table_merger('user_enrolments', $this->baseuserid, $this->mergeuserid, array('userid', 'modifierid'));
        return $merger->get_queries();
    }

    /**
     * Delivers the queries to merge two datasets in the message_conversation_members table.
     *
     * @return array An array of objects
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function deliver_message_conversation_members_queries() {
        $merger = new generic_table_merger('message_conversation_members', $this->baseuserid, $this->mergeuserid, array('userid'));
        return $merger->get_queries();
    }

    /**
     * Delivers the queries to merge two datasets in the role_assignments table.
     *
     * @return array An array of objects
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function deliver_role_assignments_queries() {
        $merger = new generic_table_merger('role_assignments', $this->baseuserid, $this->mergeuserid, array('userid'));
        return $merger->get_queries();
    }

    /**
     * Delivers the queries to merge two datasets in the favourite table.
     *
     * @return array An array of objects
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function deliver_favourite_queries() {
        $merger = new generic_table_merger('favourite', $this->baseuserid, $this->mergeuserid, array('userid'));
        return $merger->get_queries();
    }


}