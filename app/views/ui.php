<?php
/** The Prairie Dispatch — public page chrome and shared render helpers. */

/** Per-site chrome options from palette.json ("chrome": {...}). */
function pp_chrome(string $key, $default = null)
{
    return pp_brand_file()['chrome'][$key] ?? $default;
}

/** Desks shown in this site's nav, optionally filtered/ordered by chrome.nav. */
function pp_nav_categories(): array
{
    $cats = categories_all();
    $order = pp_chrome('nav');
    if (!is_array($order) || !$order) {
        return $cats;
    }
    $bySlug = array_column($cats, null, 'slug');
    return array_values(array_filter(array_map(fn ($slug) => $bySlug[$slug] ?? null, $order)));
}

/** Inline style carrying the desk colour for eyebrows, nav and section heads. */
function desk_style(?string $color): string
{
    $color = $color ?: '#17301C';
    return preg_match('/^#[0-9A-Fa-f]{3,8}$/', $color) ? ' style="--desk:' . e($color) . '"' : '';
}

function eyebrow(array $post): string
{
    if (empty($post['category_name'])) {
        return '';
    }
    $href = url('desk/' . $post['category_slug']);
    // Fill-only desk colours (Weather) set ink type on a colour block instead.
    if (!empty($post['category_color_is_fill'])) {
        return '<a class="eyebrow eyebrow--weather" href="' . e($href) . '">' . e($post['category_name']) . '</a>';
    }
    return '<a class="eyebrow" href="' . e($href) . '"' . desk_style(pp_desk_hex($post['category_slug'] ?? null, $post['category_color'])) . '>'
         . e($post['category_name']) . '</a>';
}

function story_photo(array $post, bool $withCaption = false): string
{
    if (empty($post['image'])) {
        return '';
    }
    $img = '<img src="' . e($post['image']) . '" alt="' . e($post['image_caption'] ?: $post['title']) . '" loading="lazy">';
    if (!$withCaption || ($post['image_caption'] === '' && $post['image_credit'] === '')) {
        return '<a class="photo" href="' . e(url('story/' . $post['slug'])) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>';
    }
    $cap = '<figcaption>' . e($post['image_caption']);
    if ($post['image_credit'] !== '') {
        $cap .= ' <span class="credit">' . e($post['image_credit']) . '</span>';
    }
    $cap .= '</figcaption>';
    return '<figure class="photo">' . $img . $cap . '</figure>';
}

function story_card(array $post, bool $withPhoto = true): string
{
    $html = '<article class="card">';
    if ($withPhoto) {
        $html .= story_photo($post);
    }
    $html .= eyebrow($post);
    $html .= '<h3><a href="' . e(url('story/' . $post['slug'])) . '">' . e($post['title']) . '</a></h3>';
    if (!empty($post['lede'])) {
        $html .= '<p>' . e(excerpt($post['lede'], 140)) . '</p>';
    }
    $html .= '<p class="byline">' . dateline($post) . '</p>';
    $html .= '</article>';
    return $html;
}

/**
 * Page head + masthead + nav.
 * $meta keys: title, description, canonical, og_image, og_type, jsonld (array).
 */
function page_header(array $meta = [], string $activeDesk = ''): void
{
    $siteTitle = setting('site_title', 'The Prairie Dispatch');
    $tagline   = setting('tagline', 'News to the horizon');
    $title = isset($meta['title']) && $meta['title'] !== ''
        ? $meta['title'] . ' — ' . $siteTitle
        : $siteTitle . ' — ' . $tagline;
    $description = $meta['description'] ?? setting('meta_description', '');
    $canonical   = $meta['canonical'] ?? (site_url() . ($_SERVER['REQUEST_URI'] ?? '/'));
    $canonical   = strtok($canonical, '?') ?: $canonical;
    if (!empty($meta['keep_query'])) {
        $canonical = $meta['canonical'] ?? (site_url() . ($_SERVER['REQUEST_URI'] ?? '/'));
    }
    $ogImage = $meta['og_image'] ?? (site_url() . site_asset('og-default.png'));
    $ogType  = $meta['og_type'] ?? 'website';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<?php if ($description !== ''): ?><meta name="description" content="<?= e($description) ?>">
<?php endif; ?><link rel="canonical" href="<?= e($canonical) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(site_asset('favicon.svg')) ?>">
<link rel="alternate" type="application/rss+xml" title="<?= e($siteTitle) ?>" href="<?= e(site_url()) ?>/feed/">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="/assets/css/prairie.css">
<?php if (pp_chrome('template') === 'echo-v3'): ?><link rel="stylesheet" href="/assets/css/broadsheet.css">
<?php endif; ?><?php if (pp_chrome('template') === 'aurora'): ?><link rel="stylesheet" href="/assets/css/aurora.css">
<?php endif; ?><?php if (pp_chrome('template') === 'chronicle'): ?><link rel="stylesheet" href="/assets/css/chronicle.css">
<?php endif; ?><?php if (site_asset('brand.css') !== '/assets/img/brand.css'): ?><link rel="stylesheet" href="<?= e(site_asset('brand.css')) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= e($siteTitle) ?>">
<meta property="og:title" content="<?= e($meta['title'] ?? $siteTitle) ?>">
<?php if ($description !== ''): ?><meta property="og:description" content="<?= e($description) ?>">
<?php endif; ?><meta property="og:type" content="<?= e($ogType) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php if (!empty($meta['jsonld'])): ?>
<script type="application/ld+json"><?= json_encode($meta['jsonld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
<?php $analytics = setting('analytics_code'); if ($analytics !== '') { echo $analytics . "\n"; } ?>
</head>
<body class="<?= trim((pp_chrome('cards') === 'panel' ? 'cards-panel ' : '') . (pp_chrome('template') === 'echo-v3' ? 't-dark' : '') . (pp_chrome('template') === 'aurora' ? 't-aurora' : '') . (pp_chrome('template') === 'chronicle' ? 't-chronicle' : '')) ?>">
<a class="pp-meta" href="#content" style="position:absolute;left:-9999px" onfocus="this.style.left='8px';this.style.top='8px'" onblur="this.style.left='-9999px'">Skip to the news</a>

<?php if (pp_chrome('template') === 'echo-v3'): ?>
<?php $mastWords = preg_split('/\s+/', trim(preg_replace('/^The\s+/i', '', $siteTitle))); $mastLast = count($mastWords) > 1 ? array_pop($mastWords) : ''; ?>
<div class="v3-strip">
  <div class="wrap">
    <div class="grp">
      <span><?= e(date('l, F j, Y')) ?></span>
      <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
    </div>
    <div class="grp">
      <?php if (setting('breaking_label') !== '' && setting('breaking_url') !== ''): ?>
      <a class="hot" href="<?= e(setting('breaking_url')) ?>"><?= e(setting('breaking_label')) ?></a>
      <?php endif; ?>
      <a class="hot" href="<?= e(url('subscribe')) ?>">Subscribe</a>
      <a href="<?= e(url('search')) ?>">Search</a>
    </div>
  </div>
</div>
<div class="v3-mast">
  <a class="box" href="/" aria-label="<?= e($siteTitle) ?> — front page">
    <span class="l1"><?= e($mastLast !== '' ? implode(' ', $mastWords) : $siteTitle) ?></span>
    <?php if ($mastLast !== ''): ?><span class="l2" style="display:block"><?= e($mastLast) ?></span><?php endif; ?>
  </a>
</div>
<nav class="v3-nav" aria-label="Desks">
  <div class="wrap">
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<?php $banner = pp_chrome('banner'); if (is_string($banner) && $banner !== '' && ($GLOBALS['pp_front_page'] ?? false)): ?>
<div class="v3-banner" style="background-image:url('<?= e($banner) ?>')"></div>
<?php endif; ?>
<?php elseif (pp_chrome('template') === 'aurora'): ?>
<div class="ga-strip">
  <div class="wrap">
    <div class="grp">
      <span><?= e(date('l, F j, Y')) ?></span>
      <?php if (setting('weather_line') !== ''): ?>
      <a href="<?= e(url('desk/weather')) ?>"><?= e(setting('weather_line')) ?></a>
      <?php endif; ?>
    </div>
    <div class="grp">
      <?php if (setting('breaking_label') !== '' && setting('breaking_url') !== ''): ?>
      <a class="hot" href="<?= e(setting('breaking_url')) ?>"><?= e(setting('breaking_label')) ?></a>
      <?php endif; ?>
      <?php if (setting('contact_email') !== ''): ?>
      <a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a>
      <?php endif; ?>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
      <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
    </div>
  </div>
</div>
<header class="ga-head">
  <div class="wrap">
    <a class="logo" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('logo-header.png')) ?>" alt="<?= e($siteTitle) ?>">
    </a>
    <div class="acts">
      <a class="lnk" href="<?= e(url('search')) ?>">Search</a>
      <?php if (setting('contact_email') !== ''): ?>
      <a class="ga-btn ga-btn--ghost" href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a>
      <?php endif; ?>
      <a class="ga-btn ga-btn--solid" href="<?= e(url('subscribe')) ?>">Get the newsletter</a>
    </div>
  </div>
</header>
<nav class="ga-nav" aria-label="Desks">
  <div class="wrap">
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<?php elseif (pp_chrome('template') === 'chronicle'): ?>
<div class="kc-strip">
  <div class="wrap">
    <div class="grp">
      <span><?= e(date('l, j F Y')) ?><?= setting('weather_line') !== '' ? ' · ' . e(setting('weather_line')) : '' ?></span>
    </div>
    <div class="grp">
      <?php if (setting('breaking_label') !== '' && setting('breaking_url') !== ''): ?>
      <a href="<?= e(setting('breaking_url')) ?>"><strong><?= e(setting('breaking_label')) ?></strong></a>
      <?php endif; ?>
      <?php if (setting('contact_email') !== ''): ?>
      <a href="mailto:<?= e(setting('contact_email')) ?>">Tips line</a>
      <?php endif; ?>
      <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="kc-head">
  <div class="wrap">
    <a class="brand" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('bear-crest.png')) ?>" alt="">
      <span>
        <span class="t1"><?= e($siteTitle) ?></span>
        <span class="t2"><?= e($tagline) ?></span>
      </span>
    </a>
    <div class="acts">
      <a class="kc-btn kc-btn--ghost" href="<?= e(url('corrections')) ?>">Corrections</a>
      <a class="kc-btn kc-btn--mint" href="<?= e(url('subscribe')) ?>">Subscribe</a>
    </div>
  </div>
</header>
<nav class="kc-nav" aria-label="Desks">
  <div class="wrap">
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <a class="sp" href="<?= e(url('search')) ?>">Search</a>
  </div>
</nav>
<?php elseif (pp_chrome('header') === 'bar'): ?>
<header class="topbar">
  <div class="wrap">
    <a class="tb-logo" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('logo-reversed.svg')) ?>" alt="<?= e($siteTitle) ?>">
    </a>
    <nav class="tb-nav" aria-label="Desks">
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= desk_style(pp_desk_hex($cat['slug'], $cat['color'])) ?><?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php if (setting('breaking_label') !== '' && setting('breaking_url') !== ''): ?>
    <a class="tb-breaking" href="<?= e(setting('breaking_url')) ?>"><?= e(setting('breaking_label')) ?></a>
    <?php endif; ?>
    <?php if (setting('weather_line') !== ''): ?>
    <a class="tb-weather" href="<?= e(url('desk/weather')) ?>"><?= e(setting('weather_line')) ?></a>
    <?php endif; ?>
    <a class="tb-search" href="<?= e(url('search')) ?>" aria-label="Search the archive">Search</a>
  </div>
</header>
<?php else: ?>
<div class="skybar">
  <div class="wrap pp-meta">
    <span><?= e(date('l, F j, Y')) ?></span>
    <span><a href="<?= e(url('desk/weather')) ?>"><?= e(setting('weather_line', '')) ?></a></span>
  </div>
</div>

<header class="masthead">
  <div class="wrap">
    <a class="logo" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('logo-primary.svg')) ?>" alt="<?= e($siteTitle) ?>" width="820" height="138">
    </a>
    <div class="pp-horizon pp-horizon--full" style="margin-top:8px"></div>
    <div class="tagline">
      <span class="pp-meta"><?= e($tagline) ?></span>
      <span class="pp-meta muted"><a href="<?= e(url('feed/')) ?>" style="color:inherit;text-decoration:none">RSS</a> · <a href="<?= e(url('search')) ?>" style="color:inherit;text-decoration:none">Search</a></span>
    </div>
  </div>
</header>

<nav class="desknav" aria-label="Desks">
  <div class="wrap">
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= desk_style(pp_desk_hex($cat['slug'], $cat['color'])) ?><?= !empty($cat['color_is_fill']) ? ' class="is-weather"' : '' ?><?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
  </div>
</nav>

<?php endif; ?>

<main id="content">
<?php
}

function page_footer(): void
{
    $siteTitle = setting('site_title', 'The Prairie Dispatch');
    ?>
</main>

<?php if (pp_chrome('template') === 'chronicle'): ?>
<footer class="kc-foot">
  <div class="cols">
    <div class="brand">
      <a href="/"><img src="<?= e(site_asset('bear-crest.png')) ?>" alt=""></a>
      <div class="t"><?= e($siteTitle) ?></div>
    </div>
    <div>
      <div class="fh kc-caps">Sections</div>
      <div class="lnks">
        <?php foreach (pp_nav_categories() as $cat): ?>
        <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <div class="fh kc-caps">The paper</div>
      <div class="lnks">
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="<?= e(url('feed/')) ?>">RSS feed</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="/admin/">Newsroom sign-in</a>
      </div>
    </div>
    <div>
      <div class="fh kc-caps">Support</div>
      <div class="lnks">
        <a href="<?= e(url('subscribe')) ?>">Subscribe</a>
        <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
        <?php if (setting('contact_email') !== ''): ?>
        <a href="mailto:<?= e(setting('contact_email')) ?>">Tips</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="legal">
    <div class="wrap">
      <span><?= e(setting('footer_line')) ?> © <?= e(date('Y')) ?> <?= e($siteTitle) ?>.</span>
      <span><?= e(setting('tagline')) ?></span>
    </div>
  </div>
</footer>
</body>
</html>
<?php return; endif; ?>

<?php if (pp_chrome('template') === 'aurora'): ?>
<footer class="ga-foot">
  <div class="cols">
    <div>
      <a class="logo" href="/"><img src="<?= e(site_asset('logo-header.png')) ?>" alt="<?= e($siteTitle) ?>"></a>
      <p class="about"><?= e(setting('footer_line')) ?></p>
    </div>
    <div>
      <div class="fh">News</div>
      <div class="lnks">
        <?php foreach (pp_nav_categories() as $cat): ?>
        <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <div class="fh">The paper</div>
      <div class="lnks">
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="<?= e(url('feed/')) ?>">RSS feed</a>
        <a href="<?= e(url('newsletter/')) ?>">Newsletter archive</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="/admin/">Newsroom sign-in</a>
      </div>
    </div>
    <div>
      <div class="fh">Contact</div>
      <div class="lnks">
        <?php if (setting('contact_email') !== ''): ?>
        <a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a>
        <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a>
        <?php endif; ?>
        <a href="<?= e(url('subscribe')) ?>">Get the newsletter</a>
      </div>
      <?php if (setting('paper_address') !== ''): ?>
      <p class="about" style="margin-top:14px"><?= nl2br(e(setting('paper_address'))) ?></p>
      <?php endif; ?>
    </div>
  </div>
  <div class="legal">
    <div class="wrap">
      <span>© <?= e(date('Y')) ?> <?= e($siteTitle) ?> · <?= e(setting('tagline')) ?></span>
      <span>Source Serif 4 · Archivo</span>
    </div>
  </div>
</footer>
</body>
</html>
<?php return; endif; ?>

<?php if (pp_chrome('template') === 'echo-v3'): ?>
<footer class="v3-foot">
  <div class="cols">
    <div>
      <div class="fh">Contact <?= e(preg_replace('/^The\s+/i', '', $siteTitle)) ?></div>
      <p><?= nl2br(e(setting('paper_address'))) ?><?= setting('contact_email') !== '' ? '<br>' . e(setting('contact_email')) : '' ?></p>
    </div>
    <div>
      <div class="fh">Sections</div>
      <div class="lnks">
        <?php foreach (pp_nav_categories() as $cat): ?>
        <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <div class="fh">The paper</div>
      <div class="lnks">
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="<?= e(url('feed/')) ?>">RSS feed</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="/admin/">Newsroom sign-in</a>
      </div>
    </div>
    <div>
      <div class="fh">Newsletter</div>
      <p><?= e(setting('newsletter_copy')) ?></p>
      <form class="nl" method="post" action="<?= e(url('subscribe')) ?>">
        <input type="email" name="email" required placeholder="you@email.ca" aria-label="Email address">
        <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button type="submit">Sign up</button>
      </form>
    </div>
  </div>
  <div class="legal">© <?= e(date('Y')) ?> <?= e($siteTitle) ?> · <?= e(setting('tagline')) ?></div>
</footer>
</body>
</html>
<?php return; endif; ?>

<footer class="sitefoot">
  <div class="pp-horizon pp-horizon--full"></div>
  <div class="inner">
    <div class="wrap">
      <div class="cols">
        <div class="brandcol">
          <img src="<?= e(site_asset('logo-reversed.svg')) ?>" alt="<?= e($siteTitle) ?>" width="280" height="47">
          <p><?= e(setting('footer_line', 'A regional daily for people who live between the towns: farm and market news alongside council, courts, weather and community.')) ?></p>
        </div>
        <div>
          <p class="k">Desks</p>
          <ul>
            <?php foreach (pp_nav_categories() as $cat): ?>
            <li><a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div>
          <p class="k">The paper</p>
          <ul>
            <li><a href="<?= e(url('search')) ?>">Search the archive</a></li>
            <li><a href="<?= e(url('feed/')) ?>">RSS feed</a></li>
            <li><a href="<?= e(url('newsletter/')) ?>">The 6 a.m. newsletter</a></li>
            <li><a href="<?= e(url('corrections')) ?>">The corrections file</a></li>
            <li><a href="/admin/">Newsroom sign-in</a></li>
          </ul>
        </div>
      </div>
      <div class="legal">
        <span>© <?= e(date('Y')) ?> <?= e($siteTitle) ?> · <?= e(setting('tagline', 'News to the horizon')) ?></span>
        <span>Archivo · Newsreader · IBM Plex Mono</span>
      </div>
    </div>
  </div>
</footer>
</body>
</html>
<?php
}

/** The 6 a.m. signup block, used on the rail and the subscribe page. */
function signup_block(): string
{
    $heading = setting('newsletter_heading', 'The 6 a.m.');
    $copy    = setting('newsletter_copy', 'One email before the day starts: council, markets, weather, and what happened overnight.');
    $ok = isset($_GET['subscribed']);
    $html  = '<div class="signup">';
    $html .= '<p class="k">' . e($heading) . '</p>';
    if ($ok) {
        $html .= isset($_GET['confirm'])
            ? '<p>Nearly there — we\'ve emailed you a confirmation link. One click and the next edition lands at 6 a.m.</p>'
            : '<p>You\'re on the list. The next edition lands at 6 a.m.</p>';
    } else {
        $html .= '<p>' . e($copy) . '</p>';
        $html .= '<form method="post" action="' . e(url('subscribe')) . '">';
        $html .= '<input type="email" name="email" required placeholder="you@example.com" aria-label="Email address">';
        $html .= '<input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">';
        $html .= '<button class="btn" type="submit">Subscribe</button>';
        $html .= '</form>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * An ad slot renders only when there is something to show; always labelled.
 * The ad manager (Ads in the newsroom) takes precedence; the raw-code
 * settings remain as a fallback for pasted network tags.
 */
function ad_slot(string $key): string
{
    $ad = ad_for_placement($key);
    if ($ad) {
        return '<div class="adslot adslot--' . e($key) . '"><p class="k">Advertisement</p>' . render_ad($ad) . '</div>';
    }
    $code = setting('ad_' . $key);
    if (trim($code) === '') {
        return '';
    }
    return '<div class="adslot"><p class="k">Advertisement</p>' . $code . '</div>';
}

/** Render one ad's creative. House ads are built from the brand system. */
function render_ad(array $ad): string
{
    $click = url('ad') . '?id=' . (int) $ad['id'];
    switch ($ad['kind']) {
        case 'image':
            $img = '<img src="' . e($ad['image']) . '" alt="' . e($ad['name']) . '" loading="lazy">';
            return $ad['link_url'] !== ''
                ? '<a class="ad-image" href="' . e($click) . '" rel="nofollow sponsored">' . $img . '</a>'
                : '<span class="ad-image">' . $img . '</span>';
        case 'html':
            return (string) $ad['html'];
        default: // house — Shelterbelt bed, mono kicker, condensed heading, sky button
            $html  = '<div class="housead">';
            if ($ad['kicker'] !== '') {
                $html .= '<p class="k2">' . e($ad['kicker']) . '</p>';
            }
            $html .= '<p class="h">' . e($ad['heading'] ?: $ad['name']) . '</p>';
            if ($ad['body_text'] !== '') {
                $html .= '<p class="b">' . e($ad['body_text']) . '</p>';
            }
            if ($ad['link_url'] !== '') {
                $html .= '<a class="btn" href="' . e($click) . '" rel="nofollow sponsored">' . e($ad['button_label'] ?: 'Find out more') . '</a>';
            }
            $html .= '</div>';
            return $html;
    }
}
