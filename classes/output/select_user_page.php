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
 * @package   tool_merge2users
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_merge2users\output;

use context;
use html_table;
use html_writer;
use moodle_url;
use user_filtering;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/user/filters/lib.php');
require_once($CFG->libdir.'/outputcomponents.php');

// TODO Implement a sorting and paging functionality for the table.
// TODO Reset the search after a user has been selected.

/**
 * Abstract class for the select base/merge user pages.
 *
 * This class provides the base functionality to render a search for users and a table
 * to display the users. Most of this is from the admin/user.php file.
 *
 * @package tool_merge2users
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class select_user_page {
    /** @var moodle_url $nextpage The next page to link to in the table */
    private $nextpage;

    /** @var integer $selecteduserid The user id that got selected */
    private $selecteduserid = 0;

    /** @var string $sort After what property should the table be sorted? */
    private $sort;

    /** @var string $dir In what direction should the table be sorted? */
    private $dir;

    /** @var integer $page On which page of the user search table are we on? */
    private $page;

    /** @var integer $perpage How many users should be displayed per page? */
    private $perpage;

    /** @var string $getparamname The name of the get parameter which transfers the id of the base/merge user */
    private $getparamname;

    /** @var string $heading The name of the heading for this particular page. Isn't the same as the 'normal' moodle heading */
    private $heading;

    /** @var context $context The context as seen by the calling php file */
    private $context;

    /**
     * select_user_page constructor.
     *
     * @param moodle_url $nextpage The next page to link to in the table
     * @param integer $selecteduserid The user id that got selected
     * @param string $sort After what property should the table be sorted?
     * @param string $dir In what direction should the table be sorted?
     * @param integer $page On which page of the user search table are we on?
     * @param integer $perpage How many users should be displayed per page?
     * @param string $getparamname The name of the get parameter which transfert the id of the base/merge user
     * @param string $heading The name of the heading for this particular page. Isn't the same as the 'normal' moodle heading
     * @param context $context The context as seen by the calling php file
     */
    public function __construct($nextpage, $selecteduserid, $sort, $dir, $page, $perpage, $getparamname, $heading, $context) {
        $this->nextpage = $nextpage;
        $this->selecteduserid = $selecteduserid;
        $this->sort = $sort;
        $this->dir = $dir;
        $this->page = $page;
        $this->perpage = $perpage;
        $this->getparamname = $getparamname;
        $this->heading = $heading;
        $this->context = $context;
    }

    /**
     * Displays a user search to the page.
     */
    public function display_user_search() {
        $userfilter = new user_filtering();

        echo "<h2>" . $this->heading . "</h2>";
        $userfilter->display_add();
        $userfilter->display_active();
    }

    /**
     * Returns a list of all users pagewise.
     *
     * @return array A list of users to pass to {@see display_select_user_table}
     */
    public function get_users() {
        $userfilter = new user_filtering();
        list($extrasql, $params) = $userfilter->get_sql_filter();
        $users = get_users_listing($this->sort, $this->dir, $this->page * $this->perpage, $this->perpage, '', '', '',
                $extrasql, $params, $this->context);

        return $users;
    }

    /**
     * Given the filtered users, displays the result table.
     *
     * @param array $users The return of {@see render_user_search()}
     */
    public function display_select_user_table($users) {
        $table = new html_table();
        $table->head = array();
        $table->attributes['class'] = 'admintable generaltable table-sm';

        $columns = array('firstname', 'lastname', 'email', 'city', 'lastlogin', 'selctauser');
        foreach ($columns as $column) {
            $table->head[] = get_string($column, 'core');
        }

        foreach ($users as $user) {
            $data = array(
                    $user->firstname,
                    $user->lastname,
                    $user->email,
                    $user->city
            );

            // Calculate time the user was last seen.
            if ($user->lastaccess) {
                $strlastaccess = format_time(time() - $user->lastaccess);
            } else {
                $strlastaccess = get_string('never');
            }
            $data[] = $strlastaccess;

            // This will transfer the id that the user selected.
            $this->nextpage->param($this->getparamname , $user->id);

            // If a user has already been chosen: display it.
            $selectlink = html_writer::link($this->nextpage, get_string('select', 'core'));
            if ($user->id == $this->selecteduserid) {
                $selectlink .= ' ('. html_writer::span(get_string('selected', 'core_bulkusers')).')';
            }
            $data[] = $selectlink;

            $table->data[] = $data;
        }

        echo html_writer::table($table);
    }
}
