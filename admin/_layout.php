<?php
/** The Prairie Dispatch — admin chrome. Include after bootstrap; call admin_header()/admin_footer(). */
require_once PP_ROOT . '/app/admin-theme.php';

// The newsroom works on the network's clock: its date inputs are read on
// it, so its pages print on it too (pp_paper_zone() in app/i18n.php).
if (!defined('PP_NEWSROOM_CLOCK')) {
    define('PP_NEWSROOM_CLOCK', true);
}

function flash_set(string $message, bool $error = false): void
{
    $_SESSION['flash'] = ['message' => $message, 'error' => $error];
}

function flash_show(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    echo '<div class="flash' . ($f['error'] ? ' flash--error' : '') . '">' . e($f['message']) . '</div>';
}

function admin_header(string $title, string $active = '', string $actions = ''): void
{
    $user = current_user();
    $hub = pp_is_hub();
    $items = [
        'dashboard' => ['index.php', 'Dashboard'],
        'posts'     => ['posts.php', 'Stories'],
    ];
    if (is_editor($user)) {
        // The whiteboard carries a count of reader tips nobody has looked
        // into yet: one small indexed query, like the wire time below.
        $waiting = 0;
        try {
            $waiting = pp_wb_waiting(current_site_id());
        } catch (PDOException) {
            // A board that can't be counted still opens.
        }
        $items['whiteboard'] = ['whiteboard.php', $waiting > 0 ? "Whiteboard ($waiting)" : 'Whiteboard'];
    }
    if ($hub && is_editor($user)) {
        // The control room: the network-wide pages exist only on the hub.
        $items['network'] = ['network-posts.php', 'Network desk'];
        $items['netwire'] = ['network-wire.php', 'Newswire'];
    }
    if ($hub) {
        // Editors and authors alike — AI assists, a journalist authors.
        $items['aidraft'] = ['ai-draft.php', 'Research desk'];
        // Every newsroom role watches what's surfacing across the regions.
        $items['monitor'] = ['monitor.php', 'Monitoring'];
    }
    if ($hub && is_editor($user)) {
        $items['analytics'] = ['analytics.php', 'Analytics'];
        $items['agents'] = ['agents.php', 'Agent desk'];
        $items['social'] = ['social.php', 'Social desk'];
    }
    if ($hub && $user && $user['role'] === 'admin') {
        $items['entities'] = ['entities.php', 'Entities'];
    }
    if (is_editor($user)) {
        $items['linkpost']    = ['link-post.php', 'Post a link'];
        $items['categories']  = ['categories.php', 'Desks'];
        $items['sources']     = ['sources.php', 'Sources'];
        if ($hub) {
            // Network campaigns are the hub's advertising desk, admin-only.
            if ($user['role'] === 'admin') {
                $items['ads'] = ['network-ads.php', 'Advertising'];
            }
            $items['media'] = ['media-requests.php', 'Submissions'];
        } else {
            $items['ads'] = ['ads.php', 'Advertising'];
            // The hub is not a paper — no daily edition, no subscriber list.
            $items['newsletter']  = ['newsletter.php', 'The 6 a.m.'];
            $items['subscribers'] = ['subscribers.php', 'Subscribers'];
        }
    }
    if ($hub && is_editor($user)) {
        $items['inquiries'] = ['inquiries.php', 'Inquiries'];
    }
    if ($hub) {
        // The living project document — every newsroom role can read it.
        $items['roadmap'] = ['roadmap.php', 'Roadmap'];
    }
    if ($user && $user['role'] === 'admin') {
        $items['users'] = ['users.php', 'Accounts'];
        $items['settings'] = ['settings.php', 'Settings'];
        if ($hub) {
            $items['apikeys'] = ['api-keys.php', 'API keys'];
            $items['audit'] = ['audit.php', 'Audit'];
            $items['ops'] = ['ops.php', 'Ops'];
        }
    }
    $items['profile'] = ['profile.php', 'Your profile'];
    // The dateline rail's live status — one small indexed query.
    $lastFetch = null;
    try {
        $lastFetch = db()->query('SELECT MAX(last_fetched_at) AS t FROM sources')->fetch()['t'] ?? null;
    } catch (PDOException) {
        // A dateline without a wire time is still a dateline.
    }
    // A paper's newsroom wears the paper: its palette.json "admin" block
    // recolours the sheet and draws its own masthead (app/admin-theme.php).
    // The hub has no block and keeps the control room as it is.
    $theme = pp_admin_theme();
    $paperName = setting('site_title', 'The Prairie Dispatch');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — <?= pp_is_hub() ? 'Control room' : 'Newsroom' ?> — <?= e(setting('site_title', 'The Prairie Dispatch')) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/svg+xml" href="<?= e(site_asset('favicon.svg')) ?>">
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= e(pp_admin_sheet_version()) ?>">
<?php if ($theme && $theme['css'] !== ''): ?>
<style><?= $theme['css'] ?></style>
<?php endif; ?>
</head>
<body<?= $theme ? ' class="' . e($theme['classes']) . '"' : '' ?>>
<header>
  <div class="nh-strip">
    <div class="wrap">
      <span><?= e(setting('site_title', 'The Prairie Dispatch')) ?> · <?= $hub ? 'Control room' : 'Newsroom' ?> edition</span>
      <span><?= e(date('l, F j, Y')) ?></span>
      <span><?= $lastFetch ? 'Wire last fetched ' . e(fmt_date($lastFetch, 'g:i a')) : 'Wire not yet fetched' ?></span>
    </div>
  </div>
  <div class="nh-brand">
    <div class="wrap">
      <?php if ($theme): ?>
      <a class="nh-mark nh-mark--paper" href="index.php">
        <?php if ($theme['logo']): ?>
        <img class="nh-mark__logo" src="<?= e($theme['logo']) ?>" alt="<?= e($paperName) ?>">
        <?php else: ?>
        <?php if ($theme['mark']): ?><img class="nh-mark__img" src="<?= e($theme['mark']) ?>" alt=""><?php endif; ?>
        <span class="nh-mark__name"><?= e($paperName) ?></span>
        <?php endif; ?>
        <span class="nh-mark__label">Newsroom</span>
      </a>
      <?php else: ?>
      <a class="nh-mark" href="index.php">CivisMedia <span><?= $hub ? 'Control room' : 'Newsroom' ?></span></a>
      <?php endif; ?>
      <?php if ($actions !== ''): ?><div class="nh-actions"><?= $actions ?></div><?php endif; ?>
    </div>
  </div>
  <?php if ($user): ?>
  <nav class="nh-nav" aria-label="Admin">
    <div class="wrap">
      <?php foreach ($items as $key => [$href, $label]): ?>
      <a href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <span class="who"><?= e($user['name']) ?> · <a href="logout.php">Sign out</a></span>
    </div>
  </nav>
  <?php endif; ?>
  <div class="nh-hairline"></div>
</header>
<main class="adminmain">
  <div class="wrap">
<?php
}

function admin_footer(): void
{
    ?>
  </div>
</main>
<footer class="adminfoot">
  <div class="wrap">
    <span><?= e(setting('site_title', 'The Prairie Dispatch')) ?> · <?= pp_is_hub() ? 'Control room' : 'Newsroom' ?></span>
    <span><a href="/">View the site →</a></span>
  </div>
</footer>
</body>
</html>
<?php
}
