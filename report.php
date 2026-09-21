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
 * Activity report.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_videocheck\checkpoint_manager;
use mod_videocheck\progress_manager;

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'videocheck');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videocheck:viewreport', $context);
$activity = $DB->get_record('videocheck', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url('/mod/videocheck/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('report', 'videocheck'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($action === 'reset' && $userid && confirm_sesskey()) {
    require_capability('mod/videocheck:resetprogress', $context);
    $targetuser = core_user::get_user($userid, '*', MUST_EXIST);
    if (!is_enrolled($context, $targetuser, 'mod/videocheck:submit', true)) {
        throw new moodle_exception('invaliduser', 'error');
    }
    (new progress_manager())->reset($activity, $cm, $userid);
    redirect(
        new moodle_url('/mod/videocheck/report.php', ['id' => $cm->id]),
        get_string('progressreset', 'videocheck'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$manager = new checkpoint_manager();
$total = $manager->count_total($activity->id);
$users = get_enrolled_users(
    $context,
    'mod/videocheck:submit',
    0,
    'u.id,u.firstname,u.lastname,u.email,u.picture,u.imagealt,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename',
    "u.lastname,u.firstname"
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report', 'videocheck'));
echo html_writer::div(
    html_writer::link(new moodle_url('/mod/videocheck/view.php', ['id' => $cm->id]), get_string('backtoactivity', 'videocheck')),
    'mb-3'
);

$table = new html_table();
$table->head = [
    get_string('student', 'videocheck'),
    get_string('watchedpercent', 'videocheck'),
    get_string('completedcheckpoints', 'videocheck'),
    get_string('pendingcheckpoints', 'videocheck'),
    get_string('lastaccess', 'videocheck'),
    get_string('status', 'videocheck'),
    get_string('actions'),
];

foreach ($users as $user) {
    $progress = $DB->get_record('videocheck_progress', [
        'videocheckid' => $activity->id,
        'userid' => $user->id,
    ]);
    $completed = $manager->count_completed($activity->id, $user->id);
    $pending = max(0, $total - $completed);
    $lastresponse = (int)$DB->get_field_sql(
        "SELECT COALESCE(MAX(r.timemodified), 0)
           FROM {videocheck_responses} r
           JOIN {videocheck_checkpoints} c ON c.id = r.checkpointid
          WHERE c.videocheckid = :activityid AND r.userid = :userid",
        ['activityid' => $activity->id, 'userid' => $user->id]
    );
    $lastaccess = max((int)($progress->lastaccess ?? 0), $lastresponse);
    $complete = $manager->completion_satisfied($activity, $user->id);
    if ($complete) {
        $status = get_string('statuscompleted', 'videocheck');
    } else if ($progress || $completed > 0) {
        $status = get_string('statusinprogress', 'videocheck');
    } else {
        $status = get_string('statusnotstarted', 'videocheck');
    }

    $actions = html_writer::link(
        new moodle_url('/mod/videocheck/report.php', ['id' => $cm->id, 'userid' => $user->id]),
        get_string('details', 'videocheck')
    );
    if (has_capability('mod/videocheck:resetprogress', $context)) {
        $reseturl = new moodle_url('/mod/videocheck/report.php', [
            'id' => $cm->id,
            'userid' => $user->id,
            'action' => 'reset',
            'sesskey' => sesskey(),
        ]);
        $actions .= ' · ' . html_writer::link($reseturl, get_string('resetprogress', 'videocheck'));
    }

    $table->data[] = [
        fullname($user),
        format_float((float)($progress->percent ?? 0), 1) . '%',
        $completed . ' / ' . $total,
        $pending,
        $lastaccess ? userdate($lastaccess) : get_string('never'),
        $status,
        $actions,
    ];
}
echo html_writer::table($table);

if ($userid) {
    if (!isset($users[$userid])) {
        throw new moodle_exception('invaliduser', 'error');
    }
    $user = $users[$userid];
    echo $OUTPUT->heading(get_string('studentdetails', 'videocheck', fullname($user)), 3);
    $sql = "SELECT c.id, c.title, c.positiontype, c.positionvalue, c.checkpointtype,
                   r.response, r.completed, r.attempts, r.timecompleted, r.timemodified
              FROM {videocheck_checkpoints} c
         LEFT JOIN {videocheck_responses} r ON r.checkpointid = c.id AND r.userid = :userid
             WHERE c.videocheckid = :activityid
          ORDER BY c.sortorder, c.id";
    $rows = $DB->get_records_sql($sql, ['userid' => $userid, 'activityid' => $activity->id]);
    $detail = new html_table();
    $detail->head = [
        get_string('checkpoint', 'videocheck'),
        get_string('response', 'videocheck'),
        get_string('status', 'videocheck'),
        get_string('attempts', 'videocheck'),
        get_string('completedat', 'videocheck'),
    ];
    foreach ($rows as $row) {
        $detail->data[] = [
            format_string($row->title ?: get_string('untitledcheckpoint', 'videocheck')),
            s((string)($row->response ?? '')),
            !empty($row->completed) ? get_string('statuscompleted', 'videocheck') : get_string('statuspending', 'videocheck'),
            (int)($row->attempts ?? 0),
            !empty($row->timecompleted) ? userdate($row->timecompleted) : '—',
        ];
    }
    echo html_writer::table($detail);
}

echo $OUTPUT->footer();
