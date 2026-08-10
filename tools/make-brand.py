#!/usr/bin/env python3
"""
Generate a paper's wordmark lockups for the network.

    python3 tools/make-brand.py <site-slug> "The Paper Name" [--tagline "..."]

Writes logo-primary.svg, logo-reversed.svg and logo-stacked.svg into
assets/sites/<site-slug>/, where the app picks them up automatically
(site_asset() falls back to the network defaults for anything not present,
including favicon.svg, mark.svg and og-default.png).

Faces: Archivo Narrow 700 for the wordmark, IBM Plex Mono 600 for the
tagline — the same bundled OFL fonts the social cards use. Colours follow
the default palette; a site with its own palette can post-process or supply
hand-made SVGs instead.
"""
import argparse
import math
import os
import re
import sys

from fontTools.misc.transform import Transform
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
HEAD = os.path.join(ROOT, 'assets/fonts/archivo-narrow-700.ttf')
MONO = os.path.join(ROOT, 'assets/fonts/plex-mono-600.ttf')
INK, PAPER, BOARD = '#17301C', '#F1F2F0', '#C4C0B4'

# The farmstead mark for the stacked lockup (five-band homestead silhouette).
FARMSTEAD_TPL = '''<g transform="translate({fx},62) scale(0.55)" fill="#17301C">
    <polygon points="24,-30 34,-92 44,-30"/>
    <polygon points="48,-34 57,-84 66,-34"/>
    <ellipse cx="22" cy="-28" rx="22" ry="28"/>
    <ellipse cx="48" cy="-32" rx="24" ry="32"/>
    <ellipse cx="74" cy="-24" rx="20" ry="24"/>
    <rect x="0" y="-22" width="94" height="22"/>
    <polygon points="122,0 122,-24 142,-38 162,-24 162,0"/>
    <rect x="185" y="-60" width="7" height="20"/>
    <polygon points="174,0 174,-32 202,-52 230,-32 230,0"/>
  </g>'''


def text_path(fontpath, text, cap_target, tracking_em=0.0, extra_per_gap=0.0):
    font = TTFont(fontpath)
    upm = font['head'].unitsPerEm
    cap = font['OS/2'].sCapHeight or int(upm * 0.7)
    cmap = font.getBestCmap()
    glyphs = font.getGlyphSet()
    hmtx = font['hmtx']
    scale = cap_target / cap
    x = 0.0
    parts = []
    for i, ch in enumerate(text):
        gname = cmap.get(ord(ch))
        if gname is None:
            continue
        if ch != ' ':
            pen = SVGPathPen(glyphs)
            glyphs[gname].draw(TransformPen(pen, Transform(scale, 0, 0, -scale, x, 0)))
            d = pen.getCommands()
            if d:
                parts.append(d)
        x += hmtx[gname][0] * scale + tracking_em * upm * scale
        if i < len(text) - 1:
            x += extra_per_gap
    return ' '.join(parts), x


def split_two_lines(name):
    """Split a name into two visually balanced lines at a word boundary."""
    words = name.split()
    if len(words) < 2:
        return name, ''
    best, gap = 1, math.inf
    for i in range(1, len(words)):
        a, b = ' '.join(words[:i]), ' '.join(words[i:])
        if abs(len(a) - len(b)) < gap:
            best, gap = i, abs(len(a) - len(b))
    return ' '.join(words[:best]), ' '.join(words[best:])


def fmt(x):
    return f'{x:.1f}'.rstrip('0').rstrip('.')


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('slug')
    ap.add_argument('name')
    ap.add_argument('--tagline', default='News to the horizon')
    ap.add_argument('--ink', default='#17301C')
    ap.add_argument('--paper', default='#F1F2F0')
    ap.add_argument('--board', default='#C4C0B4')
    args = ap.parse_args()

    global INK, PAPER, BOARD
    INK, PAPER, BOARD = args.ink, args.paper, args.board

    if not re.match(r'^[a-z0-9-]+$', args.slug):
        sys.exit('slug must be lowercase letters, digits and hyphens')

    out = os.path.join(ROOT, 'assets/sites', args.slug)
    os.makedirs(out, exist_ok=True)

    display = args.name.upper()
    word_d, word_w = text_path(HEAD, display, 66, tracking_em=0.012)
    width = math.ceil(word_w)
    tag_d, _ = text_path(MONO, args.tagline.upper(), 9.6, tracking_em=0.34)

    with open(os.path.join(out, 'logo-primary.svg'), 'w') as f:
        f.write(f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width} 142" width="{width}" height="142" role="img" aria-label="{args.name}">
  <path d="{word_d}" transform="translate(0,96)" fill="{INK}"/>
  <rect x="0" y="104" width="{width}" height="4" fill="{INK}"/>
  <rect x="0" y="110" width="{width}" height="1" fill="{BOARD}"/>
  <path d="{tag_d}" transform="translate(1,136)" fill="{INK}"/>
</svg>
''')

    rw = width + 80
    with open(os.path.join(out, 'logo-reversed.svg'), 'w') as f:
        f.write(f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="-40 -40 {rw} 222" width="{rw}" height="222" role="img" aria-label="{args.name}">
  <rect x="-40" y="-40" width="{rw}" height="222" fill="{INK}"/>
  <path d="{word_d}" transform="translate(0,96)" fill="{PAPER}"/>
  <rect x="0" y="104" width="{width}" height="4" fill="{PAPER}"/>
  <rect x="0" y="110" width="{width}" height="1" fill="{PAPER}" opacity="0.45"/>
  <path d="{tag_d}" transform="translate(1,136)" fill="{PAPER}"/>
</svg>
''')

    line1, line2 = split_two_lines(display)
    l1_d, l1_w = text_path(HEAD, line1, 49.5, tracking_em=0.012)
    if line2:
        _, base_w = text_path(HEAD, line2, 49.5, tracking_em=0.012)
        extra = max(0.0, (l1_w - base_w) / max(1, len(line2) - 1)) if base_w < l1_w else 0.0
        l2_d, l2_w = text_path(HEAD, line2, 49.5, tracking_em=0.012, extra_per_gap=extra)
    else:
        l2_d, l2_w = '', 0.0

    sw = math.ceil(max(l1_w, l2_w) + 24)
    fx = (sw - 230 * 0.55) / 2
    x1 = (sw - l1_w) / 2
    x2 = (sw - l2_w) / 2
    line2_el = f'\n  <path d="{l2_d}" transform="translate({fmt(x2)},224)" fill="{INK}"/>' if line2 else ''
    with open(os.path.join(out, 'logo-stacked.svg'), 'w') as f:
        f.write(f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {sw} 250" width="{sw}" height="250" role="img" aria-label="{args.name}">
  {FARMSTEAD_TPL.format(fx=fmt(fx)).replace('#17301C', INK)}
  <rect x="0" y="66" width="{sw}" height="4" fill="{INK}"/>
  <rect x="0" y="72" width="{sw}" height="1" fill="{BOARD}"/>
  <path d="{l1_d}" transform="translate({fmt(x1)},146)" fill="{INK}"/>{line2_el}
</svg>
''')

    print(f'{args.slug}: wordmark {fmt(word_w)}px wide, stacked {sw}px — assets/sites/{args.slug}/')


if __name__ == '__main__':
    main()
