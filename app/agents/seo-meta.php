<?php
/**
 * The SEO meta writer: one Claude call that drafts a search description
 * (≤155 characters) for a story that has none. It proposes; an editor
 * approves — and approval applies only if the field is still empty.
 */

function pp_agent_seo_meta(array $post): array
{
    if (trim((string) $post['meta_description']) !== '') {
        return [false, null, 'the story already has a search description'];
    }
    if (!pp_ai_enabled()) {
        return [false, null, "the research desk isn't connected (anthropic_api_key) — this agent needs it"];
    }
    $system = 'You write search descriptions for a Canadian community-news network. One sentence, at most 155 characters, plain and specific: what happened, where, for whom. No clickbait, no ellipses, no quotation marks, no trailing period needed. Use only the story material provided.';
    $body = excerpt((string) $post['body'], 900);
    $userMsg = "TITLE\n{$post['title']}\n\nLEDE\n{$post['lede']}\n\nSTORY EXCERPT\n$body";
    $res = pp_ai_message($system, [['role' => 'user', 'content' => $userMsg]], [
        'schema' => [
            'type' => 'object',
            'properties' => ['meta_description' => ['type' => 'string']],
            'required' => ['meta_description'],
            'additionalProperties' => false,
        ],
        'max_tokens' => 2000,
    ]);
    if (!$res['ok']) {
        return [false, null, $res['error']];
    }
    $decoded = json_decode($res['text'], true);
    $meta = mb_substr(trim((string) ($decoded['meta_description'] ?? '')), 0, 155);
    if ($meta === '') {
        return [false, null, 'the model returned an empty description'];
    }
    return [true, ['proposed' => $meta], 'description proposed (' . mb_strlen($meta) . ' chars)'];
}
