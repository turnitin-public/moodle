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
 * Privacy Subsystem implementation for core_ltix.
 *
 * @package    core_ltix
 * @copyright  2018 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_ltix\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for core_ltix.
 *
 * @copyright  2018 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $items) : collection {
        $items->add_database_table(
            'lti_tool_proxies',
            [
                'name' => 'privacy:metadata:lti_tool_proxies:name',
                'createdby' => 'privacy:metadata:createdby',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:lti_tool_proxies'
        );

        $items->add_database_table(
            'lti_types',
            [
                'name' => 'privacy:metadata:lti_types:name',
                'createdby' => 'privacy:metadata:createdby',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:lti_types'
        );

        return $items;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid The user to search.
     * @return  contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        return new contextlist();
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (is_a($context, \context_module::class)) {
            return;
        }

        // Fetch all LTI types.
        $sql = "SELECT ltit.createdby AS userid
                 FROM {context} c
                 JOIN {course} course
                   ON c.contextlevel = :contextlevel
                  AND c.instanceid = course.id
                 JOIN {lti_types} ltit
                   ON ltit.course = course.id
                WHERE c.id = :contextid";

        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'contextid' => $context->id,
        ];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export personal data for the given approved_contextlist. User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        self::export_user_data_lti_types($contextlist);

        self::export_user_data_lti_tool_proxies($contextlist);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // None of the the data from these tables should be deleted.
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // None of the the data from these tables should be deleted.
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        // None of the the data from these tables should be deleted.
    }

    /**
     * Export personal data for the given approved_contextlist related to LTI tool proxies.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    protected static function export_user_data_lti_tool_proxies(approved_contextlist $contextlist) {
        global $DB;

        // Filter out any contexts that are not related to system context.
        $systemcontexts = array_filter($contextlist->get_contexts(), function($context) {
            return $context->contextlevel == CONTEXT_SYSTEM;
        });

        if (empty($systemcontexts)) {
            return;
        }

        $user = $contextlist->get_user();

        $systemcontext = \context_system::instance();

        $data = [];
        $ltiproxies = $DB->get_recordset('lti_tool_proxies', ['createdby' => $user->id], 'timecreated ASC');
        foreach ($ltiproxies as $ltiproxy) {
            $data[] = [
                'name' => format_string($ltiproxy->name, true, ['context' => $systemcontext]),
                'createdby' => transform::user($ltiproxy->createdby),
                'timecreated' => transform::datetime($ltiproxy->timecreated),
                'timemodified' => transform::datetime($ltiproxy->timemodified),
            ];
        }
        $ltiproxies->close();

        $finaldata = (object) ['lti_tool_proxies' => $data];
        writer::with_context($systemcontext)->export_data([], $finaldata);
    }

    /**
     * Export personal data for the given approved_contextlist related to LTI types.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    protected static function export_user_data_lti_types(approved_contextlist $contextlist) {
        global $DB;

        // Filter out any contexts that are not related to courses.
        $courseids = array_reduce($contextlist->get_contexts(), function($carry, $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                $carry[] = $context->instanceid;
            }
            return $carry;
        }, []);

        if (empty($courseids)) {
            return;
        }

        $user = $contextlist->get_user();

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params = array_merge($inparams, ['userid' => $user->id]);
        $ltitypes = $DB->get_recordset_select('lti_types', "course $insql AND createdby = :userid", $params, 'timecreated ASC');
        self::recordset_loop_and_export($ltitypes, 'course', [], function($carry, $record) {
            $context = \context_course::instance($record->course);
            $options = ['context' => $context];
            $carry[] = [
                'name' => format_string($record->name, true, $options),
                'createdby' => transform::user($record->createdby),
                'timecreated' => transform::datetime($record->timecreated),
                'timemodified' => transform::datetime($record->timemodified),
            ];
            return $carry;
        }, function($courseid, $data) {
            $context = \context_course::instance($courseid);
            $finaldata = (object) ['lti_types' => $data];
            writer::with_context($context)->export_data([], $finaldata);
        });
    }

    /**
     * Loop and export from a recordset.
     *
     * @param \moodle_recordset $recordset The recordset.
     * @param string $splitkey The record key to determine when to export.
     * @param mixed $initial The initial data to reduce from.
     * @param callable $reducer The function to return the dataset, receives current dataset, and the current record.
     * @param callable $export The function to export the dataset, receives the last value from $splitkey and the dataset.
     * @return void
     */
    protected static function recordset_loop_and_export(\moodle_recordset $recordset, $splitkey, $initial,
                                                        callable $reducer, callable $export) {
        $data = $initial;
        $lastid = null;

        foreach ($recordset as $record) {
            if ($lastid && $record->{$splitkey} != $lastid) {
                $export($lastid, $data);
                $data = $initial;
            }
            $data = $reducer($data, $record);
            $lastid = $record->{$splitkey};
        }
        $recordset->close();

        if (!empty($lastid)) {
            $export($lastid, $data);
        }
    }
}
