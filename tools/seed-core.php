<?php
/**
 * Seed the network's base content — the founding site (from the config
 * site_slug), its desks, default settings, demo stories and the roadmap —
 * exactly what a fresh boot used to seed implicitly. Now an explicit,
 * deliberate step (F12): schema preparation (tools/migrate.php --apply)
 * and content seeding are different decisions.
 *
 *   php tools/seed-core.php
 *
 * Idempotent at the site level: if the founding site's row already exists
 * this does nothing and says so. Per-paper launch content stays where it
 * was: PP_SITE=<slug> php tools/seed-launch.php.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';
require_once PP_ROOT . '/app/seed.php';

$pdo = pp_db_connect();
$status = pp_schema_status($pdo, pp_db_driver());
if ($status['state'] !== 'ready') {
    fwrite(STDERR, "REFUSED: schema is {$status['state']} — prepare it first: php tools/migrate.php --apply\n");
    exit(2);
}

$slug = slugify((string) pp_config('site_slug', 'prairiedispatch'));
$stmt = $pdo->prepare('SELECT id FROM sites WHERE slug = ?');
$stmt->execute([$slug]);
if ($stmt->fetch()) {
    echo "Base content already seeded (site '$slug' exists) — nothing to do.\n";
    exit(0);
}

pp_seed($pdo);
echo "Base content seeded: founding site '$slug', desks, settings, demo stories, roadmap.\n";
