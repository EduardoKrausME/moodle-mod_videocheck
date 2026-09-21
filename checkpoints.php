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
 * Checkpoint management page.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once("{$CFG->libdir}/formslib.php");

use mod_videocheck\form\checkpoint_form;
use mod_videocheck\progress_manager;

$id = required_param('id', PARAM_INT);
$checkpointid = optional_param('checkpointid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'videocheck');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videocheck:managecheckpoints', $context);
$activity = $DB->get_record('videocheck', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url('/mod/videocheck/checkpoints.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('managecheckpoints', 'videocheck'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($action === 'delete' && $checkpointid && confirm_sesskey()) {
    $checkpoint = $DB->get_record('videocheck_checkpoints', [
        'id' => $checkpointid,
        'videocheckid' => $activity->id,
    ], '*', MUST_EXIST);
    $DB->delete_records('videocheck_responses', ['checkpointid' => $checkpoint->id]);
    $DB->delete_records('videocheck_checkpoints', ['id' => $checkpoint->id]);
    (new progress_manager())->refresh_all_completion($activity, $cm);
    redirect(
        new moodle_url('/mod/videocheck/checkpoints.php', ['id' => $cm->id]),
        get_string('checkpointdeleted', 'videocheck'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$customdata = [];
$form = new checkpoint_form(null, $customdata);
$defaults = (object)[
    'cmid' => $cm->id,
    'checkpointid' => 0,
    'positiontype' => 'percent',
    'positionvalue' => '25',
    'checkpointtype' => 'confirm',
    'title' => '',
    'prompt' => '',
    'options' => '',
    'expectedanswer' => '',
    'required' => 1,
];

if ($checkpointid && $action === 'edit') {
    $checkpoint = $DB->get_record('videocheck_checkpoints', [
        'id' => $checkpointid,
        'videocheckid' => $activity->id,
    ], '*', MUST_EXIST);
    $defaults->checkpointid = $checkpoint->id;
    $defaults->positiontype = $checkpoint->positiontype;
    if ($checkpoint->positiontype === 'time') {
        $seconds = (float)$checkpoint->positionvalue;
        $minutes = floor($seconds / 60);
        $remaining = $seconds - ($minutes * 60);
        $defaults->positionvalue = sprintf('%02d:%05.2f', $minutes, $remaining);
        $defaults->positionvalue = rtrim(rtrim($defaults->positionvalue, '0'), '.');
    } else {
        $defaults->positionvalue = format_float((float)$checkpoint->positionvalue, 2, true, false);
    }
    $defaults->checkpointtype = $checkpoint->checkpointtype;
    $defaults->title = $checkpoint->title;
    $defaults->prompt = $checkpoint->prompt;
    $options = json_decode((string)$checkpoint->options, true);
    $defaults->options = is_array($options) ? implode(PHP_EOL, $options) : '';
    $defaults->expectedanswer = $checkpoint->expectedanswer;
    $defaults->required = $checkpoint->required;
}
$form->set_data($defaults);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/videocheck/checkpoints.php', ['id' => $cm->id]));
} else if ($data = $form->get_data()) {
    $position = checkpoint_form::parse_position($data->positiontype, $data->positionvalue);
    $options = checkpoint_form::parse_options((string)$data->options);
    $now = time();
    $record = (object)[
        'videocheckid' => $activity->id,
        'positiontype' => $data->positiontype,
        'positionvalue' => $position,
        'checkpointtype' => $data->checkpointtype,
        'title' => trim((string)$data->title),
        'prompt' => trim((string)$data->prompt),
        'options' => json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'expectedanswer' => trim((string)$data->expectedanswer),
        'required' => !empty($data->required) ? 1 : 0,
        'timemodified' => $now,
    ];
    if ($data->checkpointid) {
        $existing = $DB->get_record('videocheck_checkpoints', [
            'id' => $data->checkpointid,
            'videocheckid' => $activity->id,
        ], '*', MUST_EXIST);
        $record->id = $existing->id;
        $DB->update_record('videocheck_checkpoints', $record);
        if ($existing->checkpointtype !== $record->checkpointtype
            || (string)$existing->options !== (string)$record->options
            || (string)$existing->expectedanswer !== (string)$record->expectedanswer) {
            $DB->delete_records('videocheck_responses', ['checkpointid' => $existing->id]);
        }
        $message = get_string('checkpointupdated', 'videocheck');
    } else {
        $record->sortorder = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), 0) + 1 FROM {videocheck_checkpoints} WHERE videocheckid = :id',
            ['id' => $activity->id]
        );
        $record->timecreated = $now;
        $DB->insert_record('videocheck_checkpoints', $record);
        $message = get_string('checkpointcreated', 'videocheck');
    }
    (new progress_manager())->refresh_all_completion($activity, $cm);
    redirect(
        new moodle_url('/mod/videocheck/checkpoints.php', ['id' => $cm->id]),
        $message,
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$checkpoints = $DB->get_records('videocheck_checkpoints', ['videocheckid' => $activity->id], 'sortorder, id');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecheckpoints', 'videocheck'));
echo html_writer::div(
    html_writer::link(new moodle_url('/mod/videocheck/view.php', ['id' => $cm->id]), get_string('backtoactivity', 'videocheck')),
    'mb-3'
);

if ($checkpoints) {
    $table = new html_table();
    $table->head = [
        get_string('position', 'videocheck'),
        get_string('checkpointtype', 'videocheck'),
        get_string('checkpointtitle', 'videocheck'),
        get_string('checkpointrequired', 'videocheck'),
        get_string('actions'),
    ];
    foreach ($checkpoints as $checkpoint) {
        $positionlabel = $checkpoint->positiontype === 'percent'
            ? format_float((float)$checkpoint->positionvalue, 2) . '%'
            : gmdate(((float)$checkpoint->positionvalue >= 3600) ? 'H:i:s' : 'i:s', (int)$checkpoint->positionvalue);
        $editurl = new moodle_url('/mod/videocheck/checkpoints.php', [
            'id' => $cm->id,
            'action' => 'edit',
            'checkpointid' => $checkpoint->id,
        ]);
        $deleteurl = new moodle_url('/mod/videocheck/checkpoints.php', [
            'id' => $cm->id,
            'action' => 'delete',
            'checkpointid' => $checkpoint->id,
            'sesskey' => sesskey(),
        ]);
        $table->data[] = [
            $positionlabel,
            get_string('type' . $checkpoint->checkpointtype, 'videocheck'),
            format_string($checkpoint->title ?: get_string('untitledcheckpoint', 'videocheck')),
            $checkpoint->required ? get_string('yes') : get_string('no'),
            html_writer::link($editurl, get_string('edit'))
            . ' · ' . html_writer::link($deleteurl, get_string('delete')),
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nocheckpoints', 'videocheck'), \core\output\notification::NOTIFY_INFO);
}

echo $OUTPUT->heading(
    $checkpointid && $action === 'edit'
        ? get_string('editcheckpoint', 'videocheck')
        : get_string('addcheckpoint', 'videocheck'),
    3
);
$form->display();
echo $OUTPUT->footer();
