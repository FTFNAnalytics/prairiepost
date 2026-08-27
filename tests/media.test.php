<?php
/** Pantheon media contract against an in-memory network database. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
function current_site_id(): int { return 1; }
function pp_like(): string { return 'LIKE'; }
function pp_config(string $key, mixed $default = null): mixed
{
    return $key === 'hub_slug' ? 'civismedia' : $default;
}
require dirname(__DIR__) . '/app/db.php';
require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/models.php';
require dirname(__DIR__) . '/app/media.php';

foreach (pp_schema_ddl('sqlite') as $sql) {
    db()->exec($sql);
}

$fails = 0;
function ok(bool $condition, string $label): void
{
    global $fails;
    if (!$condition) {
        echo "FAIL {$label}\n";
        $fails++;
    }
}

$slugs = [
    'bleuet-blanc', 'brampton-bulletin', 'edmonton-echo', 'grande-prairie-gazette',
    'kelowna-current', 'kermode-chronicle', 'kitchener-chronicle', 'mississauga-monitor',
    'pacific-post', 'pickering-post', 'prairiedispatch', 'sudbury-standard',
    'tri-cities-torch', 'turtle-island-times', 'westernwire', 'civismedia',
];
$insertSite = db()->prepare('INSERT INTO sites (name, slug, domain, created_at) VALUES (?, ?, ?, ?)');
foreach ($slugs as $slug) {
    $insertSite->execute([ucwords(str_replace('-', ' ', $slug)), $slug, $slug . '.example.test', '2026-08-27 00:00:00']);
}

$catalog = pp_media_catalog();
ok(count($catalog['properties']) === 15, 'catalog exposes exactly 15 newspaper properties');
ok(count($catalog['products']) === 45, 'catalog exposes three distinct lanes per property');
ok(!in_array('civismedia', array_column($catalog['properties'], 'externalRef'), true), 'hub is excluded from inventory');
$propertiesByRef = array_column($catalog['properties'], null, 'externalRef');
ok(array_reduce($catalog['properties'], fn ($valid, $row) => $valid && !empty($row['serviceArea']['jurisdictionCodes']), true),
   'all properties declare canonical jurisdiction coverage');
ok(($propertiesByRef['edmonton-echo']['serviceArea']['jurisdictionCodes'] ?? []) === ['ca-ab'],
   'Edmonton Echo declares Alberta coverage');
ok(($propertiesByRef['westernwire']['serviceArea']['jurisdictionCodes'] ?? []) === ['ca-bc', 'ca-ab', 'ca-sk', 'ca-mb'],
   'Western Wire declares four-province western coverage');
$torch = array_values(array_filter($catalog['properties'], fn ($row) => $row['externalRef'] === 'tri-cities-torch'))[0] ?? null;
ok($torch !== null && $torch['sandbox'] === true, 'Tri Cities Torch is the sandbox property');
$contract = json_decode((string) file_get_contents(dirname(__DIR__) . '/contracts/media-provider-v1.json'), true);
ok(is_array($contract) && $contract['schemaVersion'] === PP_MEDIA_SCHEMA_VERSION, 'shared contract fixture version matches');

$token = 'test_media_token_that_is_long_enough';
db()->prepare('INSERT INTO media_clients (name, token_hash, scopes, enabled, created_at) VALUES (?, ?, ?, 1, ?)')
    ->execute(['pantheon-test', hash('sha256', $token), implode(',', PP_MEDIA_SCOPES), '2026-08-27 00:00:00']);
$client = db()->query("SELECT * FROM media_clients WHERE name = 'pantheon-test'")->fetch();
$payload = [
    'schemaVersion' => PP_MEDIA_SCHEMA_VERSION,
    'idempotencyKey' => 'brief-1:prairiepost:v1',
    'requestId' => 'request-1',
    'briefId' => 'brief-1',
    'briefVersion' => 1,
    'objectiveRef' => 'objective-1',
    'requestKind' => 'display_ad',
    'title' => 'Power, water, and local benefit',
    'advertiserOfRecord' => 'Example organization',
    'sponsorName' => 'Example organization',
    'disclosureText' => 'Paid for by Example organization',
    'landingUrl' => 'https://example.test/data-centres',
    'desiredStart' => '2026-09-01',
    'desiredEnd' => '2026-09-14',
    'maxBudget' => 5000,
    'currency' => 'CAD',
    'targetSpec' => ['jurisdictions' => ['ca-ab']],
    'content' => ['heading' => 'The local benefit test'],
    'notes' => null,
    'placements' => [[
        'propertyRef' => 'edmonton-echo',
        'productRef' => 'edmonton-echo:display-ad',
        'budgetCap' => 2500,
        'creative' => ['placement' => 'rail'],
    ]],
];
[$code, $receipt] = pp_media_submit($client, $payload, $payload['idempotencyKey']);
ok($code === 201 && $receipt['state'] === 'received', 'valid paid request is received');
ok((int) db()->query('SELECT COUNT(*) FROM media_requests')->fetchColumn() === 1, 'one request row stored');
ok((int) db()->query('SELECT COUNT(*) FROM campaigns')->fetchColumn() === 0, 'submission creates no campaign');
ok((int) db()->query('SELECT COUNT(*) FROM posts')->fetchColumn() === 0, 'submission creates no post');
[$dupeCode, $dupe] = pp_media_submit($client, $payload, $payload['idempotencyKey']);
ok($dupeCode === 200 && !empty($dupe['duplicate']), 'idempotent retry returns the original request');
ok((int) db()->query('SELECT COUNT(*) FROM media_requests')->fetchColumn() === 1, 'retry does not duplicate');
$conflict = $payload;
$conflict['idempotencyKey'] = 'different-key-for-same-request';
try {
    pp_media_submit($client, $conflict, $conflict['idempotencyKey']);
    ok(false, 'one Pantheon request id cannot create two provider requests');
} catch (PPMediaError $e) {
    ok($e->httpCode === 409, 'one Pantheon request id cannot create two provider requests');
}
try {
    pp_media_cancel($client, $receipt['externalRef'], '');
    ok(false, 'cancellation without idempotency is refused');
} catch (PPMediaError $e) {
    ok($e->httpCode === 422, 'cancellation without idempotency is refused');
}
$cancelled = pp_media_cancel($client, $receipt['externalRef'], 'cancel-request-1');
ok($cancelled['state'] === 'cancelled', 'cancellation closes an unbooked request');
ok(pp_media_cancel($client, $receipt['externalRef'], 'cancel-request-1')['state'] === 'cancelled',
   'cancellation retry is idempotent');

$bad = $payload;
$bad['disclosureText'] = null;
try {
    pp_media_validate_submission($bad, $bad['idempotencyKey']);
    ok(false, 'paid request without disclosure is refused');
} catch (PPMediaError $e) {
    ok($e->httpCode === 422, 'paid request without disclosure is refused');
}

$contractSubmission = pp_media_validate_submission(
    $contract['submission'],
    $contract['submission']['idempotencyKey'],
);
ok($contractSubmission['kind'] === 'display_ad'
   && $contractSubmission['placements'][0]['site']['slug'] === 'tri-cities-torch',
   'cross-repository sandbox submission validates against the live catalog');

$editorial = $payload;
$editorial['idempotencyKey'] = 'brief-2:prairiepost:v1';
$editorial['requestId'] = 'request-2';
$editorial['briefId'] = 'brief-2';
$editorial['requestKind'] = 'editorial_pitch';
$editorial['advertiserOfRecord'] = null;
$editorial['sponsorName'] = null;
$editorial['disclosureText'] = null;
$editorial['maxBudget'] = null;
$editorial['placements'][0]['productRef'] = 'edmonton-echo:editorial-pitch';
[$editorialCode, $editorialReceipt] = pp_media_submit($client, $editorial, $editorial['idempotencyKey']);
ok($editorialCode === 201 && $editorialReceipt['state'] === 'received', 'editorial lane stays distinct');
$editorialId = (int) db()->query("SELECT id FROM media_requests WHERE pantheon_request_id = 'request-2'")->fetchColumn();
$ideas = pp_media_accept_editorial($editorialId, 'Editor Test');
ok($ideas === 1, 'editorial acceptance creates one idea per property');
ok((int) db()->query('SELECT COUNT(*) FROM story_ideas')->fetchColumn() === 1, 'story idea is stored');
ok((int) db()->query('SELECT COUNT(*) FROM posts')->fetchColumn() === 0, 'editorial acceptance still publishes nothing');
try {
    pp_media_review_state($editorialId, 'in_review', '', 'Editor Test');
    ok(false, 'accepted editorial request cannot be reopened by a forged action');
} catch (RuntimeException) {
    ok(true, 'accepted editorial request cannot be reopened by a forged action');
}

exit($fails ? 1 : 0);
