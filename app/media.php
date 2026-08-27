<?php
/**
 * Civis Media's provider contract with Pantheon.
 *
 * This is deliberately a request desk, not a publishing API. The catalog is
 * derived from the live sites table (hub excluded), and every inbound item is
 * stored for a human disposition. Editorial acceptance creates story ideas;
 * paid lanes can be quoted but cannot create a campaign, post, spend, or live
 * placement through this contract.
 */

const PP_MEDIA_SCHEMA_VERSION = '2026-08-27';
const PP_MEDIA_KINDS = ['editorial_pitch', 'sponsored_post', 'display_ad'];
const PP_MEDIA_SCOPES = ['catalog', 'submit', 'status', 'cancel', 'metrics'];

final class PPMediaError extends RuntimeException
{
    public function __construct(public readonly int $httpCode, string $message)
    {
        parent::__construct($message);
    }
}

function pp_media_site_setting(int $siteId, string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT svalue FROM settings WHERE site_id = ? AND skey = ?');
    $stmt->execute([$siteId, $key]);
    $value = $stmt->fetchColumn();
    return $value === false || $value === '' ? $default : (string) $value;
}

/** Canonical Pantheon jurisdiction coverage declared by each publication. */
function pp_media_jurisdiction_codes(string $slug): array
{
    return match ($slug) {
        'bleuet-blanc' => ['ca-qc'],
        'brampton-bulletin', 'kitchener-chronicle', 'mississauga-monitor',
        'pickering-post', 'sudbury-standard' => ['ca-on'],
        'edmonton-echo', 'grande-prairie-gazette', 'prairiedispatch' => ['ca-ab'],
        'kelowna-current', 'kermode-chronicle', 'pacific-post',
        'tri-cities-torch' => ['ca-bc'],
        'turtle-island-times' => ['ca'],
        'westernwire' => ['ca-bc', 'ca-ab', 'ca-sk', 'ca-mb'],
        default => ['ca'],
    };
}

/** Complete provider catalog. Civis Media is a control room, never inventory. */
function pp_media_catalog(): array
{
    $properties = [];
    $products = [];
    foreach (pp_paper_sites() as $site) {
        $slug = (string) $site['slug'];
        $regions = json_decode(pp_media_site_setting((int) $site['id'], 'regions', '[]'), true);
        $sandbox = $slug === 'tri-cities-torch';
        $properties[] = [
            'externalRef' => $slug,
            'name' => (string) $site['name'],
            'domain' => $site['domain'] !== '' ? (string) $site['domain'] : null,
            'propertyKind' => 'newspaper',
            'countryCode' => 'CA',
            'serviceArea' => [
                'siteSlug' => $slug,
                'jurisdictionCodes' => pp_media_jurisdiction_codes($slug),
                'regions' => is_array($regions) ? $regions : [],
            ],
            'status' => $sandbox ? 'sandbox' : 'active',
            'sandbox' => $sandbox,
            'metadata' => [
                'reviewRequired' => true,
                'hubExcluded' => true,
            ],
        ];
        $definitions = [
            'editorial_pitch' => ['Editorial pitch', 'Earned coverage proposal for newsroom consideration.', 'no_charge'],
            'sponsored_post' => ['Sponsored post request', 'Paid, prominently disclosed content subject to conflict and editorial review.', 'quote'],
            'display_ad' => ['Display advertising request', 'Top, rail, or after-article inventory subject to availability and quote.', 'quote'],
        ];
        foreach ($definitions as $kind => [$name, $description, $pricing]) {
            $suffix = str_replace('_', '-', $kind);
            $products[] = [
                'externalRef' => $slug . ':' . $suffix,
                'propertyRef' => $slug,
                'requestKind' => $kind,
                'name' => $name,
                'description' => $description,
                'pricingModel' => $sandbox ? 'no_charge' : $pricing,
                'currency' => 'CAD',
                'rateAmount' => $sandbox ? 0 : null,
                'specifications' => $kind === 'display_ad'
                    ? ['placements' => ['top', 'rail', 'article'], 'creative' => ['house', 'image'], 'humanLaunchRequired' => true]
                    : ['paidDisclosureRequired' => $kind === 'sponsored_post', 'newsroomReviewRequired' => true],
                'status' => 'active',
            ];
        }
    }
    return [
        'schemaVersion' => PP_MEDIA_SCHEMA_VERSION,
        'providerKey' => 'prairiepost',
        'providerName' => 'Civis Media newspaper network',
        'generatedAt' => gmdate('c'),
        'properties' => $properties,
        'products' => $products,
    ];
}

function pp_media_scope_allows(array $client, string $scope): bool
{
    if (!in_array($scope, PP_MEDIA_SCOPES, true)) {
        return false;
    }
    $scopes = array_filter(array_map('trim', explode(',', (string) ($client['scopes'] ?? ''))));
    return in_array($scope, $scopes, true);
}

/** @return array<string,array> product ref -> catalog row */
function pp_media_products_by_ref(): array
{
    $rows = [];
    foreach (pp_media_catalog()['products'] as $product) {
        $rows[$product['externalRef']] = $product;
    }
    return $rows;
}

/** Validate and normalize one Pantheon payload. */
function pp_media_validate_submission(array $payload, string $headerIdempotency = ''): array
{
    if (($payload['schemaVersion'] ?? '') !== PP_MEDIA_SCHEMA_VERSION) {
        throw new PPMediaError(422, 'unsupported schemaVersion');
    }
    $key = trim((string) ($payload['idempotencyKey'] ?? ''));
    if ($key === '' || strlen($key) > 191 || ($headerIdempotency !== '' && !hash_equals($key, $headerIdempotency))) {
        throw new PPMediaError(422, 'idempotency key is missing, too long, or does not match the header');
    }
    $kind = (string) ($payload['requestKind'] ?? '');
    if (!in_array($kind, PP_MEDIA_KINDS, true)) {
        throw new PPMediaError(422, 'unknown requestKind');
    }
    $title = trim(strip_tags((string) ($payload['title'] ?? '')));
    if ($title === '' || mb_strlen($title) > 255) {
        throw new PPMediaError(422, 'title is required, at most 255 characters');
    }
    $requestId = trim((string) ($payload['requestId'] ?? ''));
    $briefId = trim((string) ($payload['briefId'] ?? ''));
    if ($requestId === '' || $briefId === '' || strlen($requestId) > 80 || strlen($briefId) > 80) {
        throw new PPMediaError(422, 'requestId and briefId are required');
    }
    $currency = strtoupper(trim((string) ($payload['currency'] ?? 'CAD')));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        throw new PPMediaError(422, 'currency must be a three-letter code');
    }
    $maxBudget = $payload['maxBudget'] ?? null;
    if ($maxBudget !== null && (!is_numeric($maxBudget) || (float) $maxBudget < 0)) {
        throw new PPMediaError(422, 'maxBudget must be non-negative');
    }
    $advertiser = trim(strip_tags((string) ($payload['advertiserOfRecord'] ?? '')));
    $sponsor = trim(strip_tags((string) ($payload['sponsorName'] ?? '')));
    $disclosure = trim(strip_tags((string) ($payload['disclosureText'] ?? '')));
    if (mb_strlen($advertiser) > 160 || mb_strlen($sponsor) > 160 || mb_strlen($disclosure) > 2000) {
        throw new PPMediaError(422, 'advertiser/sponsor must be at most 160 characters and disclosure at most 2000');
    }
    if ($kind !== 'editorial_pitch' && ($advertiser === '' || $sponsor === '' || $disclosure === '' || $maxBudget === null)) {
        throw new PPMediaError(422, 'paid requests require advertiser, sponsor, disclosure, and maxBudget');
    }
    $landing = trim((string) ($payload['landingUrl'] ?? ''));
    if (strlen($landing) > 600 || ($landing !== '' && (!filter_var($landing, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $landing)))) {
        throw new PPMediaError(422, 'landingUrl must be http(s)');
    }
    $desiredStart = pp_media_date($payload['desiredStart'] ?? null);
    $desiredEnd = pp_media_date($payload['desiredEnd'] ?? null);
    if ($desiredStart !== null && $desiredEnd !== null && $desiredEnd < $desiredStart) {
        throw new PPMediaError(422, 'desiredEnd cannot precede desiredStart');
    }
    $notes = trim((string) ($payload['notes'] ?? ''));
    if (mb_strlen($notes) > 8000) {
        throw new PPMediaError(422, 'notes are at most 8000 characters');
    }
    $placements = $payload['placements'] ?? null;
    if (!is_array($placements) || !$placements || count($placements) > 50) {
        throw new PPMediaError(422, 'between one and 50 placements are required');
    }
    $products = pp_media_products_by_ref();
    $siteBySlug = [];
    foreach (pp_paper_sites() as $site) {
        $siteBySlug[$site['slug']] = $site;
    }
    $normalized = [];
    $seen = [];
    foreach ($placements as $index => $placement) {
        if (!is_array($placement)) {
            throw new PPMediaError(422, "placement {$index} must be an object");
        }
        $propertyRef = slugify((string) ($placement['propertyRef'] ?? ''));
        $productRef = trim((string) ($placement['productRef'] ?? ''));
        $product = $products[$productRef] ?? null;
        if (!isset($siteBySlug[$propertyRef]) || !$product
            || $product['propertyRef'] !== $propertyRef || $product['requestKind'] !== $kind) {
            throw new PPMediaError(422, "placement {$index} is not offered by this catalog");
        }
        if (isset($seen[$propertyRef])) {
            throw new PPMediaError(422, "property {$propertyRef} is duplicated");
        }
        $seen[$propertyRef] = true;
        $budgetCap = $placement['budgetCap'] ?? null;
        if ($budgetCap !== null && (!is_numeric($budgetCap) || (float) $budgetCap < 0)) {
            throw new PPMediaError(422, "placement {$index} budgetCap is invalid");
        }
        if ($budgetCap !== null && $maxBudget !== null && (float) $budgetCap > (float) $maxBudget) {
            throw new PPMediaError(422, "placement {$index} exceeds maxBudget");
        }
        $normalized[] = [
            'site' => $siteBySlug[$propertyRef],
            'product_ref' => $productRef,
            'budget_cap' => $budgetCap === null ? null : round((float) $budgetCap, 2),
            'creative' => is_array($placement['creative'] ?? null) ? $placement['creative'] : [],
        ];
    }
    return [
        'idempotency_key' => $key,
        'request_id' => $requestId,
        'brief_id' => $briefId,
        'kind' => $kind,
        'title' => $title,
        'advertiser' => $advertiser ?: null,
        'sponsor' => $sponsor ?: null,
        'disclosure' => $disclosure ?: null,
        'landing_url' => $landing ?: null,
        'desired_start' => $desiredStart,
        'desired_end' => $desiredEnd,
        'max_budget' => $maxBudget === null ? null : round((float) $maxBudget, 2),
        'currency' => $currency,
        'target' => is_array($payload['targetSpec'] ?? null) ? $payload['targetSpec'] : [],
        'content' => is_array($payload['content'] ?? null) ? $payload['content'] : [],
        'notes' => $notes ?: null,
        'placements' => $normalized,
    ];
}

function pp_media_date(mixed $value): ?string
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    if (!$date || $date->format('Y-m-d') !== $raw) {
        throw new PPMediaError(422, 'schedule dates must use YYYY-MM-DD');
    }
    return $raw;
}

function pp_media_event(int $requestId, string $event, string $actor, array $payload = []): void
{
    db()->prepare('INSERT INTO media_request_events (request_id, event_type, actor, payload, created_at)
        VALUES (?, ?, ?, ?, ?)')
        ->execute([$requestId, $event, $actor, json_encode($payload, JSON_UNESCAPED_SLASHES), now()]);
}

function pp_media_request_by_ref(array $client, string $publicRef): ?array
{
    $stmt = db()->prepare('SELECT * FROM media_requests WHERE public_ref = ? AND client_id = ?');
    $stmt->execute([$publicRef, (int) $client['id']]);
    return $stmt->fetch() ?: null;
}

function pp_media_receipt(array $request): array
{
    $receipt = [
        'schemaVersion' => PP_MEDIA_SCHEMA_VERSION,
        'providerKey' => 'prairiepost',
        'externalRef' => (string) $request['public_ref'],
        'state' => (string) $request['state'],
        'receivedAt' => date(DATE_ATOM, strtotime((string) $request['created_at'])),
        'message' => $request['review_note'] ?: null,
        'quote' => null,
        'raw' => ['requestKind' => $request['request_kind']],
    ];
    if ($request['quote_ref'] && $request['quote_amount'] !== null && $request['quote_valid_until']) {
        $receipt['quote'] = [
            'externalRef' => (string) $request['quote_ref'],
            'amount' => (float) $request['quote_amount'],
            'currency' => (string) ($request['quote_currency'] ?: $request['currency']),
            'validUntil' => date(DATE_ATOM, strtotime((string) $request['quote_valid_until'])),
            'terms' => (array) json_decode((string) ($request['quote_terms'] ?: '{}'), true),
        ];
    }
    return $receipt;
}

/** Store a request only. No story, post, campaign, ad, or spend is created. */
function pp_media_submit(array $client, array $payload, string $headerIdempotency = ''): array
{
    $input = pp_media_validate_submission($payload, $headerIdempotency);
    $pdo = db();
    $dup = $pdo->prepare('SELECT * FROM media_requests WHERE client_id = ? AND idempotency_key = ?');
    $dup->execute([(int) $client['id'], $input['idempotency_key']]);
    if ($existing = $dup->fetch()) {
        return [200, pp_media_receipt($existing) + ['duplicate' => true]];
    }
    $sameRequest = $pdo->prepare('SELECT * FROM media_requests WHERE client_id = ? AND pantheon_request_id = ?');
    $sameRequest->execute([(int) $client['id'], $input['request_id']]);
    if ($sameRequest->fetch()) {
        throw new PPMediaError(409, 'requestId was already used with a different idempotency key');
    }
    $recent = $pdo->prepare('SELECT COUNT(*) FROM media_requests WHERE client_id = ? AND created_at > ?');
    $recent->execute([(int) $client['id'], date('Y-m-d H:i:s', time() - 3600)]);
    if ((int) $recent->fetchColumn() >= 120) {
        throw new PPMediaError(429, 'submission rate limit: 120 requests per hour');
    }
    $publicRef = 'cmr_' . bin2hex(random_bytes(12));
    $now = now();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO media_requests
            (public_ref, client_id, idempotency_key, pantheon_request_id, pantheon_brief_id,
             schema_version, request_kind, title, advertiser, sponsor, disclosure,
             landing_url, desired_start, desired_end, max_budget, currency,
             target_json, content_json, notes, state, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $publicRef, (int) $client['id'], $input['idempotency_key'], $input['request_id'], $input['brief_id'],
                PP_MEDIA_SCHEMA_VERSION, $input['kind'], $input['title'], $input['advertiser'], $input['sponsor'],
                $input['disclosure'], $input['landing_url'], $input['desired_start'], $input['desired_end'],
                $input['max_budget'], $input['currency'], json_encode($input['target'], JSON_UNESCAPED_SLASHES),
                json_encode($input['content'], JSON_UNESCAPED_SLASHES), $input['notes'], 'received', $now, $now,
            ]);
        $requestId = pp_last_id('media_requests');
        $ins = $pdo->prepare('INSERT INTO media_request_sites
            (request_id, site_id, product_ref, budget_cap, creative_json)
            VALUES (?, ?, ?, ?, ?)');
        foreach ($input['placements'] as $placement) {
            $ins->execute([
                $requestId, (int) $placement['site']['id'], $placement['product_ref'], $placement['budget_cap'],
                json_encode($placement['creative'], JSON_UNESCAPED_SLASHES),
            ]);
        }
        pp_media_event($requestId, 'received', 'client:' . $client['name'], [
            'properties' => count($input['placements']),
            'request_kind' => $input['kind'],
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // A concurrent retry can pass the first lookup and lose the unique
        // insert race. Return the winning receipt instead of turning a safe
        // idempotent retry into a provider error.
        $dup->execute([(int) $client['id'], $input['idempotency_key']]);
        if ($existing = $dup->fetch()) {
            return [200, pp_media_receipt($existing) + ['duplicate' => true]];
        }
        throw $e;
    }
    return [201, pp_media_receipt(pp_media_request_by_ref($client, $publicRef))];
}

function pp_media_cancel(array $client, string $publicRef, string $idempotencyKey): array
{
    if ($idempotencyKey === '' || strlen($idempotencyKey) > 191) {
        throw new PPMediaError(422, 'cancellation idempotency key is required');
    }
    $request = pp_media_request_by_ref($client, $publicRef);
    if (!$request) {
        throw new PPMediaError(404, 'media request not found');
    }
    if (in_array($request['state'], ['cancelled', 'declined'], true)) {
        return pp_media_receipt($request);
    }
    if (!in_array($request['state'], ['received', 'in_review', 'changes_requested', 'quoted'], true)) {
        throw new PPMediaError(409, "request in state {$request['state']} cannot be cancelled through the gateway");
    }
    db()->prepare("UPDATE media_requests SET state = 'cancelled', updated_at = ? WHERE id = ?")
        ->execute([now(), (int) $request['id']]);
    pp_media_event((int) $request['id'], 'cancelled', 'client:' . $client['name']);
    return pp_media_receipt(pp_media_request_by_ref($client, $publicRef));
}

function pp_media_metrics(array $client, string $publicRef): array
{
    $request = pp_media_request_by_ref($client, $publicRef);
    if (!$request) {
        throw new PPMediaError(404, 'media request not found');
    }
    $metrics = [];
    $stmt = db()->prepare("SELECT o.*, s.slug AS site_slug
        FROM media_request_outputs o LEFT JOIN sites s ON s.id = o.site_id
        WHERE o.request_id = ? AND o.output_kind = 'campaign'");
    $stmt->execute([(int) $request['id']]);
    foreach ($stmt as $output) {
        $ads = db()->prepare('SELECT COALESCE(SUM(impressions),0) impressions, COALESCE(SUM(clicks),0) clicks,
                              MIN(start_at) period_start, MAX(end_at) period_end
                              FROM ads WHERE campaign_id = ? AND (? = 0 OR site_id = ?)');
        $ads->execute([(int) $output['output_id'], (int) $output['site_id'], (int) $output['site_id']]);
        $row = $ads->fetch();
        $start = $row['period_start'] ? substr((string) $row['period_start'], 0, 10) : substr((string) $request['created_at'], 0, 10);
        $end = $row['period_end'] ? substr((string) $row['period_end'], 0, 10) : date('Y-m-d');
        foreach (['impressions', 'clicks'] as $metric) {
            $metrics[] = [
                'propertyRef' => $output['site_slug'] ?: null,
                'periodStart' => $start,
                'periodEnd' => $end,
                'metric' => $metric,
                'value' => (float) $row[$metric],
                'unit' => 'count',
            ];
        }
    }
    return ['schemaVersion' => PP_MEDIA_SCHEMA_VERSION, 'providerKey' => 'prairiepost', 'metrics' => $metrics];
}

/* --- Human control-room workflow ----------------------------------------- */

function pp_media_requests_list(string $state = 'open', string $kind = ''): array
{
    $where = [];
    $params = [];
    if ($state === 'open') {
        $where[] = "r.state IN ('received','in_review','changes_requested','quoted')";
    } elseif ($state !== 'all') {
        $where[] = 'r.state = ?';
        $params[] = $state;
    }
    if (in_array($kind, PP_MEDIA_KINDS, true)) {
        $where[] = 'r.request_kind = ?';
        $params[] = $kind;
    }
    $sql = 'SELECT r.*, c.name AS client_name, COUNT(rs.site_id) AS properties
            FROM media_requests r
            JOIN media_clients c ON c.id = r.client_id
            LEFT JOIN media_request_sites rs ON rs.request_id = r.id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' GROUP BY r.id, c.name ORDER BY r.updated_at DESC LIMIT 150';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function pp_media_request_full(int $id): ?array
{
    $stmt = db()->prepare('SELECT r.*, c.name AS client_name FROM media_requests r
                           JOIN media_clients c ON c.id = r.client_id WHERE r.id = ?');
    $stmt->execute([$id]);
    $request = $stmt->fetch();
    if (!$request) {
        return null;
    }
    $sites = db()->prepare('SELECT rs.*, s.name AS site_name, s.slug AS site_slug, s.domain
                            FROM media_request_sites rs JOIN sites s ON s.id = rs.site_id
                            WHERE rs.request_id = ? ORDER BY s.name');
    $sites->execute([$id]);
    $request['sites'] = $sites->fetchAll();
    $events = db()->prepare('SELECT * FROM media_request_events WHERE request_id = ? ORDER BY id DESC');
    $events->execute([$id]);
    $request['events'] = $events->fetchAll();
    $outputs = db()->prepare('SELECT o.*, s.name AS site_name FROM media_request_outputs o
                              LEFT JOIN sites s ON s.id = o.site_id WHERE o.request_id = ? ORDER BY o.created_at');
    $outputs->execute([$id]);
    $request['outputs'] = $outputs->fetchAll();
    return $request;
}

function pp_media_review_state(int $id, string $state, string $note, string $reviewer): void
{
    if (!in_array($state, ['in_review', 'changes_requested', 'declined'], true)) {
        throw new InvalidArgumentException('invalid media review state');
    }
    $request = pp_media_request_full($id);
    if (!$request || !in_array($request['state'], ['received', 'in_review', 'changes_requested', 'quoted'], true)) {
        throw new RuntimeException('request is not reviewable');
    }
    db()->prepare('UPDATE media_requests SET state = ?, review_note = ?, reviewed_by = ?, updated_at = ? WHERE id = ?')
        ->execute([$state, $note ?: null, $reviewer, now(), $id]);
    pp_media_event($id, $state, 'civis:' . $reviewer, $note !== '' ? ['note' => $note] : []);
}

function pp_media_quote_request(int $id, float $amount, string $validUntil, string $note, string $reviewer): void
{
    $request = pp_media_request_full($id);
    if (!$request || !in_array($request['request_kind'], ['sponsored_post', 'display_ad'], true)) {
        throw new RuntimeException('only a paid request can be quoted');
    }
    if (!in_array($request['state'], ['received', 'in_review', 'changes_requested', 'quoted'], true)) {
        throw new RuntimeException('request is not quoteable in its current state');
    }
    if ($amount < 0 || ($request['max_budget'] !== null && $amount > (float) $request['max_budget'])) {
        throw new RuntimeException('quote must be non-negative and at or below the submitted budget ceiling');
    }
    $until = DateTimeImmutable::createFromFormat('!Y-m-d', $validUntil);
    if (!$until || $until->format('Y-m-d') !== $validUntil || $until <= new DateTimeImmutable('today')) {
        throw new RuntimeException('quote expiry must be a future YYYY-MM-DD date');
    }
    $quoteRef = 'cmq_' . bin2hex(random_bytes(10));
    $terms = [
        'note' => $note,
        'properties' => array_column($request['sites'], 'site_slug'),
        'requestOnly' => true,
        'orderApprovalRequired' => true,
    ];
    db()->prepare("UPDATE media_requests
        SET state = 'quoted', review_note = ?, quote_ref = ?, quote_amount = ?,
            quote_currency = currency, quote_valid_until = ?, quote_terms = ?,
            reviewed_by = ?, updated_at = ? WHERE id = ?")
        ->execute([$note ?: null, $quoteRef, round($amount, 2), $validUntil . ' 23:59:59',
                   json_encode($terms, JSON_UNESCAPED_SLASHES), $reviewer, now(), $id]);
    pp_media_event($id, 'quoted', 'civis:' . $reviewer, [
        'quote_ref' => $quoteRef,
        'amount' => round($amount, 2),
        'currency' => $request['currency'],
        'valid_until' => $validUntil,
    ]);
}

/**
 * Editorial acceptance creates one story idea per selected paper. It does
 * not create a post or publish anything; each newsroom still chooses whether
 * and how to report the subject.
 */
function pp_media_accept_editorial(int $id, string $reviewer): int
{
    $request = pp_media_request_full($id);
    if (!$request || $request['request_kind'] !== 'editorial_pitch') {
        throw new RuntimeException('only an editorial pitch can become story ideas');
    }
    if (!in_array($request['state'], ['received', 'in_review', 'changes_requested'], true)) {
        throw new RuntimeException('pitch is not accept-ready');
    }
    $content = (array) json_decode((string) ($request['content_json'] ?: '{}'), true);
    $target = (array) json_decode((string) ($request['target_json'] ?: '{}'), true);
    $angle = trim((string) ($content['summary'] ?? $content['body'] ?? ''));
    $rationale = 'Pantheon objective ' . $request['pantheon_brief_id'];
    if ($target) {
        $rationale .= ' · target ' . json_encode($target, JSON_UNESCAPED_SLASHES);
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare('INSERT INTO story_ideas
            (site_id, title, angle, rationale, region, origin, status, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $output = $pdo->prepare('INSERT INTO media_request_outputs
            (request_id, site_id, output_kind, output_id, created_at) VALUES (?, ?, ?, ?, ?)');
        $count = 0;
        foreach ($request['sites'] as $site) {
            $insert->execute([
                (int) $site['site_id'], $request['title'], $angle ?: null, $rationale,
                '', 'pantheon', 'open', $reviewer, now(),
            ]);
            $ideaId = pp_last_id('story_ideas');
            $output->execute([$id, (int) $site['site_id'], 'story_idea', $ideaId, now()]);
            $count++;
        }
        $pdo->prepare("UPDATE media_requests SET state = 'accepted', review_note = ?, reviewed_by = ?, updated_at = ? WHERE id = ?")
            ->execute(['Accepted into the story-idea desk; no publication commitment.', $reviewer, now(), $id]);
        pp_media_event($id, 'accepted', 'civis:' . $reviewer, ['story_ideas' => $count, 'published' => false]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return $count;
}
