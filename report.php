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
    $hasprogress = $DB->record_exists('videocheck_progress', [
        'videocheckid' => $activity->id,
        'userid' => $userid,
    ]);
    $hasresponse = $DB->record_exists_sql(
        "SELECT 1
           FROM {videocheck_responses} r
           JOIN {videocheck_checkpoints} c ON c.id = r.checkpointid
          WHERE c.videocheckid = :activityid
            AND r.userid = :userid",
        ['activityid' => $activity->id, 'userid' => $userid]
    );

    if (!is_enrolled($context, $targetuser, 'mod/videocheck:submit', true) && !$hasprogress && !$hasresponse) {
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
$totalcheckpoints = $manager->count_total($activity->id);

$userfields = 'u.id,u.firstname,u.lastname,u.email,u.picture,u.imagealt,u.firstnamephonetic,' .
    'u.lastnamephonetic,u.middlename,u.alternatename';

$users = get_enrolled_users(
    $context,
    'mod/videocheck:submit',
    0,
    $userfields,
    'u.lastname,u.firstname'
);

$trackeduserids = $DB->get_fieldset_sql(
    "SELECT userid
       FROM {videocheck_progress}
      WHERE videocheckid = :progressactivity
      UNION
     SELECT r.userid
       FROM {videocheck_responses} r
       JOIN {videocheck_checkpoints} c ON c.id = r.checkpointid
      WHERE c.videocheckid = :responseactivity",
    [
        'progressactivity' => $activity->id,
        'responseactivity' => $activity->id,
    ]
);

$missinguserids = array_values(array_diff(array_map('intval', $trackeduserids), array_map('intval', array_keys($users))));
if ($missinguserids) {
    [$insql, $inparams] = $DB->get_in_or_equal($missinguserids, SQL_PARAMS_NAMED, 'tracked');
    $historicalusers = $DB->get_records_select(
        'user',
        "id {$insql} AND deleted = 0",
        $inparams,
        'lastname, firstname',
        'id,firstname,lastname,email,picture,imagealt,firstnamephonetic,lastnamephonetic,middlename,alternatename'
    );
    foreach ($historicalusers as $historicaluser) {
        $users[$historicaluser->id] = $historicaluser;
    }
}

uasort($users, static function(stdClass $a, stdClass $b): int {
    return core_collator::compare_strings(fullname($a), fullname($b));
});

$progressrecords = $DB->get_records(
    'videocheck_progress',
    ['videocheckid' => $activity->id],
    '',
    'id,userid,duration,lastposition,uniquewatched,totalwatchtime,percent,lastaccess,timemodified'
);
$progressbyuser = [];
foreach ($progressrecords as $progressrecord) {
    $progressbyuser[(int)$progressrecord->userid] = $progressrecord;
}

$responseaggregates = $DB->get_records_sql(
    "SELECT r.userid,
            SUM(CASE WHEN r.completed = 1 THEN 1 ELSE 0 END) AS completed,
            MAX(r.timemodified) AS lastresponse
       FROM {videocheck_responses} r
       JOIN {videocheck_checkpoints} c ON c.id = r.checkpointid
      WHERE c.videocheckid = :activityid
   GROUP BY r.userid",
    ['activityid' => $activity->id]
);

$participantcount = count($users);
$startedcount = 0;
$completedusers = 0;
$percentsum = 0.0;
$totalwatchtime = 0.0;
$completedcheckpointcount = 0;
$rows = [];

foreach ($users as $user) {
    $progress = $progressbyuser[$user->id] ?? null;
    $aggregate = $responseaggregates[$user->id] ?? null;
    $completed = (int)($aggregate->completed ?? 0);
    $pending = max(0, $totalcheckpoints - $completed);
    $lastresponse = (int)($aggregate->lastresponse ?? 0);
    $lastaccess = max((int)($progress->lastaccess ?? 0), $lastresponse);
    $percent = min(100.0, max(0.0, (float)($progress->percent ?? 0)));
    $watchtime = max(0.0, (float)($progress->totalwatchtime ?? 0));

    $started = $progress !== null || $completed > 0 || $lastresponse > 0;
    if ($started) {
        $startedcount++;
    }

    $complete = false;
    if ($totalcheckpoints > 0) {
        if ($activity->completionmode === 'minimum') {
            $needed = max(1, min($totalcheckpoints, (int)$activity->completionminimum));
            $complete = $completed >= $needed;
        } else {
            $complete = $completed >= $totalcheckpoints;
        }
    }

    if ($complete) {
        $completedusers++;
        $status = get_string('statuscompleted', 'videocheck');
        $statusclass = 'bg-success';
    } else if ($started) {
        $status = get_string('statusinprogress', 'videocheck');
        $statusclass = 'bg-primary';
    } else {
        $status = get_string('statusnotstarted', 'videocheck');
        $statusclass = 'bg-secondary';
    }

    $percentsum += $percent;
    $totalwatchtime += $watchtime;
    $completedcheckpointcount += $completed;

    $detailurl = new moodle_url('/mod/videocheck/report.php', [
        'id' => $cm->id,
        'userid' => $user->id,
    ]);

    $reseturl = '';
    if (has_capability('mod/videocheck:resetprogress', $context) && $started) {
        $reseturl = (new moodle_url('/mod/videocheck/report.php', [
            'id' => $cm->id,
            'userid' => $user->id,
            'action' => 'reset',
            'sesskey' => sesskey(),
        ]))->out(false);
    }

    $rows[] = [
        'userid' => (int)$user->id,
        'avatar' => $OUTPUT->user_picture($user, ['size' => 36, 'link' => false]),
        'fullname' => fullname($user),
        'email' => (string)$user->email,
        'percent' => format_float($percent, 1),
        'progressvalue' => number_format($percent, 2, '.', ''),
        'completed' => $completed,
        'totalcheckpoints' => $totalcheckpoints,
        'pending' => $pending,
        'watchtime' => $watchtime > 0 ? format_time((int)round($watchtime)) : '—',
        'lastaccess' => $lastaccess ? userdate($lastaccess) : get_string('never'),
        'status' => $status,
        'statusclass' => $statusclass,
        'detailurl' => $detailurl->out(false),
        'reseturl' => $reseturl,
        'canreset' => $reseturl !== '',
    ];
}

$averagewatched = $participantcount > 0 ? $percentsum / $participantcount : 0.0;
$checkpointpossible = $participantcount * $totalcheckpoints;
$checkpointpercent = $checkpointpossible > 0
    ? ($completedcheckpointcount / $checkpointpossible) * 100
    : 0.0;

$details = null;
if ($userid) {
    if (!isset($users[$userid])) {
        throw new moodle_exception('invaliduser', 'error');
    }

    $user = $users[$userid];
    $progress = $progressbyuser[$userid] ?? null;
    $detailrows = $DB->get_records_sql(
        "SELECT c.id, c.title, c.positiontype, c.positionvalue, c.checkpointtype,
                r.response, r.completed, r.attempts, r.timecompleted, r.timemodified
           FROM {videocheck_checkpoints} c
      LEFT JOIN {videocheck_responses} r
             ON r.checkpointid = c.id
            AND r.userid = :userid
          WHERE c.videocheckid = :activityid
       ORDER BY c.sortorder, c.id",
        ['userid' => $userid, 'activityid' => $activity->id]
    );

    $checkpointrows = [];
    foreach ($detailrows as $detailrow) {
        if ($detailrow->positiontype === 'percent') {
            $position = format_float((float)$detailrow->positionvalue, 1) . '%';
        } else {
            $position = format_time((int)round((float)$detailrow->positionvalue));
        }

        $checkpointrows[] = [
            'title' => format_string($detailrow->title ?: get_string('untitledcheckpoint', 'videocheck')),
            'position' => $position,
            'type' => get_string('type' . $detailrow->checkpointtype, 'videocheck'),
            'response' => trim((string)($detailrow->response ?? '')) !== ''
                ? (string)$detailrow->response
                : '—',
            'status' => !empty($detailrow->completed)
                ? get_string('statuscompleted', 'videocheck')
                : get_string('statuspending', 'videocheck'),
            'statusclass' => !empty($detailrow->completed) ? 'bg-success' : 'bg-secondary',
            'attempts' => (int)($detailrow->attempts ?? 0),
            'completedat' => !empty($detailrow->timecompleted) ? userdate($detailrow->timecompleted) : '—',
        ];
    }

    $details = [
        'fullname' => fullname($user),
        'avatar' => $OUTPUT->user_picture($user, ['size' => 64, 'link' => false]),
        'email' => (string)$user->email,
        'percent' => format_float((float)($progress->percent ?? 0), 1),
        'watchtime' => !empty($progress->totalwatchtime)
            ? format_time((int)round((float)$progress->totalwatchtime))
            : '—',
        'lastposition' => !empty($progress->lastposition)
            ? format_time((int)round((float)$progress->lastposition))
            : '—',
        'lastaccess' => !empty($progress->lastaccess) ? userdate($progress->lastaccess) : get_string('never'),
        'checkpoints' => array_values($checkpointrows),
        'hascheckpoints' => !empty($checkpointrows),
    ];
}

$template = [
    'backurl' => (new moodle_url('/mod/videocheck/view.php', ['id' => $cm->id]))->out(false),
    'participantcount' => $participantcount,
    'startedcount' => $startedcount,
    'completedusers' => $completedusers,
    'averagewatched' => format_float($averagewatched, 1),
    'totalwatchtime' => $totalwatchtime > 0 ? format_time((int)round($totalwatchtime)) : '—',
    'completedcheckpointcount' => $completedcheckpointcount,
    'checkpointpossible' => $checkpointpossible,
    'checkpointpercent' => format_float($checkpointpercent, 1),
    'rows' => array_values($rows),
    'hasrows' => !empty($rows),
    'details' => $details,
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report', 'videocheck'));
echo $OUTPUT->render_from_template('mod_videocheck/report', $template);
echo $OUTPUT->footer();
