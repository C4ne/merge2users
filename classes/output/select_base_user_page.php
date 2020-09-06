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
 * Contains the select_base_user_page class.
 *
 * @package   tool_merge2users
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_merge2users\output;

defined('MOODLE_INTERNAL') || die();

use context;
use moodle_exception;
use moodle_url;
use renderable;

/**
 * Represents the page where the base user gets selected.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_merge2users
 */
class select_base_user_page extends select_user_page implements renderable {

    /**
     * select_base_user_page constructor.
     *
     * @param string $sort After what property should the table be sorted?
     * @param string $dir In what direction should the table be sorted?
     * @param integer $page On which page of the user search table are we on?
     * @param integer $perpage How many users should be displayed per page?
     * @param context $context The context as seen by the calling php file
     * @param integer $selecteduserid The user id that got selected
     * @throws moodle_exception
     */
    public function __construct($sort, $dir, $page, $perpage, $context, $selecteduserid) {
        $nextpage  = new moodle_url('/admin/tool/merge2users/select_merge_user.php');
        $getparamname = 'baseuserid';
        $heading = get_string('page_base_user_heading', 'tool_merge2users');
        parent::__construct($nextpage, $selecteduserid, $sort, $dir, $page, $perpage, $getparamname, $heading, $context);
    }
}