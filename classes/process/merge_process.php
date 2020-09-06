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

namespace tool_merge2users\process;

defined('MOODLE_INTERNAL') || die();

use single_button;
use tool_merge2users\merger\system_table_merger;
use tool_merge2users\merger\generic_table_merger;
use context_system;
use html_writer;
use moodle_transaction;
use moodle_url;
use core\lock\lock_config;
use core\lock\lock;
use core\output\notification;
use Throwable;
use dml_exception;
use dml_transaction_exception;
use moodle_exception;
use coding_exception;

/**
 * The class that takes care of the merging process.
 *
 * Creates a lock, database transaction, takes care of logging anything, determines which tables should be merged and by whom,
 * executes the sql queries, displays the result to the user.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_merge2users
 * TODO: Set debugging options for more verbose output for the user ($CFG->debug) ?
 * TODO: Do something against these weird try-catch blocks that rethrow the catched exception.
 */
class merge_process {

    /** @var int $_baseuserid A reference to the user.id column for the base user */
    private $_baseuserid;

    /** @var int $_mergeuserid A reference to the user.id column for the merge user */
    private $_mergeuserid;

    /** @var moodle_transaction $_transaction The transaction we started for this merging process */
    private $_transaction;

    /**
     * @var bool|lock $_lock A lock that guarantees that there's no other merging process going on right now. False if
     * lock could not be retrieved.
     */
    private $_lock;

    /** @var context_system $_context The context this process is executed in.  */
    private $_context;

    /** @var bool $_dryrun Whether or not this is a dry run */
    private $_dryrun;

    /** @var array $_processedtables An array so avoid processing tables multiple times */
    private $_processedtables = array();

    /**
     * Creates a lock and a database transaction. Must be called before output is started.
     *
     * @param int $baseuserid A reference to the user.id column of the base user
     * @param int $mergeuserid A reference to the user.id column of the user to be merged
     * @param context_system $context The context the events should be triggered in
     * @param bool $dryrun Set this to true to force a transaction rollback at the end
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function __construct($baseuserid, $mergeuserid, $context, $dryrun = false) {
        global $DB, $USER;

        // Activate lock first.
        $timeout = 5;
        $locktype = 'tool_merge2users_merge_process';
        $resource = 'user:'.$USER->id;
        $lockfactory = lock_config::get_lock_factory($locktype);

        if ($this->_lock = $lockfactory->get_lock($resource, $timeout)) {
            $this->_transaction = $DB->start_delegated_transaction();
            $this->_baseuserid = $baseuserid;
            $this->_mergeuserid = $mergeuserid;
            $this->_context = $context;
            $this->_dryrun = $dryrun;
            eventnotification::$context = $context;
        } else {
            if (CLI_SCRIPT) {
                cli_error(get_string('could_not_retrieve_lock', 'tool_merge2users'));
            } else {
                $url = new moodle_url('/admin/tool/merge2users/confirm_selection.php');
                redirect($url, get_string('could_not_retrieve_lock', 'tool_merge2users'));
            }
        }
    }

    /**
     * Makes sure the transaction is either rolled back or commited and that the lock gets released.
     *
     * This function may get called if the merge process was aborted or if it was successful.
     *
     * @param moodle_exception $abortexception If an exception was thrown during the merge process pass it here and we will abort
     * @throws moodle_exception We rethrow the parameter so we get more verbose information why it failed in the browser window
     */
    private function end_merge_process($abortexception = null) {
        global $DB, $OUTPUT;

        echo $OUTPUT->heading(get_string('status'));
        try {
            if (is_null($abortexception)) {
                eventnotification::trigger_merge_success($this->_baseuserid, $this->_mergeuserid);
                if ($this->_dryrun) {
                    $DB->rollback_delegated_transaction($this->_transaction,
                            new dml_transaction_exception('dmltransactionexception'));
                } else {
                    try {
                        $DB->commit_delegated_transaction($this->_transaction);
                        eventnotification::trigger_transaction_success();
                    } catch (dml_transaction_exception $ex) {
                        eventnotification::trigger_transaction_failure();
                        $DB->rollback_delegated_transaction($this->_transaction, $ex);
                    }
                }
            } else {
                eventnotification::trigger_merge_failure($this->_baseuserid, $this->_mergeuserid);
                $DB->rollback_delegated_transaction($this->_transaction, $abortexception);
            }
        } catch (moodle_exception $ex) {
            // Technically we can not know if the rollback was successful, so this notification is technically a lie.
            $notification = new notification(get_string('rollback_successful', 'tool_merge2users'),
                    notification::NOTIFY_SUCCESS);
            $notification->set_show_closebutton(false);
            echo $OUTPUT->render($notification);
        } finally {
            $this->_lock->release();
            if (!is_null($abortexception)) {
                // So we can see why things failed.
                throw $abortexception;
            }
        }
    }

    /**
     * Call this to actually perform the process.
     *
     * @throws Throwable
     * @throws coding_exception
     */
    public function perform() {
        global $OUTPUT;

        $exception = null;
        try {
            $this->merge_users();
        } catch (moodle_exception $ex) {
            $exception = $ex;
        }

        $this->end_merge_process($exception);
    }

    /**
     * Calls the special merger for the core tables first, then gets the sql from all plugins that implement the 'API'. All tables
     * that belong to plugins that do not implement the 'API' get merged by the generic_table_merger.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function merge_users() {
        global $OUTPUT, $DB;

        if ($this->_dryrun) {
            echo $OUTPUT->heading(get_string('performing_dryrun', 'tool_merge2users'), 1);
        } else {
            echo $OUTPUT->heading(get_string('merging_users', 'tool_merge2users'), 1);
        }

        // First: Merge core tables.
        echo $OUTPUT->heading(get_string('coresystem'));
        try {
            $systemmerger = new system_table_merger($this->_baseuserid, $this->_mergeuserid);
            $queriespertable = $systemmerger->get_queries_per_table();
        } catch (moodle_exception $ex) {
            $a = new \stdClass();
            $a->coresystem = get_string('coresystem');
            $notification = new notification(get_string('ex_core_queries', 'tool_merge2users', $a),
                    notification::NOTIFY_ERROR);
            $notification->set_show_closebutton(false);
            echo $OUTPUT->render($notification);
            if (!CLI_SCRIPT) {
                echo html_writer::empty_tag('hr');
            }
            throw $ex;
        }

        $this->execute_sql_per_table($queriespertable);

        if (!CLI_SCRIPT) {
            echo html_writer::empty_tag('hr');
        }

        // Second: Get all plugins that support merging.
        $pluginswithfunction = get_plugins_with_function('deliver_queries');
        if (!empty($pluginswithfunction)) {
            foreach ($pluginswithfunction as $plugintype => $plugins) {
                foreach ($plugins as $pluginfunction) {
                    $plugin = array_search($pluginfunction, $plugins);

                    $frankenstein = $plugintype.'_'.$plugin;

                    try {
                        $sql = $pluginfunction($this->_baseuserid, $this->_mergeuserid);
                    } catch (moodle_exception $ex) {
                        echo $OUTPUT->heading($frankenstein);
                        $notification = new notification(get_string('ex_plugin_queries', 'tool_merge2users', $frankenstein),
                                notification::NOTIFY_ERROR);
                        $notification->set_show_closebutton(false);
                        echo $OUTPUT->render($notification);
                        if (!CLI_SCRIPT) {
                            echo html_writer::empty_tag('hr');
                        }
                        throw $ex;
                    }

                    // TODO: Activate the generic table merger if a plugin does not deliver any queries?
                    if (!empty($sql)) {
                        echo $OUTPUT->heading($frankenstein);
                        $this->execute_sql_per_table($sql);
                        if (!CLI_SCRIPT) {
                            echo html_writer::empty_tag('hr');
                        }
                    }
                }
            }
        }

        // Third: Merge all other tables.
        $alltables = $DB->get_tables();
        $queriesexecuted = false;
        echo $OUTPUT->heading(get_string('other_tables', 'tool_merge2users'));
        foreach (array_diff($alltables, $this->_processedtables) as $table) {

            try {
                $merger = new generic_table_merger($table, $this->_baseuserid, $this->_mergeuserid);
                $queries = $merger->get_queries();
            } catch (dml_exception $ex) {
                eventnotification::trigger_merge_table_failure($table);
                throw $ex;
            }

            if (!empty($queries)) {
                $this->execute_sql_per_table(array($table => $queries));
                $queriesexecuted = true;
            }
        }
        if ($queriesexecuted && !CLI_SCRIPT) {
            echo html_writer::empty_tag('hr');
        }
    }

    /**
     * Executes the sql queries per table.
     *
     * @param array $queriespertable First dimension: the table, second dimension: the queries
     * @throws dml_exception
     */
    private function execute_sql_per_table($queriespertable) {
        global $DB;
        foreach ($queriespertable as $tablename => $queries) {
            foreach ($queries as $query) {
                try {
                    $DB->execute($query->sql, $query->params);
                    $this->_processedtables[] = $tablename;
                } catch (dml_exception $ex) {
                    eventnotification::trigger_merge_table_failure($tablename);
                    throw $ex;
                }
            }
            eventnotification::trigger_merge_table_success($tablename);
        }
    }

}