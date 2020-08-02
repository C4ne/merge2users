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
 * Displays the confirm_selection form, makes sure baseuser and mergeuser have been set.
 *
 * @package   tool_merge2users
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see \tool_merge2users\forms\confirm_selection_form
 */

use tool_merge2users\forms\confirm_selection_form;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Check access rights.
admin_externalpage_setup('tool_merge2users_confirm_selection');

// Check transaction support.
helper::enforce_database_transactions();

// Check if user ID's have been set.
$baseuserid = optional_param('baseruserid', 0, PARAM_INT);
$mergeuserid = optional_param('mergeuserid', 0, PARAM_INT);

$url = new moodle_url('/admin/tool/merge2users/select_options.php');
$title = get_string('pluginname', 'tool_merge2users');
$cache = cache::make('tool_merge2users', 'pickedusers');

// Set up the page.
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($title);

// Makes sure we have a base user.
if ($baseuserid <= 0 ) {
    $result = $cache->get('baseuserid');
    if ($result === false) {
        redirect($CFG->wwwroot.'/admin/tool/merge2users/select_base_user.php',
                get_string('warning_pick_base_user', 'tool_merge2users'),
                null,
                \core\output\notification::NOTIFY_WARNING);
    } else {
        $baseuserid = $result;
    }
}
// Makes sure we have a merge user and caches its ID.
if ($mergeuserid > 0 ) {
    $result = $cache->set('mergeuserid', $mergeuserid);
} else {
    $result = $cache->get('mergeuserid');
    if ($result === false) {
        redirect($CFG->wwwroot.'/admin/tool/merge2users/select_merge_user.php',
                get_string('warning_pick_merge_user', 'tool_merge2users'),
                null,
                \core\output\notification::NOTIFY_WARNING);
    } else {
        $mergeuserid = $result;
    }
}

// Check if base and merge user are the same.
if ($baseuserid == $mergeuserid) {
    // If this page is accessed directly, both ID are 0.
    if ($baseuserid == 0) {
        redirect($CFG->wwwroot.'/admin/tool/merge2users/select_base_user.php',
                get_string('warning_pick_base_user', 'tool_merge2users'),
                null,
                \core\output\notification::NOTIFY_WARNING);
    } else {
        redirect(new moodle_url('/admin/tool/merge2users/select_base_user.php'),
                get_string('warning_select_two_different_users', 'tool_merge2users'),
                null,
                \core\output\notification::NOTIFY_WARNING);
    }
}

echo $OUTPUT->header();

$mergeusersurl = $CFG->wwwroot.'/admin/tool/merge2users/merge_users.php';
$selectoptionsform = new confirm_selection_form($mergeusersurl, array('baseuserid' => $baseuserid, 'mergeuserid' => $mergeuserid));
$selectoptionsform->display();

echo $OUTPUT->footer();