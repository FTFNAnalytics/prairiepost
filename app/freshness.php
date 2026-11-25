<?php
/**
 * Widgets that are only true for a while: a breaking banner, the traffic
 * box, closing prices and the events list.
 *
 * The 24 Nov 2026 audit found launch-day values still up three months
 * later: breaking banners from August, August events, traffic items that
 * linked to "#", and invented closing prices. Each of these widgets now
 * carries its own clock, and shows nothing once it runs out:
 *
 * - A breaking banner shows until `breaking_until`, which Settings sets
 *   when the banner is saved (six hours unless the editor picks a time).
 *   A banner with no expiry never shows, and every launch-day banner has
 *   none.
 * - Traffic and closing prices show for a set time after an editor last
 *   changed them in Settings (`traffic_items_saved`, `markets_saved`).
 * - An event shows through the end of its own date. An event whose date
 *   can't be read as a calendar day never shows.
 *
 * Nothing here edits a row. The launch-day values stay in the database,
 * unread, as the typed weather lines did when live weather replaced them.
 */

/** How long traffic and prices stay up after an editor saves them. */
const PP_FRESH_FOR = ['traffic_items' => 12 * 3600, 'markets' => 30 * 3600];

/** A new banner's lifetime when the editor doesn't pick one. */
const PP_BREAKING_DEFAULT = 6 * 3600;

/** Is there a breaking banner to show: a label, a link, and an expiry still ahead? */
function pp_breaking_live(?int $now = null): bool
{
    $until = setting('breaking_until');
    return setting('breaking_label') !== '' && setting('breaking_url') !== ''
        && ctype_digit($until) && (int) $until > ($now ?? time());
}

/** A JSON rail setting's rows while they are fresh, or [] once they are stale or were never dated. */
function pp_fresh_rows(string $key, ?int $now = null): array
{
    $saved = setting($key . '_saved');
    $maxAge = PP_FRESH_FOR[$key] ?? 0;
    if (!ctype_digit($saved) || ($now ?? time()) - (int) $saved > $maxAge) {
        return [];
    }
    return setting_json($key);
}

/**
 * The calendar day an event's date names, as a timestamp at its start, or
 * null when the text names no month and day. "Aug 13", "Sat Aug 15" and
 * "2026-12-04" are days; "Saturday" or "Tuesday, 9:30 a.m." are not, since
 * they would never expire. A month and day with no year is taken in the
 * year that puts it nearest: "Jan 4" written in December is next January.
 */
function pp_event_day(string $text, ?int $now = null): ?int
{
    $now ??= time();
    if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $text, $m) === 1) {
        $ts = mktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $ts : null;
    }
    $months = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
               'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];
    $hit = preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?\s+(\d{1,2})\b(?:,?\s+(\d{4}))?/i', $text, $m) === 1
        ? [$months[strtolower($m[1])], (int) $m[2], (int) ($m[3] ?? 0)]
        : (preg_match('/\b(\d{1,2})\s+(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?(?:\s+(\d{4}))?/i', $text, $m) === 1
            ? [$months[strtolower($m[2])], (int) $m[1], (int) ($m[3] ?? 0)] : null);
    if ($hit === null) {
        return null;
    }
    [$month, $day, $year] = $hit;
    if ($year === 0) {
        $year = (int) date('Y', $now);
        // Nearest year: more than half a year behind means next year.
        if (checkdate($month, $day, $year) && mktime(0, 0, 0, $month, $day, $year) < $now - 183 * 86400) {
            $year++;
        }
    }
    return checkdate($month, $day, $year) ? mktime(0, 0, 0, $month, $day, $year) : null;
}

/** The rows of an events list that are today or later. Each row is [label, link, date, venue]. */
function pp_events_upcoming(array $rows, ?int $now = null): array
{
    $now ??= time();
    $today = mktime(0, 0, 0, (int) date('n', $now), (int) date('j', $now), (int) date('Y', $now));
    return array_values(array_filter($rows, static function ($row) use ($now, $today): bool {
        $day = is_array($row) && is_string($row[2] ?? null) ? pp_event_day($row[2], $now) : null;
        return $day !== null && $day >= $today;
    }));
}
