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
 * Core callbacks for Video Checkpoints.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videocheck\source_manager;

/**
 * Declares supported Moodle features.
 *
 * @param string $feature Feature constant.
 * @return bool|null
 */
function videocheck_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Creates a Video Checkpoints activity.
 *
 * @param stdClass $data Form data.
 * @param mod_videocheck_mod_form|null $mform Module form.
 * @return int
 */
function videocheck_add_instance(stdClass $data, ?mod_videocheck_mod_form $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $id = $DB->insert_record('videocheck', $data);
    $data->id = $id;
    videocheck_save_files($data, $mform);
    return $id;
}

/**
 * Updates a Video Checkpoints activity.
 *
 * @param stdClass $data Form data.
 * @param mod_videocheck_mod_form|null $mform Module form.
 * @return bool
 */
function videocheck_update_instance(stdClass $data, ?mod_videocheck_mod_form $mform = null): bool {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    $result = $DB->update_record('videocheck', $data);
    videocheck_save_files($data, $mform);
    return $result;
}

/**
 * Saves uploaded video and poster files.
 *
 * @param stdClass $data Form data.
 * @param mod_videocheck_mod_form|null $mform Module form.
 * @return void
 */
function videocheck_save_files(stdClass $data, ?mod_videocheck_mod_form $mform): void {
    if (!$mform) {
        return;
    }
    $context = $mform->get_context();
    $fs = get_file_storage();

    if ($data->videosource === 'upload' && !empty($data->videofile)) {
        file_save_draft_area_files(
            $data->videofile,
            $context->id,
            'mod_videocheck',
            'video',
            0,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['video']]
        );
    } else if ($data->videosource !== 'upload') {
        $fs->delete_area_files($context->id, 'mod_videocheck', 'video');
    }

    if (in_array($data->videosource, ['upload', 'url'], true) && !empty($data->poster)) {
        file_save_draft_area_files(
            $data->poster,
            $context->id,
            'mod_videocheck',
            'poster',
            0,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]
        );
    } else if (in_array($data->videosource, ['youtube', 'vimeo'], true)) {
        $fs->delete_area_files($context->id, 'mod_videocheck', 'poster');
    }
}

/**
 * Deletes an activity and its user data.
 *
 * @param int $id Activity id.
 * @return bool
 */
function videocheck_delete_instance(int $id): bool {
    global $DB;

    $activity = $DB->get_record('videocheck', ['id' => $id]);
    if (!$activity) {
        return false;
    }
    $cm = get_coursemodule_from_instance('videocheck', $id, $activity->course, false, IGNORE_MISSING);
    $context = $cm ? context_module::instance($cm->id) : null;

    $checkpoints = $DB->get_fieldset_select(
        'videocheck_checkpoints',
        'id',
        'videocheckid = :activityid',
        ['activityid' => $id]
    );
    if ($checkpoints) {
        [$insql, $params] = $DB->get_in_or_equal($checkpoints, SQL_PARAMS_NAMED, 'cp');
        $DB->delete_records_select('videocheck_responses', "checkpointid {$insql}", $params);
    }
    $DB->delete_records('videocheck_progress', ['videocheckid' => $id]);
    $DB->delete_records('videocheck_checkpoints', ['videocheckid' => $id]);
    $DB->delete_records('videocheck', ['id' => $id]);

    if ($context) {
        get_file_storage()->delete_area_files($context->id, 'mod_videocheck');
    }
    return true;
}

/**
 * Serves protected uploaded videos and posters.
 *
 * @param stdClass $course Course record.
 * @param stdClass $cm Course module.
 * @param context $context Context.
 * @param string $filearea File area.
 * @param array $args Path arguments.
 * @param bool $forcedownload Download flag.
 * @param array $options Serving options.
 * @return bool
 */
function mod_videocheck_pluginfile($course, $cm, $context, string $filearea, array $args,
                                   bool $forcedownload, array $options = []): bool {
    if ($context->contextlevel !== CONTEXT_MODULE || !in_array($filearea, ['video', 'poster'], true)) {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/videocheck:view', $context);

    $itemid = (int)array_shift($args);
    if ($itemid !== 0) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file(
        $context->id,
        'mod_videocheck',
        $filearea,
        0,
        $filepath,
        $filename
    );
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Returns File API area names.
 *
 * @param stdClass $course Course record.
 * @param stdClass $cm Course module.
 * @param context $context Context.
 * @return array
 */
function videocheck_get_file_areas($course, $cm, $context): array {
    return [
        'video' => get_string('videofile', 'videocheck'),
        'poster' => get_string('poster', 'videocheck'),
    ];
}

/**
 * Adds basic cached course-module information.
 *
 * @param stdClass $coursemodule Course module.
 * @return cached_cm_info|null
 */
function videocheck_get_coursemodule_info($coursemodule) {
    global $DB;

    $activity = $DB->get_record('videocheck', ['id' => $coursemodule->instance], 'id,name,intro,introformat');
    if (!$activity) {
        return null;
    }
    $info = new cached_cm_info();
    $info->name = $activity->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('videocheck', $activity, $coursemodule->id, false);
    }
    return $info;
}
