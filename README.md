# Video Checkpoints (mod_videocheck)

Video Checkpoints is a deliberately simple Moodle activity for placing mandatory or optional checkpoints inside a video.

Teachers can use an uploaded video, a direct video URL, YouTube, or Vimeo. Checkpoints may be positioned by percentage
or by exact time and can request confirmation, a simple answer, a keyword, a short answer, or a choice between options.

The activity tracks unique watched segments, total playback time, last position, resume position, checkpoint completion,
and the student's latest access. Seeking can be unrestricted or limited to already watched content.

Completion can require every checkpoint or a configurable minimum number of completed checkpoints.

The report shows, per student, watched percentage, completed and pending checkpoints, last access, and activity status.

## Requirements

- Moodle 4.4 or later.
- PHP supported by the installed Moodle version.

## Installation

Copy the `videocheck` directory to `mod/videocheck`, visit Site administration > Notifications, and complete the Moodle
upgrade.

## Video sources

- Uploaded video through Moodle File API.
- Direct browser-playable video URL (for example MP4, WebM, or OGG).
- YouTube through the IFrame Player API.
- Vimeo through the Player API.

## Credits

The architecture and tracking approach reuse ideas from:
https://github.com/EduardoKrausME/moodle-mod_videoprogress

## License

GNU GPL v3 or later.

Copyright 2026 Eduardo Kraus.
