<?php
/**
 * Repair and top up the wire sources, from a list verified against the
 * live feeds before it was written here.
 *
 *   php tools/fix-sources.php            # dry run: says what it would do
 *   php tools/fix-sources.php --apply    # does it
 *
 * Why a tool and not the seeder: tools/seed-launch.php matches sources by
 * URL and only ever INSERTs. A feed whose URL has gone stale therefore
 * cannot be repaired by re-seeding — the corrected URL arrives as a
 * second row and the broken one keeps its place in the rotation. Fixing
 * a URL in place is exactly the operation the seeder does not do.
 *
 * Why the additions matter as much as the repairs: news_items.url_hash is
 * UNIQUE across the whole table, and sources are matched by URL, so one
 * feed can only ever fill one region bucket. A bucket cannot be rescued
 * by pointing a neighbouring bucket's feed at it; it needs a feed of its
 * own. Several packs list CBC Toronto for `ontario` and `durham` after
 * another pack has already claimed that URL for `gta` — those entries
 * have never done anything, which is why `ontario` ran on one source and
 * `durham` on none.
 *
 * Everything here is idempotent, and nothing is ever deleted. One narrow
 * disable exists, learned from the first production run: if the packs
 * were re-seeded BEFORE this ran, the seeder inserted the replacement
 * URLs as new rows, so "the new URL exists" no longer proves the repair
 * happened — the old broken row is still there, still fetched every
 * cycle, still erroring. In that one case the old row is retired
 * (enabled = 0, audited), because a feed on this list's left-hand side
 * is broken by definition and re-fetching it forever helps nobody.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

$apply = in_array('--apply', array_slice($argv, 1), true);

/**
 * [current URL to find, new name, new URL, region].
 * Each new URL was fetched and parsed through the application's own
 * http_get()/parse_feed() before being listed, and the item count is the
 * one that call returned.
 */
$repairs = [
    // A one-character break: the CBC path is case-sensitive. 20 items.
    ['https://www.cbc.ca/webfeed/rss/rss-canada-indigenous',
     'CBC Indigenous', 'https://www.cbc.ca/webfeed/rss/rss-Indigenous', 'national'],

    // windspeaker.com answers 404 on every feed path it used to serve.
    // The Turtle Island News covers the same national beat. 10 items.
    ['https://windspeaker.com/rss.xml',
     'The Turtle Island News', 'https://theturtleislandnews.com/index.php/feed/', 'national'],

    // Radio-Canada retired feed 5877. 1000524 is the rolling national
    // wire — renamed rather than relabelled, because it is not the
    // économie feed and pretending otherwise would mislead a desk. 50 items.
    ['https://ici.radio-canada.ca/rss/5877',
     'Radio-Canada · En continu', 'https://ici.radio-canada.ca/rss/1000524', 'national'],

    // tvo.org/feeds/rss/all now returns the site's HTML shell — the feed
    // is gone, not moved. The Trillium covers Queen's Park. 20 items.
    ['https://www.tvo.org/feeds/rss/all',
     'The Trillium', 'https://www.thetrillium.ca/rss', 'ontario'],

    // bramptonist.com stopped completing a TLS handshake. 10 items.
    ['https://www.bramptonist.com/feed/',
     'Brampton Focus', 'https://bramptonfocus.ca/feed', 'brampton'],
];

/**
 * [name, URL, region] — buckets that were running on a single feed, or
 * on none. Same verification: fetched and parsed before being listed.
 */
$additions = [
    ['Durham Radio News',        'https://www.durhamradionews.com/feed',        'durham'],   // 50; bucket was empty
    ['The Oshawa Express',       'https://www.oshawaexpress.ca/feed/',          'durham'],   // 10
    ['SooToday',                 'https://www.sootoday.com/rss',                'northern'], // 20; bucket had one feed
    ['Northern Ontario Business','https://www.northernontariobusiness.com/rss', 'northern'], // 20
    ['The London Free Press',    'https://www.lfpress.com/feed',                'ontario'],  // 10; so ontario is not one feed again
];

$pdo = db();
$byUrl   = $pdo->prepare('SELECT id, name, url, region, enabled FROM sources WHERE url = ?');
$update  = $pdo->prepare('UPDATE sources SET name = ?, url = ?, region = ?, enabled = 1 WHERE id = ?');
$insert  = $pdo->prepare('INSERT INTO sources (name, url, region, enabled) VALUES (?, ?, ?, 1)');
$changed = 0;

echo $apply ? "Applying.\n\n" : "Dry run — nothing is written. Add --apply to do it.\n\n";

echo "== REPAIRS\n";
$retire = $pdo->prepare('UPDATE sources SET enabled = 0 WHERE id = ?');
foreach ($repairs as [$oldUrl, $name, $newUrl, $region]) {
    $byUrl->execute([$newUrl]);
    $newRow = $byUrl->fetch();
    $byUrl->execute([$oldUrl]);
    $row = $byUrl->fetch();
    if ($newRow) {
        if ($row && !empty($row['enabled'])) {
            printf("  %-13s %-28s old row still enabled alongside its replacement — disabling it\n",
                $apply ? 'retiring' : 'would retire', $row['name']);
            if ($apply) {
                $retire->execute([(int) $row['id']]);
                pp_audit('source.retired', (string) $row['name'], "broken feed {$row['url']} disabled; replaced by {$newUrl}", ['id' => 0, 'name' => 'cli:fix-sources']);
                $changed++;
            }
        } else {
            printf("  done already  %-28s %s\n", $name, $newUrl);
        }
        continue;
    }
    if (!$row) {
        printf("  NOT FOUND     %-28s no row with url %s\n", $name, $oldUrl);
        continue;
    }
    printf("  %-13s %-28s %s\n           -> %s\n", $apply ? 'repairing' : 'would fix', $row['name'], $row['url'], $newUrl);
    if ($apply) {
        $update->execute([$name, $newUrl, $region, (int) $row['id']]);
        pp_audit('source.repaired', $name, "{$row['url']} -> {$newUrl}", ['id' => 0, 'name' => 'cli:fix-sources']);
        $changed++;
    }
}

echo "\n== ADDITIONS\n";
foreach ($additions as [$name, $url, $region]) {
    $byUrl->execute([$url]);
    if ($byUrl->fetch()) {
        printf("  exists        %-28s %s\n", $name, $url);
        continue;
    }
    printf("  %-13s %-28s %s  [%s]\n", $apply ? 'adding' : 'would add', $name, $url, $region);
    if ($apply) {
        $insert->execute([$name, $url, $region]);
        pp_audit('source.added', $name, "{$url} into {$region}", ['id' => 0, 'name' => 'cli:fix-sources']);
        $changed++;
    }
}

echo "\n";
if (!$apply) {
    echo "Nothing written. Re-run with --apply, then fetch once from\n";
    echo "/admin -> Sources -> 'Fetch now', and confirm with tools/wire-map.php.\n";
    exit;
}
echo "{$changed} change(s) written. Now fetch once — /admin -> Sources -> 'Fetch now',\n";
echo "or wait for the cron — then run tools/wire-map.php: `ontario`, `national`,\n";
echo "`brampton`, `durham` and `northern` should all leave the SILENT list.\n";
echo "\nStill unsolved, and not guessed at here: `tri-cities` and `saguenay` have\n";
echo "no working feed I could verify. They stay DEAD until someone finds one.\n";
