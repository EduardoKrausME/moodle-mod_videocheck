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
 * Update progress external function.
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
use mod_videocheck\progress_manager;

/**
 * AJAX endpoint for watched ranges.
 */
class update_progress extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'position' => new external_value(PARAM_FLOAT, 'Current position'),
            'duration' => new external_value(PARAM_FLOAT, 'Video duration'),
            'segments' => new external_value(PARAM_RAW, 'JSON watched ranges'),
            'watchtime' => new external_value(PARAM_FLOAT, 'Elapsed playback time'),
        ]);
    }

    /**
     * Executes progress update.
     *
     * @param int $cmid Course module id.
     * @param float $position Position.
     * @param float $duration Duration.
     * @param string $segments Watched ranges.
     * @param float $watchtime Watch time.
     * @return array
     */
    public static function execute(int $cmid, float $position, float $duration, string $segments, float $watchtime): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'position' => $position,
            'duration' => $duration,
            'segments' => $segments,
            'watchtime' => $watchtime,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'videocheck');
        require_login($course, false, $cm);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/videocheck:submit', $context);

        $activity = $DB->get_record('videocheck', ['id' => $cm->instance], '*', MUST_EXIST);
        return (new progress_manager())->update(
            $activity,
            $cm,
            $USER->id,
            (float)$params['position'],
            (float)$params['duration'],
            (string)$params['segments'],
            (float)$params['watchtime']
        );
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'percent' => new external_value(PARAM_FLOAT, 'Watched percentage'),
            'lastposition' => new external_value(PARAM_FLOAT, 'Resume position'),
            'maxwatched' => new external_value(PARAM_FLOAT, 'Contiguous watched position'),
            'segments' => new external_value(PARAM_RAW, 'Merged ranges'),
        ]);
    }
}
