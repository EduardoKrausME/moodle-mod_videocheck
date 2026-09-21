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
 * External functions for Video Checkpoints.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_videocheck_update_progress' => [
        'classname' => 'mod_videocheck\external\update_progress',
        'methodname' => 'execute',
        'description' => 'Stores watched video segments and returns authoritative progress.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/videocheck:submit',
    ],
    'mod_videocheck_submit_checkpoint' => [
        'classname' => 'mod_videocheck\external\submit_checkpoint',
        'methodname' => 'execute',
        'description' => 'Submits a response to a video checkpoint.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/videocheck:submit',
    ],
];
