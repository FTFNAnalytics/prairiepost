<?php
/**
 * Issue, list, and revoke Hermes ingest tokens. The only writer of the
 * ingest_agents table — tokens never enter the repository, the database
 * stores only their sha256, and the raw token is printed exactly once.
 *
 *   php tools/make-agent.php create --name hermes-bleuet \
 *       --sites bleuet-blanc [--desks actualites,economie] [--draft-only]
 *   php tools/make-agent.php list
 *   php tools/make-agent.php revoke --name hermes-bleuet
 *   php tools/make-agent.php enable --name hermes-bleuet
 *
 * Scoping: --sites is required (comma-separated slugs; every slug must be
 * a real site). --desks is optional; empty means any existing desk on the
 * scoped sites. Revocation is the kill switch: a revoked token answers
 * 401 on the next request, identically to a token that never existed.
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

const PP_AGENT_USAGE = 'Usage: php tools/make-agent.php create --name <n> --sites <a,b> [--desks <c,d>] [--draft-only]'
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
    'create' => ['name', 'sites', 'desks', 'draft-only'],
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
        } elseif (preg_match('/^--(name|sites|desks)=(.*)$/s', $arg, $m)) {
            $key = $m[1];
            $value = $m[2];
        } elseif (preg_match('/^--(name|sites|desks)$/', $arg, $m)) {
            $key = $m[1];
            if (!isset($argv[$i + 1])) {
                pp_agent_refuse("--{$key} needs a value.");
            }
            $value = (string) $argv[++$i];
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
        $token = 'hermes_' . bin2hex(random_bytes(28));
        $pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, draft_only, created_at)
            VALUES (?, ?, ?, ?, 1, ?, ?)')
            ->execute([$name, hash('sha256', $token), implode(',', $sites), implode(',', $desks), $draftOnly, now()]);
        echo "Agent:  {$name}\n";
        echo 'Sites:  ' . implode(', ', $sites) . "\n";
        echo 'Desks:  ' . ($desks ? implode(', ', $desks) : '(any existing desk)') . "\n";
        echo 'Mode:   ' . ($draftOnly
            ? "DRAFT-ONLY — files drafts; wire desks and publish_on_image never publish for this token, /api/ingest-publish refuses it"
            : 'standard — wire desks publish at once; publish_on_image is honoured') . "\n";
        echo "Token:  {$token}\n";
        echo "This token is shown ONCE and stored only as a hash. Give it to exactly one agent.\n";
        break;

    case 'list':
        foreach ($pdo->query('SELECT name, sites, desks, enabled, draft_only, created_at, last_used_at FROM ingest_agents ORDER BY name') as $a) {
            printf(
                "%-24s %-9s %-10s sites=%s desks=%s created=%s last_used=%s\n",
                $a['name'],
                $a['enabled'] ? 'active' : 'REVOKED',
                (int) $a['draft_only'] === 1 ? 'draft-only' : 'standard',
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
