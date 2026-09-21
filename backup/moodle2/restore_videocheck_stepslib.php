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
 * Restore structure for Video Checkpoints.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Activity structure restore step.
 */
class restore_videocheck_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines paths.
     *
     * @return restore_path_element[]
     */
    protected function define_structure(): array {
        $paths = [
            new restore_path_element('videocheck', '/activity/videocheck'),
            new restore_path_element('videocheck_checkpoint', '/activity/videocheck/checkpoints/checkpoint'),
        ];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element(
                'videocheck_response',
                '/activity/videocheck/checkpoints/checkpoint/responses/response'
            );
            $paths[] = new restore_path_element(
                'videocheck_progress',
                '/activity/videocheck/progresses/progress'
            );
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores main activity.
     *
     * @param array $data Data.
     * @return void
     */
    protected function process_videocheck($data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record('videocheck', $data);
        $this->apply_activity_instance($newid);
        $this->set_mapping('videocheck', $oldid, $newid);
    }

    /**
     * Restores a checkpoint.
     *
     * @param array $data Data.
     * @return void
     */
    protected function process_videocheck_checkpoint($data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->videocheckid = $this->get_new_parentid('videocheck');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record('videocheck_checkpoints', $data);
        $this->set_mapping('videocheck_checkpoint', $oldid, $newid);
    }

    /**
     * Restores a checkpoint response.
     *
     * @param array $data Data.
     * @return void
     */
    protected function process_videocheck_response($data): void {
        global $DB;

        $data = (object)$data;
        $data->checkpointid = $this->get_new_parentid('videocheck_checkpoint');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return;
        }
        $data->timecompleted = $data->timecompleted ? $this->apply_date_offset($data->timecompleted) : 0;
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $DB->insert_record('videocheck_responses', $data);
    }

    /**
     * Restores progress.
     *
     * @param array $data Data.
     * @return void
     */
    protected function process_videocheck_progress($data): void {
        global $DB;

        $data = (object)$data;
        $data->videocheckid = $this->get_new_parentid('videocheck');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return;
        }
        $data->lastaccess = $data->lastaccess ? $this->apply_date_offset($data->lastaccess) : 0;
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $DB->insert_record('videocheck_progress', $data);
    }

    /**
     * Restores files.
     *
     * @return void
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_videocheck', 'video', null);
        $this->add_related_files('mod_videocheck', 'poster', null);
    }
}
