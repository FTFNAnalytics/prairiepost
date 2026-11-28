<?php
/**
 * Live weather, from Environment and Climate Change Canada (ECCC).
 *
 * Until 24 Nov 2026 a paper's weather was a line an editor typed into
 * Settings at launch. It was printed beside today's date and never touched
 * again, so in late November Brampton still read "Brampton, Ont. · 27°C ·
 * Humid, smog advisory". Now the `weather` job (cron/weather.php, every 30 minutes on
 * the hub) reads ECCC's city pages through the MSC GeoMet API
 * (api.weather.gc.ca: free, no key, English and French) and stores each
 * paper's reading as its `weather_live` setting.
 *
 * A paper names its city in its palette.json:
 *
 *     "weather": {"city": "mb-38", "place": "Winnipeg"}
 *
 * `city` is the ECCC city-page identifier; `place` is how the paper names
 * the area that page's forecast covers. A paper without the block shows no
 * weather at all.
 *
 * Two rules the templates rely on:
 * - A reading observed more than PP_WEATHER_MAX_AGE ago is never shown:
 *   the widget disappears rather than print stale numbers.
 * - Every rendering names the source, as ECCC's licence asks. Box widgets
 *   carry a visible source line; one-line strips link to the city's
 *   forecast, with the source and the observing station in the title.
 */

const PP_WEATHER_API = 'https://api.weather.gc.ca/collections/citypageweather-realtime/items/';

/** Three hours: ECCC observes hourly, so anything older means the feed or the job has stopped. */
const PP_WEATHER_MAX_AGE = 3 * 3600;

/**
 * The local clock for an observation time, by the city's province.
 *
 * The conversion is the server's time-zone database, so it is only as right
 * as that database is new. In 2026 British Columbia, Alberta and Manitoba
 * stopped changing clocks and stayed on daylight time (UTC-7, -6 and -5;
 * ECCC prints PCT, ABT and MBT). tzdata learned them in 2026b, 2026c and
 * 2026e. On older data, every time printed for a western paper is an hour
 * early: Winnipeg's 22:00 UTC observation must read 5:00 p.m.
 *
 * Mapping by province is right for every paper that has a block. A city in
 * a province's minority zone (the East Kootenay, northwestern Ontario,
 * Labrador) would need its own zone here first.
 *
 * The Northwest Territories map to America/Edmonton. tzdata keeps
 * America/Yellowknife only as an alias of it, so PHP doesn't list it as a
 * zone, and a paper's palette zone must be a listed one (the clock test).
 * The rules, and every time printed, are the same either way.
 */
const PP_WEATHER_ZONES = [
    'bc' => 'America/Vancouver', 'ab' => 'America/Edmonton', 'sk' => 'America/Regina',
    'mb' => 'America/Winnipeg',  'on' => 'America/Toronto',  'qc' => 'America/Toronto',
    'nb' => 'America/Moncton',   'ns' => 'America/Halifax',  'pe' => 'America/Halifax',
    'nl' => 'America/St_Johns',  'yt' => 'America/Whitehorse', 'nt' => 'America/Edmonton',
    'nu' => 'America/Iqaluit',
];

const PP_WEATHER_WORDS = [
    'en' => ['credit' => 'Data source: Environment and Climate Change Canada', 'observed' => 'Observed %s',
             'at' => '%s, observed %s', 'humidity' => 'Humidity'],
    'fr' => ['credit' => 'Source des données : Environnement et Changement climatique Canada', 'observed' => 'Observé à %s',
             'at' => '%s, observé à %s', 'humidity' => 'Humidité'],
];

/* --- Configuration ----------------------------------------------------------- */

/** The `weather` block of a decoded palette.json, validated, or null. */
function pp_weather_config_from(array $palette): ?array
{
    $w = $palette['weather'] ?? null;
    if (!is_array($w)) {
        return null;
    }
    $city = (string) ($w['city'] ?? '');
    $place = trim((string) ($w['place'] ?? ''));
    if (preg_match('/^[a-z]{2}-\d{1,3}$/', $city) !== 1 || !isset(PP_WEATHER_ZONES[substr($city, 0, 2)])
        || $place === '' || mb_strlen($place) > 40) {
        return null;
    }
    return ['city' => $city, 'place' => $place];
}

/** Any paper's weather block, by slug (the job's view of the network). */
function pp_weather_config(string $slug): ?array
{
    $file = PP_ROOT . '/assets/sites/' . basename($slug) . '/palette.json';
    if ($slug === '' || !is_file($file)) {
        return null;
    }
    $palette = json_decode((string) file_get_contents($file), true);
    return is_array($palette) ? pp_weather_config_from($palette) : null;
}

/* --- Fetching (the weather job) ----------------------------------------------- */

/**
 * One ECCC city-page record, decoded, reduced to the reading we keep:
 * observation time, temperature, condition, station, the next high and low,
 * humidity and the forecast page's address. Null when the record has no
 * current temperature or no observation time, which happens when a
 * station is down.
 */
function pp_weather_parse(array $record): ?array
{
    $p = $record['properties'] ?? null;
    if (!is_array($p)) {
        return null;
    }
    $cc = is_array($p['currentConditions'] ?? null) ? $p['currentConditions'] : [];
    $temp = $cc['temperature']['value']['en'] ?? null;
    $observed = strtotime((string) ($cc['timestamp']['en'] ?? ''));
    if (!is_numeric($temp) || $observed === false) {
        return null;
    }
    // Both languages, plain text only, bounded: this is a third party's text.
    $pair = static function ($node, int $max): array {
        $out = [];
        foreach (['en', 'fr'] as $l) {
            $v = is_array($node) ? (string) ($node[$l] ?? '') : '';
            $out[$l] = mb_substr(trim((string) preg_replace('/[\x00-\x20\x7F]+/u', ' ', $v)), 0, $max);
        }
        return $out;
    };
    $forecasts = array_values(array_filter((array) ($p['forecastGroup']['forecasts'] ?? []), 'is_array'));
    // What the station saw. Some stations report a temperature and no sky;
    // then there is no condition, never a forecast passed off as one.
    $cond = $pair($cc['condition'] ?? null, 60);
    // What comes next, always labelled with its period ("Tonight: Mainly cloudy").
    $outlook = ['en' => '', 'fr' => ''];
    if (isset($forecasts[0])) {
        $name = $pair($forecasts[0]['period']['textForecastName'] ?? null, 30);
        $sky = $pair($forecasts[0]['abbreviatedForecast']['textSummary'] ?? null, 60);
        foreach (['en' => ': ', 'fr' => ' : '] as $l => $colon) {
            $outlook[$l] = $name[$l] !== '' && $sky[$l] !== '' ? $name[$l] . $colon . $sky[$l] : '';
        }
    }
    // The next high and the next low: tonight's low and tomorrow's high in
    // the evening, today's high and tonight's low in the morning.
    $hi = $lo = null;
    foreach (array_slice($forecasts, 0, 3) as $f) {
        $temps = $f['temperatures']['temperature'] ?? [];
        if (isset($temps['class'])) {
            $temps = [$temps];   // one temperature arrives as an object, not a list
        }
        foreach ((array) $temps as $t) {
            $v = is_array($t) ? ($t['value']['en'] ?? null) : null;
            $class = is_array($t) ? ($t['class']['en'] ?? '') : '';
            if (!is_numeric($v)) {
                continue;
            }
            if ($class === 'high' && $hi === null) {
                $hi = (int) round((float) $v);
            } elseif ($class === 'low' && $lo === null) {
                $lo = (int) round((float) $v);
            }
        }
    }
    // Only ECCC's own forecast pages ever become a link.
    $url = [];
    foreach (['en' => 'https://weather.gc.ca/', 'fr' => 'https://meteo.gc.ca/'] as $l => $home) {
        $u = (string) ($p['url'][$l] ?? '');
        $url[$l] = str_starts_with($u, $home) && preg_match('#^[A-Za-z0-9._~:/?\#@!$&()*+,;=%-]+$#', $u) === 1 ? $u : $home;
    }
    $humidity = $cc['relativeHumidity']['value']['en'] ?? null;
    return [
        'v'        => 1,
        'observed' => $observed,
        'temp'     => (float) $temp,
        'cond'     => $cond,
        'outlook'  => $outlook,
        'station'  => $pair($cc['station']['value'] ?? null, 80),
        'hi'       => $hi,
        'lo'       => $lo,
        'humidity' => is_numeric($humidity) ? (int) round((float) $humidity) : null,
        'url'      => $url,
    ];
}

/**
 * The API base. Test instrumentation can point it at a fixture server, but
 * only in a CLI process that armed the transport's own test hooks
 * (PP_TRANSPORT_TEST=1). A web request never reads the variable.
 */
function pp_weather_api(): string
{
    $test = PHP_SAPI === 'cli' && getenv('PP_TRANSPORT_TEST') === '1' ? (string) getenv('PP_WEATHER_TEST_API') : '';
    return $test !== '' ? $test : PP_WEATHER_API;
}

/** Fetch one city page through the safe transport. Returns [reading, null] or [null, reason]. */
function pp_weather_fetch(string $city): array
{
    [$body, $err] = pp_http_get(pp_weather_api() . rawurlencode($city) . '?f=json',
        ['timeout' => 20, 'max_bytes' => 1024 * 1024, 'max_redirects' => 2]);
    if ($err !== null) {
        return [null, $err];
    }
    $record = json_decode((string) $body, true);
    if (!is_array($record)) {
        return [null, 'the answer was not JSON'];
    }
    $reading = pp_weather_parse($record);
    return $reading === null ? [null, 'the answer carried no current temperature'] : [$reading, null];
}

/**
 * The weather job: every paper with a weather block gets its city's
 * current reading, and each city is fetched once however many papers
 * share it. A failed city leaves its papers' last reading in place; it
 * ages out of sight by itself after PP_WEATHER_MAX_AGE. Returns
 * [report lines, cities fetched, cities failed].
 */
function pp_weather_refresh_all(): array
{
    $byCity = [];
    foreach (db()->query('SELECT id, slug FROM sites ORDER BY id') as $site) {
        $cfg = pp_weather_config((string) $site['slug']);
        if ($cfg !== null) {
            $byCity[$cfg['city']][] = [(int) $site['id'], (string) $site['slug'], $cfg['place']];
        }
    }
    $lines = [];
    $ok = $failed = 0;
    foreach ($byCity as $city => $papers) {
        [$reading, $err] = pp_weather_fetch($city);
        if ($reading === null) {
            $failed++;
            $lines[] = "WARN  $city: $err (" . implode(', ', array_column($papers, 1)) . ' keep their last reading until it ages out)';
            continue;
        }
        $ok++;
        foreach ($papers as [$siteId, $slug, $place]) {
            $stored = $reading + ['city' => $city, 'place' => $place, 'fetched' => time()];
            set_setting('weather_live', (string) json_encode($stored, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $siteId);
            $lines[] = sprintf('ok    %s: %s %s, %s (observed %s UTC)', $slug, $place, pp_weather_temp($reading['temp']),
                $reading['cond']['en'] !== '' ? $reading['cond']['en'] : 'no sky reported', gmdate('H:i', $reading['observed']));
        }
    }
    return [$lines, $ok, $failed];
}

/* --- Reading (the templates) -------------------------------------------------- */

/**
 * This paper's reading, ready to print, or null when there is nothing
 * fresh to show: no weather block, no reading yet, a reading for another
 * city, or one older than PP_WEATHER_MAX_AGE.
 */
function pp_weather(): ?array
{
    $cfg = pp_weather_config_from(function_exists('pp_brand_file') ? pp_brand_file() : []);
    $r = setting_json('weather_live');
    $now = time();
    if ($cfg === null || ($r['city'] ?? '') !== $cfg['city'] || !is_numeric($r['temp'] ?? null)
        || !is_numeric($r['observed'] ?? null) || $now - (int) $r['observed'] > PP_WEATHER_MAX_AGE
        || (int) $r['observed'] > $now + 3600) {
        return null;
    }
    $lang = pp_weather_lang();
    $words = PP_WEATHER_WORDS[$lang];
    $zone = new DateTimeZone(PP_WEATHER_ZONES[substr($cfg['city'], 0, 2)]);
    $at = (new DateTimeImmutable('@' . (int) $r['observed']))->setTimezone($zone);
    $time = $lang === 'fr' ? $at->format('G \h i') : str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $at->format('g:i a'));
    $pick = static fn ($pair): string => (string) ($pair[$lang] ?? '') !== '' ? (string) $pair[$lang] : (string) ($pair['en'] ?? '');
    $cond = $pick($r['cond'] ?? []);
    $outlook = $pick($r['outlook'] ?? []);
    $station = $pick($r['station'] ?? []);
    $url = (string) ($r['url'][$lang] ?? '');
    return [
        'place'    => $cfg['place'],
        'temp'     => pp_weather_temp((float) $r['temp']),
        'cond'     => $cond,
        'outlook'  => $outlook,
        'hi'       => pp_weather_deg($r['hi'] ?? null),
        'lo'       => pp_weather_deg($r['lo'] ?? null),
        'humidity' => is_numeric($r['humidity'] ?? null) ? (int) $r['humidity'] . '%' : '',
        'time'     => $time,
        'observed' => sprintf($words['observed'], $time),
        'credit'   => $words['credit'],
        'title'    => $words['credit'] . ' · ' . ($station !== '' ? sprintf($words['at'], $station, $time) : sprintf($words['observed'], $time)),
        'url'      => str_starts_with($url, 'https://weather.gc.ca/') || str_starts_with($url, 'https://meteo.gc.ca/') ? $url : 'https://weather.gc.ca/',
    ];
}

function pp_weather_lang(): string
{
    return function_exists('pp_lang') && pp_lang() === 'fr' ? 'fr' : 'en';
}

/** -3.1 → "-3°C"; never "-0°C". */
function pp_weather_temp(float $celsius): string
{
    $n = (int) round($celsius);
    return ($n === 0 ? '0' : (string) $n) . '°C';
}

function pp_weather_deg($value): string
{
    return is_numeric($value) ? (int) $value . '°' : '—';
}

/** "temp|condition", the shape the split-style templates were written for; '' when nothing is fresh. */
function pp_weather_line(): string
{
    $w = pp_weather();
    return $w === null ? '' : $w['temp'] . ($w['cond'] !== '' ? '|' . $w['cond'] : '');
}

/** "Place · temp · condition", for the strips that print one phrase; '' when nothing is fresh. */
function pp_weather_text(): string
{
    $w = pp_weather();
    return $w === null ? '' : $w['place'] . ' · ' . $w['temp'] . ($w['cond'] !== '' ? ' · ' . $w['cond'] : '');
}

/**
 * Wrap already-escaped HTML in a link to the city's forecast, with the
 * source and the observing station in its title. '' when nothing is fresh.
 */
function pp_weather_link(string $html, string $class = ''): string
{
    $w = pp_weather();
    if ($w === null) {
        return '';
    }
    return '<a href="' . e($w['url']) . '" title="' . e($w['title']) . '"' . ($class !== '' ? ' class="' . e($class) . '"' : '')
        . ' rel="external">' . $html . '</a>';
}

/** A box widget's source line: the credit, linked, and the observation time. */
function pp_weather_source_html(): string
{
    $w = pp_weather();
    if ($w === null) {
        return '';
    }
    return '<a href="' . e($w['url']) . '" rel="external">' . e($w['credit']) . '</a> · ' . e($w['observed']);
}

/**
 * The forecast-block shape index.php and the Echo's front page were written
 * for (temp, hi, lo, line, fact_label, fact), plus the source line; [] when
 * nothing is fresh, which hides the block.
 */
function pp_weather_today(): array
{
    $w = pp_weather();
    if ($w === null) {
        return [];
    }
    return [
        'temp'       => $w['temp'],
        'hi'         => $w['hi'],
        'lo'         => $w['lo'],
        'line'       => $w['cond'] !== '' ? $w['cond'] : $w['outlook'],
        'fact_label' => PP_WEATHER_WORDS[pp_weather_lang()]['humidity'],
        'fact'       => $w['humidity'] !== '' ? $w['humidity'] : '—',
        'url'        => $w['url'],
        'credit'     => $w['credit'],
        'observed'   => $w['observed'],
        'source'     => pp_weather_source_html(),
    ];
}
