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
 * English strings for Video Checkpoints.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['addcheckpoint'] = 'Add checkpoint';
$string['allowseek'] = 'Allow free seeking';
$string['allowseek_help'] = 'When disabled, students can only advance through the continuously watched portion of the video. They may still go backwards.';
$string['attempts'] = 'Attempts';
$string['backtoactivity'] = 'Back to activity';
$string['checkpoint'] = 'Checkpoint';
$string['checkpointcompleted'] = 'Checkpoint completed.';
$string['checkpointcompletion'] = 'Checkpoint completion';
$string['checkpointcreated'] = 'Checkpoint created.';
$string['checkpointdeleted'] = 'Checkpoint deleted.';
$string['checkpointincorrect'] = 'The response does not complete this checkpoint.';
$string['checkpointnotice'] = 'Create and edit checkpoints after saving the activity using “Manage checkpoints”.';
$string['checkpointoptions'] = 'Options';
$string['checkpointoptions_help'] = 'For choice checkpoints, enter one option per line.';
$string['checkpointprompt'] = 'Prompt or question';
$string['checkpointrequired'] = 'Required';
$string['checkpoints'] = 'Checkpoints';
$string['checkpointtitle'] = 'Title';
$string['checkpointtype'] = 'Checkpoint type';
$string['checkpointupdated'] = 'Checkpoint updated.';
$string['chooseoption'] = 'Choose an option';
$string['completedat'] = 'Completed at';
$string['completedcheckpoints'] = 'Completed checkpoints';
$string['completioncheckpoints'] = 'Require checkpoint completion';
$string['completioncheckpoints_desc'] = 'Complete the activity when the configured checkpoint requirement is satisfied.';
$string['completiondetailall'] = 'Complete all video checkpoints';
$string['completiondetailminimum'] = 'Complete at least {$a} video checkpoints';
$string['completionminimum'] = 'Minimum checkpoints';
$string['completionminimumerror'] = 'The minimum must be at least 1.';
$string['completionmode'] = 'Checkpoint requirement';
$string['completionmodeall'] = 'Complete all checkpoints';
$string['completionmodeminimum'] = 'Complete a minimum number of checkpoints';
$string['confirmcheckpoint'] = 'Confirm';
$string['details'] = 'Details';
$string['directurl'] = 'Direct video URL';
$string['editcheckpoint'] = 'Edit checkpoint';
$string['expectedanswer'] = 'Expected answer';
$string['expectedanswer_help'] = 'Optional. Leave empty to accept any non-empty response. For choice checkpoints, type one of the options exactly.';
$string['expectedchoiceinvalid'] = 'The expected answer must exactly match one of the options.';
$string['invalidpercentposition'] = 'The percentage must be greater than 0 and no greater than 100.';
$string['invalidposition'] = 'Enter a valid percentage or time.';
$string['invalidvideosource'] = 'The selected video source is not supported.';
$string['invalidvideourl'] = 'Enter a valid URL for the selected video source.';
$string['invalidvimeourl'] = 'The Vimeo URL is not valid or the video id could not be identified.';
$string['invalidyoutubeurl'] = 'The YouTube URL is not valid or the video id could not be identified.';
$string['lastaccess'] = 'Last access';
$string['managecheckpoints'] = 'Manage checkpoints';
$string['modulename'] = 'Video Checkpoints';
$string['modulenameplural'] = 'Video Checkpoints';
$string['noactivities'] = 'There are no Video Checkpoints activities in this course.';
$string['nocheckpoints'] = 'No checkpoints have been created yet.';
$string['of'] = 'of';
$string['optionsminimum'] = 'Enter at least two options.';
$string['pendingcheckpoints'] = 'Pending checkpoints';
$string['playbacksettings'] = 'Playback';
$string['pluginadministration'] = 'Video Checkpoints administration';
$string['pluginname'] = 'Video Checkpoints';
$string['position'] = 'Position';
$string['positionpercent'] = 'Percentage of the video';
$string['positiontime'] = 'Specific time';
$string['positiontype'] = 'Checkpoint position';
$string['positionvalue'] = 'Position';
$string['positionvalue_help'] = 'For percentage, enter a value from 0 to 100. For time, enter seconds or a time such as 03:00, 08:30, or 01:15:40.';
$string['poster'] = 'Poster image';
$string['previewmode'] = 'Preview mode: progress and checkpoint responses are not stored.';
$string['privacy:export:progress'] = 'Playback progress';
$string['privacy:export:responses'] = 'Checkpoint responses';
$string['privacy:metadata:progress'] = 'Stores the student’s playback progress for a Video Checkpoints activity.';
$string['privacy:metadata:progress:duration'] = 'The detected video duration.';
$string['privacy:metadata:progress:lastaccess'] = 'Last access time recorded for the activity.';
$string['privacy:metadata:progress:lastposition'] = 'The last playback position.';
$string['privacy:metadata:progress:percent'] = 'The percentage of unique video content watched.';
$string['privacy:metadata:progress:segments'] = 'The watched video ranges.';
$string['privacy:metadata:progress:timemodified'] = 'The last time the progress record was updated.';
$string['privacy:metadata:progress:totalwatchtime'] = 'The accumulated playback time.';
$string['privacy:metadata:progress:uniquewatched'] = 'The number of unique watched seconds.';
$string['privacy:metadata:progress:userid'] = 'The user id.';
$string['privacy:metadata:progress:videocheckid'] = 'The Video Checkpoints activity id.';
$string['privacy:metadata:responses'] = 'Stores responses submitted to video checkpoints.';
$string['privacy:metadata:responses:attempts'] = 'The number of submitted attempts.';
$string['privacy:metadata:responses:checkpointid'] = 'The checkpoint id.';
$string['privacy:metadata:responses:completed'] = 'Whether the response completed the checkpoint.';
$string['privacy:metadata:responses:response'] = 'The response entered or selected by the user.';
$string['privacy:metadata:responses:timecompleted'] = 'The time the checkpoint was completed.';
$string['privacy:metadata:responses:userid'] = 'The user id.';
$string['progresslocktimeout'] = 'The progress record is temporarily busy. Try again.';
$string['progressreset'] = 'The student progress was reset.';
$string['report'] = 'Video Checkpoints report';
$string['resetprogress'] = 'Reset progress';
$string['response'] = 'Response';
$string['responserequired'] = 'Enter or select a response before continuing.';
$string['resumeplayback'] = 'Resume from the last position';
$string['resumeplayback_help'] = 'When enabled, the student returns to the last stored playback position.';
$string['skipcheckpoint'] = 'Skip';
$string['sourceupload'] = 'Uploaded video';
$string['sourceurl'] = 'Direct video URL';
$string['sourcevimeo'] = 'Vimeo';
$string['sourceyoutube'] = 'YouTube';
$string['status'] = 'Status';
$string['statuscompleted'] = 'Completed';
$string['statusinprogress'] = 'In progress';
$string['statusnotstarted'] = 'Not started';
$string['statuspending'] = 'Pending';
$string['student'] = 'Student';
$string['studentdetails'] = 'Checkpoint details: {$a}';
$string['submitcheckpoint'] = 'Submit';
$string['typechoice'] = 'Choice between options';
$string['typeconfirm'] = 'Confirmation only';
$string['typekeyword'] = 'Keyword';
$string['typequestion'] = 'Simple question';
$string['typeshortanswer'] = 'Short answer';
$string['untitledcheckpoint'] = 'Untitled checkpoint';
$string['videocheck:addinstance'] = 'Add a new Video Checkpoints activity';
$string['videocheck:managecheckpoints'] = 'Manage Video Checkpoints checkpoints';
$string['videocheck:resetprogress'] = 'Reset Video Checkpoints progress';
$string['videocheck:submit'] = 'Submit Video Checkpoints responses';
$string['videocheck:view'] = 'View Video Checkpoints';
$string['videocheck:viewreport'] = 'View Video Checkpoints report';
$string['videocheckname'] = 'Activity name';
$string['videofile'] = 'Video file';
$string['videofilerequired'] = 'A video file is required when the source is Upload.';
$string['videonotconfigured'] = 'No video is configured for this activity.';
$string['videonotsupported'] = 'Your browser cannot play this video.';
$string['videoplayer'] = 'Video player';
$string['videosettings'] = 'Video';
$string['videosource'] = 'Video source';
$string['viewreport'] = 'View report';
$string['vimeourl'] = 'Vimeo URL';
$string['watched'] = 'Watched';
$string['watchedpercent'] = 'Watched percentage';
$string['yourresponse'] = 'Your response';
$string['youtubeurl'] = 'YouTube URL';
