<?php
/**
 * Seed the entity directory with Canada's elected officials, from Open
 * North's free Represent API — MPs and the provincial legislatures the
 * network covers by default, or any representative sets you name:
 *
 *   PP_SITE=civismedia php tools/import-represent.php
 *   PP_SITE=civismedia php tools/import-represent.php house-of-commons ontario-legislature
 *
 * Existing entities are never overwritten — the import skips a name whose
 * slug is already in the directory, so curation always wins. Re-running
 * is safe. CLI only.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}
if (!pp_is_hub()) {
    exit("The entity directory belongs to the hub — run with PP_SITE=civismedia.\n");
}

$base = rtrim(getenv('PP_REPRESENT_BASE') ?: 'https://represent.opennorth.ca', '/');
$sets = array_slice($argv, 1) ?: ['house-of-commons', 'alberta-legislature', 'bc-legislature', 'legislative-assembly-of-ontario'];

$db = db();
$probe = $db->prepare('SELECT 1 FROM entities WHERE slug = ?');
$insert = $db->prepare('INSERT INTO entities (name, slug, kind, url, aliases, enabled, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)');
$totalAdded = 0;
$totalSkipped = 0;

foreach ($sets as $set) {
    $added = 0;
    $skipped = 0;
    $offset = 0;
    while (true) {
        $url = "$base/representatives/" . rawurlencode($set) . "/?limit=100&offset=$offset";
        [$body, $err] = http_get($url, 30);
        if ($body === null) {
            echo "ERROR $set: $err\n";
            break;
        }
        $data = json_decode($body, true);
        $objects = (array) ($data['objects'] ?? []);
        if (!$objects) {
            break;
        }
        foreach ($objects as $rep) {
            $name = trim((string) ($rep['name'] ?? ''));
            if ($name === '' || mb_strlen($name) < 4) {
                $skipped++;
                continue;
            }
            $slug = slugify($name);
            $probe->execute([$slug]);
            if ($probe->fetch()) {
                $skipped++;
                continue;
            }
            // The official's own page beats the source listing when present.
            $bio = trim((string) ($rep['personal_url'] ?? '')) ?: trim((string) ($rep['url'] ?? ''));
            if (!preg_match('#^https?://#i', $bio)) {
                $skipped++;
                continue;
            }
            // District and role make useful context, not aliases — aliases
            // stay empty for admins to curate (nicknames, honorifics).
            $insert->execute([mb_substr($name, 0, 160), $slug, 'politician', mb_substr($bio, 0, 600), '[]', now()]);
            $added++;
        }
        $next = $data['meta']['next'] ?? null;
        if (!$next) {
            break;
        }
        $offset += 100;
        if ($offset > 5000) {
            echo "WARN $set: stopped at $offset — that set is unexpectedly large\n";
            break;
        }
    }
    echo "$set: $added added, $skipped skipped\n";
    $totalAdded += $added;
    $totalSkipped += $skipped;
}

echo "----\n$totalAdded added, $totalSkipped skipped (already known, no URL, or unusable). Curate under /admin → Entities.\n";
