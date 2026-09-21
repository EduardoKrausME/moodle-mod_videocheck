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
 * Backup structure for Video Checkpoints.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Activity structure step.
 */
class backup_videocheck_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value('userinfo');

        $videocheck = new backup_nested_element('videocheck', ['id'], [
            'name', 'intro', 'introformat', 'videosource', 'videourl',
            'resumeplayback', 'allowseek', 'completioncheckpoints',
            'completionmode', 'completionminimum', 'timecreated', 'timemodified',
        ]);

        $checkpoints = new backup_nested_element('checkpoints');
        $checkpoint = new backup_nested_element('checkpoint', ['id'], [
            'positiontype', 'positionvalue', 'checkpointtype', 'title', 'prompt',
            'options', 'expectedanswer', 'required', 'sortorder', 'timecreated', 'timemodified',
        ]);
        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element('response', ['id'], [
            'userid', 'response', 'completed', 'attempts', 'timecompleted', 'timecreated', 'timemodified',
        ]);
        $progresses = new backup_nested_element('progresses');
        $progress = new backup_nested_element('progress', ['id'], [
            'userid', 'duration', 'lastposition', 'uniquewatched', 'totalwatchtime',
            'percent', 'watchedsegments', 'lastaccess', 'timecreated', 'timemodified',
        ]);

        $videocheck->add_child($checkpoints);
        $checkpoints->add_child($checkpoint);
        $checkpoint->add_child($responses);
        $responses->add_child($response);
        $videocheck->add_child($progresses);
        $progresses->add_child($progress);

        $videocheck->set_source_table('videocheck', ['id' => backup::VAR_ACTIVITYID]);
        $checkpoint->set_source_table('videocheck_checkpoints', [
            'videocheckid' => backup::VAR_PARENTID,
        ]);
        if ($userinfo) {
            $response->set_source_table('videocheck_responses', [
                'checkpointid' => backup::VAR_PARENTID,
            ]);
            $progress->set_source_table('videocheck_progress', [
                'videocheckid' => backup::VAR_PARENTID,
            ]);
        }

        $response->annotate_ids('user', 'userid');
        $progress->annotate_ids('user', 'userid');

        $videocheck->annotate_files('mod_videocheck', 'video', null);
        $videocheck->annotate_files('mod_videocheck', 'poster', null);

        return $this->prepare_activity_structure($videocheck);
    }
}
