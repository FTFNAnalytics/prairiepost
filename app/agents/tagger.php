<?php
/**
 * The tagger: proposes three to six tags from the network's EXISTING tag
 * vocabulary, so tag pages consolidate instead of fragmenting. Anything
 * the model suggests outside the vocabulary is dropped, not created.
 * Approval merges — it never removes a tag a person chose.
 */

function pp_agent_tagger(array $post): array
{
    if (!pp_ai_enabled()) {
        return [false, null, "the research desk isn't connected (anthropic_api_key) — this agent needs it"];
    }
    $vocab = db()->query('SELECT t.name, COUNT(pt.post_id) AS n FROM tags t
                          LEFT JOIN post_tags pt ON pt.tag_id = t.id
                          GROUP BY t.id, t.name ORDER BY n DESC LIMIT 200')->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!$vocab) {
        return [false, null, 'the network has no tags yet — the tagger only reuses existing vocabulary'];
    }
    $current = db()->prepare('SELECT t.name FROM tags t JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ?');
    $current->execute([(int) $post['id']]);
    $currentTags = $current->fetchAll(PDO::FETCH_COLUMN);

    $system = 'You tag stories for a Canadian community-news network. Choose three to six tags for the story — ONLY from the provided vocabulary, exactly as written there. Prefer specific over general. Return fewer if fewer fit; never invent a tag.';
    $userMsg = "VOCABULARY\n" . implode("\n", array_keys($vocab))
             . "\n\nSTORY\nTitle: {$post['title']}\nLede: {$post['lede']}\n\n" . excerpt((string) $post['body'], 900);
    $res = pp_ai_message($system, [['role' => 'user', 'content' => $userMsg]], [
        'schema' => [
            'type' => 'object',
            'properties' => ['tags' => ['type' => 'array', 'items' => ['type' => 'string']]],
            'required' => ['tags'],
            'additionalProperties' => false,
        ],
        'max_tokens' => 2000,
    ]);
    if (!$res['ok']) {
        return [false, null, $res['error']];
    }
    $decoded = json_decode($res['text'], true);
    $byLower = array_change_key_case(array_combine(array_keys($vocab), array_keys($vocab)), CASE_LOWER);
    $proposed = [];
    foreach ((array) ($decoded['tags'] ?? []) as $tag) {
        $hit = $byLower[mb_strtolower(trim((string) $tag))] ?? null;
        if ($hit !== null && !in_array($hit, $currentTags, true) && !in_array($hit, $proposed, true)) {
            $proposed[] = $hit;
        }
    }
    $proposed = array_slice($proposed, 0, 6);
    if (!$proposed) {
        return [false, null, 'nothing new to propose — the model\'s picks were outside the vocabulary or already on the story'];
    }
    return [true, ['proposed_tags' => $proposed, 'current_tags' => $currentTags],
            count($proposed) . ' tag(s) proposed'];
}
