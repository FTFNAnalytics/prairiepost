<?php
/**
 * Read-only relational-integrity report. Counts orphans in every
 * meaningful relationship, lists identifiers sufficient for repair (ids
 * only — never content, so the output is CI-safe), proposes what a
 * constraint WOULD look like, and checks supporting-index coverage.
 *
 *   php tools/integrity-report.php            table report
 *   php tools/integrity-report.php --ids      include orphan ids (up to 20 per relation)
 *
 * It changes nothing. Constraint and repair decisions are the owner's:
 * see the sentinel notes below before believing any "orphan" here is a
 * defect — settings/audit rows with site_id 0 are GLOBAL metadata by
 * design, and seeded demo content carries author_id 1 with no user row
 * on fixtures, which a users foreign key would reject.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

$showIds = in_array('--ids', $argv, true);
$pdo = pp_db_connect();
$driver = pp_db_driver();
$status = pp_schema_status($pdo, $driver);
if (!in_array($status['state'], ['ready', 'behind'], true)) {
    fwrite(STDERR, "REFUSED: schema is {$status['state']} — nothing to audit here.\n");
    exit(2);
}

/**
 * relation => [child table, child col, parent table, parent col,
 *              orphan WHERE qualifier ('' = plain), sentinel note]
 */
$relations = [
    'post_sites.post_id → posts.id'        => ['post_sites', 'post_id', 'posts', 'id', '', ''],
    'post_sites.site_id → sites.id'        => ['post_sites', 'site_id', 'sites', 'id', '', ''],
    'post_tags.post_id → posts.id'         => ['post_tags', 'post_id', 'posts', 'id', '', ''],
    'post_tags.tag_id → tags.id'           => ['post_tags', 'tag_id', 'tags', 'id', '', ''],
    'post_revisions.post_id → posts.id'    => ['post_revisions', 'post_id', 'posts', 'id', '', ''],
    'posts.author_id → users.id'           => ['posts', 'author_id', 'users', 'id', 'c.author_id IS NOT NULL AND c.author_id != 0',
        'seeded demo content ships author_id 1 before any user exists — an FK here would reject every launch pack'],
    'posts.category_id → categories.id'    => ['posts', 'category_id', 'categories', 'id', 'c.category_id IS NOT NULL', ''],
    'posts.canonical_site_id → sites.id'   => ['posts', 'canonical_site_id', 'sites', 'id', 'c.canonical_site_id IS NOT NULL', ''],
    'news_items.source_id → sources.id'    => ['news_items', 'source_id', 'sources', 'id', '', ''],
    'subscribers.site_id → sites.id'       => ['subscribers', 'site_id', 'sites', 'id', '', ''],
    'settings.site_id → sites.id'          => ['settings', 'site_id', 'sites', 'id', 'c.site_id != 0',
        'site_id 0 is the GLOBAL sentinel (schema_version lives there) — a naive FK would reject it'],
    'audit_log.site_id → sites.id'         => ['audit_log', 'site_id', 'sites', 'id', 'c.site_id != 0',
        'site_id 0 records global actions (founding the first admin)'],
    'ads.site_id → sites.id'               => ['ads', 'site_id', 'sites', 'id', '', ''],
    'ads.campaign_id → campaigns.id'       => ['ads', 'campaign_id', 'campaigns', 'id', 'c.campaign_id IS NOT NULL AND c.campaign_id != 0', ''],
    'social_shares.post_id → posts.id'     => ['social_shares', 'post_id', 'posts', 'id', '', ''],
    'story_sources.post_id → posts.id'     => ['story_sources', 'post_id', 'posts', 'id', '', ''],
    'agent_tasks.post_id → posts.id'       => ['agent_tasks', 'post_id', 'posts', 'id', '', ''],
    'domains.site_slug → sites.slug'       => ['domains', 'site_slug', 'sites', 'slug', '', ''],
    'media_requests.client_id → media_clients.id' => ['media_requests', 'client_id', 'media_clients', 'id', '', ''],
    'media_request_sites.request_id → media_requests.id' => ['media_request_sites', 'request_id', 'media_requests', 'id', '', ''],
    'media_orders.request_id → media_requests.id' => ['media_orders', 'request_id', 'media_requests', 'id', '', ''],
];

function ir_index_covers(PDO $pdo, string $driver, string $table, string $column): bool
{
    // "Covered" = some index whose FIRST column is $column (a composite
    // starting elsewhere doesn't serve the lookup).
    if ($driver === 'sqlite') {
        foreach ($pdo->query("PRAGMA index_list(" . preg_replace('/\W/', '', $table) . ")") as $ix) {
            foreach ($pdo->query("PRAGMA index_info(" . preg_replace('/\W/', '', (string) $ix['name']) . ")") as $col) {
                if ((int) $col['seqno'] === 0 && strcasecmp((string) $col['name'], $column) === 0) {
                    return true;
                }
            }
        }
        // The INTEGER PRIMARY KEY is its own index.
        foreach ($pdo->query('PRAGMA table_info(' . preg_replace('/\W/', '', $table) . ')') as $c) {
            if ((int) $c['pk'] === 1 && strcasecmp((string) $c['name'], $column) === 0) {
                return true;
            }
        }
        return false;
    }
    if ($driver === 'pgsql') {
        $s = $pdo->prepare("SELECT 1 FROM pg_index i JOIN pg_class t ON t.oid = i.indrelid
            JOIN pg_namespace n ON n.oid = t.relnamespace
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = i.indkey[0]
            WHERE n.nspname = ? AND t.relname = ? AND a.attname = ?");
        $s->execute([pp_pg_schema(), $table, $column]);
        return $s->fetch() !== false;
    }
    $s = $pdo->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? AND seq_in_index = 1');
    $s->execute([$table, $column]);
    return $s->fetch() !== false;
}

printf("Relational integrity — read-only. Driver %s, schema version %s.\n\n", $driver, $status['version']);
printf("%-52s %8s  %-7s  %s\n", 'relation', 'orphans', 'index', 'note');

$totalOrphans = 0;
foreach ($relations as $label => [$child, $col, $parent, $pcol, $where, $note]) {
    try {
        $cond = $where !== '' ? "($where) AND" : '';
        $sql = "SELECT COUNT(*) FROM $child c WHERE $cond NOT EXISTS (SELECT 1 FROM $parent p WHERE p.$pcol = c.$col)";
        $orphans = (int) $pdo->query($sql)->fetchColumn();
    } catch (PDOException $e) {
        printf("%-52s %8s  %-7s  table absent on this version\n", $label, '-', '-');
        continue;
    }
    $totalOrphans += $orphans;
    $covered = ir_index_covers($pdo, $driver, $child, $col) ? 'yes' : 'NO';
    printf("%-52s %8d  %-7s  %s\n", $label, $orphans, $covered, $note);
    if ($orphans > 0 && $showIds) {
        $ids = $pdo->query(str_replace('COUNT(*)', "c.$col", $sql) . ' LIMIT 20')->fetchAll(PDO::FETCH_COLUMN);
        echo '    orphaned ' . $col . ' values: ' . implode(', ', array_unique($ids)) . "\n";
    }
}

echo "\n";
echo "Proposed constraint behavior (owner decision — NOT applied):\n";
echo "  - Join/membership rows (post_sites, post_tags): FK with ON DELETE CASCADE would\n";
echo "    mirror what admin/posts.php already deletes by hand. Requires table rebuilds on\n";
echo "    SQLite and identical treatment in fresh DDL + a migration to keep install parity.\n";
echo "  - Content and provenance rows (posts, post_revisions, story_sources, audit_log):\n";
echo "    NO cascade — history must outlive convenience. RESTRICT at most.\n";
echo "  - Sentinels: settings.site_id=0 and audit_log.site_id=0 are metadata by design;\n";
echo "    any FK there needs the 0 rows exempted (a partial/NOT VALID constraint on\n";
echo "    Postgres; not expressible as a plain FK on SQLite).\n";
echo "  - posts.author_id: leave unconstrained while launch packs seed author 1 pre-users.\n";
echo "\n$totalOrphans orphaned row(s) across all audited relations. Nothing was changed.\n";
exit(0);
