<?php
/**
 * Opening a paper to search engines, from its Settings page.
 *
 * Every page carries noindex until the paper's `indexing_enabled` setting is
 * '1' (pp_indexing_enabled(), app/helpers.php). Settings sets it with one
 * button, and refuses while either of these is true, because each would
 * put the wrong thing in front of search engines:
 *
 *   - the paper's pages name another site as their address. site_url()
 *     must be the host the newsroom is working on, or one of the paper's
 *     own hostnames. Thirteen papers printed the flagship's address until
 *     #101 (PLAN.md 10.0), and indexing them would have sent search
 *     engines to another paper. After #101 only a configured address that
 *     isn't the paper's can trip this;
 *   - one of its demonstration launch stories is still live. Those are
 *     invented editorial about real places, signed by people who don't
 *     exist, and come down before search engines see the paper (CLAUDE.md,
 *     debt 1; PLAN.md 10.3).
 *
 * Two more items are shown and don't refuse, because they wait on the
 * owner: an About page with no text of its own, and the network's missing
 * privacy page (PLAN.md 10.5).
 *
 * Hiding a paper again is never refused. Loaded by admin/settings.php, and
 * by tools/demo-inventory.php, which reports the same demonstration stories.
 */

/**
 * The slugs of the stories a paper launched with as demonstration copy:
 * its launch pack's stories when the pack says 'demonstration' => true,
 * and the core seed's sample stories for the founding paper. A slug is the
 * one the seeder gives it: the story's own 'slug', else its title's.
 *
 * So a demonstration story keeps its title (or gains its old slug as
 * 'slug') in its pack for good: production's copy has the slug it was
 * seeded with, and a retitled entry would stop finding it. Checked against
 * every pack's history on 26 Nov 2026: no seeded story has been retitled.
 * Western Wire's pre-launch titles that changed were never its own
 * stories; they were its sister papers' slugs, skipped as taken.
 */
function pp_demonstration_slugs(string $siteSlug): array
{
    $stories = [];
    $file = PP_ROOT . '/assets/sites/' . basename($siteSlug) . '/launch.php';
    if ($siteSlug !== '' && is_file($file)) {
        $pack = (static fn (string $f) => require $f)($file);
        if (is_array($pack) && !empty($pack['demonstration'])) {
            $stories = (array) ($pack['stories'] ?? []);
        }
    }
    if ($siteSlug === 'prairiedispatch') {
        require_once PP_ROOT . '/app/seed.php';
        $stories = array_merge($stories, pp_seed_stories());
    }
    $slugs = [];
    foreach ($stories as $s) {
        $slugs[] = (string) ($s['slug'] ?? slugify((string) ($s['title'] ?? '')));
    }
    return array_values(array_unique(array_filter($slugs)));
}

/**
 * What stands between this paper and search engines:
 *   'blockers' — reasons the Open button refuses, in plain words;
 *   'notes'    — things worth knowing that don't refuse;
 *   'address'  — the address its pages name;
 *   'live'     — its demonstration stories still published or scheduled,
 *                as rows of id, title and status.
 */
function pp_indexing_readiness(): array
{
    $site = current_site();
    $address = site_url();
    $host = strtolower((string) parse_url($address, PHP_URL_HOST));
    $bare = static fn (string $h): string => preg_replace('/^www\./', '', strtolower(preg_replace('/:\d+$/', '', $h)));
    $here = $bare((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $blockers = [];
    $notes = [];

    $own = $host !== '' && (($here !== '' && $bare($host) === $here) || in_array((string) $site['slug'], pp_host_claimants($host), true));
    if (!$own) {
        $blockers[] = 'Its pages name ' . ($host !== '' ? $host : 'no host at all') . ' as their address, which is not one of'
            . ' this paper\'s hostnames, so search engines would file its pages under another site. The address comes from'
            . ' the server\'s configuration and the paper\'s hostnames (CLAUDE.md, lesson 10b).';
    }

    $live = [];
    $slugs = pp_demonstration_slugs((string) $site['slug']);
    if ($slugs) {
        $marks = implode(',', array_fill(0, count($slugs), '?'));
        $stmt = db()->prepare("SELECT p.id, p.title, p.status FROM posts p JOIN post_sites ps ON ps.post_id = p.id
            WHERE ps.site_id = ? AND p.status IN ('published', 'scheduled') AND p.slug IN ($marks) ORDER BY p.id");
        $stmt->execute(array_merge([(int) $site['id']], $slugs));
        $live = $stmt->fetchAll();
    }
    if ($live) {
        $blockers[] = count($live) . ' of its demonstration launch stories ' . (count($live) === 1 ? 'is' : 'are')
            . ' still live: invented editorial about real places, signed by people who don\'t exist.'
            . ' Move ' . (count($live) === 1 ? 'it' : 'them') . ' to draft: in Stories, tick '
            . (count($live) === 1 ? 'it' : 'them') . ', choose Draft under "Change status to…", then Apply.';
    }

    if (trim((string) setting('about_body')) === '') {
        $notes[] = 'Its About page has no text of its own yet, so it shows only the one-line search description.';
    }
    $notes[] = 'The network has no privacy page yet' . (trim((string) setting('analytics_code')) !== ''
        ? ', and this paper loads an analytics tag.' : '.');

    return ['blockers' => $blockers, 'notes' => $notes, 'address' => $address, 'live' => $live];
}

/** The last time this paper was opened to or hidden from search engines: the audit row, or null. */
function pp_indexing_last_change(): ?array
{
    $stmt = db()->prepare("SELECT action, user_name, created_at FROM audit_log
        WHERE site_id = ? AND action IN ('indexing.on', 'indexing.off') ORDER BY id DESC LIMIT 1");
    $stmt->execute([current_site_id()]);
    $row = $stmt->fetch();
    return $row ?: null;
}
