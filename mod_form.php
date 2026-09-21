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
 * Activity configuration form.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videocheck\source_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Video Checkpoints module form.
 */
class mod_videocheck_mod_form extends moodleform_mod {
    /**
     * Defines the form.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('videocheckname', 'videocheck'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'videosettings', get_string('videosettings', 'videocheck'));
        $mform->addElement('select', 'videosource', get_string('videosource', 'videocheck'), [
            'upload' => get_string('sourceupload', 'videocheck'),
            'url' => get_string('sourceurl', 'videocheck'),
            'youtube' => get_string('sourceyoutube', 'videocheck'),
            'vimeo' => get_string('sourcevimeo', 'videocheck'),
        ]);
        $mform->setDefault('videosource', 'upload');

        $videooptions = [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['video'],
        ];
        $mform->addElement('filemanager', 'videofile', get_string('videofile', 'videocheck'), null, $videooptions);
        $mform->hideIf('videofile', 'videosource', 'neq', 'upload');

        $mform->addElement('url', 'directurl', get_string('directurl', 'videocheck'),
            ['size' => 80], ['usefilepicker' => false]);
        $mform->setType('directurl', PARAM_URL);
        $mform->hideIf('directurl', 'videosource', 'neq', 'url');

        $mform->addElement('url', 'youtubeurl', get_string('youtubeurl', 'videocheck'),
            ['size' => 80], ['usefilepicker' => false]);
        $mform->setType('youtubeurl', PARAM_URL);
        $mform->hideIf('youtubeurl', 'videosource', 'neq', 'youtube');

        $mform->addElement('url', 'vimeourl', get_string('vimeourl', 'videocheck'),
            ['size' => 80], ['usefilepicker' => false]);
        $mform->setType('vimeourl', PARAM_URL);
        $mform->hideIf('vimeourl', 'videosource', 'neq', 'vimeo');

        $posteroptions = [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['image'],
        ];
        $mform->addElement('filemanager', 'poster', get_string('poster', 'videocheck'), null, $posteroptions);
        $mform->hideIf('poster', 'videosource', 'in', ['youtube', 'vimeo']);

        $mform->addElement('header', 'playbacksettings', get_string('playbacksettings', 'videocheck'));
        $mform->addElement('selectyesno', 'resumeplayback', get_string('resumeplayback', 'videocheck'));
        $mform->setDefault('resumeplayback', 1);
        $mform->addHelpButton('resumeplayback', 'resumeplayback', 'videocheck');

        $mform->addElement('selectyesno', 'allowseek', get_string('allowseek', 'videocheck'));
        $mform->setDefault('allowseek', 1);
        $mform->addHelpButton('allowseek', 'allowseek', 'videocheck');

        $mform->addElement('header', 'checkpointcompletion', get_string('checkpointcompletion', 'videocheck'));
        $mform->addElement('select', 'completionmode', get_string('completionmode', 'videocheck'), [
            'all' => get_string('completionmodeall', 'videocheck'),
            'minimum' => get_string('completionmodeminimum', 'videocheck'),
        ]);
        $mform->setDefault('completionmode', 'all');
        $mform->addElement('text', 'completionminimum', get_string('completionminimum', 'videocheck'), ['size' => 6]);
        $mform->setType('completionminimum', PARAM_INT);
        $mform->setDefault('completionminimum', 1);
        $mform->hideIf('completionminimum', 'completionmode', 'neq', 'minimum');
        $mform->addElement('static', 'checkpointnotice', '', get_string('checkpointnotice', 'videocheck'));

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Adds custom completion rule.
     *
     * @return array
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;
        $mform->addElement(
            'advcheckbox',
            'completioncheckpoints',
            get_string('completioncheckpoints', 'videocheck'),
            get_string('completioncheckpoints_desc', 'videocheck')
        );
        return ['completioncheckpoints'];
    }

    /**
     * Indicates whether custom completion is enabled.
     *
     * @param stdClass $data Form data.
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        if (is_array($data)) {
            return !empty($data['completioncheckpoints']);
        }
        return !empty($data->completioncheckpoints);
    }

    /**
     * Prepares file drafts and source-specific URL fields.
     *
     * @param array $defaultvalues Defaults.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        $source = (string)($defaultvalues['videosource'] ?? 'upload');
        $url = (string)($defaultvalues['videourl'] ?? '');
        if ($source === 'url') {
            $defaultvalues['directurl'] = $url;
        } else if ($source === 'youtube') {
            $defaultvalues['youtubeurl'] = $url;
        } else if ($source === 'vimeo') {
            $defaultvalues['vimeourl'] = $url;
        }

        if (empty($this->current->instance)) {
            return;
        }
        $context = $this->context;

        $videodraftid = file_get_submitted_draft_itemid('videofile');
        file_prepare_draft_area(
            $videodraftid,
            $context->id,
            'mod_videocheck',
            'video',
            0,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['video']]
        );
        $defaultvalues['videofile'] = $videodraftid;

        $posterdraftid = file_get_submitted_draft_itemid('poster');
        file_prepare_draft_area(
            $posterdraftid,
            $context->id,
            'mod_videocheck',
            'poster',
            0,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]
        );
        $defaultvalues['poster'] = $posterdraftid;
    }

    /**
     * Validates source URLs and minimum completion count.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $source = (string)($data['videosource'] ?? 'upload');
        $field = match ($source) {
            'url' => 'directurl',
            'youtube' => 'youtubeurl',
            'vimeo' => 'vimeourl',
            default => '',
        };
        if ($field !== '') {
            $url = trim((string)($data[$field] ?? ''));
            if ($url === '' || !source_manager::is_valid($source, $url)) {
                $errors[$field] = get_string('invalidvideourl', 'videocheck');
            }
        } else if ($source === 'upload') {
            global $USER;
            $draftid = (int)($data['videofile'] ?? 0);
            $files = $draftid > 0
                ? get_file_storage()->get_area_files(
                    context_user::instance($USER->id)->id,
                    'user',
                    'draft',
                    $draftid,
                    'id',
                    false
                )
                : [];
            if (!$files) {
                $errors['videofile'] = get_string('videofilerequired', 'videocheck');
            }
        }
        if (($data['completionmode'] ?? '') === 'minimum' && (int)($data['completionminimum'] ?? 0) < 1) {
            $errors['completionminimum'] = get_string('completionminimumerror', 'videocheck');
        }
        return $errors;
    }

    /**
     * Normalises source-specific URL fields into videourl.
     *
     * @return stdClass|null
     */
    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return $data;
        }
        $data->videourl = match ((string)$data->videosource) {
            'url' => trim((string)($data->directurl ?? '')),
            'youtube' => trim((string)($data->youtubeurl ?? '')),
            'vimeo' => trim((string)($data->vimeourl ?? '')),
            default => '',
        };
        unset($data->directurl, $data->youtubeurl, $data->vimeourl);
        return $data;
    }
}
