<?php
/**
 * The linkifier: scans a story's body for names from the entity directory
 * and proposes anchors to their bio URLs. Pure text mechanics, no AI.
 *
 * Rules, in order: longest alias wins; word boundaries only; text already
 * inside a link is never touched, nor are crossheads; one link per entity
 * per story. The proposal is the rebuilt body — an editor approves or
 * rejects it whole.
 */

function pp_agent_linkify(array $post): array
{
    $entities = db()->query("SELECT id, name, url, aliases FROM entities WHERE enabled = 1 AND url <> ''")->fetchAll();
    if (!$entities) {
        return [false, null, 'the entity directory is empty (or nothing in it has a URL) — seed it under Entities'];
    }

    // Every alias, longest first, so "Dana Rowe-Smith" beats "Dana Rowe".
    $needles = [];
    foreach ($entities as $ent) {
        $aliases = array_filter(array_map('trim', (array) json_decode((string) ($ent['aliases'] ?? '[]'), true)));
        $aliases[] = trim((string) $ent['name']);
        foreach (array_unique($aliases) as $alias) {
            if (mb_strlen($alias) >= 4) {
                $needles[] = ['alias' => $alias, 'entity' => $ent];
            }
        }
    }
    usort($needles, fn ($a, $b) => mb_strlen($b['alias']) <=> mb_strlen($a['alias']));

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="pp-root">' . (string) $post['body'] . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);
    $root = $doc->getElementById('pp-root');
    if (!$root) {
        return [false, null, "the story body couldn't be parsed"];
    }

    $linked = [];   // entity id => alias actually linked
    $links = [];
    foreach ($needles as $n) {
        $ent = $n['entity'];
        if (isset($linked[$ent['id']])) {
            continue;
        }
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($n['alias'], '/') . '(?![\p{L}\p{N}])/u';
        // Text nodes only, never inside an existing link or a crosshead.
        foreach ($xpath->query('.//text()[not(ancestor::a) and not(ancestor::h1) and not(ancestor::h2) and not(ancestor::h3) and not(ancestor::h4)]', $root) as $node) {
            if (!preg_match($pattern, $node->nodeValue, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            // Byte offset from preg — split in byte terms, then wrap.
            $at = $m[0][1];
            $len = strlen($m[0][0]);
            $tail = $node->splitText($at + $len);
            $mid = $node->splitText($at);
            $a = $doc->createElement('a');
            $a->setAttribute('href', $ent['url']);
            $a->appendChild($doc->createTextNode($mid->nodeValue));
            $mid->parentNode->replaceChild($a, $mid);
            $linked[$ent['id']] = $n['alias'];
            $links[] = ['entity' => $ent['name'], 'alias' => $n['alias'], 'url' => $ent['url']];
            break;
        }
    }

    if (!$links) {
        return [false, null, 'no directory names appear in this story — nothing to propose'];
    }

    $proposed = '';
    foreach ($root->childNodes as $child) {
        $proposed .= $doc->saveHTML($child);
    }
    return [true, [
        'links' => $links,
        'proposed_body' => sanitize_html($proposed),
        // The body this proposal was built FROM — approval refuses to apply
        // over anything edited after this moment.
        'based_on' => (string) ($post['updated_at'] ?? ''),
    ], count($links) . ' link(s) proposed'];
}
