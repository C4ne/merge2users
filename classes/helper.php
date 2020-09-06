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
 * Contains the helper class.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   tool_merge2users
 */

namespace tool_merge2users;

use coding_exception;
use dml_exception;
use moodle_exception;
use moodle_url;
use ReflectionException;
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
     * Workaround to be able to check whether or not the current database supports transactions.
     *
     * @return bool Wether or not the current database supports transactions
     * @throws ReflectionException
     */
    public static function database_supports_transactions() {
        global $DB;
        $method = new ReflectionMethod($DB, 'transactions_supported');
        $method->setAccessible(true);
        return $method->invoke($DB);
    }

    /**
     * Enforces that the user agrees to the risk of running this without support for transactions.
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function enforce_database_transactions() {
        if (!self::database_supports_transactions()) {
            if (get_config('tool_merge2users', 'merge_without_transaction') === 0) {
                $settingsurl = new moodle_url('/admin/settings.php', array('section' => 'tool_merge2users_settings'));
                if (CLI_SCRIPT) {
                    cli_error(get_string('cli_transactions_unsupported', 'tool_merge2users').': '.$settingsurl->out());
                } else {
                    redirect($settingsurl, get_string('transactions_unsupported', 'tool_merge2users'));
                }
            }
        }
    }
}