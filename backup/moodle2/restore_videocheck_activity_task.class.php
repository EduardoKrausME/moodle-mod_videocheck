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
 * Restore task for Video Checkpoints.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Activity restore task.
 */
class restore_videocheck_activity_task extends restore_activity_task {
    /**
     * Defines activity settings.
     *
     * @return void
     */
    protected function define_my_settings(): void {
    }

    /**
     * Defines restore steps.
     *
     * @return void
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_videocheck_activity_structure_step(
            'videocheck_structure',
            'videocheck.xml'
        ));
    }

    /**
     * Defines content decoding rules.
     *
     * @return array
     */
    public static function define_decode_contents(): array {
        return [
            new restore_decode_content('videocheck', ['intro'], 'videocheck'),
        ];
    }

    /**
     * Defines URL decoding rules.
     *
     * @return array
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule('VIDEOCHECKVIEWBYID', '/mod/videocheck/view.php?id=$1', 'course_module'),
        ];
    }

    /**
     * Defines restore log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules(): array {
        return [];
    }
}
