<?php
/**
 * Issue, list, and revoke Hermes ingest tokens. The only writer of the
 * ingest_agents table — tokens never enter the repository, the database
 * stores only their sha256, and the raw token is printed exactly once.
 *
 *   php tools/make-agent.php create --name hermes-bleuet \
 *       --sites bleuet-blanc [--desks actualites,economie] [--draft-only]
 *       [--lookup own|all] [--update-published] [--read published|all]
 *   php tools/make-agent.php list
 *   php tools/make-agent.php revoke --name hermes-bleuet
 *   php tools/make-agent.php enable --name hermes-bleuet
 *
 * Scoping: --sites is required (comma-separated slugs; every slug must be
 * a real site). --desks is optional; empty means any existing desk on the
 * scoped sites. Revocation is the kill switch: a revoked token answers
 * 401 on the next request, identically to a token that never existed.
 *
 * --lookup grants the separate article:lookup permission
 * (ingest_agents.lookup_scope) behind the read-only
 * POST /api/ingest/lookup: 'own' answers over the token's own filings
 * plus published stories, 'all' over the full editorial queue within the
 * token's papers and desks — which is what finding editor-created or
 * other filers' drafts requires. Omitted means NO lookup: the endpoint
 * refuses the token outright. Like draft_only, the grant is a property
 * of the credential, set at minting, never of a request.
 *
 * --update-published grants the one exception to "published stories
 * change only at an editor's desk" (ingest_agents.update_published):
 * POST /api/ingest/update may change the headline, lede, body and image
 * of a PUBLISHED story within the token's papers and desks — never its
 * URL, status, dates, byline, desk or correction note; every change is
 * snapshotted into the story's history and audited. Independent of
 * --draft-only: a draft-only token holding it still publishes nothing
 * new, but can edit what is already live. Omitted means no such power.
 *
 * --read grants GET /api/ingest/stories (ingest_agents.read_scope):
 * 'published' reads the paper's live stories, 'all' every story in the
 * editorial queue too — drafts, review and scheduled, whoever made them,
 * editors included. Omitted means no reading: the endpoint refuses the
 * token. Strict like --lookup: any other value mints nothing.
 *
 * --draft-only mints a credential that can file but never publish
 * (ingest_agents.draft_only = 1): the paper's wire_desks exception and
 * the publish_on_image flag are withheld for it server-side, and
 * /api/ingest-publish refuses it. Everything it files waits for the
 * newsroom. This is the credential an external evidence feed must hold
 * before it delivers to any desk (milestone NEWS1). The flag is set at
 * minting and is a property of the credential, never of a request.
 *
 * Arguments are strict, because this tool mints credentials: anything it
 * does not recognise stops it with exit code 2 before the database is
 * opened, and so does an option given to a subcommand it means nothing
 * to. A mistyped --draft_only therefore mints nothing — it can never
 * fall through to a standard, publish-capable token.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

$cmd = $argv[1] ?? '';

const PP_AGENT_USAGE = 'Usage: php tools/make-agent.php create --name <n> --sites <a,b> [--desks <c,d>] [--draft-only] [--lookup own|all] [--update-published] [--read published|all]'
    . ' | list | revoke --name <n> | enable --name <n>';

/** Stop having changed nothing: the reason on stderr, a non-zero exit. */
function pp_agent_refuse(string $message): never
{
    fwrite(STDERR, $message . "\nNothing was created or changed.\n" . PP_AGENT_USAGE . "\n");
    exit(2);
}

// The options each subcommand understands. An argument this tool does not
// recognise STOPS it: when unknown arguments were skipped, a mistyped
// `create … --draft_only` (or `--draft-only=1`) minted a standard,
// publish-capable token and exited 0. An option that means nothing to the
// subcommand it was given to is an error too, never a silent no-op. All of
// this runs before the database is opened, so a refusal writes nothing.
$allowed = [
    'create' => ['name', 'sites', 'desks', 'draft-only', 'lookup', 'update-published', 'read'],
    'list'   => [],
    'revoke' => ['name'],
    'enable' => ['name'],
];
// getopt() stops at the first non-option (the subcommand), so parse by hand.
$opt = [];
if (isset($allowed[$cmd])) {
    for ($i = 2; $i < count($argv); $i++) {
        $arg = (string) $argv[$i];
        if ($arg === '--draft-only') {
            $key = 'draft-only';
            $value = true;
        } elseif ($arg === '--update-published') {
            $key = 'update-published';
            $value = true;
        } elseif (preg_match('/^--(name|sites|desks|lookup|read)=(.*)$/s', $arg, $m)) {
            $key = $m[1];
            $value = $m[2];
        } elseif (preg_match('/^--(name|sites|desks|lookup|read)$/', $arg, $m)) {
            $key = $m[1];
            if (!isset($argv[$i + 1])) {
                pp_agent_refuse("--{$key} needs a value.");
            }
            $value = (string) $argv[++$i];
        } elseif (preg_match('/^-{1,2}update[-_]?published/i', $arg)) {
            pp_agent_refuse("Unrecognised argument '{$arg}'. The flag is spelled exactly --update-published and takes no value.");
        } elseif (preg_match('/^-{1,2}draft[-_]?only/i', $arg)) {
            pp_agent_refuse("Unrecognised argument '{$arg}'. The flag is spelled exactly --draft-only and takes no value.");
        } else {
            pp_agent_refuse("Unrecognised argument '{$arg}'.");
        }
        // A value never starts with "--": `--name --draft-only` would
        // otherwise swallow the flag as the name and mint a standard token.
        if (is_string($value) && str_starts_with($value, '--')) {
            pp_agent_refuse("--{$key} needs a value, and '{$value}' looks like another option.");
        }
        if (!in_array($key, $allowed[$cmd], true)) {
            pp_agent_refuse("--{$key} has no meaning for '{$cmd}'.");
        }
        if (array_key_exists($key, $opt)) {
            pp_agent_refuse("--{$key} was given more than once.");
        }
        $opt[$key] = $value;
    }
}
$pdo = db();

function pp_agent_by_name(PDO $pdo, string $name): array
{
    $stmt = $pdo->prepare('SELECT * FROM ingest_agents WHERE name = ?');
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if (!$row) {
        exit("No agent named '{$name}'. `list` shows what exists.\n");
    }
    return $row;
}

switch ($cmd) {
    case 'create':
        $name = slugify((string) ($opt['name'] ?? ''));
        if ($name === '') {
            exit("--name is required (letters, digits, hyphens).\n");
        }
        $sites = array_filter(array_map('slugify', explode(',', (string) ($opt['sites'] ?? ''))));
        if (!$sites) {
            exit("--sites is required: comma-separated site slugs.\n");
        }
        $sel = $pdo->prepare('SELECT 1 FROM sites WHERE slug = ?');
        foreach ($sites as $s) {
            $sel->execute([$s]);
            if (!$sel->fetch()) {
                exit("Unknown site '{$s}' — sites are created at launch, and a token only scopes to what exists.\n");
            }
        }
        // Trim-and-filter BEFORE slugify: slugify('') returns its 'story'
        // fallback, which turned an omitted --desks into a lookup for a
        // desk named 'story' and refused the token.
        $desks = array_map('slugify', array_filter(array_map('trim', explode(',', (string) ($opt['desks'] ?? '')))));
        $selD = $pdo->prepare('SELECT 1 FROM categories WHERE slug = ?');
        foreach ($desks as $d) {
            $selD->execute([$d]);
            if (!$selD->fetch()) {
                exit("Unknown desk '{$d}'.\n");
            }
        }
        $stmt = $pdo->prepare('SELECT 1 FROM ingest_agents WHERE name = ?');
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            exit("Agent '{$name}' already exists. Revoke it and create a new name, or keep its token.\n");
        }
        $draftOnly = !empty($opt['draft-only']) ? 1 : 0;
        // The lookup grant is strict like every argument here: a value this
        // tool does not recognise mints nothing, so a typo can never widen
        // (or silently drop) a permission.
        $lookup = (string) ($opt['lookup'] ?? '');
        if (!in_array($lookup, ['', 'own', 'all'], true)) {
            pp_agent_refuse("--lookup takes exactly 'own' or 'all' (got '{$lookup}').");
        }
        $updatePublished = !empty($opt['update-published']) ? 1 : 0;
        $read = (string) ($opt['read'] ?? '');
        if (!in_array($read, ['', 'published', 'all'], true)) {
            pp_agent_refuse("--read takes exactly 'published' or 'all' (got '{$read}').");
        }
        $token = 'hermes_' . bin2hex(random_bytes(28));
        $pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, draft_only, lookup_scope, update_published, read_scope, created_at)
            VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?)')
            ->execute([$name, hash('sha256', $token), implode(',', $sites), implode(',', $desks), $draftOnly, $lookup, $updatePublished, $read, now()]);
        echo "Agent:  {$name}\n";
        echo 'Sites:  ' . implode(', ', $sites) . "\n";
        echo 'Desks:  ' . ($desks ? implode(', ', $desks) : '(any existing desk)') . "\n";
        echo 'Lookup: ' . ($lookup !== '' ? $lookup . ($lookup === 'all'
            ? ' — /api/ingest/lookup answers over the full editorial queue within its papers and desks'
            : ' — /api/ingest/lookup answers over its own filings plus published stories')
            : 'none — /api/ingest/lookup refuses this token') . "\n";
        echo 'Read:   ' . ($read === 'all'
            ? 'all — /api/ingest/stories lists and reads every story in its scope, drafts included'
            : ($read === 'published' ? 'published — /api/ingest/stories reads live stories only' : 'none — /api/ingest/stories refuses this token')) . "\n";
        echo 'Update: ' . ($updatePublished
            ? 'YES — /api/ingest/update may change the headline, lede, body and image of PUBLISHED stories in its scope'
            : 'no — published stories stay the newsroom\'s') . "\n";
        echo 'Mode:   ' . ($draftOnly
            ? "DRAFT-ONLY — files drafts; wire desks and publish_on_image never publish for this token, /api/ingest-publish refuses it"
            : 'standard — wire desks publish at once; publish_on_image is honoured') . "\n";
        echo "Token:  {$token}\n";
        echo "This token is shown ONCE and stored only as a hash. Give it to exactly one agent.\n";
        break;

    case 'list':
        foreach ($pdo->query('SELECT name, sites, desks, enabled, draft_only, lookup_scope, update_published, read_scope, created_at, last_used_at FROM ingest_agents ORDER BY name') as $a) {
            printf(
                "%-24s %-9s %-10s lookup=%-4s update=%-3s read=%-9s sites=%s desks=%s created=%s last_used=%s\n",
                $a['name'],
                $a['enabled'] ? 'active' : 'REVOKED',
                (int) $a['draft_only'] === 1 ? 'draft-only' : 'standard',
                (string) ($a['lookup_scope'] ?? '') !== '' ? $a['lookup_scope'] : 'none',
                (int) ($a['update_published'] ?? 0) === 1 ? 'yes' : 'no',
                (string) ($a['read_scope'] ?? '') !== '' ? $a['read_scope'] : 'none',
                $a['sites'],
                $a['desks'] !== '' ? $a['desks'] : '(any)',
                substr((string) $a['created_at'], 0, 10),
                $a['last_used_at'] ? substr((string) $a['last_used_at'], 0, 16) : 'never'
            );
        }
        break;

    case 'revoke':
    case 'enable':
        $name = slugify((string) ($opt['name'] ?? ''));
        $agent = pp_agent_by_name($pdo, $name);
        $pdo->prepare('UPDATE ingest_agents SET enabled = ? WHERE id = ?')
            ->execute([$cmd === 'enable' ? 1 : 0, (int) $agent['id']]);
        echo $cmd === 'enable'
            ? "Agent '{$name}' re-enabled — its existing token works again.\n"
            : "Agent '{$name}' revoked — its token now answers 401, effective immediately.\n";
        break;

    default:
        // No subcommand, or one this tool does not have: nothing ran.
        pp_agent_refuse($cmd === '' ? 'A subcommand is required.' : "Unrecognised subcommand '{$cmd}'.");
}
