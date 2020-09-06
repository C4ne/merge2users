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
 * Command line utility to merge the datasets of two users.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   tool_merge2users
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');

use tool_merge2users\helper;
use tool_merge2users\process\merge_process;

$settingsurl = $CFG->wwwroot.'/admin/settings.php?section=tool_merge2users_settings';
$help = "Command line utility to merge the datasets of two users

This process can not be undone unless you do a whole backup of your database!
Transactions will ensure that the data stays consistent if an error occurs.

Options:
--baseuser=INT          The ID of the user that will stay
--mergeuser=INT         The ID of the user that will go
--confirm-users         You confirm that you want to merge these exact two users
--confirm-settings      You confirm that you want to merge the two users with the current settings (See below for a direct link).
--run                   Run the actual merging process. If this is not set this script will perform a dry run (in the future)
-h, --help              Print out this help

Link to the settings: $settingsurl

Example:
\$sudo -u www-data /usr/bin/php merge_users.php --baseuser=3387 --mergeuser=3341 --confirm-users --confirm-settings --run
";

list($options, $unrecognized) = cli_get_params(
        array(
                'baseuser'          => '',
                'mergeuser'         => '',
                'run'               => false,
                'confirm-users'     => false,
                'confirm-settings'  => false,
                'help'              => false
        ),
        array(
                'h' => 'help'
        )
);

if ($options['help']) {
    echo $help;
    die;
}

if (empty($options['baseuser'])) {
    cli_error(get_string('cli_provide_baseuser', 'tool_merge2users'));
}

if (empty($options['mergeuser'])) {
    cli_error(get_string('cli_provide_mergeuser', 'tool_merge2users'));
}

try {
    $baseuserexists = $DB->record_exists('user', array('id' => $options['baseuser']));
} catch (\dml_exception $ex) {
    cli_error(get_string('cli_baseuser_not_found', 'tool_merge2users'));
}
if (!$baseuserexists) {
    cli_error(get_string('cli_baseuser_not_found', 'tool_merge2users'));
}

try {
    $mergeuserexists = $DB->record_exists('user', array('id' => $options['mergeuser']));
} catch (dml_exception $ex) {
    cli_error(get_string('cli_mergeuser_not_found', 'tool_merge2users'));
}
if (!$mergeuserexists) {
    cli_error(get_string('cli_mergeuser_not_found', 'tool_merge2users'));
}

if (!$options['confirm-users']) {
    cli_error(get_string('cli_confirm_users', 'tool_merge2users'));
}

if (!$options['confirm-settings']) {
    cli_error(get_string('cli_confirm_settings', 'tool_merge2users').': '.$settingsurl);
}

helper::enforce_database_transactions();

// TODO: Change dryrun variable. Its set to true for development purposes.
$process = new merge_process($options['baseuser'], $options['mergeuser'], context_system::instance(), true);
$process->perform();