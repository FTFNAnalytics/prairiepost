<?php
/** The Prairie Dispatch — admin chrome. Include after bootstrap; call admin_header()/admin_footer(). */

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
    if ($hub && is_editor($user)) {
        // The control room: the network-wide pages exist only on the hub.
        $items['network'] = ['network-posts.php', 'Network desk'];
        $items['netwire'] = ['network-wire.php', 'Newswire'];
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
    }
    $items['profile'] = ['profile.php', 'Your profile'];
    // The dateline rail's live status — one small indexed query.
    $lastFetch = null;
    try {
        $lastFetch = db()->query('SELECT MAX(last_fetched_at) AS t FROM sources')->fetch()['t'] ?? null;
    } catch (PDOException) {
        // A dateline without a wire time is still a dateline.
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — <?= pp_is_hub() ? 'Control room' : 'Newsroom' ?> — <?= e(setting('site_title', 'The Prairie Dispatch')) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/svg+xml" href="<?= e(site_asset('favicon.svg')) ?>">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
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
      <a class="nh-mark" href="index.php">CivisMedia <span><?= $hub ? 'Control room' : 'Newsroom' ?></span></a>
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
