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
 * Student activity view.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_videocheck\checkpoint_manager;
use mod_videocheck\progress_manager;
use mod_videocheck\segment_manager;
use mod_videocheck\source_manager;

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'videocheck');
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/videocheck:view', $context);
$activity = $DB->get_record('videocheck', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url('/mod/videocheck/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$event = \mod_videocheck\event\course_module_viewed::create([
    'objectid' => $activity->id,
    'context' => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('videocheck', $activity);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$cantrack = has_capability('mod/videocheck:submit', $context);
$checkpointmanager = new checkpoint_manager();
if ($cantrack) {
    $progress = (new progress_manager())->touch_access($activity->id, $USER->id);
    $checkpoints = $checkpointmanager->get_player_checkpoints($activity->id, $USER->id);
} else {
    $progress = (object)[
        'percent' => 0,
        'lastposition' => 0,
        'watchedsegments' => '[]',
    ];
    $checkpoints = $checkpointmanager->get_player_checkpoints($activity->id, 0);
}
$totalcheckpoints = count($checkpoints);
$completedcheckpoints = count(array_filter($checkpoints, static fn(array $checkpoint): bool => $checkpoint['completed']));

$source = source_manager::parse($activity->videosource, (string)$activity->videourl);
$videourl = '';
$posterurl = '';
if ($activity->videosource === 'upload') {
    $files = get_file_storage()->get_area_files(
        $context->id,
        'mod_videocheck',
        'video',
        0,
        'itemid, filepath, filename',
        false
    );
    if ($files) {
        $file = reset($files);
        $videourl = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_videocheck',
            'video',
            0,
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }
} else if ($activity->videosource === 'url') {
    $videourl = $source['url'];
}

$posters = get_file_storage()->get_area_files(
    $context->id,
    'mod_videocheck',
    'poster',
    0,
    'itemid, filepath, filename',
    false
);
if ($posters) {
    $poster = reset($posters);
    $posterurl = moodle_url::make_pluginfile_url(
        $context->id,
        'mod_videocheck',
        'poster',
        0,
        $poster->get_filepath(),
        $poster->get_filename()
    )->out(false);
}

if (in_array($activity->videosource, ['upload', 'url'], true) && $videourl === '') {
    throw new moodle_exception('videonotconfigured', 'videocheck');
}

$playerid = 'videocheck-media-' . $cm->id;
$rootid = 'videocheck-root-' . $cm->id;
$template = [
    'rootid' => $rootid,
    'playerid' => $playerid,
    'ishtml5' => in_array($activity->videosource, ['upload', 'url'], true),
    'isyoutube' => $activity->videosource === 'youtube',
    'isvimeo' => $activity->videosource === 'vimeo',
    'videourl' => $videourl,
    'posterurl' => $posterurl,
    'youtubeid' => $source['id'] ?? '',
    'vimeoembed' => $activity->videosource === 'vimeo'
        ? 'https://player.vimeo.com/video/' . $source['id']
        . (!empty($source['hash']) ? '?h=' . rawurlencode($source['hash']) . '&dnt=1' : '?dnt=1')
        : '',
    'percent' => format_float((float)$progress->percent, 1),
    'completedcount' => $completedcheckpoints,
    'totalcount' => $totalcheckpoints,
    'hascheckpoints' => $totalcheckpoints > 0,
    'cantrack' => $cantrack,
];

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($activity->name));
if (trim((string)$activity->intro) !== '') {
    echo $OUTPUT->box(format_module_intro('videocheck', $activity, $cm->id), 'generalbox mod_introbox', 'videocheckintro');
}
if (has_capability('mod/videocheck:managecheckpoints', $context) ||
    has_capability('mod/videocheck:viewreport', $context)) {
    $buttons = [];
    if (has_capability('mod/videocheck:managecheckpoints', $context)) {
        $buttons[] = html_writer::link(
            new moodle_url('/mod/videocheck/checkpoints.php', ['id' => $cm->id]),
            get_string('managecheckpoints', 'videocheck'),
            ['class' => 'btn btn-secondary']
        );
    }
    if (has_capability('mod/videocheck:viewreport', $context)) {
        $buttons[] = html_writer::link(
            new moodle_url('/mod/videocheck/report.php', ['id' => $cm->id]),
            get_string('viewreport', 'videocheck'),
            ['class' => 'btn btn-secondary']
        );
    }
    echo html_writer::div(implode(' ', $buttons), 'mb-3');
}
echo $OUTPUT->render_from_template('mod_videocheck/player', $template);

$PAGE->requires->js_call_amd('mod_videocheck/player', 'init', [[
    'cmid' => (int)$cm->id,
    'rootid' => $rootid,
    'playerid' => $playerid,
    'source' => (string)$activity->videosource,
    'youtubeid' => (string)($source['id'] ?? ''),
    'resumeplayback' => (bool)$activity->resumeplayback,
    'allowseek' => (bool)$activity->allowseek,
    'cantrack' => $cantrack,
    'lastposition' => (float)$progress->lastposition,
    'percent' => (float)$progress->percent,
    'maxwatched' => segment_manager::contiguous_end(segment_manager::decode((string)$progress->watchedsegments)),
    'checkpoints' => $checkpoints,
    'strings' => [
        'confirm' => get_string('confirmcheckpoint', 'videocheck'),
        'submit' => get_string('submitcheckpoint', 'videocheck'),
        'skip' => get_string('skipcheckpoint', 'videocheck'),
        'responseRequired' => get_string('responserequired', 'videocheck'),
        'preview' => get_string('previewmode', 'videocheck'),
    ],
]]);

echo $OUTPUT->footer();
