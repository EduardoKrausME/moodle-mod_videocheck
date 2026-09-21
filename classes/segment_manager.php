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
 * Watched segment utilities.
 *
 * @package   mod_videocheck
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videocheck;

/**
 * Normalises and merges watched video ranges.
 */
class segment_manager {
    /**
     * Decodes persisted segments.
     *
     * @param string|null $json JSON-encoded ranges.
     * @return array
     */
    public static function decode(?string $json): array {
        if (!$json) {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $segments = [];
        foreach ($decoded as $segment) {
            if (!is_array($segment) || count($segment) !== 2) {
                continue;
            }
            $start = (float)$segment[0];
            $end = (float)$segment[1];
            if ($end > $start && $start >= 0) {
                $segments[] = [$start, $end];
            }
        }
        return $segments;
    }

    /**
     * Encodes ranges using compact JSON.
     *
     * @param array $segments Watched ranges.
     * @return string
     */
    public static function encode(array $segments): string {
        return json_encode(array_values($segments), JSON_UNESCAPED_SLASHES);
    }

    /**
     * Merges new watched ranges into an existing map.
     *
     * @param array $existing Existing ranges.
     * @param array $incoming New ranges.
     * @param float $duration Known video duration.
     * @return array
     */
    public static function merge(array $existing, array $incoming, float $duration): array {
        $all = [];
        foreach (array_merge($existing, $incoming) as $segment) {
            if (!is_array($segment) || count($segment) !== 2) {
                continue;
            }
            $start = max(0.0, (float)$segment[0]);
            $end = (float)$segment[1];
            if ($duration > 0) {
                $start = min($start, $duration);
                $end = min($end, $duration);
            }
            if ($end <= $start) {
                continue;
            }
            $all[] = [$start, $end];
        }

        usort($all, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($all as $segment) {
            if (!$merged) {
                $merged[] = $segment;
                continue;
            }
            $lastindex = count($merged) - 1;
            if ($segment[0] <= $merged[$lastindex][1] + 0.75) {
                $merged[$lastindex][1] = max($merged[$lastindex][1], $segment[1]);
            } else {
                $merged[] = $segment;
            }
        }
        return $merged;
    }

    /**
     * Calculates unique watched seconds.
     *
     * @param array $segments Merged ranges.
     * @return float
     */
    public static function unique_seconds(array $segments): float {
        $seconds = 0.0;
        foreach ($segments as $segment) {
            $seconds += max(0.0, (float)$segment[1] - (float)$segment[0]);
        }
        return round($seconds, 3);
    }

    /**
     * Returns the end of the continuously watched area starting at zero.
     *
     * @param array $segments Merged ranges.
     * @return float
     */
    public static function contiguous_end(array $segments): float {
        if (!$segments) {
            return 0.0;
        }
        $end = 0.0;
        foreach ($segments as $segment) {
            $start = (float)$segment[0];
            $segmentend = (float)$segment[1];
            if ($start > $end + 1.5) {
                break;
            }
            $end = max($end, $segmentend);
        }
        return round($end, 3);
    }
}
