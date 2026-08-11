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

function admin_header(string $title, string $active = ''): void
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
        $items['ads']         = ['ads.php', 'Ads'];
        if (!$hub) {
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
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — Newsroom — <?= e(setting('site_title', 'The Prairie Dispatch')) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/svg+xml" href="<?= e(site_asset('favicon.svg')) ?>">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="adminbar">
  <div class="wrap">
    <a href="/" title="View the site"><img src="<?= e(site_asset('logo-reversed.svg')) ?>" alt="<?= e(setting('site_title')) ?>"></a>
    <span class="who"><?= pp_is_hub() ? 'Control room' : 'Newsroom' ?></span>
    <?php if ($user): ?>
    <nav aria-label="Admin">
      <?php foreach ($items as $key => [$href, $label]): ?>
      <a href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="post-edit.php">+ New story</a>
      <a href="logout.php"><?= e($user['name']) ?> · Sign out</a>
    </nav>
    <?php endif; ?>
  </div>
</div>
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
    <span><a href="/" style="color:inherit">View the site →</a></span>
  </div>
</footer>
</body>
</html>
<?php
}
