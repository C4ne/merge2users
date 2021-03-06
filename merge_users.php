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
 * Starts and displays the merging process.
 *
 * @package   tool_merge2users
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @copyright 1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_merge2users\process\merge_process;
use tool_merge2users\helper;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Check access rights.
admin_externalpage_setup('tool_merge2users_merge_user');

// Check transaction support.
helper::enforce_database_transactions();

// Check if dry run option has been set.
$dryrun = required_param('perform_dryrun', PARAM_BOOL);

$url = new moodle_url('/admin/tool/merge2users/merge_users.php');
$title = get_string('pluginname', 'tool_merge2users');
$cache = cache::make('tool_merge2users', 'pickedusers');
$context = context_system::instance();

// Set up the page.
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->requires->js_init_code("window.scrollTo(0, 5000000);");

// Check if users have been set.
$baseuserid = $cache->get('baseuserid');
if (!$baseuserid || $baseuserid <= 0) {
    redirect($CFG->wwwroot.'/admin/tool/merge2users/select_base_user.php',
    get_string('warning_pick_base_user', 'tool_merge2users'),
    null,
    \core\output\notification::NOTIFY_WARNING);
}
$mergeuserid = $cache->get('mergeuserid');
if (!$mergeuserid || $baseuserid <= 0) {
    redirect($CFG->wwwroot.'/admin/tool/merge2users/select_merge_user.php',
            get_string('warning_pick_merge_user', 'tool_merge2users'),
            null,
            \core\output\notification::NOTIFY_WARNING);
}

// Check if the users are valid.
if (!$DB->record_exists('user', array('id' => $baseuserid))) {
    redirect(new moodle_url('/admin/tool/merge2users/select_base_user.php'),
            get_string('baseuser_doesnt_exist', 'tool_merge2users'),
            null,
            \core\output\notification::NOTIFY_ERROR);
}
if (!$DB->record_exists('user', array('id' => $mergeuserid))) {
    redirect(new moodle_url('/admin/tool/merge2users/select_merge_user.php'),
            get_string('mergeuser_doesnt_exist', 'tool_merge2users'),
            null,
            \core\output\notification::NOTIFY_ERROR);
}

$mergeprocess = new merge_process($baseuserid, $mergeuserid, $context, $dryrun);

echo $OUTPUT->header();

$mergeprocess->perform();
if ($dryrun) {
    $url = new moodle_url('/admin/tool/merge2users/merge_users.php',
            array('baseuserid' => $baseuserid, 'mergeuserid' => $mergeuserid, 'perform_dryrun' => false));
    $button = new single_button($url, get_string('perform_merge', 'tool_merge2users'), 'post', true);
    echo $OUTPUT->render($button);
} else {
    $url = new moodle_url('/admin/tool/merge2users/select_base_user.php');
    $button = new single_button($url, get_string('merge_another', 'tool_merge2users'), 'post', true, array('id' => 'anotheruser'));
    echo $OUTPUT->render($button);
}

echo $OUTPUT->footer();

