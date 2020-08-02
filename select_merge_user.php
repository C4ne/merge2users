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
 * Builds the 'select merge user' page and handles the selection from the previous page.
 *
 * @package   tool_merge2users
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_merge2users\output\select_merge_user_page;
use tool_merge2users\helper;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Check access rights.
admin_externalpage_setup('tool_merge2users_select_merge_user');

// Check transaction support.
helper::enforce_database_transactions();

// Check if user ID's have been set.
$baseuserid   = optional_param('baseuserid', 0, PARAM_INT);
$mergeuserid  = optional_param('mergeuserid', 0, PARAM_INT);

// Parameters needed to display the table.
$sort         = optional_param('sort', 'firstname', PARAM_ALPHANUM);
$dir          = optional_param('dir', 'ASC', PARAM_ALPHA);
$page         = optional_param('page', 0, PARAM_INT);
$perpage      = optional_param('perpage', 30, PARAM_INT);

$url = new moodle_url('/admin/tool/merge2users/select_merge_user.php');
$title = get_string('pluginname', 'tool_merge2users');
$output = $PAGE->get_renderer('tool_merge2users');
$cache = cache::make('tool_merge2users', 'pickedusers');
$context = context_system::instance();

// Set up the page.
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_cacheable(false); // Disable caches, so the user sees what he has selected if he goes one page back.

// Makes sure we have a base user and caches its ID.
if ($baseuserid > 0 ) {
    $result = $cache->set('baseuserid', $baseuserid);
} else {
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

// Check if there's already a user selected for this page and if it's the same as the baseuser: unset it.
$result = $cache->get('mergeuserid');
if ($result !== false) {
    if ($result === $baseuserid || $mergeuserid === $baseuserid) {
        \core\notification::add(get_string('warning_merge_user_unset', 'tool_merge2users'),
                \core\output\notification::NOTIFY_WARNING);
        $cache->delete('mergeuserid');
    } else {
        $mergeuserid = $result;
    }
}

echo $OUTPUT->header();

$selectmergeuserpage = new select_merge_user_page($sort, $dir, $page, $perpage, $context, $mergeuserid);
$output->display_select_user_page($selectmergeuserpage);

echo $OUTPUT->footer();