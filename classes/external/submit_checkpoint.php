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
 * Submit checkpoint external function.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videocheck\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_videocheck\checkpoint_manager;
use mod_videocheck\progress_manager;

/**
 * AJAX endpoint for checkpoint responses.
 */
class submit_checkpoint extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'checkpointid' => new external_value(PARAM_INT, 'Checkpoint id'),
            'response' => new external_value(PARAM_RAW_TRIMMED, 'Response'),
        ]);
    }

    /**
     * Executes checkpoint submission.
     *
     * @param int $cmid Course module id.
     * @param int $checkpointid Checkpoint id.
     * @param string $response Response.
     * @return array
     */
    public static function execute(int $cmid, int $checkpointid, string $response): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'checkpointid' => $checkpointid,
            'response' => $response,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'videocheck');
        require_login($course, false, $cm);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/videocheck:submit', $context);

        $activity = $DB->get_record('videocheck', ['id' => $cm->instance], '*', MUST_EXIST);
        $checkpoint = $DB->get_record('videocheck_checkpoints', [
            'id' => $params['checkpointid'],
            'videocheckid' => $activity->id,
        ], '*', MUST_EXIST);

        $manager = new checkpoint_manager();
        $saved = $manager->submit($checkpoint, $USER->id, $params['response']);
        (new progress_manager())->refresh_completion($activity, $cm, $USER->id);

        return [
            'completed' => (bool)$saved->completed,
            'message' => $saved->completed
                ? get_string('checkpointcompleted', 'videocheck')
                : get_string('checkpointincorrect', 'videocheck'),
            'completedcount' => $manager->count_completed($activity->id, $USER->id),
            'totalcount' => $manager->count_total($activity->id),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'completed' => new external_value(PARAM_BOOL, 'Whether checkpoint is complete'),
            'message' => new external_value(PARAM_TEXT, 'Result message'),
            'completedcount' => new external_value(PARAM_INT, 'Completed checkpoints'),
            'totalcount' => new external_value(PARAM_INT, 'Total checkpoints'),
        ]);
    }
}
