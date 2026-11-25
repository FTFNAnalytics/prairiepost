<?php
/**
 * The tips form. A reader's tip goes onto this paper's whiteboard
 * (admin/whiteboard.php), where only its editors read it.
 *
 * It replaced the tips mailto links on 25 Nov 2026: 22 of the 24 papers'
 * tips addresses were on domains with no mail server, so a tip sent there
 * went nowhere. What the form keeps, and what it doesn't, is set out in
 * app/whiteboard.php: the tip and whatever the reader chose to add, never
 * their IP address.
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

// The hub is not a paper; its brochure has its own contact form.
if (pp_is_hub()) {
    redirect('/#contact');
}

$siteId = current_site_id();
$paper = setting('site_title', 'The Prairie Dispatch');
$state = '';
$errors = [];
$in = ['tip' => '', 'link' => '', 'name' => '', 'contact' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($in) as $k) {
        $in[$k] = is_string($_POST[$k] ?? null) ? $_POST[$k] : '';
    }
    [$state, $errors] = pp_tips_submit($siteId, $_POST);
    if ($state === 'sent') {
        redirect(url('tips') . '?sent=1');
    }
    http_response_code($state === 'busy' ? 429 : 422);
}
$sent = $state === '' && isset($_GET['sent']);

/** One field's error, translated, with the id its input points at. */
$err = static function (string $field, string $id) use ($errors): string {
    return isset($errors[$field]) ? '<p class="pp-tipform__err" id="' . $id . '-err">' . e(pp_t($errors[$field])) . '</p>' : '';
};
$aria = static function (string $field, string $id, bool $help = false) use ($errors): string {
    $by = array_filter([$help ? "$id-help" : '', isset($errors[$field]) ? "$id-err" : '']);
    return ($by ? ' aria-describedby="' . implode(' ', $by) . '"' : '') . (isset($errors[$field]) ? ' aria-invalid="true"' : '');
};

page_header([
    'title'       => pp_t('Send a tip'),
    'description' => sprintf(pp_t('Tell the editors of %s what you know. Tips go straight to the newsroom.'), $paper),
    'canonical'   => site_url() . '/tips',
], 'tips');
?>
<div class="pagehead wrap">
  <span class="pp-meta"><?= e(pp_t('The newsroom')) ?></span>
  <h1><?= e(pp_t('Send a tip')) ?></h1>
  <p class="desc"><?= e(sprintf(pp_t('Seen something %s should look into? Tell the editors here. Your tip goes straight to the newsroom, and nothing you send is published as it stands.'), $paper)) ?></p>
  <div class="pp-horizon"></div>
</div>

<div class="article wrap">
  <div class="pp-tips">
  <?php if ($sent): ?>
    <div class="pp-tips__done" role="status">
      <h2><?= e(pp_t('Thank you. Your tip is with the editors.')) ?></h2>
      <p><?= e(pp_t("If you left a way to reach you, someone may be in touch. If you didn't, we have no way to reply, and that is fine.")) ?></p>
      <p><a href="<?= e(url('tips')) ?>"><?= e(pp_t('Send another tip')) ?></a> · <a href="<?= e(url()) ?>"><?= e(pp_t('Back to the front page')) ?></a></p>
    </div>
  <?php else: ?>
    <?php if ($state === 'expired'): ?>
    <p class="pp-tips__alert" role="alert"><?= e(pp_t('The form expired, or was sent too quickly. Check your tip below and send it again.')) ?></p>
    <?php elseif ($state === 'busy'): ?>
    <p class="pp-tips__alert" role="alert"><?= e(pp_t('The tips line is busy right now. Please try again in an hour.')) ?></p>
    <?php elseif ($state === 'invalid'): ?>
    <p class="pp-tips__alert" role="alert"><?= e(pp_t('Some of the form needs another look.')) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= e(url('tips')) ?>" class="pp-tipform">
      <input type="hidden" name="t" value="<?= e(pp_tips_token($siteId)) ?>">
      <div class="pp-tipform__hp" aria-hidden="true">
        <label>Leave this empty <input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label>
      </div>

      <label for="tip-text"><?= e(pp_t('What should we know?')) ?></label>
      <textarea id="tip-text" name="tip" rows="8" maxlength="4000" required<?= $aria('tip', 'tip-text', true) ?>><?= e($in['tip']) ?></textarea>
      <p class="pp-tipform__help" id="tip-text-help"><?= e(pp_t('What happened, where and when, and how you know. Up to 4,000 characters.')) ?></p>
      <?= $err('tip', 'tip-text') ?>

      <label for="tip-link"><?= e(pp_t('A link, if there is one')) ?></label>
      <input id="tip-link" type="url" name="link" maxlength="1000" inputmode="url" placeholder="https://" value="<?= e($in['link']) ?>"<?= $aria('link', 'tip-link') ?>>
      <?= $err('link', 'tip-link') ?>

      <label for="tip-name"><?= e(pp_t('Your name')) ?> <span class="pp-tipform__opt"><?= e(pp_t('Optional.')) ?></span></label>
      <input id="tip-name" type="text" name="name" maxlength="120" autocomplete="name" value="<?= e($in['name']) ?>"<?= $aria('name', 'tip-name') ?>>
      <?= $err('name', 'tip-name') ?>

      <label for="tip-contact"><?= e(pp_t('How can we reach you?')) ?> <span class="pp-tipform__opt"><?= e(pp_t('Optional.')) ?></span></label>
      <input id="tip-contact" type="text" name="contact" maxlength="255" value="<?= e($in['contact']) ?>"<?= $aria('contact', 'tip-contact', true) ?>>
      <p class="pp-tipform__help" id="tip-contact-help"><?= e(pp_t("An email address or a phone number. Leave your name and this blank to stay anonymous, but then we can't ask you anything.")) ?></p>
      <?= $err('contact', 'tip-contact') ?>

      <button type="submit"><?= e(pp_t('Send the tip')) ?></button>
    </form>

    <div class="pp-tips__privacy">
      <h2><?= e(pp_t('Who reads it')) ?></h2>
      <p><?= e(sprintf(pp_t('Only the editors of %s. Your tip is saved without your IP address or any details of your device. We never publish your name or your contact details without your permission.'), $paper)) ?></p>
      <p><?= e(pp_t("Please don't send documents through this form. If you have them, leave a way to reach you and we'll arrange a safer way to receive them.")) ?></p>
    </div>
  <?php endif; ?>
  </div>
</div>
<?php page_footer(); ?>
