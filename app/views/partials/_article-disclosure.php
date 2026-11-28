<?php
/**
 * Shared AI & automated workflow disclosure partial.
 * Used by both the default article template and all 19 bespoke article-*.php
 * views so disclosure policy applies uniformly across every paper.
 *
 * Expects $post in scope. Off by default unless the paper's `ai_disclosure`
 * setting is non-empty ('1' for standard bilingual wording, or custom text).
 */
$ppOrigin = (string) ($post['origin'] ?? '');
$ppAiLine = trim((string) setting('ai_disclosure', ''));
if ($ppAiLine === '' || !in_array($ppOrigin, ['ai', 'hermes'], true)) {
    return;
}
$GLOBALS['ppDisclosureRendered'] = true;
if ($ppAiLine === '1') {
    $ppText = $ppOrigin === 'hermes'
        ? pp_t('Filed via automated newsroom workflow and published under editorial oversight.')
        : pp_t('Prepared with AI assistance and reviewed by an editor.');
} else {
    $ppText = $ppAiLine;
}
?>
<p class="byline pp-disclosure" role="note"><?= e($ppText) ?></p>
