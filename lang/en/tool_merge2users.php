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
 * Strings for tool 'merge2users'
 *
 * @package   tool_merge2users
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Merge two users';
$string['cachedef_pickedusers'] = 'Cache for picked users';
$string['select_users'] = 'Select users';
$string['page_base_user_heading'] = 'Select base user';
$string['page_merge_user_heading'] = 'Select user to be merged';
$string['merging_users'] = 'Merging users';
$string['performing_dryrun'] = 'Performing dry run';
$string['perform_dryrun'] = 'Perform dry run';
$string['trans_will_rollback'] = 'The transaction will rollback automatically';
$string['page_confirm_selection_heading'] = 'Confirm selection';
$string['warning_pick_base_user'] = 'Please pick the base user first';
$string['warning_pick_merge_user'] = 'Please pick the user to be merged first';
$string['warning_merge_user_unset'] = 'You can not select the same user twice. The merge user has been unset';
$string['warning_select_two_different_users'] = 'Please select two different users';
$string['merge_users'] = 'Merge users';
$string['debug_wrong_usage_of_merge_options'] = 'The fakesettingpage does not seem to be an instance of admin_settingpage anymore';
$string['debug_no_baseuserid_provided'] = 'No baseuser id was provided';
$string['debug_no_mergeusersid_provided'] = 'No mergeuser id was provided';
$string['debug_failed_to_create_new_mergeprocess'] = 'Failed to create new merge process';
$string['error_loading_tables_from_xml'] = 'Error while loading tables for plugin {$a}';
$string['start_merging'] = 'Start merging';
$string['acknowledge_settings'] = 'I acknowledge that I want to merge the two users with these {$a}'; // ... these settings.
$string['acknowledge_users'] = 'I acknowledge that I want to merge {$a->baseuser} into {$a->mergeuser}.';
$string['disable_merging_for'] = 'Disable merging process for';
$string['disable_merging_for_description'] = 'Select the plugins which you do not want to merge its records of the user data';
$string['confirm_users'] = 'Confirm users';
$string['confirm_settings'] = 'Confirm settings';
$string['rollback_successful'] = 'The transaction was rolled back successfully';
$string['event_table_merged_success_desc'] = 'Successfully merged table {$a}';
$string['event_table_merged_failure_desc'] = 'Failed to merge table {$a}';
$string['event_merge_failure_desc'] = 'Failed to merge user id {$a->baseuserid} into user id {$a->mergeuserid}';
$string['event_merge_success_desc'] = 'Succeeded to merge user id {$a->baseuserid} into user id {$a->mergeuserid}';
$string['event_transaction_failure_desc'] = 'The transaction could not be commited successfully';
$string['event_transaction_success_desc'] = 'The transaction was commited successfully';
$string['aborting_merge_process'] = 'Aborting merge process';
$string['could_not_retrieve_lock'] = 'Could not retrieve the lock. There is probably another merge process going on right now. Please try again later.';
$string['merge_without_transactions'] = 'Merge without transaction support';
$string['merge_without_transactions_desc'] = 'It seems like your database does not support transactions. It is highly discouraged to enable this option. We expect you to make a database backup at least before proceeding. Enable this setting to force the process without support for transactions';
$string['baseuser_doesnt_exist'] = 'The ID you selected for the base user does not exist, please select another one';
$string['mergeuser_doesnt_exist'] = 'The ID you selected for the user to be merged does not exist, please select another one';
$string['cli_provide_baseuser'] = 'You must provide a base user. Use the -h flag for more information';
$string['cli_provide_mergeuser'] = 'You must provide a merge user. Use the -h flag for more information';
$string['cli_baseuser_not_found'] = 'Base user does not exist. Use the -h flag for more information';
$string['cli_mergeuser_not_found'] = 'Merge user does not exist. Use the -h flag for more information';
$string['cli_confirm_users'] = 'You must confirm that you want to merge these two users. Use the -h flag for more information';
$string['cli_confirm_settings'] = 'You must confirm that you want to merge the users with these settings';
$string['cli_transactions_unsupported'] = 'Your database does not seem to support transactions. Have a look into the settings for more options';
$string['transactions_unsupported'] = 'Your database does not seem to support transactions';
$string['other_tables'] = 'Other tables';
$string['ex_core_queries'] = 'An exception occured while trying to obtain the queries from the {$a->coresystem}';
$string['ex_plugin_queries'] = 'An exception occured while trying to obtain the queries from {$a}';
$string['merge_another'] = 'Merge another user';
$string['perform_merge'] = 'Perform merge';


