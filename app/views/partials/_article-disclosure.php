<?php
/**
 * Shared optional AI disclosure partial.
 * Strictly OFF by default — renders nothing unless the paper's `ai_disclosure`
 * setting is explicitly turned on in Settings ('1' for standard bilingual
 * wording, or custom text).
 */
$ppOrigin = (string) ($post['origin'] ?? '');
$ppAiLine = trim((string) setting('ai_disclosure', ''));
if ($ppAiLine === '' || !in_array($ppOrigin, ['ai', 'hermes'], true)) {
    return;
}
$GLOBALS['ppDisclosureRendered'] = true;
$ppText = $ppAiLine === '1'
    ? pp_t('Prepared with AI assistance and reviewed by an editor.')
    : $ppAiLine;
?>
<p class="byline pp-disclosure" role="note"><?= e($ppText) ?></p>
