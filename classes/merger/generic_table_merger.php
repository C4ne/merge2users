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
 * Contains the generic_table_merger class.
 *
 * @package   tool_merge2users
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @copyright Jordi Pujol-Ahulló <jordi.pujol@urv.cat>, SREd, Universitat Rovira i Virgili
 * @author 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_merge2users\merger;

use dml_exception;
use moodle_exception;
use mysql_xdevapi\Exception;
use stdClass;
use xmldb_file;
use xmldb_key;
use xmldb_table;

defined('MOODLE_INTERNAL') || die();

/**
 * A merger that tries to obtain as much data as possible. If a row causes a conflict, it gets removed though.
 *
 * This merger assumes that this table has a primary key that consists of one column named 'id'.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @copyright Jordi Pujol-Ahulló <jordi.pujol@urv.cat>,  SREd, Universitat Rovira i Virgili
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_merge2users
 */
class generic_table_merger {
    /** @var int $_baseuserid A reference to user.id of the base user */
    private $_baseuserid;

    /** @var int $_mergeuserid A reference to user.id of the user to be merged */
    private $_mergeuserid;

    /** @var string The table that this merger should process */
    private $_tablename;

    /** @var array $_usercolumns An array of strings containing names of columns holding a reference to the user.id column */
    private $_usercolumns;

    /**
     * @var array $_conflictingconstraints An array of arrays holding the names of columns that are in a UNIQUE constraint that
     * could potentially cause a conflict.
     *
     * Conflicting constraints are UNIQUE keys, indexes, PRIMARY keys or FOREIGN UNIQUE keys that are composed of at least
     * one column that contains a reference to the user.id column.
     */
    private $_conflictingconstraints = array();

    /**
     * @var string $_xmlfilepath Used for better auto-detection of columns that hold a reference to user.id. If you want to use this
     * for your plugin please instead use the $usercolumns parameter of the constructor!
     */
    public $_xmlfilepath = '';

    /**
     * generic_table_merger constructor.
     *
     * @param string $tablename The table that this merger should process
     * @param int $baseuserid A reference to user.id of the base user
     * @param int $mergeuserid A reference to user.id of the user to be merged
     * @param array $usercolumns An array listing all names of columns that hold a reference to the user.id column
     * @throws moodle_exception If the xmlfile is not readable, not parsable or does not exist
     */
    public function __construct($tablename, $baseuserid, $mergeuserid, $usercolumns = array()) {
        $this->_tablename = $tablename;
        $this->_baseuserid = $baseuserid;
        $this->_mergeuserid = $mergeuserid;
        if (empty($usercolumns)) {
            $this->set_user_columns();
        } else {
            $this->_usercolumns = $usercolumns;
        }
    }

    /**
     * Returns and array of sql queries and their parameters. Use this to merge the table of this instance.
     *
     * @return array An array of arrays holding the sql under the 'sql' key and another array under the 'params' key for the params
     * @throws dml_exception
     */
    public function get_queries() {
        global $DB;

        $queries = array();

        if (!empty($this->_usercolumns)) {
            // First: Remove all conflicting rows in this table.
            $conflictingrows = $this->get_conflicts();
            if (!empty($conflictingrows)) {
                $whereidsequal = implode(' OR ', array_map(function($row) {
                    return "id=".$row->id;
                }, $conflictingrows));
                $query = new stdClass();
                $query->sql = "DELETE FROM {".$this->_tablename."} WHERE ".$whereidsequal;
                $query->params = null;
                $queries[] = $query;
            }

            $selectusercolumns = array();
            $params = array();
            foreach ($this->_usercolumns as $usercolumn) {
                $selectusercolumns[] = $usercolumn."=?";
                $params[] = $this->_mergeuserid;
            }
            $selectusercolumns = implode(' OR ', $selectusercolumns);

            if (empty($conflictingrows)) {
                $updateablerecords = $DB->record_exists_select($this->_tablename, $selectusercolumns, $params);
            } else {
                // This takes into account that we are deleting some records beforehand.
                $whereidsunequal = implode(' OR ', array_map(function($row) {
                    return "id<>".$row->id;
                }, $conflictingrows));
                $updateablerecords = $DB->record_exists_select($this->_tablename,
                        "(".$whereidsunequal.") AND (".$selectusercolumns.")", $params);
            }

            // Second: If there are non conflicting rows: replace the userfields.
            if ($updateablerecords) {
                foreach ($this->_usercolumns as $usercolumn) {
                    $query = new stdClass();
                    $query->sql = "UPDATE {".$this->_tablename."}
                                          SET ".$usercolumn."=:baseuserid
                                          WHERE ".$usercolumn."=:mergeuserid";
                    $query->params = array('baseuserid' => $this->_baseuserid, 'mergeuserid' => $this->_mergeuserid);
                    $queries[] = $query;
                }
            }
        }

        return $queries;
    }

    /**
     * Gets all the rows that cause a conflict for this table.
     *
     * A conflict is caused when:
     * - You want to change a value of one or multiple columns that holds a reference to user.id
     * - That column is part of a 'conflicting constraint'
     * - There are two rows that hold the same values for all columns in that conflicting constraint (minus the the user column(s))
     *
     * TODO: Look out for columns that get referenced as a FOREIGN key.
     *
     * @return array An array of arrays containing conflict objects. The keys of the first layer are the names of the constraints
     * @throws dml_exception
     */
    private function get_conflicts() {
        global $DB;
        $conflicts = array();

        // Don't get confused as did I. This var can also hold UNIQUE or FOREIGN-UNIQUE keys. But does not include the primary key.
        $indexes = $DB->get_indexes($this->_tablename);

        foreach ($indexes as $indexname => $index) {
            if ($index['unique']) {
                if (!empty(array_intersect($this->_usercolumns, $index['columns']))) {
                    $this->_conflictingconstraints[$indexname] = $index['columns'];
                }
            }
        }

        // We already checked in the calling function if usercolumns is empty.
        if (!empty($this->_conflictingconstraints)) {
            foreach ($this->_conflictingconstraints as $conflict) {
                foreach ($this->power_set($this->_usercolumns) as $columncombination) {
                    $identforsametableone = "a";
                    $identforsametabletwo = "b";

                    // Every column must have the same value on two distinct rows.
                    $samevalues = array_diff($conflict, $columncombination);
                    // Example: a.otherfield1=b.otherfield1 AND a.otherfield2=b.other field2 AND a.otherfield3=a.otherfield3 ...
                    $samevaluesjoin  = " ON " . implode(" AND ",
                                    array_map(function($elem) use ($identforsametableone, $identforsametabletwo) {
                                        return $identforsametableone.".".$elem."=".$identforsametabletwo.".".$elem;
                                    }, $samevalues));

                    $params = array_merge(
                            array_fill(0, count($columncombination), $this->_baseuserid),
                            array_fill(0, count($columncombination), $this->_mergeuserid));

                    if (empty($samevalues)) {
                        /*
                         * This is the case when there are only user columns in this constraint and we search for rows where all
                         * of them are either base user id or merge user id
                         */
                        $samevalues = $columncombination;

                        // We do not need to specify columns for a JOIN because there can only be one row in either JOIN table.
                        $samevaluesjoin = '';

                        // Assertion. There should not ever be two rows that hold the user id for all rows in one table for this
                        // constraint.
                        $sql = "SELECT * FROM {".$this->_tablename."} WHERE";
                        $selector = implode(' AND ', array_map(function($column) {
                            return $column."=?";
                        }, $columncombination));

                        $sql .= '('.$selector.') OR ('.$selector.')';

                        if (count($DB->get_records_sql($sql, $params, 0, 3)) > 2) {
                            throw new Exception('Assertion error. ');
                        }
                    }

                    $samevaluesselect = implode(', ', $samevalues);

                    // Example: userfield1=? AND userfield2=? ...
                    $whereuserid = implode(' AND ', array_map(function($column) {
                        return $column.'=?';
                    }, $columncombination));

                    $sql = "SELECT ".$identforsametableone.".*
                            FROM {".$this->_tablename."} ".$identforsametableone."
                            INNER JOIN (
                                        SELECT ".$samevaluesselect."
                                        FROM {".$this->_tablename."}
                                        WHERE ".$whereuserid."
                                        ) ".$identforsametabletwo.$samevaluesjoin."
                            WHERE ".$identforsametableone.".".$whereuserid.";";

                    $rows = $DB->get_records_sql($sql, $params);
                    if (!empty($rows)) {
                        $conflicts = array_merge($conflicts, $rows);
                    }
                }
            }

            // If there are several constraints there could be rows that cause conflicts on multiple constraints.
            $conflicts = array_unique($conflicts, SORT_REGULAR);
        }

        return $conflicts;
    }

    /**
     * Returns the power set of the passed array (minus the empty set).
     *
     * @param array $array The array you want to obtain the power set from
     * @return array The power set minus the empty set
     * TODO: Is code that was stolen from stackoverflow allowed?
     */
    private function power_set($array) {
        $results = array(array());

        foreach ($array as $element) {
            foreach ($results as $combination) {
                $results[] = array_merge(array($element), $combination);
            }
        }

        unset($results[0]);

        return $results;
    }

    /**
     * This function tries to auto-detect all columns on this table that hold a reference to the user.id column.
     *
     * @throws moodle_exception If the xml file is not readable, not parsable or does not exist
     */
    private function set_user_columns() {
        global $DB;
        // TODO Use an admin setting to add additional usercolumns for third-party plugins?.
        $usercolumnnames = array();
        $tablecolumnnames = array_keys($DB->get_columns($this->_tablename));

        // TODO: Find out which tables the standard values belong to.
        /*
         * Be _very_ conservative to add any values to the 'default' array from below. These column names MUST hold a reference
         * to the user.id column for any table we come across here.
         */
        $usercolumnnames = array_merge(
                array_intersect($tablecolumnnames, array('authorid', 'reviewerid', 'userid', 'user_id', 'id_user', 'user')),
                $usercolumnnames);

        // Search for columns in the xmldb declaration.
        if (!empty($this->_xmlfilepath)) {
            /** @var xmldb_table $table */
            $table = $this->get_table_by_xml($this->_xmlfilepath);
            $tablekeys = $table->getKeys();
            /** @var xmldb_key $tablekey */
            foreach ($tablekeys as $tablekey) {
                if ($tablekey->getRefTable() == 'user') {
                    foreach ($tablekey->getRefFields() as $numerickey => $reffield) {
                        if ($reffield == 'id') {
                            $keyfields = $tablekey->getFields();
                            $usercolumnnames[] = $keyfields[$numerickey];
                        }
                    }
                }
            }
        }

        $this->_usercolumns = array_unique($usercolumnnames);
    }

    /**
     * Returns all tables that are defined in the xml file. Only used to get all columns that hold a reference to user.id .
     *
     * @param string $xmlfilepath Full filepath to a install.xml file
     * @return xmldb_table The table you want to work with
     * @throws moodle_exception If the file is not readable, not parsable or does not exist
     */
    private function get_table_by_xml($xmlfilepath) {
        global $CFG;

        $a = $xmlfilepath;

        if (file_exists($xmlfilepath)) {
            if (is_readable($xmlfilepath)) {
                $xmldbfile = new xmldb_file($xmlfilepath);
                if ($xmldbfile->loadXMLStructure()) {

                    $xmldbstructure = $xmldbfile->getStructure();
                    /** @var xmldb_table $table */
                    foreach ($xmldbstructure->getTables() as $table) {
                        if ($table->getName() == $this->_tablename) {
                            return $table;
                        }
                    }

                    $exceptionreason = 'ddltablenotexist';
                    $a = $this->_tablename;
                } else {
                    $exceptionreason = 'invalidxmlfile';
                }
            } else {
                $exceptionreason = 'filenotreadable';
            }
        } else {
            $exceptionreason = 'filenotfound';
        }

        throw new moodle_exception($exceptionreason, 'merge_process',
                $CFG->wwwroot.'/admin/tool/merge2users/select_base_user.php', $a);
    }
}