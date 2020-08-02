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
 * Contains the merge_process class.
 *
 * @package   tool_merge2users
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_merge2users\merge\process;

defined('MOODLE_INTERNAL') || die();

use tool_merge2users\merge\tables\generic_table_merger;
use tool_merge2users\merge\tables\system_table_merger;
use tool_merge2users\helper;
use core\lock;
use core\output\notification;
use core_component;

/**
 * The class that takes care of the merging process.
 *
 * Creates a lock, database transaction, takes care of logging anything, determines which tables should be merged and by whom,
 * executes the sql queries, displays the result to the user.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_merge2users
 * TODO: Replcace xmldb code with code that gets that information directly from the database (way more robust).
 * TODO: Decouple the printing from the process itself (necessary for the cli script for examlple).
 */
final class merge_process {

    /** @var int $_baseuserid A reference to the user.id column for the base user */
    private $_baseuserid;

    /** @var int $_mergeuserid A reference to the user.id column for the merge user */
    private $_mergeuserid;

    /** @var array $tablesperplugin the key is a plugin in frankenstein notation,
     * the value is all the associated tables as a xmldb_table object
     */
    private $_tablesperplugin = array();

    /** @var \moodle_transaction $_transaction The transaction we started for this merging process */
    private $_transaction;

    /**
     * @var bool|\core\lock\lock $_lock A lock that guarantees that there's no other merging process going on right now. False if
     * lock could not be retrieved.
     */
    private $_lock;

    /** @var \context_system $_context The context this process is executed in.  */
    private $_context;

    /**
     * Creates a lock and a database transaction.
     *
     * @param int $baseuserid A reference to the user.id column of the base user
     * @param int $mergeuserid A reference to the user.id column of the user to be merged
     * @param \context_system $context The context the events should be triggered in
     * @throws \coding_exception
     * @throws \moodle_exception If this would be an AJAX or CLI script
     */
    public function __construct($baseuserid, $mergeuserid, $context) {
        global $DB, $USER;

        // Activate lock first.
        $timeout = 5;
        $locktype = 'tool_merge2users_merge_process';
        $resource = 'user:'.$USER->id;
        $lockfactory = lock\lock_config::get_lock_factory($locktype);

        if ($this->_lock = $lockfactory->get_lock($resource, $timeout)) {
            $this->_transaction = $DB->start_delegated_transaction();
            $this->_tablesperplugin = $this->get_tables_per_plugin();
            $this->_baseuserid = $baseuserid;
            $this->_mergeuserid = $mergeuserid;
            $this->_context = $context;
            eventnotification::$context = $context;

        } else {
            $url = new \moodle_url('/admin/tool/merge2users/confirm_selection.php');
            redirect($url, get_string('could_not_retrieve_lock', 'tool_merge2users'));
        }
    }

    /**
     * Makes sure the transaction is either rolled back or commited and that the lock gets released.
     *
     * This function may get called if the merge process was aborted or if it was successful.
     *
     * @param bool $abort If set to true the transaction will be rolled back
     * @throws \Throwable
     * @throws \coding_exception
     */
    private function end_merge_process($abort) {
        global $DB, $OUTPUT;

        echo $OUTPUT->heading(get_string('status'));
        try {
            // Has the merge been successful or has it been aborted?
            if (!$abort) {
                eventnotification::trigger_merge_success();
                try {
                    // Try to commit the transaction.
                    $DB->commit_delegated_transaction($this->_transaction);
                    // Only render message if the transaction was successfully.
                    eventnotification::trigger_transaction_success();
                } catch (\dml_transaction_exception $ex) {
                    // Transaction commit failed.
                    eventnotification::trigger_transaction_failure();
                    $DB->rollback_delegated_transaction($this->_transaction, $ex);
                }
            } else {
                eventnotification::trigger_merge_failure();
                $DB->rollback_delegated_transaction($this->_transaction, new \moodle_exception('dmltransactionexception'));
            }
        } catch (\moodle_exception $ex) {
            // Technically we can not know if the rollback was successful, so this notification is technically a lie.
            $notification = new notification(get_string('rollback_successful', 'tool_merge2users'),
                    notification::NOTIFY_SUCCESS);
            $notification->set_show_closebutton(false);
            echo $OUTPUT->render($notification);
        } finally {
            $this->_lock->release();
        }
    }

    /**
     * Tries to perform the merge process.
     *
     * @throws \Throwable
     * @throws \coding_exception
     */
    public function perform() {
        $abort = true; // For development purposes. I wont have to roll back the db everytime. We will change this to false later.
        try {
            $this->merge_users();
        } catch (\dml_exception $ex) {
            $abort = true;
        } finally {
            $this->end_merge_process($abort);
        }
    }

    /**
     * Calls the special merger for the core tables first, then gets the sql from all plugins that implement the 'API'. All tables
     * that belong to plugins that do not implement the 'API' get merged by the generic_table_merger.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function merge_users() {
        global $OUTPUT;

        // First: Merge core tables.
        $systemmerger = new system_table_merger($this->_baseuserid, $this->_mergeuserid);
        $queriespertable = $systemmerger->get_sql_and_parameters_per_table();

        echo $OUTPUT->heading(get_string('coresystem'));
        $this->execute_sql_per_table($queriespertable);
        echo \html_writer::empty_tag('hr');
        unset($this->_tablesperplugin['core']);

        // Second: Get all plugins that support merging.
        $pluginswithfunction = get_plugins_with_function('deliver_merge_sql_and_parameters');
        if (!empty($pluginswithfunction)) {
            foreach ($pluginswithfunction as $plugintype => $plugins) {
                foreach ($plugins as $pluginfunction) {
                    $plugin = array_search($pluginfunction, $plugins);
                    echo $OUTPUT->heading($plugintype . '_' . $plugin);

                    $sql = $pluginfunction($this->_baseuserid, $this->_mergeuserid);
                    if (!empty($sql)) {
                        $this->execute_sql_per_table($sql);
                    }

                    unset($this->_tablesperplugin[$plugin]);
                    echo \html_writer::empty_tag('hr');
                }
            }
        }

        // Third: Merge all other tables.
        foreach ($this->_tablesperplugin as $pluginname => $tables) {
            $queries = array();
            foreach ($tables as $table) {
                $merger = new generic_table_merger($table, $this->_baseuserid, $this->_mergeuserid);
                $queries[$table->getName()] = $merger->get_sql_and_parameters();
            }

            if (!empty($queries)) {
                echo $OUTPUT->heading($pluginname);
                $this->execute_sql_per_table($queries);
                echo \html_writer::empty_tag('hr');
            }
            unset($this->_tablesperplugin[$pluginname]);
        }
    }

    /**
     * Gets all tables per installed plugin and makes sure these tables are present on the current database.
     *
     * @copyright 1999 onwards Martin Dougiamas http://dougiamas.com
     * @return array See _tablesperplugin member variable for a detailed explanation of the structure
     */
    private function get_tables_per_plugin() {
        global $CFG, $DB;

        // See variable declaration of $_tablesperplugin for a description of the structure of this array.
        $tablesperplugin = array();
        // Add core tables.
        $tablesperplugin['core'] = helper::get_tables_by_xml($CFG->libdir.'/db/install.xml');
        $plugintypes = core_component::get_plugin_types();
        foreach ($plugintypes as $plugintype => $pluginbasedir) {
            if ($plugins = core_component::get_plugin_list($plugintype)) {
                foreach ($plugins as $plugin => $plugindir) {
                    $xmlfilepath = $plugindir.'/db/install.xml';
                    if ($tables = helper::get_tables_by_xml($xmlfilepath, $plugin)) {
                        $tablesperplugin[$plugintype.'_'.$plugin] = $tables;
                    }
                }
            }
        }

        // We need to check this because there could be a table defined in a install.xml that is not installed yet.
        $allinstalledtables = $DB->get_tables();
        $tablesfoundbyxml = array();
        foreach ($tablesperplugin as $tables) {
            foreach ($tables as $table) {
                $tablesfoundbyxml[] = $table->getName();
            }
        }

        // We need to remove these so we dont try to access tables that are not installed.
        $definedbutnotinstalledtables = array_diff($tablesfoundbyxml, $allinstalledtables);

        // These could be tables of removed plugins or tables of some hacky code that does not register as a plugin.
        // TODO Decide what to do with these orphaned tables.
        $installedbutnotdefinedtables = array_diff($allinstalledtables, $tablesfoundbyxml);

        // Remove all tables that are not installed but defined in a install.xml .
        if (!count($definedbutnotinstalledtables) == 0) {
            foreach ($tablesperplugin as $plugin => $tables) {
                foreach ($tables as $tablekey => $table) {
                    if (in_array($table->getName(), $definedbutnotinstalledtables)) {
                        unset($tablesperplugin[$plugin][$tablekey]);
                        if (count($tablesperplugin[$plugin]) == 0) {
                            unset($tablesperplugin[$plugin]);
                        }
                    }
                }
            }
        }

        return $tablesperplugin;
    }

    /**
     * Executes the sql queries per table.
     *
     * @param $sql First dimension: the table, second dimension: the query, third dimension the sql string and the params
     * @throws \coding_exception
     * TODO This method is still a bit wierd. Half of the displaying is done here, the other half in merge_users ...
     */
    private function execute_sql_per_table($sql) {
        global $DB;
        if (!empty($sql)) {
            foreach ($sql as $tablename => $queries) {
                foreach ($queries as $queriespertable) {
                    try {
                        $DB->execute($queriespertable["sql"], $queriespertable["params"]);
                    } catch (\dml_exception $ex) {
                        eventnotification::trigger_merge_table_failure($tablename);
                        throw $ex;
                    }

                    eventnotification::trigger_merge_table_success($tablename);
                }
            }
        }
        // TODO: What should we do if it is empty?
    }

}