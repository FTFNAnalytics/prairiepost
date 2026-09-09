<?php
/**
 * Content-safety layer: parser-based HTML sanitization and context-correct
 * encoding for JSON embedded in HTML.
 *
 * The old sanitize_html() was a strip_tags + three regexes; entity-encoded
 * URL schemes (jav&#x61;script:) walked straight through it, because a
 * regex never sees what a browser sees. Sanitization has to run on the
 * parsed document, so it is delegated to HTML Purifier (vendored, pinned —
 * see composer.lock and docs/build/phase-01-handoff.md), which decodes
 * entities, normalizes malformed markup and validates URI schemes the way
 * a browser will read them.
 */

/** The one shared purifier instance, configured for editorial copy. */
function pp_purifier(): HTMLPurifier
{
    static $purifier = null;
    if ($purifier !== null) {
        return $purifier;
    }

    require_once PP_ROOT . '/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';

    $config = HTMLPurifier_Config::createDefault();
    // The editorial allowlist — every tag the story editor can produce and
    // nothing else. Attributes are per-element; anything unlisted is dropped.
    $config->set('HTML.Allowed', implode(',', [
        'p[style]', 'br', 'strong', 'em', 'b', 'i', 'u', 's',
        'a[href|title|target]',
        'h2', 'h3', 'blockquote[cite]', 'cite', 'ul', 'ol[start]', 'li',
        'figure', 'figcaption',
        'img[src|alt|width|height|loading]',
        'table', 'thead', 'tbody', 'tr',
        'td[colspan|rowspan|style]', 'th[colspan|rowspan|scope|style]',
        'hr', 'div[style]', 'span[style]',
    ]));
    // Executable and document-smuggling schemes are simply not in this set;
    // data: stays out entirely (uploads produce /uploads paths, never data:).
    $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
    // The style attribute survives only for the handful of properties the
    // editor's formatting buttons can produce.
    $config->set('CSS.AllowedProperties', [
        'text-align' => true, 'font-weight' => true, 'font-style' => true,
        'text-decoration' => true, 'padding-left' => true,
    ]);
    $config->set('Attr.AllowedFrameTargets', ['_blank']);
    $config->set('HTML.TargetNoopener', true);
    $config->set('HTML.TargetNoreferrer', true);

    // Definition cache: fast after first use. data/ is the app's writable
    // tree; when it isn't writable (read-only release mid-roll) the purifier
    // just rebuilds definitions per request instead of failing.
    $cacheDir = PP_ROOT . '/data/cache/htmlpurifier';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    if (is_dir($cacheDir) && is_writable($cacheDir)) {
        $config->set('Cache.SerializerPath', $cacheDir);
    } else {
        $config->set('Cache.DefinitionImpl', null);
    }

    // figure/figcaption postdate HTML 4.01, so they are taught explicitly;
    // same for img's loading attribute. Bump the revision when this changes
    // so serialized definition caches invalidate.
    $config->set('HTML.DefinitionID', 'pp-editorial');
    $config->set('HTML.DefinitionRev', 1);
    if ($def = $config->maybeGetRawHTMLDefinition()) {
        $def->addElement('figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common');
        $def->addElement('figcaption', 'Inline', 'Flow', 'Common');
        $def->addAttribute('img', 'loading', 'Enum#lazy,eager');
    }

    $purifier = new HTMLPurifier($config);
    return $purifier;
}

/**
 * Encode data for a JSON context inside an HTML document (JSON-LD blocks,
 * inline `var X = …;` assignments). The HEX flags make it impossible for a
 * value to terminate the surrounding <script> element or open a comment/
 * CDATA context, whatever the data contains. Returns '{}' / '""'-safe
 * output even when encoding fails, so a template never interpolates PHP
 * `false` into a script block.
 */
function pp_json_for_html(mixed $data): string
{
    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
           | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
    $json = json_encode($data, $flags);
    if ($json === false) {
        return is_array($data) || is_object($data) ? '{}' : '""';
    }
    return $json;
}
