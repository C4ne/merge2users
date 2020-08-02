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
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   tool_merge2users
 */

namespace tool_merge2users\merge\tables;

defined('MOODLE_INTERNAL') || die();

use tool_merge2users\helper;

/**
 * A merger that provides the sql to merge the data of all core tables.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_merge2users
 * TODO: Add a PHPUnit test that checks if there exists a function for every table in /lib/db/install.xml
 */
class system_table_merger extends component_merger {

    /** @var array|bool An array containing all core tables as an xmldb_table object */
    private $_tables;

    /**
     * system_table_merger constructor.
     *
     * @param $baseuserid A reference to user.id of the base user
     * @param $mergeuserid A reference to user.id of the user to be merged
     * @throws \moodle_exception If there goes something wrong getting the tables
     */
    public function __construct($baseuserid, $mergeuserid) {
        global $CFG;
        parent::__construct($baseuserid, $mergeuserid);
        $this->_tables = helper::get_tables_by_xml($CFG->libdir.'/db/install.xml');
    }

    /**
     * Displays the options for all core tables.
     *
     * @param $settingpage admin_settingpage The admin settings page the options will be added to.
     */
    public static function deliver_merge_options(&$settingpage) {
        $settingpage->add(new \admin_setting_configcheckbox('delete_merge_user', 'Delete the user that got merged',
                'The record in mdl_user for this user will be deleted', 1));
    }

    /**
     * Gets the sql and parameters from all functions that are declared like 'deliver_'.tablename.'_sql_and_parameters' and
     * returns it in an array.
     *
     * @return array
     * @throws \coding_exception
     */
    public function get_sql_and_parameters_per_table() {
        global $DB;

        $sqlandparameters = array();

        $functions = get_class_methods($this);
        $prefix = 'deliver_';
        $postfix = '_sql_and_parameters';
        // Filter out all functions that do not start with $prefix and end with $postfix.
        $deliverfunctions = array_filter($functions, function($function) use ($prefix, $postfix) {
            return (substr($function, 0, strlen($prefix)) === $prefix &&
                    substr($function, strlen($function) - strlen($postfix), strlen($postfix)) === $postfix);
        });

        $tables = $DB->get_tables();
        foreach ($deliverfunctions as $deliverfunction) {
            $tablename = substr(substr($deliverfunction, 0, -strlen($postfix)), strlen($prefix));
            // TODO Implement this as a phpunit test instead of executing it
            // TODO in production everytime (We know which tables are installed by default).
            if (!in_array($tablename, $tables)) {
                throw new \coding_exception('The methodname is wrong',
                        'The methodname '.$deliverfunction.' holds a tablename that does not exist.');
            }

            if (!empty($queries = $this->$deliverfunction())) {
                $sqlandparameters[$tablename] = $queries;
            }

        }

        return $sqlandparameters;
    }

    private function deliver_user_sql_and_parameters() {
        $config = get_config('tool_merge2users', 'delete_merge_user');
        $queries = array();
        if ($config == 1) {
            $queries[] = array('sql' => 'DELETE from {user} WHERE id=?', 'params' => array($this->mergeuserid));
        }

        return $queries;
    }

    private function deliver_user_enrolments_sql_and_parameters() {
        $merger = new generic_table_merger($this->_tables['user_enrolments'], $this->baseuserid, $this->mergeuserid,
                array('userid', 'modifierid'));
        return $merger->get_sql_and_parameters();
    }

    private function deliver_message_conversation_members_sql_and_parameters() {
        $merger = new generic_table_merger($this->_tables['message_conversation_members'], $this->baseuserid, $this->mergeuserid,
                array('userid'));
        return $merger->get_sql_and_parameters();
    }

    private function deliver_role_assignments_sql_and_parameters() {
        $merger = new generic_table_merger($this->_tables['role_assignments'], $this->baseuserid, $this->mergeuserid,
                array('userid'));
        return $merger->get_sql_and_parameters();
    }

    private function deliver_favourite_sql_and_parameters() {
        $merger = new generic_table_merger($this->_tables['favourite'], $this->baseuserid, $this->mergeuserid,
                array('userid'));
        return $merger->get_sql_and_parameters();
    }


}