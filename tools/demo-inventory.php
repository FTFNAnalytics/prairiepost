<?php
/**
 * Inventory the launch-pack demonstration content, read-only (F06).
 *
 *   php tools/demo-inventory.php            summary per paper
 *   php tools/demo-inventory.php --csv      one row per matched story
 *
 * Provenance, not title-guessing: each paper's launch pack
 * (assets/sites/<slug>/launch.php) declares its demonstration stories,
 * and this tool matches those declared slugs against the database. What
 * it never does is delete — the launch-readiness cleanup is a later
 * phase's editorial decision, made from this report.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$csv = in_array('--csv', $argv, true);
$pdo = db();

$bySlug = $pdo->prepare('SELECT id, status, filed_by, published_at FROM posts WHERE slug = ?');
$siteRow = $pdo->prepare('SELECT id, name FROM sites WHERE slug = ?');

if ($csv) {
    echo "site,post_id,slug,status,origin\n";
}

$totalDemo = 0;
foreach (glob(PP_ROOT . '/assets/sites/*/launch.php') ?: [] as $packFile) {
    $siteSlug = basename(dirname($packFile));
    $pack = require $packFile;
    $stories = (array) ($pack['stories'] ?? []);
    $siteRow->execute([$siteSlug]);
    $site = $siteRow->fetch();

    $found = [];
    $missing = 0;
    foreach ($stories as $story) {
        $slug = (string) ($story['slug'] ?? slugify((string) ($story['title'] ?? '')));
        if ($slug === '') {
            continue;
        }
        $bySlug->execute([$slug]);
        if ($row = $bySlug->fetch()) {
            $found[] = ['slug' => $slug, 'id' => (int) $row['id'], 'status' => $row['status']];
            $totalDemo++;
            if ($csv) {
                printf("%s,%d,%s,%s,launch-pack\n", $siteSlug, (int) $row['id'], $slug, $row['status']);
            }
        } else {
            $missing++;
        }
    }

    if (!$csv) {
        $live = count(array_filter($found, fn ($f) => $f['status'] === 'published'));
        printf("%-24s %s pack declares %2d demo stor%s: %2d in the database (%d published), %d not present%s\n",
            $siteSlug,
            $site ? '' : '(no site row) ',
            count($stories), count($stories) === 1 ? 'y' : 'ies',
            count($found), $live, $missing,
            $missing ? ' (never seeded here, or already replaced)' : '');
    }
}

if (!$csv) {
    // Agent filings and human stories, for the full picture per paper.
    echo "\n";
    foreach ($pdo->query('SELECT s.slug, COUNT(p.id) AS n,
                                 SUM(CASE WHEN COALESCE(p.filed_by, \'\') != \'\' THEN 1 ELSE 0 END) AS agent
                          FROM sites s
                          LEFT JOIN post_sites ps ON ps.site_id = s.id
                          LEFT JOIN posts p ON p.id = ps.post_id
                          GROUP BY s.slug ORDER BY s.slug') as $r) {
        printf("%-24s %3d post(s) mapped, %d agent-filed\n", $r['slug'], (int) $r['n'], (int) $r['agent']);
    }
    echo "\n$totalDemo launch-pack demonstration stor" . ($totalDemo === 1 ? 'y' : 'ies') . " present across the network.\n";
    echo "Read-only: nothing was changed. The launch-readiness cleanup is a later phase.\n";
}
