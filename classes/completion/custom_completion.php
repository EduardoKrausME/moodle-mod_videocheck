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
 * Custom completion rules.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videocheck\completion;

use coding_exception;
use core_completion\activity_custom_completion;
use mod_videocheck\checkpoint_manager;

/**
 * Completes the activity from checkpoint counts.
 */
class custom_completion extends activity_custom_completion {
    /**
     * Resolves custom rule state.
     *
     * @param string $rule Rule id.
     * @return int
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);
        $activity = $DB->get_record('videocheck', ['id' => $this->cm->instance], '*', MUST_EXIST);
        return (new checkpoint_manager())->completion_satisfied($activity, $this->userid)
            ? COMPLETION_COMPLETE
            : COMPLETION_INCOMPLETE;
    }

    /**
     * Defined rules.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completioncheckpoints'];
    }

    /**
     * Rule descriptions.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;

        $activity = $DB->get_record('videocheck', ['id' => $this->cm->instance], '*', MUST_EXIST);
        if ($activity->completionmode === 'minimum') {
            return [
                'completioncheckpoints' => get_string(
                    'completiondetailminimum',
                    'videocheck',
                    (int)$activity->completionminimum
                ),
            ];
        }
        return ['completioncheckpoints' => get_string('completiondetailall', 'videocheck')];
    }

    /**
     * Display order.
     *
     * @return string[]
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completioncheckpoints',
        ];
    }
}
