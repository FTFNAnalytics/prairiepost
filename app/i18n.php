<?php
/**
 * Language for the public site.
 *
 * A paper declares its language in palette.json:
 *
 *     "chrome": { "lang": "fr", ... }
 *
 * Anything that does not declare one is English, which is every paper on the
 * network today — so `pp_t()` returning the key's English string unchanged is
 * the default path and existing output does not move.
 *
 * Two things live here:
 *
 *  - pp_t()  — chrome strings. Only the furniture the platform itself writes:
 *              nav labels, buttons, section heads, the words around a byline.
 *              Editorial copy is never translated; it is written in the
 *              paper's language by the newsroom.
 *
 *  - the date helpers — PHP's date() emits English month and day names
 *              regardless of locale, so a French paper needs IntlDateFormatter.
 *              These wrap it and fall back to date() when intl is absent.
 */

/** The current paper's language tag: 'en' unless its palette says otherwise. */
function pp_lang(): string
{
    static $lang = null;
    if ($lang === null) {
        // feed.php and sitemap.php never load ui.php, so pp_chrome() may not
        // exist yet. Read the palette directly rather than assuming it does.
        $l = 'en';
        if (function_exists('pp_chrome')) {
            $l = (string) (pp_chrome('lang') ?? 'en');
        } elseif (function_exists('pp_brand_file')) {
            $l = (string) (pp_brand_file()['chrome']['lang'] ?? 'en');
        }
        $lang = preg_match('/^[a-z]{2}(-[A-Za-z]{2})?$/', $l) ? $l : 'en';
    }
    return $lang;
}

/** The ICU locale to format dates and times in — Quebec French, not France. */
function pp_locale(): string
{
    return match (pp_lang()) {
        'fr' => 'fr_CA',
        default => 'en_CA',
    };
}

/**
 * A chrome string in the paper's language.
 *
 * The key IS the English string, so an untranslated key renders correctly in
 * English rather than as a missing-token placeholder. That also means adding a
 * new string to a template needs no work at all for the English papers.
 */
function pp_t(string $key): string
{
    static $table = null;
    if ($table === null) {
        $file = PP_ROOT . '/app/lang/' . pp_lang() . '.php';
        $table = is_file($file) ? require $file : [];
    }
    return $table[$key] ?? $key;
}

/**
 * Format a timestamp with an ICU pattern, in the paper's locale and timezone.
 * Falls back to a date() pattern when intl is unavailable, so the site still
 * renders — in English — rather than fataling.
 */
function pp_fmt(int $ts, string $icu, string $fallback): string
{
    if (!class_exists('IntlDateFormatter')) {
        return date($fallback, $ts);
    }
    static $cache = [];
    $key = pp_locale() . '|' . $icu;
    if (!isset($cache[$key])) {
        // A dateline is furniture on every page. If intl is present but
        // refuses the locale or the pattern, fall back to an English date
        // rather than take the whole paper down over a masthead line.
        try {
            $cache[$key] = new IntlDateFormatter(
                pp_locale(),
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE,
                date_default_timezone_get(),
                null,
                $icu
            );
        } catch (Throwable) {
            $cache[$key] = false;
        }
    }
    try {
        $out = $cache[$key] === false ? false : $cache[$key]->format($ts);
    } catch (Throwable) {
        $out = false;
    }
    return $out === false ? date($fallback, $ts) : $out;
}

/** "Sunday, August 23, 2026" / "dimanche 23 août 2026" — a masthead dateline. */
function pp_date_full(?int $ts = null): string
{
    $ts ??= time();
    return pp_lang() === 'fr'
        ? pp_fmt($ts, 'EEEE d MMMM y', 'l, F j, Y')
        : date('l, F j, Y', $ts);
}

/** "August 23, 2026" / "23 août 2026" — a byline date. */
function pp_date_long(?int $ts = null): string
{
    $ts ??= time();
    return pp_lang() === 'fr'
        ? pp_fmt($ts, 'd MMMM y', 'F j, Y')
        : date('F j, Y', $ts);
}

/**
 * "6:15 a.m." / "6 h 15".
 * Quebec sets the hour, a hard space, 'h', a hard space, the minutes; the
 * spaces are non-breaking so a clock never wraps mid-value.
 */
function pp_clock(int $ts): string
{
    if (pp_lang() === 'fr') {
        return str_replace(' ', "\u{202F}", pp_fmt($ts, "H 'h' mm", 'H\\hi'));
    }
    return strtolower(str_replace(['AM', 'PM'], ['a.m.', 'p.m.'], date('g:i A', $ts)));
}

/**
 * French typographic spacing: a narrow no-break space before ; : ! ? and
 * inside guillemets. Applied to chrome strings the platform composes, never
 * to editorial copy — the newsroom types its own punctuation.
 */
function pp_fr_spacing(string $s): string
{
    if (pp_lang() !== 'fr') {
        return $s;
    }
    $s = preg_replace('/\s*([;:!?])/u', "\u{202F}$1", $s);
    $s = preg_replace('/«\s*/u', "«\u{202F}", $s);
    return preg_replace('/\s*»/u', "\u{202F}»", $s);
}
