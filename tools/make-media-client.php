<?php
/**
 * Issue and revoke credentials for the Pantheon media gateway.
 *
 *   php tools/make-media-client.php create --name pantheon \
 *       --scopes catalog,submit,status,cancel,metrics,order
 *   php tools/make-media-client.php list
 *   php tools/make-media-client.php revoke --name pantheon
 *   php tools/make-media-client.php enable --name pantheon
 *   php tools/make-media-client.php scopes --name pantheon \
 *       --scopes catalog,submit,status,cancel,metrics,order
 *
 * The raw token is printed once. Only its SHA-256 hash enters the database.
 * This credential is deliberately separate from Hermes reporting tokens.
 */
if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

$cmd = $argv[1] ?? '';
$opt = [];
for ($i = 2; $i < count($argv); $i++) {
    if (preg_match('/^--(name|scopes)$/', $argv[$i], $m) && isset($argv[$i + 1])) {
        $opt[$m[1]] = $argv[++$i];
    } elseif (preg_match('/^--(name|scopes)=(.*)$/', $argv[$i], $m)) {
        $opt[$m[1]] = $m[2];
    }
}
$pdo = db();

function pp_media_client_named(PDO $pdo, string $name): array
{
    $stmt = $pdo->prepare('SELECT * FROM media_clients WHERE name = ?');
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if (!$row) {
        exit("No media client named '{$name}'.\n");
    }
    return $row;
}
switch ($cmd) {
    case 'create':
        $name = slugify((string) ($opt['name'] ?? ''));
        if ($name === '') {
            exit("--name is required.\n");
        }
        $scopes = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) ($opt['scopes'] ?? implode(',', PP_MEDIA_SCOPES)))))));
        foreach ($scopes as $scope) {
            if (!in_array($scope, PP_MEDIA_SCOPES, true)) {
                exit("Unknown scope '{$scope}'. Allowed: " . implode(', ', PP_MEDIA_SCOPES) . "\n");
            }
        }
        if (!$scopes) {
            exit("At least one scope is required.\n");
        }
        $exists = $pdo->prepare('SELECT 1 FROM media_clients WHERE name = ?');
        $exists->execute([$name]);
        if ($exists->fetch()) {
            exit("Media client '{$name}' already exists. Use a new name or keep its token.\n");
        }
        $token = 'civis_media_' . bin2hex(random_bytes(28));
        $pdo->prepare('INSERT INTO media_clients (name, token_hash, scopes, enabled, created_at)
                       VALUES (?, ?, ?, 1, ?)')
            ->execute([$name, hash('sha256', $token), implode(',', $scopes), now()]);
        echo "Client: {$name}\n";
        echo 'Scopes: ' . implode(', ', $scopes) . "\n";
        echo "Token:  {$token}\n";
        echo "Shown once; store it in Pantheon's server-only provider configuration.\n";
        break;

    case 'list':
        foreach ($pdo->query('SELECT name, scopes, enabled, created_at, last_used_at FROM media_clients ORDER BY name') as $row) {
            printf("%-24s %-9s scopes=%s created=%s last_used=%s\n",
                $row['name'], $row['enabled'] ? 'active' : 'REVOKED', $row['scopes'],
                substr((string) $row['created_at'], 0, 10),
                $row['last_used_at'] ? substr((string) $row['last_used_at'], 0, 16) : 'never');
        }
        break;

    case 'revoke':
    case 'enable':
        $name = slugify((string) ($opt['name'] ?? ''));
        $row = pp_media_client_named($pdo, $name);
        $pdo->prepare('UPDATE media_clients SET enabled = ? WHERE id = ?')
            ->execute([$cmd === 'enable' ? 1 : 0, (int) $row['id']]);
        echo $cmd === 'enable'
            ? "Media client '{$name}' enabled.\n"
            : "Media client '{$name}' revoked; its next call answers 401.\n";
        break;

    case 'scopes':
        // Re-scope without rotating: the token stays as issued, so granting a
        // new capability (e.g. 'order') never forces a credential handoff.
        $name = slugify((string) ($opt['name'] ?? ''));
        $row = pp_media_client_named($pdo, $name);
        $scopes = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) ($opt['scopes'] ?? ''))))));
        foreach ($scopes as $scope) {
            if (!in_array($scope, PP_MEDIA_SCOPES, true)) {
                exit("Unknown scope '{$scope}'. Allowed: " . implode(', ', PP_MEDIA_SCOPES) . "\n");
            }
        }
        if (!$scopes) {
            exit("--scopes is required, e.g. --scopes " . implode(',', PP_MEDIA_SCOPES) . "\n");
        }
        $pdo->prepare('UPDATE media_clients SET scopes = ? WHERE id = ?')
            ->execute([implode(',', $scopes), (int) $row['id']]);
        echo "Media client '{$name}' scopes: " . implode(', ', $scopes) . "\n";
        break;

    default:
        exit("Usage: php tools/make-media-client.php create|list|revoke|enable|scopes [--name ...] [--scopes ...]\n");
}
