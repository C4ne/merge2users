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

namespace tool_merge2users\merge\tables;

defined('MOODLE_INTERNAL') || die();

/**
 * A merger that tries to obtain as much data as possible. If a row causes a conflict, it gets removed though.
 *
 * Although it is not quite correct the world 'field' means 'column' in this class.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @copyright Jordi Pujol-Ahulló <jordi.pujol@urv.cat>,  SREd, Universitat Rovira i Virgili
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_merge2users
 */
class generic_table_merger extends table_merger {

    /** @var \xmldb_table The table that this merger should process */
    private $_table;

    /** @var array $_usercolumns An array listing all names of columns that hold a reference to the user.id column */
    private $_usercolumns;

    /**
     * @var array $_conflictingconstraints An array of arrays holding the names of columns that are in a UNIQUE constraint that
     * could potentially cause a conflict.
     *
     * Conflicting constraints are UNIQUE keys, indexes, PRIMARY keys or FOREIGN UNIQUE keys that are composed of at least
     * one column that contains a reference to the user.id column.
     */
    private $_conflictingconstraints;

    /**
     * @var array $_primarykey An array holding all column names of the primary key. We need this because compound keys are
     * still possible, though pretty uncommon.
     */
    private $_primarykey;

    /**
     * generic_table_merger constructor.
     *
     * @param \xmldb_table $table The table that this merger should process
     * @param int $baseuserid A reference to user.id of the base user
     * @param int $mergeuserid A reference to user.id of the user to be merged
     * @param array $usercolumns An array listing all names of columns that hold a reference to the user.id column
     */
    public function __construct($table, $baseuserid, $mergeuserid, $usercolumns = array()) {
        parent::__construct($baseuserid, $mergeuserid);
        $this->_table = $table;
        if (empty($usercolumns)) {
            $this->_usercolumns = $this->get_usercolumns();
        } else {
            $this->_usercolumns = $usercolumns;
        }

        // No work needs to be done if there are no columns that hold a reference to user.id .
        if (!empty($this->_usercolumns)) {
            $this->_conflictingconstraints = $this->get_conflicting_constraints();
            $this->_primarykey = $this->get_primary_key();
        }
    }

    /**
     * Returns and array of sql queries and their parameters. Use this to merge the table of this instance.
     *
     * @return array An array of arrays holding the sql under the 'sql' key and another array under the 'params' key for the params
     * @throws \dml_exception
     */
    public function get_sql_and_parameters() {
        global $DB;

        // See return annotation in the PHPDoc for this method.
        $sql = array();

        if (!empty($this->_usercolumns)) {
            $tablename = $this->_table->getName();

            // First: Remove all conflicts in this table.
            $idstoremove = $this->get_conflicting_rows();
            if (!empty($idstoremove)) {
                // Example: (id1=0 AND id2=3) OR (id1=5 AND id2=4) .
                $deletesqlforallrows = $this->get_selector_for_rows($idstoremove);
                $sql[] = array("sql" => "DELETE FROM {".$tablename."} WHERE ".$deletesqlforallrows,
                        "params" => null);
            }

            // Second: If there are still non conflicting rows: replace the userfields.
            foreach ($this->_usercolumns as $userfield) {
                // Check if any records exist that we could update.
                if (empty($idstoremove)) {
                    $updateablerecords = $DB->record_exists($tablename, array($userfield => $this->mergeuserid));
                } else {
                    // This takes into account that we are deleting some records beforehand.
                    $updateablerecords = $DB->record_exists_select($tablename,
                            $this->get_selector_for_rows($idstoremove, true)." AND ".$userfield."=:mergeuserid",
                            array('mergeuserid' => $this->mergeuserid));
                }

                if ($updateablerecords) {
                    $sql[] = array("sql" => "UPDATE {".$tablename."}
                                              SET ".$userfield."=:baseuserid
                                              WHERE ".$userfield."=:mergeuserid",
                            "params" => array('baseuserid' => $this->baseuserid, 'mergeuserid' => $this->mergeuserid));
                }
            }

        }

        return $sql;
    }

    /**
     * Computes which UNIQUE key, index of PRIMARY key could cause a conflict if we replace a usercolumn.
     *
     * See get_conflicting_rows for an implementation that searches for concrete rows that cause a conflict.
     *
     * @return array See the member variable $_conflictingconstraints for a detailed explanation.
     */
    private function get_conflicting_constraints(): array {
        $conflictingconstraints = array();

        // First: Search through all indexes of the table.
        $indexes = $this->_table->getIndexes();
        foreach ($indexes as $index) {
            // Only unique indexes cause conflicts.
            if ($index->getUnique()) {
                $indexfields = $index->getFields();
                // Check if there is at least one field that contains a user.id AND is in a UNIQUE index.
                if (count(array_intersect($indexfields, $this->_usercolumns)) > 0) {
                    $conflictingconstraints[] = $indexfields;
                }
            }
        }

        // Second: Search through all the keys of the table.
        $keys = $this->_table->getKeys();
        foreach ($keys as $key) {
            $keytype = $key->getType();
            // Only keys with UNIQUE constraints cause conflicts.
            if ($keytype == XMLDB_KEY_PRIMARY || $keytype == XMLDB_KEY_UNIQUE || $keytype == XMLDB_KEY_FOREIGN_UNIQUE) {
                $keyfields = $key->getFields();
                if (count(array_intersect($keyfields, $this->_usercolumns)) > 0) {
                    $conflictingconstraints[] = $keyfields;
                }
            }
        }

        return $conflictingconstraints;
    }

    /**
     * Gets all values of the primary key for a row that causes a conflict for the table of this instance.
     *
     * A conflict is caused when:
     * - You want to change a value of a column that holds a reference to user.id from the merge user id to the base user id
     * - That column is part of a 'conflicting constraint'
     * - There are two rows that hold the same values for all columns in that conflicting constraint (minus one of the user fields)
     *
     * @return array An array of arrays holding the field name and value as a key value pair for each row
     * @throws \dml_exception
     */
    private function get_conflicting_rows() {
        global $DB;
        $tablename = $this->_table->getName();
        $idstoremove = array();

        // First check if there are even any constraints that cause conflicts for this table.
        if (!empty($this->_conflictingconstraints)) {
            foreach ($this->_conflictingconstraints as $conflict) {
                foreach ($this->_usercolumns as $userfieldinquestion) {
                    $fieldswiththesamevalues = array_diff($conflict, array($userfieldinquestion));
                    $identforsametableone = "a";
                    $identforsametabletwo = "b";

                    // Example: ufield1, ufield2, ufield3 .
                    $fieldswiththesamevaluesselect = implode(", ", $fieldswiththesamevalues);

                    // Example: a.ufield1=b.ufield1 AND a.ufield2=b.ufield2 AND a.ufield3=a.ufield3 .
                    $fieldswiththesamevaluesjoin = implode(" AND ",
                            array_map(function($elem) use ($identforsametableone, $identforsametabletwo) {
                                return $identforsametableone.".".$elem."=".$identforsametabletwo.".".$elem;
                            }, $fieldswiththesamevalues));

                    // Example: table.key1, table.key2 .
                    $primarykeysstring = implode(', ', array_map(function ($elem) use($identforsametableone) {
                        return $identforsametableone.".".$elem;
                    }, $this->_primarykey));

                    /*
                     * One table holds all records where $userfieldinquestion is the baseuserid
                     * the other table holds all records where $userfieldinquestion is the mergeuserid.
                     * The query then gets all the rows that have the same value for all $fieldswiththesamevalues
                     */
                    $sql = "SELECT ".$primarykeysstring."
                            FROM {".$tablename."} ".$identforsametableone."
                            INNER JOIN (
                                        SELECT ".$fieldswiththesamevaluesselect."
                                        FROM {".$tablename."}
                                        WHERE ".$userfieldinquestion."=:baseuserid
                                        ) ".$identforsametabletwo." ON ".$fieldswiththesamevaluesjoin."
                            WHERE ".$userfieldinquestion."=:mergeuserid;";

                    $params = array('baseuserid' => $this->baseuserid, 'mergeuserid' => $this->mergeuserid);
                    foreach ($DB->get_records_sql($sql, $params) as $record) {
                        $row = array();
                        foreach ($this->_primarykey as $primarykeyfield) {
                            $row[$primarykeyfield] = $record->$primarykeyfield;
                        }
                        $idstoremove[] = $row;
                    }
                }
            }

            // If there are several constraints there could be rows that cause conflicts on multiple constraints.
            return array_unique($idstoremove);
        } else {
            return $idstoremove;
        }
    }

    /**
     * Calculates which columns contain a reference to user.id and how that column is called.
     *
     * @return array An array containing the name(s) of (a) column(s) that contains a reference to the user.id column
     */
    private function get_usercolumns() {
        // TODO Use an admin setting to add additional usercolumns for third-party plugins.
        $usercolumnnames = array();

        $tablecolumnnames = array_map(function($elem) {
            return $elem->getName();
        }, $this->_table->getFields());

        // TODO: Find out which tables the standard values belong to.
        /*
         * Be _very_ conservative to add any values to the 'default' array from below. These column names MUST hold a reference
         * to the user.id column for any table we come across here.
         */
        $usercolumnnames = array_merge(
                array_intersect($tablecolumnnames, array('authorid', 'reviewerid', 'userid', 'user_id', 'id_user', 'user')),
                $usercolumnnames);

        // Search for keys that hold a reference to the user.id column.
        $tablekeys = $this->_table->getKeys();
        foreach ($tablekeys as $tablekey) {
            if ($tablekey->getRefTable() == 'user') {
                foreach ($tablekey->getRefFields() as $reffield) {
                    if ($reffield == 'id') {
                        $usercolumnnames[] = $tablekey->getName();
                    }
                }
            }
        }

        return $usercolumnnames;
    }

    /**
     * Computes a string to be used in a WHERE clause to select all the rows that are provided through the parameters.
     *
     * @param array $rows An array of arrays holding the field name and value as a key value pair for each row
     * @param bool $invert Pass true if you want to get a selector which excludes all the given rows
     * @return string The rows themselves are OR connected, the columns of the primary key are
     */
    private function get_selector_for_rows($rows, $invert=false) {
        $sqlforallrows = array();
        foreach ($rows as $row) {
            $sqlforrow = array();
            foreach ($row as $primarykeycolumn => $value) {
                if ($invert) {
                    $sqlforrow[] = $primarykeycolumn . "<>" . $value;
                } else {
                    $sqlforrow[] = $primarykeycolumn . "=" . $value;
                }
            }
            $sqlforallrows[] = "(" . implode(' AND ', $sqlforrow) . ")";
        }

        return implode(' OR ', $sqlforallrows);
    }

    /**
     * Gets all column names of the PRIMARY KEY per table.
     *
     * @return array An array holding all column names of the primary key
     */
    private function get_primary_key() {
        $primarykeycolumns = array();

        foreach ($this->_table->getKeys() as $key) {
            if ($key->getType() == XMLDB_KEY_PRIMARY) {
                $primarykeycolumns = $key->getFields();
            }
        }

        return $primarykeycolumns;
    }
}