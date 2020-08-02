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
 * Contains the abstract select_user_page class.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   tool_merge2users
 */

namespace tool_merge2users;

use ReflectionMethod;

/**
 * Contains various methods that we will need at multiple places.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_merge2users
 */
class helper {

    /**
     * Workaround to be able to check wether or not the current database supports transactions.
     *
     * @return bool Wether or not the current database supports transactions
     * @throws \ReflectionException
     */
    public static function database_supports_transactions() {
        global $DB;
        $method = new ReflectionMethod($DB, 'transactions_supported');
        $method->setAccessible(true);
        return $method->invoke($DB);
    }

    /**
     * Redirects if transactions not supported and user did not agree to take the risk
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function enforce_database_transactions() {
        if (!self::database_supports_transactions()) {
            if (get_config('tool_merge2users', 'merge_without_transaction') === 0) {
                $settingsurl = new \moodle_url('/admin/settings.php', array('section' => 'tool_merge2users_settings'));
                redirect($settingsurl, 'Your database does not seem to support transactions');
            }
        }
    }

    /**
     * Retrieves all tables of a specific plugin.
     *
     * @param string $xmlfilepath Full filepath to a xml file
     * @return bool|array Array of xmldb_table objects or false if the file doesn't exist
     * @throws \moodle_exception If file is not readable or not parsable
     */
    public static function get_tables_by_xml($xmlfilepath) {
        global $CFG;
        if (file_exists($xmlfilepath)) {
            if (is_readable($xmlfilepath)) {
                $xmldbfile = new \xmldb_file($xmlfilepath);
                if ($xmldbfile->loadXMLStructure()) {
                    $xmldbstructure = $xmldbfile->getStructure();

                    $tables = array();
                    foreach ($xmldbstructure->getTables() as $table) {
                        $tables[$table->getName()] = $table;
                    }
                    return $tables;
                } else {
                    $exceptionreason = 'invalidxmlfile';
                }
            } else {
                $exceptionreason = 'filenotreadable';
            }
        } else {
            return false;
        }

        throw new \moodle_exception($exceptionreason, 'merge_process',
                $CFG->wwwroot.'/admin/tool/merge2users/select_base_user.php', $xmlfilepath);
    }
}