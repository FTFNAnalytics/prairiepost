<?php
/**
 * The research & drafting desk's Claude client — a small raw-HTTP wrapper
 * around POST /v1/messages, because this stack is deliberately Composer-free.
 *
 * The key lives in config.php only (never the database); the model id is a
 * hub setting so it can change without a release. Every caller must treat
 * the output as working material for a journalist — nothing here publishes.
 */

function pp_ai_key(): string
{
    return trim((string) pp_config('anthropic_api_key', ''));
}

function pp_ai_enabled(): bool
{
    return pp_ai_key() !== '';
}

function pp_ai_model(): string
{
    $m = trim((string) setting('ai_model', ''));
    return $m !== '' ? $m : 'claude-opus-5';
}

/** Overridable only for a local stub or an outbound proxy — not a setting. */
function pp_ai_base(): string
{
    return rtrim((string) pp_config('anthropic_base_url', 'https://api.anthropic.com'), '/');
}

/**
 * One non-streaming Messages API call.
 * $opts: model, max_tokens, timeout, schema (a JSON Schema array — sent as
 * output_config.format so the reply text is guaranteed valid JSON).
 *
 * Returns ['ok', 'text', 'stop_reason', 'usage' => ['in','out'], 'error'].
 * ok=false always carries a plain-English 'error' fit for a flash message.
 */
function pp_ai_message(string $system, array $messages, array $opts = []): array
{
    $out = ['ok' => false, 'text' => '', 'stop_reason' => null, 'usage' => ['in' => 0, 'out' => 0], 'error' => null];
    if (!pp_ai_enabled()) {
        $out['error'] = "The research desk isn't connected yet — add anthropic_api_key to the hub's config.php.";
        return $out;
    }

    $payload = [
        'model'      => (string) ($opts['model'] ?? pp_ai_model()),
        'max_tokens' => (int) ($opts['max_tokens'] ?? 16000),
        'system'     => $system,
        'messages'   => $messages,
    ];
    if (!empty($opts['schema'])) {
        $payload['output_config'] = ['format' => ['type' => 'json_schema', 'schema' => $opts['schema']]];
    }

    $ch = curl_init(pp_ai_base() . '/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . pp_ai_key(),
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int) ($opts['timeout'] ?? 240),
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false) {
        $out['error'] = "The call didn't complete: " . ($err ?: 'network error') . '. A long draft can take a couple of minutes — try once more.';
        return $out;
    }
    $data = json_decode((string) $body, true);
    if (!is_array($data)) {
        $out['error'] = "The API answered HTTP $code with an unreadable body.";
        return $out;
    }
    if (($data['type'] ?? '') === 'error') {
        $type = (string) ($data['error']['type'] ?? 'error');
        $msg  = (string) ($data['error']['message'] ?? 'unknown error');
        $out['error'] = $type === 'authentication_error'
            ? 'The API key was rejected — check anthropic_api_key in config.php.'
            : "The API declined the request ($type): $msg";
        return $out;
    }

    $out['stop_reason'] = $data['stop_reason'] ?? null;
    $out['usage'] = [
        'in'  => (int) ($data['usage']['input_tokens'] ?? 0),
        'out' => (int) ($data['usage']['output_tokens'] ?? 0),
    ];
    // Thinking blocks precede the text on current models; collect text only.
    foreach (($data['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') {
            $out['text'] .= (string) ($block['text'] ?? '');
        }
    }

    if ($out['stop_reason'] === 'refusal') {
        $out['error'] = 'The model declined this request. Rephrase the assignment, or research it by hand.';
        return $out;
    }
    if ($out['stop_reason'] === 'max_tokens') {
        $out['error'] = 'The reply ran past the length limit and was cut off — narrow the assignment and try again.';
        return $out;
    }
    if ($out['text'] === '') {
        $out['error'] = 'The model returned no text.';
        return $out;
    }
    $out['ok'] = true;
    return $out;
}

/**
 * The draft contract — structured output, so there is no fragile parsing.
 * body_html's first paragraph starts with the dateline; editor_note carries
 * thin-source warnings and anything the journalist should know first.
 */
function pp_ai_draft_schema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'title'            => ['type' => 'string'],
            'dateline'         => ['type' => 'string'],
            'lede'             => ['type' => 'string'],
            'body_html'        => ['type' => 'string'],
            'meta_description' => ['type' => 'string'],
            'suggested_tags'   => ['type' => 'array', 'items' => ['type' => 'string']],
            'suggested_desk'   => ['type' => 'string'],
            'editor_note'      => ['type' => 'string'],
        ],
        'required' => ['title', 'dateline', 'lede', 'body_html', 'meta_description',
                       'suggested_tags', 'suggested_desk', 'editor_note'],
        'additionalProperties' => false,
    ];
}

/**
 * Fetch a page and reduce it to readable text for grounding.
 * Returns [text, error]; text is capped so a long page can't blow the prompt.
 */
function pp_ai_readable(string $url, int $cap = 14000): array
{
    if (!preg_match('#^https?://#i', $url)) {
        return [null, 'only http(s) URLs can be fetched'];
    }
    [$html, $err] = http_get($url, 20);
    if ($html === null) {
        return [null, $err];
    }
    $html = preg_replace('#<(script|style|nav|header|footer|aside|form)\b[^>]*>.*?</\1>#is', ' ', $html);
    $html = preg_replace('#<(br|/p|/div|/h[1-6]|/li|/tr)>#i', "\n", $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/[ \t]+/', ' ', preg_replace('/\n{3,}/', "\n\n", $text)));
    if ($text === '') {
        return [null, 'the page yielded no readable text'];
    }
    return [mb_substr($text, 0, $cap), null];
}
