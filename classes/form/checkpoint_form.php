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
 * Checkpoint editing form.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videocheck\form;

use moodleform;

/**
 * Add/edit checkpoint form.
 */
class checkpoint_form extends moodleform {
    /**
     * Defines checkpoint fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'checkpointid');
        $mform->setType('checkpointid', PARAM_INT);

        $mform->addElement('select', 'positiontype', get_string('positiontype', 'videocheck'), [
            'percent' => get_string('positionpercent', 'videocheck'),
            'time' => get_string('positiontime', 'videocheck'),
        ]);
        $mform->addElement('text', 'positionvalue', get_string('positionvalue', 'videocheck'), ['size' => 15]);
        $mform->setType('positionvalue', PARAM_TEXT);
        $mform->addHelpButton('positionvalue', 'positionvalue', 'videocheck');
        $mform->addRule('positionvalue', null, 'required', null, 'client');

        $mform->addElement('select', 'checkpointtype', get_string('checkpointtype', 'videocheck'), [
            'confirm' => get_string('typeconfirm', 'videocheck'),
            'question' => get_string('typequestion', 'videocheck'),
            'keyword' => get_string('typekeyword', 'videocheck'),
            'shortanswer' => get_string('typeshortanswer', 'videocheck'),
            'choice' => get_string('typechoice', 'videocheck'),
        ]);

        $mform->addElement('text', 'title', get_string('checkpointtitle', 'videocheck'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addElement('textarea', 'prompt', get_string('checkpointprompt', 'videocheck'), [
            'rows' => 4,
            'cols' => 70,
        ]);
        $mform->setType('prompt', PARAM_TEXT);

        $mform->addElement('textarea', 'options', get_string('checkpointoptions', 'videocheck'), [
            'rows' => 5,
            'cols' => 60,
        ]);
        $mform->setType('options', PARAM_TEXT);
        $mform->addHelpButton('options', 'checkpointoptions', 'videocheck');
        $mform->hideIf('options', 'checkpointtype', 'neq', 'choice');

        $mform->addElement('text', 'expectedanswer', get_string('expectedanswer', 'videocheck'), ['size' => 60]);
        $mform->setType('expectedanswer', PARAM_TEXT);
        $mform->addHelpButton('expectedanswer', 'expectedanswer', 'videocheck');
        $mform->hideIf('expectedanswer', 'checkpointtype', 'eq', 'confirm');

        $mform->addElement('advcheckbox', 'required', get_string('checkpointrequired', 'videocheck'));
        $mform->setDefault('required', 1);

        $this->add_action_buttons();
    }

    /**
     * Validates position and choice options.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $value = self::parse_position((string)($data['positiontype'] ?? ''), (string)($data['positionvalue'] ?? ''));
        if ($value === null) {
            $errors['positionvalue'] = get_string('invalidposition', 'videocheck');
        } else if (($data['positiontype'] ?? '') === 'percent' && ($value <= 0 || $value > 100)) {
            $errors['positionvalue'] = get_string('invalidpercentposition', 'videocheck');
        }
        if (($data['checkpointtype'] ?? '') === 'choice') {
            $options = self::parse_options((string)($data['options'] ?? ''));
            if (count($options) < 2) {
                $errors['options'] = get_string('optionsminimum', 'videocheck');
            }
            $expected = trim((string)($data['expectedanswer'] ?? ''));
            if ($expected !== '' && !in_array($expected, $options, true)) {
                $errors['expectedanswer'] = get_string('expectedchoiceinvalid', 'videocheck');
            }
        }
        return $errors;
    }

    /**
     * Converts a percentage or timecode into a numeric value.
     *
     * @param string $type Position type.
     * @param string $value User input.
     * @return float|null
     */
    public static function parse_position(string $type, string $value): ?float {
        $value = trim($value);
        if ($type === 'percent') {
            return is_numeric($value) ? (float)$value : null;
        }
        if ($type !== 'time' || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return max(0.0, (float)$value);
        }
        $parts = explode(':', $value);
        if (count($parts) < 2 || count($parts) > 3) {
            return null;
        }
        foreach ($parts as $part) {
            if (!preg_match('/^\d+(?:\.\d+)?$/', $part)) {
                return null;
            }
        }
        $parts = array_map('floatval', $parts);
        if (count($parts) === 2) {
            return $parts[0] * 60 + $parts[1];
        }
        return $parts[0] * 3600 + $parts[1] * 60 + $parts[2];
    }

    /**
     * Splits one-option-per-line input.
     *
     * @param string $text Raw options.
     * @return array
     */
    public static function parse_options(string $text): array {
        $lines = preg_split('/\R/', $text);
        $options = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && !in_array($line, $options, true)) {
                $options[] = $line;
            }
        }
        return $options;
    }
}
