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
 * Checkpoint management.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videocheck;

use coding_exception;
use dml_exception;
use stdClass;

/**
 * Reads checkpoints, validates answers, and stores responses.
 */
class checkpoint_manager {
    /**
     * Returns checkpoints for an activity.
     *
     * @param int $activityid Activity id.
     * @return array
     * @throws dml_exception
     */
    public function get_checkpoints(int $activityid): array {
        global $DB;
        return array_values($DB->get_records(
            'videocheck_checkpoints',
            ['videocheckid' => $activityid],
            'sortorder ASC, id ASC'
        ));
    }

    /**
     * Returns browser-safe checkpoint definitions plus user state.
     *
     * Expected answers are intentionally omitted.
     *
     * @param int $activityid Activity id.
     * @param int $userid User id.
     * @return array
     * @throws dml_exception
     */
    public function get_player_checkpoints(int $activityid, int $userid): array {
        global $DB;

        $checkpoints = $this->get_checkpoints($activityid);
        if (!$checkpoints) {
            return [];
        }
        $ids = array_map(static fn(stdClass $checkpoint): int => (int)$checkpoint->id, $checkpoints);
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'cp');
        $params['userid'] = $userid;
        $responses = $DB->get_records_select(
            'videocheck_responses',
            "userid = :userid AND checkpointid {$insql}",
            $params
        );
        $bycheckpoint = [];
        foreach ($responses as $response) {
            $bycheckpoint[(int)$response->checkpointid] = $response;
        }

        $result = [];
        foreach ($checkpoints as $checkpoint) {
            $options = json_decode((string)$checkpoint->options, true);
            $result[] = [
                'id' => (int)$checkpoint->id,
                'positiontype' => (string)$checkpoint->positiontype,
                'positionvalue' => (float)$checkpoint->positionvalue,
                'checkpointtype' => (string)$checkpoint->checkpointtype,
                'title' => format_string((string)$checkpoint->title),
                'prompt' => trim((string)$checkpoint->prompt),
                'options' => is_array($options) ? array_values($options) : [],
                'required' => (bool)$checkpoint->required,
                'completed' => !empty($bycheckpoint[(int)$checkpoint->id]->completed),
                'response' => isset($bycheckpoint[(int)$checkpoint->id])
                    ? (string)$bycheckpoint[(int)$checkpoint->id]->response
                    : '',
            ];
        }
        return $result;
    }

    /**
     * Saves a response and returns the resulting state.
     *
     * @param stdClass $checkpoint Checkpoint record.
     * @param int $userid User id.
     * @param string $response Submitted response.
     * @return stdClass
     * @throws dml_exception
     */
    public function submit(stdClass $checkpoint, int $userid, string $response): stdClass {
        global $DB;

        $response = trim($response);
        $valid = $this->is_valid_response($checkpoint, $response);
        $record = $DB->get_record('videocheck_responses', [
            'checkpointid' => $checkpoint->id,
            'userid' => $userid,
        ]);
        $now = time();
        if (!$record) {
            $record = (object)[
                'checkpointid' => $checkpoint->id,
                'userid' => $userid,
                'response' => '',
                'completed' => 0,
                'attempts' => 0,
                'timecompleted' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('videocheck_responses', $record);
        }
        $record->response = $response;
        $record->attempts = (int)$record->attempts + 1;
        $record->completed = $valid ? 1 : 0;
        $record->timecompleted = $valid ? $now : 0;
        $record->timemodified = $now;
        $DB->update_record('videocheck_responses', $record);
        return $record;
    }

    /**
     * Counts completed checkpoint responses.
     *
     * @param int $activityid Activity id.
     * @param int $userid User id.
     * @return int
     * @throws dml_exception
     */
    public function count_completed(int $activityid, int $userid): int {
        global $DB;
        $sql = "SELECT COUNT(1)
                  FROM {videocheck_responses} r
                  JOIN {videocheck_checkpoints} c ON c.id = r.checkpointid
                 WHERE c.videocheckid = :activityid
                   AND r.userid = :userid
                   AND r.completed = 1";
        return (int)$DB->count_records_sql($sql, ['activityid' => $activityid, 'userid' => $userid]);
    }

    /**
     * Counts total checkpoints.
     *
     * @param int $activityid Activity id.
     * @return int
     * @throws dml_exception
     */
    public function count_total(int $activityid): int {
        global $DB;
        return $DB->count_records('videocheck_checkpoints', ['videocheckid' => $activityid]);
    }

    /**
     * Determines whether configured completion has been satisfied.
     *
     * @param stdClass $activity Activity record.
     * @param int $userid User id.
     * @return bool
     * @throws dml_exception
     */
    public function completion_satisfied(stdClass $activity, int $userid): bool {
        $total = $this->count_total((int)$activity->id);
        if ($total < 1) {
            return false;
        }
        $completed = $this->count_completed((int)$activity->id, $userid);
        if ($activity->completionmode === 'minimum') {
            $needed = max(1, min($total, (int)$activity->completionminimum));
            return $completed >= $needed;
        }
        return $completed >= $total;
    }

    /**
     * Validates a response without exposing the expected answer to the browser.
     *
     * @param stdClass $checkpoint Checkpoint record.
     * @param string $response Submitted response.
     * @return bool
     */
    private function is_valid_response(stdClass $checkpoint, string $response): bool {
        if ($checkpoint->checkpointtype === 'confirm') {
            return $response === '1';
        }
        if ($response === '') {
            return false;
        }
        if ($checkpoint->checkpointtype === 'choice') {
            $options = json_decode((string)$checkpoint->options, true);
            if (!is_array($options) || !in_array($response, $options, true)) {
                return false;
            }
        }
        $expected = trim((string)$checkpoint->expectedanswer);
        if ($expected === '') {
            return true;
        }
        return \core_text::strtolower($response) === \core_text::strtolower($expected);
    }
}
