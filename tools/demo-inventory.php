<?php
/**
 * Inventory the network's demonstration content, read-only (F06).
 *
 *   php tools/demo-inventory.php            summary per paper
 *   php tools/demo-inventory.php --csv      one row per matched story
 *
 * Provenance, not title-guessing: a launch pack that says
 * 'demonstration' => true declares its stories demonstration copy, and the
 * core seed's sample stories are the founding paper's. The list is
 * pp_demonstration_slugs() (app/indexing.php), the one Settings checks
 * before it opens a paper to search engines, and a story counts for the
 * paper it is mapped to, as it does there. The newer papers' inaugural
 * editions are about the papers themselves, true by construction, and
 * aren't demonstration copy. This tool never deletes: moving the stories
 * to draft is the newsroom's editorial decision, made from this report.
 */

require dirname(__DIR__) . '/app/bootstrap.php';
require_once PP_ROOT . '/app/indexing.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$csv = in_array('--csv', $argv, true);
$pdo = db();

$onPaper = $pdo->prepare('SELECT p.id, p.status FROM posts p JOIN post_sites ps ON ps.post_id = p.id
    JOIN sites s ON s.id = ps.site_id WHERE s.slug = ? AND p.slug = ?');
$siteRow = $pdo->prepare('SELECT id, name FROM sites WHERE slug = ?');

if ($csv) {
    echo "site,post_id,slug,status,origin\n";
}

$totalDemo = 0;
foreach (glob(PP_ROOT . '/assets/sites/*', GLOB_ONLYDIR) ?: [] as $dir) {
    $siteSlug = basename($dir);
    $slugs = pp_demonstration_slugs($siteSlug);
    if (!$slugs) {
        continue;
    }
    $siteRow->execute([$siteSlug]);
    $site = $siteRow->fetch();

    $found = [];
    $missing = 0;
    foreach ($slugs as $slug) {
        $onPaper->execute([$siteSlug, $slug]);
        if ($row = $onPaper->fetch()) {
            $found[] = ['slug' => $slug, 'id' => (int) $row['id'], 'status' => $row['status']];
            $totalDemo++;
            if ($csv) {
                printf("%s,%d,%s,%s,demonstration\n", $siteSlug, (int) $row['id'], $slug, $row['status']);
            }
        } else {
            $missing++;
        }
    }

    if (!$csv) {
        $live = count(array_filter($found, fn ($f) => in_array($f['status'], ['published', 'scheduled'], true)));
        printf("%-24s %sdeclares %2d demonstration stor%s: %2d on the paper (%d live), %d not present%s\n",
            $siteSlug,
            $site ? '' : '(no site row) ',
            count($slugs), count($slugs) === 1 ? 'y' : 'ies',
            count($found), $live, $missing,
            $missing ? ' (never seeded here, or deleted)' : '');
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
    echo "\n$totalDemo demonstration stor" . ($totalDemo === 1 ? 'y' : 'ies') . " present across the network.\n";
    echo "Read-only: nothing was changed. Settings won't open a paper to search engines while any of its\n"
        . "demonstration stories is live (published or scheduled).\n";
}
