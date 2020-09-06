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
 * Admin settings for this plugin but gathers settings from other plugins and the system_table_merger too.
 *
 * @package   tool_merge2users
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @copyright 2019 Liip SA <elearning@liip.ch>
 * @author    2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

use tool_merge2users\helper;
use tool_merge2users\merger\system_table_merger;

global $ADMIN;
/** @var admin_root $ADMIN */

// Register a admin category for this plugin.
$ADMIN->add('accounts',
        new admin_category('tool_merge2users_index',
                get_string('pluginname', 'tool_merge2users'),
                true));

// Register 'select base user' page as admin externalpage.
$ADMIN->add('tool_merge2users_index',
        new admin_externalpage('tool_merge2users_select_base_user',
                get_string('page_base_user_heading', 'tool_merge2users'),
                $CFG->wwwroot.'/admin/tool/merge2users/select_base_user.php',
                'tool/merge2users:mergeusers',
                true));

// Register 'select merge user' page as admin externalpage.
$ADMIN->add('tool_merge2users_index',
        new admin_externalpage('tool_merge2users_select_merge_user',
                get_string('page_merge_user_heading', 'tool_merge2users'),
                $CFG->wwwroot.'/admin/tool/merge2users/select_merge_user.php',
                'tool/merge2users:mergeusers',
                true));

// Register 'confirm selection' page as admin externalpage.
$ADMIN->add('tool_merge2users_index',
        new admin_externalpage('tool_merge2users_confirm_selection',
                get_string('page_confirm_selection_heading', 'tool_merge2users'),
                $CFG->wwwroot.'/admin/tool/merge2users/confirm_selection.php',
                'tool/merge2users:mergeusers',
                true));

// Register 'merge user' page as admin externalpage.
$ADMIN->add('tool_merge2users_index',
        new admin_externalpage('tool_merge2users_merge_user',
                get_string('merging_users', 'tool_merge2users'),
                $CFG->wwwroot.'/admin/tool/merge2users/merge_users.php',
                'tool/merge2users:mergeusers',
                true));

// The settingspage for this plugin, holding the settings for other plugins too.
$settings = new admin_settingpage('tool_merge2users_settings',
        get_string('merge_users', 'tool_merge2users'));

// Settings for this plugin.
// Header for this plugin.
$settings->add(new admin_setting_heading('tool_merge2users_heading',
        get_string('generalsettings', 'core_admin'),
        get_string('pluginname', 'tool_merge2users').' '.get_string('options')));

// Disable plugins setting (Currently not in use).
// Builds a 2d array where the first dimension is the type and the second one the pluginname.
$plugintypes = core_component::get_plugin_types();
$pluginsbytype = array();
foreach ($plugintypes as $type => $dir) {
    $pluginsbytype[$type] = array_map(function($plugindir) {
        return basename($plugindir);
    }, core_component::get_plugin_list($type));
}

// TODO: Should all plugins that do not hold user records be disabled by default?
$settings->add(new admin_setting_configmultiselect('tool_merge2users/disable_plugins',
        get_string('disable_merging_for', 'tool_merge2users').':',
        get_string('disable_merging_for_description', 'tool_merge2users'),
        null,
        $pluginsbytype));

// Only add this section if the current database does not support transactions.
if (!helper::database_supports_transactions()) {
    $settings->add(new admin_setting_configcheckbox('tool_merge2users/merge_without_transaction',
            get_string('merge_without_transactions', 'tool_merge2users'),
            get_string('merge_without_transactions_desc', 'tool_merge2users'),
            0));
}

// Gets the settings for merging core tables.
$settings->add(new admin_setting_heading('tool_merge2users/system_settings_heading', get_string('sitesettings'), ''));
$fakesettingpage = new admin_settingpage('tool_merge2users/', 'system_settings');
system_table_merger::deliver_merge_options($fakesettingpage);
foreach ($fakesettingpage->settings as $systemmergesetting) {
    $systemmergesetting->plugin = 'tool_merge2users';
    $settings->add($systemmergesetting);
}

// Gather settings for all other plugins.
// TODO What should we do if the lib.php of a plugin got udpated (the function got removed), but this plugin did not get udpated?
$pluginswithfunction = get_plugins_with_function('deliver_merge_options');
if (!empty($pluginswithfunction)) {
    foreach ($pluginswithfunction as $plugintype => $plugins) {
        foreach ($plugins as $pluginfunction) {
            $plugin = array_search($pluginfunction, $plugins);
            $frankenstein = $plugintype.'_'.$plugin;
            $frankensteinsettings = $frankenstein.' '.get_string('settings');

            // We create a fake settinpage where every plugin can add its options.
            $fakesettingpage = new admin_settingpage('tool_merge2users/', $frankensteinsettings);
            // Get the settings for the current plugin.
            $pluginfunction($fakesettingpage);

            if ($fakesettingpage instanceof admin_settingpage) {
                $settings->add(new admin_setting_heading($frankenstein.'_heading',
                        $frankensteinsettings,
                        get_string('pluginname', $frankenstein).' '.get_string('settings')));
                /** @var admin_setting $pluginmergesetting */
                // TODO: Do this on the outside of this loop or remove the need to pass the settingpage by reference.
                foreach ($fakesettingpage->settings as $pluginmergesetting) {
                    if (substr($pluginmergesetting->get_full_name(), 0, strlen($frankenstein)) === $frankenstein) {
                        throw new coding_exception('Setting has the wrong prefix',
                                'The name of the given setting does not begin with the pluginname');
                    }
                    $pluginmergesetting->plugin = 'tool_merge2users';
                    $settings->add($pluginmergesetting);
                }
            } else {
                debugging(get_string('debug_wrong_usage_of_merge_options', 'tool_merge2users'));
            }
        }
    }
}

// Add the settingpage of this plugin to the admintree.
$ADMIN->add('tools', $settings);