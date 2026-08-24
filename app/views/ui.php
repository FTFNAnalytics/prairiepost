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

/** Whether a post is a wire link — its headline links to the source outlet. */
function is_link_post(array $post): bool
{
    return ($post['post_type'] ?? '') === 'link' && !empty($post['source_url']);
}

/** Where a post's headline points: the story page, or straight to the outlet. */
function post_href(array $post): string
{
    return is_link_post($post) ? (string) $post['source_url'] : url('story/' . $post['slug']);
}

/** Extra anchor attributes for outbound wire links. */
function post_link_attr(array $post): string
{
    return is_link_post($post) ? ' target="_blank" rel="noopener"' : '';
}

/** The outlet a wire link credits: its saved name, or the link's hostname. */
function post_source_label(array $post): string
{
    if (!empty($post['source_name'])) {
        return (string) $post['source_name'];
    }
    $host = parse_url((string) ($post['source_url'] ?? ''), PHP_URL_HOST) ?: '';
    return preg_replace('/^www\./', '', $host);
}

/**
 * The Torch's card kicker. The three cities are the paper's subject, so a
 * story datelined to one of them leads with the place — "BELCARRA",
 * "PORT MOODY". Everything else carries its desk instead — "COMMUNITY",
 * "BUSINESS". Kicker colour is set by the card variant, never here.
 */
function torch_kicker(array $post): string
{
    if (!empty($post['dateline'])) {
        return e($post['dateline']);
    }
    if (!empty($post['category_name'])) {
        return e(pp_desk_label($post['category_slug'] ?? null, $post['category_name']));
    }
    return '';
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
        return '<a class="photo" href="' . e(post_href($post)) . '"' . post_link_attr($post) . ' tabindex="-1" aria-hidden="true">' . $img . '</a>';
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
    $html .= '<h3><a href="' . e(post_href($post)) . '"' . post_link_attr($post) . '>' . e($post['title']) . '</a></h3>';
    if (!empty($post['lede'])) {
        $html .= '<p>' . e(excerpt($post['lede'], 140)) . '</p>';
    }
    $html .= '<p class="byline">' . dateline($post) . '</p>';
    $html .= '</article>';
    return $html;
}

/**
 * One card for the standard template, used by the front page, the section
 * index and the article page's related row.
 *
 * Where there is no photograph the headline runs large on navy in the
 * photograph's place — it replaces the image, so it is not repeated in the
 * body beneath. Anatomy stays kicker → headline → deck → meta either way.
 */
function standard_card(array $p, array $o = []): string
{
    $kicker  = $o['kicker'] ?? ('Opinion' . (!empty($p['category_name']) ? ' · ' . $p['category_name'] : ''));
    $red     = !empty($o['red']);
    $deckLen = (int) ($o['deck'] ?? 0);
    $meta    = $o['meta'] ?? '';
    $class   = trim('sd-card ' . ($o['class'] ?? ''));
    $eager   = !empty($o['eager']);
    $kclass  = 'sd-kicker' . ($red ? ' sd-kicker--red' : '');

    $html  = '<a class="' . e($class) . '" href="' . e(url('story/' . $p['slug'])) . '">';
    if (!empty($p['image'])) {
        $html .= '<span class="ph"><img src="' . e($p['image']) . '" alt=""'
               . ($eager ? '' : ' loading="lazy"') . '></span>';
        $html .= '<span class="body">';
        $html .= '<span class="' . $kclass . '">' . e($kicker) . '</span>';
        $html .= '<h2>' . e($p['title']) . '</h2>';
    } else {
        $html .= '<span class="noph">';
        $html .= '<span class="' . $kclass . ' on-navy">' . e($kicker) . '</span>';
        $html .= '<span class="hl">' . e($p['title']) . '</span>';
        $html .= '</span>';
        $html .= '<span class="body">';
    }
    if ($deckLen > 0 && !empty($p['lede'])) {
        $html .= '<p class="deck">' . e(excerpt($p['lede'], $deckLen)) . '</p>';
    }
    if ($meta !== '') {
        $html .= '<span class="by">' . $meta . '</span>';
    }
    $html .= '</span></a>';
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
<html lang="<?= e(pp_lang()) ?>">
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
<?php endif; ?><?php if (pp_chrome('template') === 'pacific'): ?><link rel="stylesheet" href="/assets/css/pacific.css">
<?php endif; ?><?php if (pp_chrome('template') === 'current'): ?><link rel="stylesheet" href="/assets/css/current.css">
<?php endif; ?><?php if (pp_chrome('template') === 'bulletin'): ?><link rel="stylesheet" href="/assets/css/bulletin.css">
<?php endif; ?><?php if (pp_chrome('template') === 'westernwire'): ?><link rel="stylesheet" href="/assets/css/westernwire.css">
<?php endif; ?><?php if (pp_chrome('template') === 'torch'): ?><link rel="stylesheet" href="/assets/css/torch.css">
<?php endif; ?><?php if (pp_chrome('template') === 'standard'): ?><link rel="stylesheet" href="/assets/css/standard.css">
<?php endif; ?><?php if (pp_chrome('template') === 'turtleisland'): ?><link rel="stylesheet" href="/assets/css/turtleisland.css">
<?php endif; ?><?php if (pp_chrome('template') === 'pickering'): ?><link rel="stylesheet" href="/assets/css/pickering.css">
<?php endif; ?><?php if (pp_chrome('template') === 'bleuetblanc'): ?><link rel="stylesheet" href="/assets/css/bleuetblanc.css">
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
<body class="<?= trim((pp_chrome('cards') === 'panel' ? 'cards-panel ' : '') . (pp_chrome('template') === 'echo-v3' ? 't-dark' : '') . (pp_chrome('template') === 'aurora' ? 't-aurora' : '') . (pp_chrome('template') === 'chronicle' ? 't-chronicle' : '') . (pp_chrome('template') === 'pacific' ? 't-pacific' : '') . (pp_chrome('template') === 'current' ? 't-current' : '') . (pp_chrome('template') === 'bulletin' ? 't-bulletin' : '') . (pp_chrome('template') === 'westernwire' ? 't-westernwire' : '') . (pp_chrome('template') === 'torch' ? 't-torch' : '') . (pp_chrome('template') === 'standard' ? 't-standard' : '') . (pp_chrome('template') === 'turtleisland' ? 't-turtleisland' : '') . (pp_chrome('template') === 'pickering' ? 't-pickering' : '') . (pp_chrome('template') === 'bleuetblanc' ? 't-bleuetblanc' : '')) ?>">
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
<?php elseif (pp_chrome('template') === 'pacific'): ?>
<div class="pf-strip">
  <div class="wrap">
    <div class="grp">
      <span><?= e(date('l, F j, Y')) ?><?= setting('weather_line') !== '' ? ' · ' . e(setting('weather_line')) : '' ?></span>
    </div>
    <div class="grp">
      <a class="hide-s" href="<?= e(url('newsletter/')) ?>">Newsletters</a>
      <a class="hide-s" href="/admin/">Sign in</a>
      <a class="pf-btn pf-btn--inlet" href="<?= e(url('subscribe')) ?>">Subscribe</a>
    </div>
  </div>
</div>
<header class="pf-plate">
  <div class="inner">
    <a href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <span class="the">The</span>
      <span class="name">
        <img class="mark" src="<?= e(site_asset('mark.svg')) ?>" alt="">
        <span><?= e(preg_replace('/^The\s+/i', '', $siteTitle)) ?></span>
      </span>
    </a>
  </div>
</header>
<nav class="pf-nav" aria-label="Desks">
  <div class="wrap">
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <a class="sp" href="<?= e(url('search')) ?>">Search</a>
  </div>
</nav>
<?php if (setting('breaking_label') !== '' && setting('breaking_url') !== ''): ?>
<div class="pf-breaking">
  <div class="wrap">
    <span class="b">Breaking</span>
    <a href="<?= e(setting('breaking_url')) ?>"><?= e(setting('breaking_label')) ?></a>
  </div>
</div>
<?php endif; ?>
<?php elseif (pp_chrome('template') === 'current'): ?>
<?php $mastWords = preg_split('/\s+/', trim($siteTitle), 2); ?>
<div class="cu-util">
  <div class="wrap">
    <div class="grp">
      <strong><?= e(date('l, F j')) ?></strong>
      <?php if (setting('weather_line') !== ''): ?><span><?= e(setting('weather_line')) ?></span><?php endif; ?>
    </div>
    <div class="grp">
      <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
      <a href="<?= e(url('subscribe')) ?>">Support local journalism</a>
      <strong><a href="/admin/" style="color:#fff;text-decoration:none">Sign in</a></strong>
    </div>
  </div>
</div>
<header class="cu-mast">
  <div class="wrap">
    <div class="side"><?= nl2br(e(pp_chrome('mast_left') ?: '')) ?></div>
    <a class="lockup" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <span class="l1"><?= e(strtoupper($mastWords[0])) ?></span>
      <span class="l2"><?= e($mastWords[1] ?? '') ?></span>
      <svg class="wave" viewBox="0 0 300 14" aria-hidden="true"><path d="M4 8c48-5 96-5 146 0s96 5 146 0" fill="none" stroke="#2A8C86" stroke-width="3" stroke-linecap="round"/></svg>
      <span class="l3"><?= e($tagline) ?></span>
    </a>
    <div class="side"><?= nl2br(e(pp_chrome('mast_right') ?: '')) ?></div>
  </div>
</header>
<nav class="cu-nav" aria-label="Desks">
  <div class="wrap">
    <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Latest</a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <a class="sp" href="<?= e(url('search')) ?>" aria-label="Search the archive">Search</a>
  </div>
</nav>
<?php if (setting('breaking_label') !== '' && setting('breaking_url') !== ''): ?>
<div class="cu-strip<?= pp_chrome('strip_tone') === 'teal' ? ' cu-strip--teal' : '' ?>">
  <div class="wrap">
    <span class="lab">The Current</span>
    <span class="cp"><a href="<?= e(setting('breaking_url')) ?>"><?= e(setting('breaking_label')) ?></a></span>
    <a class="go" href="<?= e(setting('breaking_url')) ?>">Read more</a>
  </div>
</div>
<?php endif; ?>
<?php elseif (pp_chrome('template') === 'torch'): ?>
<?php
// The wordmark stacks "Tri Cities" over "TORCH"; split the title once here.
$ttWords = preg_split('/\s+/', trim($siteTitle));
$ttLast  = count($ttWords) > 1 ? array_pop($ttWords) : '';
$ttFirst = $ttLast !== '' ? implode(' ', $ttWords) : $siteTitle;
// Article and section pages carry the header lockup from the first pixel;
// the home page fades it in once the masthead has scrolled away.
$ttFront = (bool) ($GLOBALS['pp_front_page'] ?? false);
?>
<nav class="tt-nav<?= $ttFront ? '' : ' always-lockup' ?>" id="ttnav" aria-label="Sections">
  <div class="in">
    <a class="lockup" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
      <span class="wm"><b><?= e($ttFirst) ?></b><span><?= e(mb_strtoupper($ttLast)) ?></span></span>
    </a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<?php if ($ttFront): ?>
<script>
(function () {
  var nav = document.getElementById('ttnav');
  if (!nav) return;
  var on = false;
  var check = function () {
    var should = window.scrollY > 220;
    if (should !== on) { on = should; nav.classList.toggle('is-stuck', should); }
  };
  window.addEventListener('scroll', check, { passive: true });
  check();
})();
</script>
<?php endif; ?>
<?php elseif (pp_chrome('template') === 'standard'): ?>
<?php
// The wordmark stacks: "The Sudbury" over "STANDARD". The blackletter S is
// the monogram only — never a word — and it carries its own font.
$sdWords = preg_split('/\s+/', trim($siteTitle));
$sdLast  = count($sdWords) > 1 ? array_pop($sdWords) : '';
$sdFirst = $sdLast !== '' ? implode(' ', $sdWords) : $siteTitle;
$sdMono  = mb_substr(preg_replace('/^The\s+/i', '', $siteTitle), 0, 1);
?>
<div class="sd-util">
  <div class="in">
    <span><?= e(pp_chrome('place') ?: setting('tagline')) ?> &middot; <?= e(date('j F Y')) ?></span>
    <span class="grp">
      <a href="<?= e(url('newsletter/')) ?>">Newsletter</a>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Tips</a><?php endif; ?>
      <a href="/admin/">Sign in</a>
    </span>
  </div>
</div>
<header class="sd-mast">
  <div class="in">
    <a class="lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <span class="sx" aria-hidden="true"><?= e($sdMono) ?></span>
      <span>
        <span class="l1"><?= e($sdFirst) ?></span>
        <span class="l2"><?= e(mb_strtoupper($sdLast)) ?></span>
      </span>
    </a>
    <nav class="sd-nav" aria-label="Sections">
      <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('about')) ?>"<?= $activeDesk === 'about' ? ' aria-current="page"' : '' ?>>About</a>
    </nav>
  </div>
</header>
<?php elseif (pp_chrome('template') === 'turtleisland'): ?>
<?php
// The ink block. The turtle sits over the nameplate and the caps cut into its
// shell, so the two read as one shape — the mark is positioned over the type,
// not stacked above it, and the keyline in the stylesheet is what holds white
// letters against a white mark.
//
// On an article or a section front the block condenses; a section front puts
// the desk's name where the nameplate goes. $ppTiMast is set by those
// templates before page_header() runs.
$tiMode = $GLOBALS['ppTiMast'] ?? 'full';
$tiPlate = $GLOBALS['ppTiPlate'] ?? $siteTitle;
?>
<div class="ti-field">
  <div class="ti-col">
    <header class="ti-mast<?= $tiMode === 'slim' ? ' ti-mast--slim' : '' ?><?= $tiMode === 'section' ? ' ti-mast--section' : '' ?>">
      <div class="ti-util">
        <span><?= e(setting('tagline')) ?></span>
        <span class="grp">
          <a href="<?= e(url('subscribe')) ?>">Subscribe</a>
          <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Contact</a><?php endif; ?>
          <a href="/admin/">Sign in</a>
        </span>
      </div>
      <div class="ti-name">
        <?php if ($tiMode !== 'section'): ?>
        <span class="shell" aria-hidden="true"><img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="" width="140" height="124"></span>
        <?php endif; ?>
        <?php if ($tiMode === 'full'): ?>
        <h1 class="wm"><a style="color:inherit" href="/"><?= e($tiPlate) ?></a></h1>
        <?php else: ?>
        <p class="wm"><a style="color:inherit" href="<?= e($tiMode === 'section' ? '/' : '/') ?>"><?= e($tiPlate) ?></a></p>
        <?php endif; ?>
      </div>
      <div class="ti-rail">
        <nav aria-label="Sections">
          <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
          <?php foreach (pp_nav_categories() as $cat): ?>
          <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
          <?php endforeach; ?>
          <a href="<?= e(url('about')) ?>"<?= $activeDesk === 'about' ? ' aria-current="page"' : '' ?>>About</a>
        </nav>
        <form method="get" action="<?= e(url('search')) ?>" role="search">
          <input type="search" name="q" placeholder="Search" aria-label="Search the archive">
          <button type="submit" aria-label="Search">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="7" cy="7" r="4.6"/><path d="M10.4 10.4 14 14"/></svg>
          </button>
        </form>
      </div>
    </header>
    <div class="ti-pad">
<?php elseif (pp_chrome('template') === 'bleuetblanc'): ?>
<?php
// La ligne sous le nom du journal : la devise à la une, la rubrique ailleurs,
// pour que le lecteur sache toujours dans quelle section il se trouve.
$bbRub = $tagline;
// Toutes les rubriques, pas seulement celles de la barre : « le fil » n'y
// figure pas et doit tout de même se nommer.
foreach ($activeDesk !== '' ? categories_all() : [] as $c) {
    if ($c['slug'] === $activeDesk) {
        $bbRub = pp_desk_label($c['slug'], $c['name']);
        break;
    }
}
?>
<div class="bb-page">
  <div class="bb-bandeau">
    <span>Édition du <?= e(pp_date_full()) ?></span>
    <span class="grp">
      <a href="<?= e(url('newsletter/')) ?>"><?= e(pp_t('Newsletters')) ?></a>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(pp_t('Contact')) ?></a><?php endif; ?>
      <a href="/admin/"><?= e(pp_t('Sign in')) ?></a>
    </span>
  </div>

  <header class="bb-tete">
    <a class="bb-lock" href="/" aria-label="<?= e($siteTitle) ?>">
      <span class="sym" aria-hidden="true"><img src="<?= e(site_asset('mark.svg')) ?>" alt="" width="46" height="40"></span>
      <span>
        <span class="bb-nom"><?= e($siteTitle) ?></span>
        <span class="rub" style="display:block"><?= e($bbRub) ?></span>
      </span>
    </a>
    <form class="bb-chercher" method="get" action="<?= e(url('search')) ?>" role="search">
      <input type="search" name="q" placeholder="<?= e(pp_t('Search')) ?>" aria-label="<?= e(pp_t('Search the archive')) ?>">
      <button class="bb-btn" type="submit"><?= e(pp_t('Search')) ?></button>
    </form>
  </header>

  <nav class="bb-rubriques" aria-label="<?= e(pp_t('Sections')) ?>">
    <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>><?= e(pp_t('Home')) ?></a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
  </nav>
<?php elseif (pp_chrome('template') === 'pickering'): ?>
<?php
// The tile lockup leads, because that is what the masthead uses. The name
// splits into "The" over the rest so the two lines set flush beside the tile.
$pkWords = preg_split('/\s+/', trim($siteTitle));
$pkThe   = (strcasecmp($pkWords[0] ?? '', 'The') === 0) ? array_shift($pkWords) : '';
$pkName  = implode(' ', $pkWords);
$pkInit  = mb_strtoupper(mb_substr($pkName !== '' ? $pkName : $siteTitle, 0, 1));
// A section front replaces the tile masthead with the centred nameplate;
// section.php sets these before page_header() runs.
$pkMode  = $GLOBALS['ppPkMode'] ?? 'full';
?>
<div class="pk-page">
  <div class="pk-util">
    <span><?= e(setting('tagline')) ?> &middot; <?= e(date('l, F j, Y')) ?></span>
    <span class="grp">
      <a href="<?= e(url('newsletter/')) ?>">Newsletter</a>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Contact</a><?php endif; ?>
      <a href="/admin/">Sign in</a>
    </span>
  </div>
  <header class="pk-mast">
    <a class="pk-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <span class="pk-tile" aria-hidden="true"><?= e($pkInit) ?></span>
      <span>
        <?php if ($pkThe !== ''): ?><span class="sub" style="display:block"><?= e($pkThe) ?></span><?php endif; ?>
        <span class="pk-name"><?= e($pkName !== '' ? $pkName : $siteTitle) ?></span>
      </span>
    </a>
    <a class="pk-btn" href="<?= e(url('subscribe')) ?>">Subscribe</a>
  </header>
  <nav class="pk-nav" aria-label="Sections">
    <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(url('contact')) ?>"<?= $activeDesk === 'contact' ? ' aria-current="page"' : '' ?>>Contact</a>
  </nav>
<?php elseif (pp_chrome('template') === 'westernwire'): ?>
<div class="ww-strip">
  <div class="wrap">
    <div class="grp">
      <span>Western Canada · <?= e(date('l, j F Y')) ?></span>
    </div>
    <div class="grp">
      <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
      <?php if (setting('contact_email') !== ''): ?>
      <a href="mailto:<?= e(setting('contact_email')) ?>">Submit a tip</a>
      <?php endif; ?>
      <a href="/admin/">Sign in</a>
      <a class="hot" href="<?= e(url('subscribe')) ?>">Subscribe</a>
    </div>
  </div>
</div>
<header class="ww-mast">
  <div class="wrap">
    <a class="brand" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark.svg')) ?>" alt="">
      <span class="wm"><?= e($siteTitle) ?></span>
    </a>
    <div class="wire" aria-hidden="true"><span class="live"></span></div>
    <p class="tagline"><?= e($tagline) ?></p>
  </div>
</header>
<nav class="ww-nav" aria-label="Sections">
  <div class="wrap">
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <?php $wwRegions = setting_json('regions'); if ($wwRegions): ?>
    <span class="div" aria-hidden="true"></span>
    <?php foreach ($wwRegions as $rk => $rl): ?>
    <a class="rg" href="<?= e(url('region/' . $rk)) ?>"><?= e($rl) ?></a>
    <?php endforeach; ?>
    <?php endif; ?>
    <a class="sp" href="<?= e(url('search')) ?>" aria-label="Search the archive">Search</a>
  </div>
</nav>
<?php if (setting('breaking_label') !== '' && setting('breaking_url') !== ''): ?>
<div class="ww-dev">
  <div class="wrap">
    <span class="d">Developing</span>
    <a href="<?= e(setting('breaking_url')) ?>"><?= e(setting('breaking_label')) ?></a>
  </div>
</div>
<?php endif; ?>
<?php elseif (pp_chrome('template') === 'bulletin'): ?>
<div class="bb-util">
  <div class="wrap">
    <div class="grp">
      <span><?= e(date('l, F j, Y')) ?><?= setting('weather_line') !== '' ? ' · ' . e(setting('weather_line')) : '' ?></span>
    </div>
    <div class="grp">
      <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
      <a href="/admin/">Sign in</a>
      <a class="bb-btn" href="<?= e(url('subscribe')) ?>">Subscribe</a>
    </div>
  </div>
</div>
<header class="bb-mast">
  <div class="wrap">
    <a class="brand" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark.svg')) ?>" alt="">
      <span class="plate"><?= e(preg_replace('/^The\s+/i', '', $siteTitle)) ?></span>
    </a>
    <span></span>
    <div class="vol">
      <?= e(pp_chrome('mast_note') ?: 'Independent · reader-funded') ?><br>
      <?= e($tagline) ?>
    </div>
  </div>
</header>
<nav class="bb-nav" aria-label="Desks">
  <div class="wrap">
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a<?= $cat['slug'] === 'opinion' ? ' class="op"' : '' ?> href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <?php if (setting('breaking_label') !== '' && setting('breaking_url') !== ''): ?>
    <a class="live" href="<?= e(setting('breaking_url')) ?>">
      <span class="dot"></span><span class="l">Live</span>
      <span class="t"><?= e(setting('breaking_label')) ?></span>
    </a>
    <?php else: ?>
    <a class="live" href="<?= e(url('search')) ?>" style="text-decoration:none"><span class="t" style="font-weight:400;color:var(--bb-muted)">Search</span></a>
    <?php endif; ?>
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
<?php if ($ppAdsShown = pp_ads_shown()): ?>
<?php /* Impression beacon: pages may be served from the nginx microcache
         where PHP never runs, so the page itself reports which ads it
         carried. The /ad endpoint is never cached; every view counts. */ ?>
<img src="<?= e(url('ad')) ?>?imp=<?= implode('-', $ppAdsShown) ?>" alt="" width="1" height="1" style="position:absolute;left:-9999px" aria-hidden="true">
<?php endif; ?>

<?php if (pp_chrome('template') === 'torch'): ?>
<?php
$ttWords = preg_split('/\s+/', trim($siteTitle));
$ttLast  = count($ttWords) > 1 ? array_pop($ttWords) : '';
$ttFirst = $ttLast !== '' ? implode(' ', $ttWords) : $siteTitle;
?>
<div class="tt-footindex">
  <div class="in">
    <div>
      <a class="lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
        <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
        <span><b><?= e($ttFirst) ?></b><span><?= e(mb_strtoupper($ttLast)) ?></span></span>
      </a>
      <p class="blurb"><?= e(setting('footer_line')) ?></p>
    </div>
    <div>
      <p class="fh">Sections</p>
      <div class="lnks">
        <?php foreach (pp_nav_categories() as $cat): ?>
        <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <p class="fh">The Torch</p>
      <div class="lnks">
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="<?= e(url('feed/')) ?>">RSS</a>
        <?php if (setting('contact_email') !== ''): ?>
        <a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a>
        <?php endif; ?>
        <a href="/admin/">Newsroom sign-in</a>
      </div>
    </div>
  </div>
</div>
<footer class="tt-foot">
  <div class="in">
    <p>© <?= e(date('Y')) ?> <?= e($siteTitle) ?> · Coquitlam, BC</p>
    <div class="soc">
      <a href="<?= e(url('feed/')) ?>">RSS</a>
      <a href="<?= e(url('subscribe')) ?>">Newsletter</a>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
    </div>
  </div>
</footer>
</body>
</html>
<?php return; endif; ?>
<?php if (pp_chrome('template') === 'bleuetblanc'): ?>
  <footer class="bb-pied">
    <div class="haut">
      <div>
        <span class="sym" aria-hidden="true"><img src="<?= e(site_asset('mark.svg')) ?>" alt="" width="54" height="47"></span>
        <div class="nom"><?= e($siteTitle) ?></div>
        <p class="devise"><?= e(setting('footer_line')) ?></p>
      </div>
      <div class="cols">
        <div>
          <h4><?= e(pp_t('Sections')) ?></h4>
          <?php foreach (pp_nav_categories() as $cat): ?>
          <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
          <?php endforeach; ?>
        </div>
        <div>
          <h4><?= e(pp_t('The paper')) ?></h4>
          <a href="<?= e(url('contact')) ?>"><?= e(pp_t('Contact')) ?></a>
          <a href="<?= e(url('corrections')) ?>"><?= e(pp_t('Corrections')) ?></a>
          <a href="<?= e(url('search')) ?>"><?= e(pp_t('Search the archive')) ?></a>
          <a href="/admin/"><?= e(pp_t('Newsroom sign-in')) ?></a>
        </div>
        <div>
          <h4><?= e(pp_t('Follow')) ?></h4>
          <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', pp_t('Newsletter'))) ?></a>
          <a href="<?= e(url('feed/')) ?>">RSS</a>
          <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(pp_t('Send a tip')) ?></a><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="legal">
      <span>&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?></span>
      <span><?= e(setting('footer_line')) ?></span>
    </div>
  </footer>
</div>
</body>
</html>
<?php return; endif; ?>
<?php if (pp_chrome('template') === 'pickering'): ?>
<?php
$pkWords = preg_split('/\s+/', trim($siteTitle));
if (strcasecmp($pkWords[0] ?? '', 'The') === 0) { array_shift($pkWords); }
$pkName = implode(' ', $pkWords);
$pkInit = mb_strtoupper(mb_substr($pkName !== '' ? $pkName : $siteTitle, 0, 1));
?>
  <footer class="pk-foot">
    <div class="cols">
      <div>
        <span class="lock">
          <span class="pk-tile" aria-hidden="true"><?= e($pkInit) ?></span>
          <span class="nm"><?= e($pkName !== '' ? $pkName : $siteTitle) ?></span>
        </span>
        <p style="font-size:15px;margin:0;color:color-mix(in srgb,var(--paper) 82%,transparent)"><?= e(setting('footer_line')) ?></p>
      </div>
      <div>
        <h4>Sections</h4>
        <?php foreach (pp_nav_categories() as $cat): ?>
        <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
        <?php endforeach; ?>
      </div>
      <div>
        <h4>The paper</h4>
        <a href="<?= e(url('contact')) ?>">Contact</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="/admin/">Newsroom sign-in</a>
      </div>
      <div>
        <h4>Follow</h4>
        <a href="<?= e(url('newsletter/')) ?>">The morning email</a>
        <a href="<?= e(url('feed/')) ?>">RSS</a>
        <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a><?php endif; ?>
      </div>
    </div>
    <div class="base">
      &copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?> &middot; <?= e(setting('footer_line')) ?>
    </div>
  </footer>
</div>
</body>
</html>
<?php return; endif; ?>
<?php if (pp_chrome('template') === 'turtleisland'): ?>
    </div><?php // closes .ti-pad, opened in the header ?>
    <footer class="ti-foot">
      <div class="cols">
        <div>
          <h4>The paper</h4>
          <a href="<?= e(url('about')) ?>">About &amp; contact</a>
          <a href="<?= e(url('corrections')) ?>">Corrections</a>
          <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a><?php endif; ?>
          <a href="/admin/">Newsroom sign-in</a>
        </div>
        <div>
          <h4>Sections</h4>
          <?php foreach (pp_nav_categories() as $cat): ?>
          <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
          <?php endforeach; ?>
        </div>
        <div>
          <h4>Follow</h4>
          <a href="<?= e(url('search')) ?>">Search the archive</a>
          <a href="<?= e(url('newsletter/')) ?>">The morning brief</a>
          <a href="<?= e(url('feed/')) ?>">RSS</a>
        </div>
      </div>
      <div class="base">
        <?= e($siteTitle) ?> &middot; <?= e(setting('footer_line')) ?> &middot; &copy; <?= e(date('Y')) ?>
      </div>
    </footer>
  </div>
</div>
</body>
</html>
<?php return; endif; ?>

<?php if (pp_chrome('template') === 'standard'): ?>
<?php
$sdWords = preg_split('/\s+/', trim($siteTitle));
$sdLast  = count($sdWords) > 1 ? array_pop($sdWords) : '';
$sdMono  = mb_substr(preg_replace('/^The\s+/i', '', $siteTitle), 0, 1);
?>
<footer class="sd-foot">
  <div class="in">
    <a class="lock" href="/">
      <span class="sx" aria-hidden="true"><?= e($sdMono) ?></span>
      <span class="nm"><?= e(mb_strtoupper($sdLast ?: $siteTitle)) ?></span>
    </a>
    <span class="meta">&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?> &middot; <?= e(setting('footer_line', 'Independent, reader-funded')) ?></span>
    <span class="lnks">
      <a href="<?= e(url('about')) ?>">About</a>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
      <a href="<?= e(url('search')) ?>">Search</a>
      <a href="<?= e(url('feed/')) ?>">RSS</a>
    </span>
  </div>
</footer>
</body>
</html>
<?php return; endif; ?>


<?php if (pp_chrome('template') === 'westernwire'): ?>
<footer class="ww-foot">
  <div class="cols">
    <div>
      <a class="brand" href="/">
        <img src="<?= e(site_asset('mark.svg')) ?>" alt="">
        <span class="t"><?= e($siteTitle) ?></span>
      </a>
      <p class="about"><?= e(setting('footer_line')) ?></p>
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
      <div class="fh">By province</div>
      <div class="lnks">
        <?php foreach (setting_json('regions') as $rk => $rl): ?>
        <a href="<?= e(url('region/' . $rk)) ?>"><?= e($rl) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <div class="fh">The wire</div>
      <div class="lnks">
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="<?= e(url('feed/')) ?>">RSS feed</a>
        <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="<?= e(url('subscribe')) ?>">Subscribe</a>
        <?php if (setting('contact_email') !== ''): ?>
        <a href="mailto:<?= e(setting('contact_email')) ?>">Submit a tip</a>
        <?php endif; ?>
        <a href="/admin/">Desk sign-in</a>
      </div>
    </div>
  </div>
  <div class="legal">
    <div class="wrap">
      <span>© <?= e(date('Y')) ?> <?= e($siteTitle) ?> · <?= e(setting('tagline')) ?></span>
      <span>Every headline links to the outlet that reported it</span>
    </div>
  </div>
</footer>
</body>
</html>
<?php return; endif; ?>

<?php if (pp_chrome('template') === 'bulletin'): ?>
<footer class="bb-foot">
  <div class="cols">
    <div>
      <a class="brand" href="/">
        <img src="<?= e(site_asset('mark.svg')) ?>" alt="">
        <span class="t"><?= e($siteTitle) ?></span>
      </a>
      <p class="about"><?= e(setting('footer_line')) ?></p>
    </div>
    <div>
      <div class="fh">Beats</div>
      <div class="lnks">
        <?php foreach (pp_nav_categories() as $cat): ?>
        <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <div class="fh">The Bulletin</div>
      <div class="lnks">
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="/admin/">Newsroom sign-in</a>
        <a href="<?= e(url('feed/')) ?>">RSS</a>
      </div>
    </div>
    <div>
      <div class="fh">Support us</div>
      <div class="lnks">
        <a href="<?= e(url('subscribe')) ?>">Subscribe</a>
        <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
        <?php if (setting('contact_email') !== ''): ?>
        <a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="legal">
    <div class="wrap">
      <span>© <?= e(date('Y')) ?> <?= e($siteTitle) ?> · <?= e(setting('tagline')) ?></span>
      <span>Set in Source Serif 4 on newsprint</span>
    </div>
  </div>
</footer>
</body>
</html>
<?php return; endif; ?>

<?php if (pp_chrome('template') === 'current'): ?>
<footer class="cu-foot">
  <div class="grid">
    <div class="brand">
      <h2><?= e($siteTitle) ?></h2>
      <p><?= e(setting('footer_line')) ?></p>
    </div>
    <div class="col">
      <h3>Read</h3>
      <a href="/">Latest</a>
      <?php foreach (array_slice(pp_nav_categories(), 0, 4) as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="col">
      <h3>About</h3>
      <a href="<?= e(url('search')) ?>">Search the archive</a>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
      <a href="/admin/">Newsroom sign-in</a>
      <?php if (setting('contact_email') !== ''): ?>
      <a href="mailto:<?= e(setting('contact_email')) ?>">Contact</a>
      <?php endif; ?>
    </div>
    <div class="col">
      <h3>Connect</h3>
      <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
      <a href="<?= e(url('subscribe')) ?>">Membership</a>
      <a href="<?= e(url('feed/')) ?>">RSS</a>
      <?php if (setting('contact_email') !== ''): ?>
      <a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="bottom">
    <span>© <?= e(date('Y')) ?> <?= e($siteTitle) ?></span>
    <span><?= e(setting('tagline')) ?></span>
  </div>
</footer>
</body>
</html>
<?php return; endif; ?>

<?php if (pp_chrome('template') === 'pacific'): ?>
<footer class="pf-foot">
  <div class="cols">
    <div>
      <a class="brand" href="/">
        <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
        <span class="t"><?= e($siteTitle) ?></span>
      </a>
      <p class="ack"><?= e(setting('footer_line')) ?></p>
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
      <div class="fh">The Post</div>
      <div class="lnks">
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="/admin/">Newsroom sign-in</a>
        <?php if (setting('contact_email') !== ''): ?>
        <a href="mailto:<?= e(setting('contact_email')) ?>">Contact</a>
        <?php endif; ?>
      </div>
    </div>
    <div>
      <div class="fh">Follow</div>
      <div class="lnks">
        <a href="<?= e(url('subscribe')) ?>">Subscribe</a>
        <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
        <a href="<?= e(url('feed/')) ?>">RSS</a>
      </div>
    </div>
  </div>
  <div class="legal">
    <div class="wrap">
      <span>© <?= e(date('Y')) ?> <?= e($siteTitle) ?> · <?= e(setting('tagline')) ?></span>
      <span>Set in Source Serif 4</span>
    </div>
  </div>
</footer>
</body>
</html>
<?php return; endif; ?>

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
