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
 * Course activity index.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_course_login($course);

$PAGE->set_url('/mod/videocheck/index.php', ['id' => $course->id]);
$PAGE->set_title(get_string('modulenameplural', 'videocheck'));
$PAGE->set_heading(format_string($course->fullname));

$instances = get_all_instances_in_course('videocheck', $course);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'videocheck'));

if (!$instances) {
    echo $OUTPUT->notification(get_string('noactivities', 'videocheck'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [get_string('name'), get_string('sectionname', 'format_' . $course->format)];
foreach ($instances as $instance) {
    $table->data[] = [
        html_writer::link(new moodle_url('/mod/videocheck/view.php',
            ['id' => $instance->coursemodule]), format_string($instance->name)),
        get_section_name($course, $instance->section),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
