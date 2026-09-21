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
 * Playback progress management.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videocheck;

use cm_info;
use coding_exception;
use completion_info;
use core\lock\lock_config;
use dml_exception;
use moodle_exception;
use stdClass;

/**
 * Stores server-authoritative watched ranges and resume position.
 */
class progress_manager {
    /**
     * Gets or creates a progress row.
     *
     * @param int $activityid Activity id.
     * @param int $userid User id.
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_or_create(int $activityid, int $userid): stdClass {
        global $DB;

        $params = ['videocheckid' => $activityid, 'userid' => $userid];
        if ($record = $DB->get_record('videocheck_progress', $params)) {
            return $record;
        }

        $factory = lock_config::get_lock_factory('mod_videocheck');
        $lock = $factory->get_lock('progress:' . $activityid . ':' . $userid, 10);
        if (!$lock) {
            throw new moodle_exception('progresslocktimeout', 'videocheck');
        }
        try {
            if ($record = $DB->get_record('videocheck_progress', $params)) {
                return $record;
            }
            $now = time();
            $record = (object)[
                'videocheckid' => $activityid,
                'userid' => $userid,
                'duration' => 0,
                'lastposition' => 0,
                'uniquewatched' => 0,
                'totalwatchtime' => 0,
                'percent' => 0,
                'watchedsegments' => '[]',
                'lastaccess' => $now,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('videocheck_progress', $record);
            return $record;
        } finally {
            $lock->release();
        }
    }

    /**
     * Records that the student opened the activity.
     *
     * @param int $activityid Activity id.
     * @param int $userid User id.
     * @return stdClass Updated progress record.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function touch_access(int $activityid, int $userid): stdClass {
        global $DB;

        $progress = $this->get_or_create($activityid, $userid);
        $progress->lastaccess = time();
        $DB->set_field('videocheck_progress', 'lastaccess', $progress->lastaccess, ['id' => $progress->id]);
        return $progress;
    }

    /**
     * Merges browser-reported watched ranges after strict shape and length checks.
     *
     * @param stdClass $activity Activity record.
     * @param stdClass $cm Course module.
     * @param int $userid User id.
     * @param float $position Current player position.
     * @param float $duration Current duration.
     * @param string $segmentsjson JSON ranges accumulated since the previous call.
     * @param float $watchtime Actual elapsed playback time represented by the update.
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function update(stdClass $activity, cm_info|stdClass $cm, int $userid, float $position, float $duration,
                           string   $segmentsjson, float $watchtime): array {
        global $DB;

        $duration = max(0.0, min(86400.0, $duration));
        $position = max(0.0, $duration > 0 ? min($duration, $position) : $position);
        $watchtime = max(0.0, min(30.0, $watchtime));
        $incoming = json_decode($segmentsjson, true);
        if (!is_array($incoming)) {
            $incoming = [];
        }

        $clean = [];
        $incomingseconds = 0.0;
        foreach (array_slice($incoming, 0, 20) as $segment) {
            if (!is_array($segment) || count($segment) !== 2) {
                continue;
            }
            $start = max(0.0, (float)$segment[0]);
            $end = max(0.0, (float)$segment[1]);
            if ($duration > 0) {
                $start = min($start, $duration);
                $end = min($end, $duration);
            }
            $length = $end - $start;
            if ($length <= 0 || $length > 15.0) {
                continue;
            }
            $incomingseconds += $length;
            $clean[] = [$start, $end];
        }

        // A single update cannot claim substantially more watched content than playback time.
        if ($incomingseconds > max(4.0, $watchtime * 2.5 + 3.0)) {
            $clean = [];
        }

        $progress = $this->get_or_create((int)$activity->id, $userid);
        $segments = segment_manager::decode($progress->watchedsegments);

        // When seeking is disabled, new ranges must continue from the already watched frontier.
        if (empty($activity->allowseek)) {
            $frontier = segment_manager::contiguous_end($segments);
            $maximum = $frontier + 4.0;
            $restricted = [];
            foreach ($clean as $segment) {
                if ($segment[0] > $maximum) {
                    continue;
                }
                $segment[1] = min($segment[1], $maximum);
                if ($segment[1] > $segment[0]) {
                    $restricted[] = $segment;
                }
            }
            $clean = $restricted;
            if ($position > $maximum) {
                $position = $frontier;
            }
        }

        // Required unanswered checkpoints are authoritative server-side barriers.
        $blockposition = $this->get_blocking_checkpoint_position(
            (int)$activity->id,
            $userid,
            $duration,
            $position
        );
        if ($blockposition !== null) {
            $restricted = [];
            foreach ($clean as $segment) {
                if ($segment[0] >= $blockposition) {
                    continue;
                }
                $segment[1] = min($segment[1], $blockposition);
                if ($segment[1] > $segment[0]) {
                    $restricted[] = $segment;
                }
            }
            $clean = $restricted;
            $position = $blockposition;
        }

        $segments = segment_manager::merge($segments, $clean, $duration);

        $progress->duration = max((float)$progress->duration, $duration);
        $progress->lastposition = $position;
        $progress->uniquewatched = segment_manager::unique_seconds($segments);
        $progress->totalwatchtime = round((float)$progress->totalwatchtime + $watchtime, 3);
        $progress->percent = $progress->duration > 0
            ? round(min(100.0, ($progress->uniquewatched / $progress->duration) * 100.0), 2)
            : 0.0;
        $progress->watchedsegments = segment_manager::encode($segments);
        $progress->lastaccess = time();
        $progress->timemodified = $progress->lastaccess;
        $DB->update_record('videocheck_progress', $progress);

        $this->refresh_completion($activity, $cm, $userid);

        return [
            'percent' => (float)$progress->percent,
            'lastposition' => (float)$progress->lastposition,
            'maxwatched' => segment_manager::contiguous_end($segments),
            'segments' => $progress->watchedsegments,
        ];
    }

    /**
     * Returns the first required unanswered checkpoint crossed by the requested position.
     *
     * @param int $activityid Activity id.
     * @param int $userid User id.
     * @param float $duration Video duration.
     * @param float $position Requested player position.
     * @return float|null Blocking position in seconds, or null when playback may continue.
     * @throws dml_exception
     */
    private function get_blocking_checkpoint_position(
        int $activityid,
        int $userid,
        float $duration,
        float $position
    ): ?float {
        global $DB;

        if ($position <= 0) {
            return null;
        }
        $sql = "SELECT c.id, c.positiontype, c.positionvalue, COALESCE(r.completed, 0) AS completed
                  FROM {videocheck_checkpoints} c
             LEFT JOIN {videocheck_responses} r
                    ON r.checkpointid = c.id AND r.userid = :userid
                 WHERE c.videocheckid = :activityid
                   AND c.required = 1";
        $records = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'activityid' => $activityid,
        ]);

        $blocking = null;
        foreach ($records as $checkpoint) {
            if (!empty($checkpoint->completed)) {
                continue;
            }
            $seconds = $checkpoint->positiontype === 'percent'
                ? ($duration > 0 ? $duration * (float)$checkpoint->positionvalue / 100.0 : null)
                : (float)$checkpoint->positionvalue;
            if ($seconds === null || $position <= $seconds + 0.25) {
                continue;
            }
            if ($blocking === null || $seconds < $blocking) {
                $blocking = max(0.0, $seconds);
            }
        }
        return $blocking;
    }

    /**
     * Re-evaluates Moodle completion after progress or checkpoint changes.
     *
     * @param stdClass $activity Activity record.
     * @param stdClass $cm Course module.
     * @param int $userid User id.
     * @return void
     * @throws dml_exception
     */
    public function refresh_completion(stdClass $activity, cm_info|stdClass $cm, int $userid): void {
        if (empty($activity->completioncheckpoints)) {
            return;
        }
        $completion = new completion_info(get_course($activity->course));
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
    }

    /**
     * Re-evaluates completion for users affected by a checkpoint configuration change.
     *
     * @param stdClass $activity Activity record.
     * @param stdClass $cm Course module.
     * @return void
     * @throws dml_exception
     */
    public function refresh_all_completion(stdClass $activity, cm_info|stdClass $cm): void {
        global $DB;

        if (empty($activity->completioncheckpoints)) {
            return;
        }
        $completion = new completion_info(get_course($activity->course));
        if (!$completion->is_enabled($cm)) {
            return;
        }

        $sql = "SELECT userid FROM {videocheck_progress} WHERE videocheckid = :progressactivity
                UNION
                SELECT r.userid
                  FROM {videocheck_responses} r
                  JOIN {videocheck_checkpoints} c ON c.id = r.checkpointid
                 WHERE c.videocheckid = :responseactivity
                UNION
                SELECT userid FROM {course_modules_completion} WHERE coursemoduleid = :cmid";
        $userids = $DB->get_fieldset_sql($sql, [
            'progressactivity' => $activity->id,
            'responseactivity' => $activity->id,
            'cmid' => $cm->id,
        ]);
        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            if ($userid > 0) {
                $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
            }
        }
    }

    /**
     * Deletes playback and checkpoint response state for one user.
     *
     * @param stdClass $activity Activity record.
     * @param stdClass $cm Course module.
     * @param int $userid User id.
     * @return void
     * @throws dml_exception
     */
    public function reset(stdClass $activity, cm_info|stdClass $cm, int $userid): void {
        global $DB;

        $DB->delete_records_select(
            'videocheck_responses',
            'userid = :userid AND checkpointid IN (
                SELECT id FROM {videocheck_checkpoints} WHERE videocheckid = :activityid
            )',
            ['userid' => $userid, 'activityid' => $activity->id]
        );
        $DB->delete_records('videocheck_progress', [
            'videocheckid' => $activity->id,
            'userid' => $userid,
        ]);
        $this->refresh_completion($activity, $cm, $userid);
    }
}
