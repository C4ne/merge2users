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
 * Contains the eventnotification class.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   tool_merge2users
 */

namespace tool_merge2users\merge\process;

defined('MOODLE_INTERNAL') || die();

use core\output\notification;

/**
 * This class triggers events while displaying corresponding messages at the same time.
 *
 * @copyright 2020, Carsten Schöffel <carsten.schoeffel@cs.hs-fulda.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_merge2users
 */
class eventnotification {

    /** @var \core\base\event $_event The event that will be triggered */
    private $_event;

    /** @var notification $_notification The notification that will be displayed */
    private $_notification;

    /** @var \context_system $context All events are going to be triggered in the same context. */
    public static $context;

    /**
     * Private constructor so we can create these objects with even shorter function calls (see below).
     *
     * @param class $eventtype A class from the tool_merge2users\event namespace
     * @param string $identifier The identifier of the lang string for 'tool_merge2users'
     * @param int $messagetype The messagetype constant defined in \core\output\notification
     * @param string|object|array $a Variables that get passed to get_string
     * @param array $other Additional values you want to insert into the event
     * @throws \coding_exception
     */
    private function __construct($eventtype, $identifier, $messagetype, $a, $other) {
        if (!isset(self::$context)) {
            throw new \coding_exception('Context has not been initialised',
                    'Set the static context variable before calling any function of this class');
        }

        // Initialise event.
        $classname = 'tool_merge2users\\event\\'.$eventtype;
        // TODO Move this check into a PHPUnit test.
        if (class_exists($classname)) {
            // TODO Move this check into a PHPUnit test.
            $reflection = new \ReflectionClass($classname);
            if ($reflection->isSubclassOf('core\event\base')) {
                $this->_event = $classname::create(array('other' => $other, 'context' => self::$context));
            } else {
                throw new \coding_exception($classname.' does not seem to be a subtype of \core\base\event');
            }
        } else {
            throw new \coding_exception('Could not find class '.$classname,
                    'Please provide a correct classname or visit /admin/index.php');
        }

        // Initialise notification.
        $this->_notification = new notification(get_string($identifier, 'tool_merge2users', $a), $messagetype);
        $this->_notification->set_show_closebutton(false);
    }

    /**
     * Triggers the event and displays the notification.
     *
     * @param class $eventtype A class from the tool_merge2users\event namespace
     * @param string $identifier The identifier of the lang string for 'tool_merge2users'
     * @param int $messagetype The messagetype constant defined in \core\output\notification
     * @param string|object|array $a Variables that get passed to get_string
     * @param array $other Additional values you want to insert into the event
     * @throws \coding_exception
     */
    private static function trigger_output($eventtype, $identifier, $messagetype = notification::NOTIFY_SUCCESS,
            $a = null, $other = array()) {
        global $OUTPUT;
        $eventnotification = new eventnotification($eventtype, $identifier, $messagetype, $a, $other);

        $eventnotification->_event->trigger();
        echo $OUTPUT->render($eventnotification->_notification);
    }

    /**
     * Convenience functions. The code if even shorter this way. May resolve these functions later on.
     */
    public static function trigger_transaction_failure() {
        self::trigger_output('usermerge_transaction_failure',
                'event_transaction_failure',
                notification::NOTIFY_ERROR);
    }

    public static function trigger_transaction_success() {
        self::trigger_output('usermerge_transaction_success',
                'event_transaction_success');
    }

    public static function trigger_merge_failure() {
        self::trigger_output('usermerge_merge_failure',
                'event_merge_failure',
                notification::NOTIFY_ERROR);

    }

    public static function trigger_merge_success() {
        self::trigger_output('usermerge_merge_succes',
                'event_merge_success');
    }

    public static function trigger_merge_table_failure($tablename) {
        self::trigger_output('usermerge_merge_table_failure',
                'event_table_merged_failure',
                notification::NOTIFY_ERROR,
                $tablename,
                array('table' => $tablename));
    }

    public static function trigger_merge_table_success($tablename) {
        self::trigger_output('usermerge_merge_table_success',
                'event_table_merged_success',
                notification::NOTIFY_SUCCESS,
                $tablename,
                array('table' => $tablename));
    }

}