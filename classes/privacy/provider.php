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
 * Privacy provider.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videocheck\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\plugin\provider as request_provider;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation.
 */
class provider implements metadata_provider, request_provider {
    /**
     * Metadata.
     *
     * @param collection $collection Collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('videocheck_progress', [
            'videocheckid' => 'privacy:metadata:progress:videocheckid',
            'userid' => 'privacy:metadata:progress:userid',
            'duration' => 'privacy:metadata:progress:duration',
            'lastposition' => 'privacy:metadata:progress:lastposition',
            'uniquewatched' => 'privacy:metadata:progress:uniquewatched',
            'totalwatchtime' => 'privacy:metadata:progress:totalwatchtime',
            'percent' => 'privacy:metadata:progress:percent',
            'watchedsegments' => 'privacy:metadata:progress:segments',
            'lastaccess' => 'privacy:metadata:progress:lastaccess',
            'timemodified' => 'privacy:metadata:progress:timemodified',
        ], 'privacy:metadata:progress');

        $collection->add_database_table('videocheck_responses', [
            'checkpointid' => 'privacy:metadata:responses:checkpointid',
            'userid' => 'privacy:metadata:responses:userid',
            'response' => 'privacy:metadata:responses:response',
            'completed' => 'privacy:metadata:responses:completed',
            'attempts' => 'privacy:metadata:responses:attempts',
            'timecompleted' => 'privacy:metadata:responses:timecompleted',
        ], 'privacy:metadata:responses');
        return $collection;
    }

    /**
     * Contexts containing user data.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {videocheck} v ON v.id = cm.instance
             LEFT JOIN {videocheck_progress} p ON p.videocheckid = v.id AND p.userid = :userid1
             LEFT JOIN {videocheck_checkpoints} c ON c.videocheckid = v.id
             LEFT JOIN {videocheck_responses} r ON r.checkpointid = c.id AND r.userid = :userid2
                 WHERE p.id IS NOT NULL OR r.id IS NOT NULL";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'videocheck',
            'userid1' => $userid,
            'userid2' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Exports user data.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('videocheck', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $progress = $DB->get_record('videocheck_progress', [
                'videocheckid' => $cm->instance,
                'userid' => $contextlist->get_user()->id,
            ]);
            if ($progress) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:export:progress', 'videocheck')],
                    (object)[
                        'duration' => $progress->duration,
                        'lastposition' => $progress->lastposition,
                        'uniquewatched' => $progress->uniquewatched,
                        'totalwatchtime' => $progress->totalwatchtime,
                        'percent' => $progress->percent,
                        'watchedsegments' => $progress->watchedsegments,
                        'lastaccess' => $progress->lastaccess,
                        'timemodified' => $progress->timemodified,
                    ]
                );
            }

            $sql = "SELECT c.title, r.response, r.completed, r.attempts, r.timecompleted, r.timemodified
                      FROM {videocheck_responses} r
                      JOIN {videocheck_checkpoints} c ON c.id = r.checkpointid
                     WHERE c.videocheckid = :activityid AND r.userid = :userid
                  ORDER BY c.sortorder, c.id";
            $responses = $DB->get_records_sql($sql, [
                'activityid' => $cm->instance,
                'userid' => $contextlist->get_user()->id,
            ]);
            if ($responses) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:export:responses', 'videocheck')],
                    (object)['responses' => array_values($responses)]
                );
            }
        }
    }

    /**
     * Deletes all user data in a context.
     *
     * @param context $context Context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('videocheck', $context->instanceid);
        if (!$cm) {
            return;
        }
        $DB->delete_records('videocheck_progress', ['videocheckid' => $cm->instance]);
        $checkpointids = $DB->get_fieldset_select(
            'videocheck_checkpoints',
            'id',
            'videocheckid = :activityid',
            ['activityid' => $cm->instance]
        );
        if ($checkpointids) {
            [$insql, $params] = $DB->get_in_or_equal($checkpointids, SQL_PARAMS_NAMED, 'cp');
            $DB->delete_records_select('videocheck_responses', "checkpointid {$insql}", $params);
        }
    }

    /**
     * Deletes one user's data in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('videocheck', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $userid = $contextlist->get_user()->id;
            $DB->delete_records('videocheck_progress', [
                'videocheckid' => $cm->instance,
                'userid' => $userid,
            ]);
            $DB->delete_records_select(
                'videocheck_responses',
                'userid = :userid AND checkpointid IN (
                    SELECT id FROM {videocheck_checkpoints} WHERE videocheckid = :activityid
                )',
                ['userid' => $userid, 'activityid' => $cm->instance]
            );
        }
    }
}
