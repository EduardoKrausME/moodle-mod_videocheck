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
 * player.js
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const loadScript = (src, ready) => new Promise((resolve, reject) => {
    if (ready()) {
        resolve();
        return;
    }
    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing) {
        const timer = window.setInterval(() => {
            if (ready()) {
                window.clearInterval(timer);
                resolve();
            }
        }, 100);
        window.setTimeout(() => {
            window.clearInterval(timer);
            if (ready()) {
                resolve();
            } else {
                reject(new Error(`Unable to load ${src}`));
            }
        }, 15000);
        return;
    }
    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.onload = () => {
        if (ready()) {
            resolve();
            return;
        }
        const timer = window.setInterval(() => {
            if (ready()) {
                window.clearInterval(timer);
                resolve();
            }
        }, 100);
        window.setTimeout(() => {
            window.clearInterval(timer);
            if (ready()) {
                resolve();
            } else {
                reject(new Error(`Unable to initialise ${src}`));
            }
        }, 15000);
    };
    script.onerror = reject;
    document.head.appendChild(script);
});

const html5Adapter = (player) => {
    const timeCallbacks = [];
    const playCallbacks = [];
    const pauseCallbacks = [];
    const endedCallbacks = [];

    const emitTime = () => {
        timeCallbacks.forEach((callback) => callback(player.currentTime || 0, player.duration || 0));
    };
    player.addEventListener('timeupdate', emitTime);
    player.addEventListener('seeking', emitTime);
    player.addEventListener('loadedmetadata', emitTime);
    player.addEventListener('play', () => playCallbacks.forEach((callback) => callback()));
    player.addEventListener('pause', () => pauseCallbacks.forEach((callback) => callback()));
    player.addEventListener('ended', () => endedCallbacks.forEach((callback) => callback()));

    return Promise.resolve({
        onTime: (callback) => timeCallbacks.push(callback),
        onPlay: (callback) => playCallbacks.push(callback),
        onPause: (callback) => pauseCallbacks.push(callback),
        onEnded: (callback) => endedCallbacks.push(callback),
        getCurrent: () => Promise.resolve(player.currentTime || 0),
        getDuration: () => Promise.resolve(Number.isFinite(player.duration) ? player.duration : 0),
        seek: (seconds) => {
            player.currentTime = Math.max(0, seconds);
            return Promise.resolve();
        },
        play: () => {
            const result = player.play();
            return result && typeof result.then === 'function' ? result : Promise.resolve();
        },
        pause: () => {
            player.pause();
            return Promise.resolve();
        },
    });
};

const youtubeAdapter = async (config) => {
    await loadScript('https://www.youtube.com/iframe_api', () => Boolean(window.YT && window.YT.Player));

    const timeCallbacks = [];
    const playCallbacks = [];
    const pauseCallbacks = [];
    const endedCallbacks = [];
    let player = null;
    let ready = false;
    let playing = false;

    await new Promise((resolve) => {
        player = new window.YT.Player(config.playerid, {
            videoId: config.youtubeid,
            playerVars: {
                rel: 0,
                playsinline: 1,
            },
            events: {
                onReady: () => {
                    ready = true;
                    resolve();
                },
                onStateChange: (event) => {
                    if (event.data === window.YT.PlayerState.PLAYING) {
                        playing = true;
                        playCallbacks.forEach((callback) => callback());
                    } else if (event.data === window.YT.PlayerState.PAUSED) {
                        playing = false;
                        pauseCallbacks.forEach((callback) => callback());
                    } else if (event.data === window.YT.PlayerState.ENDED) {
                        playing = false;
                        endedCallbacks.forEach((callback) => callback());
                    }
                },
            },
        });
    });

    window.setInterval(() => {
        if (!ready || !player || typeof player.getCurrentTime !== 'function') {
            return;
        }
        const current = player.getCurrentTime() || 0;
        const duration = player.getDuration() || 0;
        timeCallbacks.forEach((callback) => callback(current, duration, playing));
    }, 500);

    return {
        onTime: (callback) => timeCallbacks.push(callback),
        onPlay: (callback) => playCallbacks.push(callback),
        onPause: (callback) => pauseCallbacks.push(callback),
        onEnded: (callback) => endedCallbacks.push(callback),
        getCurrent: () => Promise.resolve(player.getCurrentTime() || 0),
        getDuration: () => Promise.resolve(player.getDuration() || 0),
        seek: (seconds) => {
            player.seekTo(Math.max(0, seconds), true);
            return Promise.resolve();
        },
        play: () => {
            player.playVideo();
            return Promise.resolve();
        },
        pause: () => {
            player.pauseVideo();
            return Promise.resolve();
        },
    };
};

const vimeoAdapter = async (config) => {
    await loadScript('https://player.vimeo.com/api/player.js', () => Boolean(window.Vimeo && window.Vimeo.Player));
    const element = document.getElementById(config.playerid);
    const player = new window.Vimeo.Player(element);
    await player.ready();

    const timeCallbacks = [];
    const playCallbacks = [];
    const pauseCallbacks = [];
    const endedCallbacks = [];
    let playing = false;

    player.on('timeupdate', (data) => {
        timeCallbacks.forEach((callback) => callback(data.seconds || 0, data.duration || 0, playing));
    });
    player.on('play', () => {
        playing = true;
        playCallbacks.forEach((callback) => callback());
    });
    player.on('pause', () => {
        playing = false;
        pauseCallbacks.forEach((callback) => callback());
    });
    player.on('ended', () => {
        playing = false;
        endedCallbacks.forEach((callback) => callback());
    });

    return {
        onTime: (callback) => timeCallbacks.push(callback),
        onPlay: (callback) => playCallbacks.push(callback),
        onPause: (callback) => pauseCallbacks.push(callback),
        onEnded: (callback) => endedCallbacks.push(callback),
        getCurrent: () => player.getCurrentTime(),
        getDuration: () => player.getDuration(),
        seek: (seconds) => player.setCurrentTime(Math.max(0, seconds)),
        play: () => player.play(),
        pause: () => player.pause(),
    };
};

const buildAdapter = (config) => {
    const player = document.getElementById(config.playerid);
    if (config.source === 'youtube') {
        return youtubeAdapter(config);
    }
    if (config.source === 'vimeo') {
        return vimeoAdapter(config);
    }
    return html5Adapter(player);
};

const checkpointSeconds = (checkpoint, duration) => checkpoint.positiontype === 'percent'
    ? duration * checkpoint.positionvalue / 100
    : checkpoint.positionvalue;

export const init = async (config) => {
    const root = document.getElementById(config.rootid);
    if (!root) {
        return;
    }

    let adapter;
    try {
        adapter = await buildAdapter(config);
    } catch (error) {
        Notification.exception(error);
        return;
    }

    const panel = root.querySelector('[data-region="checkpoint-panel"]');
    const title = root.querySelector('[data-region="checkpoint-title"]');
    const prompt = root.querySelector('[data-region="checkpoint-prompt"]');
    const textRow = root.querySelector('[data-region="text-response"]');
    const textInput = root.querySelector('[data-region="response-input"]');
    const choiceRow = root.querySelector('[data-region="choice-response"]');
    const choice = root.querySelector('[data-region="response-choice"]');
    const errorBox = root.querySelector('[data-region="checkpoint-error"]');
    const submitButton = root.querySelector('[data-action="submit-checkpoint"]');
    const skipButton = root.querySelector('[data-action="skip-checkpoint"]');
    const percentNode = root.querySelector('[data-region="percent"]');
    const completedNode = root.querySelector('[data-region="completed-count"]');

    let duration = await adapter.getDuration();
    let playing = false;
    let blockedCheckpoint = null;
    let lastTime = null;
    let lastWall = performance.now();
    let lastFlush = Date.now();
    let pendingSegments = [];
    let pendingWatchtime = 0;
    let serverMax = Number(config.maxwatched || 0);
    let localMax = serverMax;
    let flushing = false;
    let tickBusy = false;

    const completed = new Set(
        (config.checkpoints || []).filter((checkpoint) => checkpoint.completed).map((checkpoint) => Number(checkpoint.id))
    );
    const triggered = new Set();
    const checkpoints = (config.checkpoints || []).map((checkpoint) => ({
        ...checkpoint,
        seconds: 0,
    }));

    const updateCheckpointPositions = () => {
        checkpoints.forEach((checkpoint) => {
            checkpoint.seconds = checkpointSeconds(checkpoint, duration);
        });
        checkpoints.sort((a, b) => a.seconds - b.seconds || a.id - b.id);
    };
    updateCheckpointPositions();

    const hideError = () => {
        errorBox.hidden = true;
        errorBox.textContent = '';
    };

    const closePanel = async () => {
        panel.hidden = true;
        blockedCheckpoint = null;
        hideError();
        await adapter.play().catch(() => {
        });
    };

    const openCheckpoint = async (checkpoint) => {
        if (blockedCheckpoint) {
            return;
        }
        blockedCheckpoint = checkpoint;
        triggered.add(Number(checkpoint.id));
        await adapter.pause().catch(() => {
        });
        await adapter.seek(checkpoint.seconds).catch(() => {
        });

        title.textContent = checkpoint.title || `Checkpoint`;
        prompt.textContent = checkpoint.prompt || '';
        textInput.value = checkpoint.response || '';
        choice.textContent = '';
        hideError();

        const isConfirm = checkpoint.checkpointtype === 'confirm';
        const isChoice = checkpoint.checkpointtype === 'choice';
        textRow.hidden = isConfirm || isChoice;
        choiceRow.hidden = !isChoice;

        if (isChoice) {
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = '—';
            choice.appendChild(empty);
            (checkpoint.options || []).forEach((optionText) => {
                const option = document.createElement('option');
                option.value = optionText;
                option.textContent = optionText;
                if (checkpoint.response === optionText) {
                    option.selected = true;
                }
                choice.appendChild(option);
            });
        }

        submitButton.textContent = isConfirm ? config.strings.confirm : config.strings.submit;
        skipButton.textContent = config.strings.skip;
        skipButton.hidden = Boolean(checkpoint.required);
        panel.hidden = false;

        if (!isConfirm && !isChoice) {
            textInput.focus();
        } else if (isChoice) {
            choice.focus();
        } else {
            submitButton.focus();
        }
    };

    const flush = async () => {
        if (!config.cantrack || flushing) {
            return;
        }
        if (pendingSegments.length === 0 && pendingWatchtime <= 0) {
            return;
        }

        flushing = true;
        const segments = pendingSegments;
        const watchtime = pendingWatchtime;
        pendingSegments = [];
        pendingWatchtime = 0;

        try {
            const position = await adapter.getCurrent();
            const currentDuration = await adapter.getDuration();
            if (currentDuration > 0) {
                duration = currentDuration;
                updateCheckpointPositions();
            }
            const request = Ajax.call([{
                methodname: 'mod_videocheck_update_progress',
                args: {
                    cmid: config.cmid,
                    position,
                    duration,
                    segments: JSON.stringify(segments),
                    watchtime,
                },
            }])[0];
            const response = await request;
            serverMax = Number(response.maxwatched || 0);
            localMax = Math.max(localMax, serverMax);
            percentNode.textContent = Number(response.percent || 0).toFixed(1);
            const authoritativePosition = Number(response.lastposition || 0);
            if (position > authoritativePosition + 0.8) {
                await adapter.seek(authoritativePosition).catch(() => {
                });
                lastTime = authoritativePosition;
            }
            lastFlush = Date.now();
        } catch (error) {
            pendingSegments = segments.concat(pendingSegments);
            pendingWatchtime += watchtime;
            Notification.exception(error);
        } finally {
            flushing = false;
        }
    };

    const firstPendingCrossedCheckpoint = (from, to) => {
        if (to < from) {
            return null;
        }
        for (const checkpoint of checkpoints) {
            if (completed.has(Number(checkpoint.id)) || triggered.has(Number(checkpoint.id))) {
                continue;
            }
            if (checkpoint.seconds <= to + 0.25 && checkpoint.seconds >= from - 0.25) {
                return checkpoint;
            }
        }
        return null;
    };

    const handleTick = async (current, eventDuration) => {
        if (tickBusy) {
            return;
        }
        tickBusy = true;
        try {
            if (eventDuration > 0 && Math.abs(eventDuration - duration) > 0.5) {
                duration = eventDuration;
                updateCheckpointPositions();
            }

            if (blockedCheckpoint) {
                if (current > blockedCheckpoint.seconds + 0.8) {
                    await adapter.seek(blockedCheckpoint.seconds);
                }
                lastTime = blockedCheckpoint.seconds;
                lastWall = performance.now();
                return;
            }

            const now = performance.now();
            if (lastTime !== null) {
                const delta = current - lastTime;
                const wallSeconds = Math.max(0, Math.min(3, (now - lastWall) / 1000));

                if (!config.allowseek && delta > 3.5 && current > localMax + 1.5) {
                    await adapter.seek(localMax);
                    current = localMax;
                } else if (playing && delta > 0 && delta <= 3.5) {
                    pendingSegments.push([lastTime, current]);
                    pendingWatchtime += wallSeconds;
                    localMax = Math.max(localMax, current);
                }

                const checkpoint = firstPendingCrossedCheckpoint(Math.min(lastTime, current), current);
                if (checkpoint) {
                    await flush();
                    await openCheckpoint(checkpoint);
                    lastTime = checkpoint.seconds;
                    lastWall = now;
                    return;
                }
            }

            lastTime = current;
            lastWall = now;
            if (Date.now() - lastFlush >= 8000) {
                await flush();
            }
        } finally {
            tickBusy = false;
        }
    };

    submitButton.addEventListener('click', async () => {
        if (!blockedCheckpoint) {
            return;
        }
        hideError();

        let response = '1';
        if (blockedCheckpoint.checkpointtype === 'choice') {
            response = choice.value;
        } else if (blockedCheckpoint.checkpointtype !== 'confirm') {
            response = textInput.value.trim();
        }

        if (blockedCheckpoint.checkpointtype !== 'confirm' && response === '') {
            errorBox.textContent = config.strings.responseRequired;
            errorBox.hidden = false;
            return;
        }

        if (!config.cantrack) {
            await closePanel();
            return;
        }

        submitButton.disabled = true;
        try {
            const result = await Ajax.call([{
                methodname: 'mod_videocheck_submit_checkpoint',
                args: {
                    cmid: config.cmid,
                    checkpointid: Number(blockedCheckpoint.id),
                    response,
                },
            }])[0];

            if (!result.completed) {
                errorBox.textContent = result.message;
                errorBox.hidden = false;
                return;
            }
            completed.add(Number(blockedCheckpoint.id));
            completedNode.textContent = String(result.completedcount);
            await closePanel();
        } catch (error) {
            Notification.exception(error);
        } finally {
            submitButton.disabled = false;
        }
    });

    skipButton.addEventListener('click', async () => {
        if (!blockedCheckpoint || blockedCheckpoint.required) {
            return;
        }
        await closePanel();
    });

    adapter.onPlay(() => {
        playing = true;
        if (blockedCheckpoint) {
            adapter.pause().catch(() => {
            });
        }
    });
    adapter.onPause(() => {
        playing = false;
        flush().catch(() => {
        });
    });
    adapter.onEnded(() => {
        playing = false;
        Promise.all([adapter.getCurrent(), adapter.getDuration()])
            .then(([current, finalDuration]) => handleTick(
                Number(finalDuration || current || 0),
                Number(finalDuration || duration || 0)
            ))
            .then(() => flush())
            .catch(Notification.exception);
    });
    adapter.onTime((current, eventDuration) => {
        handleTick(Number(current || 0), Number(eventDuration || duration || 0)).catch(Notification.exception);
    });

    duration = await adapter.getDuration();
    updateCheckpointPositions();

    if (config.resumeplayback && Number(config.lastposition || 0) > 1) {
        let resume = Number(config.lastposition || 0);
        if (!config.allowseek) {
            resume = Math.min(resume, serverMax + 1.5);
        }
        const blocker = checkpoints.find((checkpoint) =>
            checkpoint.required && !completed.has(Number(checkpoint.id)) && checkpoint.seconds <= resume
        );
        if (blocker) {
            resume = blocker.seconds;
        }
        await adapter.seek(resume).catch(() => {
        });
        lastTime = resume;
        localMax = Math.max(localMax, Math.min(resume, serverMax + 1.5));
        if (blocker) {
            await openCheckpoint(blocker);
        }
    }

    window.addEventListener('pagehide', () => {
        flush().catch(() => {
        });
    });
};
