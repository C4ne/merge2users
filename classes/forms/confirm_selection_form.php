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
 * Contains the confirm_selection_form class.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   tool_merge2users
 */

namespace tool_merge2users\forms;

defined('MOODLE_INTERNAL') || die();

use html_writer;
use moodle_exception;
use moodle_url;
use moodleform;

/**
 * Displays a confirmation form for the selected users and the selected settings.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_merge2users
 */
class confirm_selection_form extends moodleform {

    /**
     * Form definition.
     *
     * @throws moodle_exception
     */
    protected function definition() {
        global $DB;
        $mform = $this->_form;

        if (empty($this->_customdata['baseuserid'])) {
            debugging(get_string('debug_no_baseuserid_provided', 'tool_merge2users'));
        }

        if (empty($this->_customdata['mergeuserid'])) {
            debugging(get_string('debug_no_mergeusersid_provided', 'tool_merge2users'));
        }

        // Get information about the two users.
        $baseuser = $DB->get_record('user',
                array('id' => $this->_customdata['baseuserid']),
                'id,firstname,lastname',
                MUST_EXIST);
        $mergeuser = $DB->get_record('user',
                array('id' => $this->_customdata['mergeuserid']),
                'id,firstname,lastname',
                MUST_EXIST);

        // Build the links to the user profiles.
        $baseuserurl = new moodle_url('/user/profile.php', array('id' => $this->_customdata['baseuserid']));
        $mergeuserurl = new moodle_url('/user/profile.php', array('id' => $this->_customdata['mergeuserid']));
        $users = array();
        $users['mergeuser'] = html_writer::link($baseuserurl, $baseuser->firstname.' '.$baseuser->lastname);
        $users['baseuser'] = html_writer::link($mergeuserurl, $mergeuser->firstname.' '.$mergeuser->lastname);

        // Link to the settings page.
        $settingsurl = new moodle_url('/admin/settings.php', array('section' => 'tool_merge2users_settings'));

        $mform->addElement('checkbox', 'settings_confirmed', get_string('confirm_settings', 'tool_merge2users'),
                get_string('acknowledge_settings', 'tool_merge2users', html_writer::link(
                        $settingsurl, get_string('settings'), array('section' => 'tool_merge2users_settings'))));
        $mform->addElement('checkbox', 'users_confirmed', get_string('confirm_users', 'tool_merge2users'),
                get_string('acknowledge_users', 'tool_merge2users', $users));
        $mform->addElement('checkbox', 'perform_dryrun', get_string('perform_dryrun', 'tool_merge2users'),
                get_string('trans_will_rollback', 'tool_merge2users'));

        $mform->addRule('settings_confirmed', null, 'required', null, 'client');
        $mform->addRule('users_confirmed', null, 'required', null, 'client');

        $this->add_action_buttons(false, get_string('start_merging', 'tool_merge2users'));
    }
}