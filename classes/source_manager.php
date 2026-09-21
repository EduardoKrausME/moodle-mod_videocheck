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
 * Video source helpers.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videocheck;

use moodle_exception;

/**
 * Validates supported external video sources.
 */
class source_manager {
    /**
     * Validates a source URL.
     *
     * @param string $source Source type.
     * @param string $url URL supplied by the teacher.
     * @return bool
     */
    public static function is_valid(string $source, string $url): bool {
        try {
            self::parse($source, $url);
            return true;
        } catch (moodle_exception $exception) {
            return false;
        }
    }

    /**
     * Parses a configured source into player data.
     *
     * @param string $source Source type.
     * @param string $url Stored URL.
     * @return array
     * @throws moodle_exception
     */
    public static function parse(string $source, string $url): array {
        $url = trim($url);
        if ($source === 'upload') {
            return ['type' => 'upload'];
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) ||
            !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new moodle_exception('invalidvideourl', 'videocheck');
        }
        if ($source === 'url') {
            return ['type' => 'url', 'url' => $url];
        }
        if ($source === 'youtube') {
            return ['type' => 'youtube', 'id' => self::youtube_id($url)];
        }
        if ($source === 'vimeo') {
            return array_merge(['type' => 'vimeo'], self::vimeo_config($url));
        }
        throw new moodle_exception('invalidvideosource', 'videocheck');
    }

    /**
     * Extracts a YouTube video id.
     *
     * @param string $url YouTube URL.
     * @return string
     * @throws moodle_exception
     */
    private static function youtube_id(string $url): string {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
        $id = '';
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $id = explode('/', $path)[0] ?? '';
        } else if (in_array($host, [
            'youtube.com', 'www.youtube.com', 'm.youtube.com',
            'youtube-nocookie.com', 'www.youtube-nocookie.com',
        ], true)) {
            parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
            $id = (string)($query['v'] ?? '');
            if ($id === '' && preg_match('~(?:embed|shorts)/([A-Za-z0-9_-]{6,20})~', $path, $matches)) {
                $id = $matches[1];
            }
        }
        if (!preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id)) {
            throw new moodle_exception('invalidyoutubeurl', 'videocheck');
        }
        return $id;
    }

    /**
     * Extracts Vimeo id and optional unlisted hash.
     *
     * @param string $url Vimeo URL.
     * @return array
     * @throws moodle_exception
     */
    private static function vimeo_config(string $url): array {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (!in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            throw new moodle_exception('invalidvimeourl', 'videocheck');
        }
        $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
        if (!preg_match('~^(?:video/)?(\d+)(?:/([A-Za-z0-9]+))?$~', $path, $matches)) {
            throw new moodle_exception('invalidvimeourl', 'videocheck');
        }
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $hash = $matches[2] ?? '';
        if ($hash === '' && !empty($query['h']) && preg_match('/^[A-Za-z0-9]+$/', $query['h'])) {
            $hash = (string)$query['h'];
        }
        return ['id' => $matches[1], 'hash' => $hash];
    }
}
