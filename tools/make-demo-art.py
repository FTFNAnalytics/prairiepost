#!/usr/bin/env python3
"""
Demo artwork for the two showcase papers.

Flat vector scenes drawn from each paper's own palette, so the demo content
photographs like the design system rather than like stock. Each paper gets
eight 4:3 scenes; the front-page hero crops from the same file.

Run:  python3 tools/make-demo-art.py
"""

import os

W, H = 1200, 900


def svg(*body, sky=("#B9C6CC", "#DCE3E2")):
    grad = (f'<linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">'
            f'<stop offset="0" stop-color="{sky[0]}"/>'
            f'<stop offset="1" stop-color="{sky[1]}"/></linearGradient>')
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}">'
            f'<defs>{grad}</defs><rect width="{W}" height="{H}" fill="url(#sky)"/>'
            + ''.join(body) + '</svg>\n')


def ridge(y, amp, fill, seed=0, op=1.0):
    """A soft hill line across the sheet."""
    import math
    pts = []
    for i in range(0, W + 1, 60):
        pts.append(f'{i},{y + amp * math.sin((i / 190.0) + seed) - amp * 0.35:.0f}')
    return (f'<path d="M0,{H} L0,{y} L' + ' L'.join(pts) +
            f' L{W},{H} Z" fill="{fill}" opacity="{op}"/>')


def water(y, fill):
    return f'<rect x="0" y="{y}" width="{W}" height="{H - y}" fill="{fill}"/>'


def ripples(y, fill, rows=5):
    out = []
    for i in range(rows):
        yy = y + 40 + i * 62
        rx = 120 + (i % 3) * 90
        cx = 200 + (i * 260) % (W - 200)
        out.append(f'<ellipse cx="{cx}" cy="{yy}" rx="{rx}" ry="7" fill="{fill}" opacity="0.45"/>')
    return ''.join(out)


def tree(x, base, h, fill):
    w = h * 0.52
    return (f'<path d="M{x},{base - h} L{x - w/2},{base} L{x + w/2},{base} Z" fill="{fill}"/>'
            f'<rect x="{x - h*0.035:.0f}" y="{base - h*0.12:.0f}" width="{h*0.07:.0f}" '
            f'height="{h*0.14:.0f}" fill="{fill}"/>')


def figure(x, base, h, fill):
    """A standing silhouette: head, shoulders, tapering body. Narrow enough to
    read as a person rather than a bollard at full-bleed sizes."""
    r = h * 0.115
    top = base - h
    sh = top + r * 2.5                       # shoulder line
    hw = r * 1.45                            # half-width at the shoulders
    tw = r * 1.05                            # half-width at the hem
    return (f'<circle cx="{x}" cy="{top + r:.0f}" r="{r:.0f}" fill="{fill}"/>'
            f'<path d="M{x - hw:.0f},{sh:.0f} Q{x:.0f},{sh - r*0.9:.0f} {x + hw:.0f},{sh:.0f} '
            f'L{x + tw:.0f},{base:.0f} L{x - tw:.0f},{base:.0f} Z" fill="{fill}"/>')


def building(x, y, w, h, fill, win=None, winfill="#EDD9A8"):
    out = [f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{fill}"/>']
    if win:
        for r in range(win[1]):
            for c in range(win[0]):
                out.append(f'<rect x="{x + 18 + c*(w-36)/max(win[0],1):.0f}" '
                           f'y="{y + 22 + r*46}" width="20" height="26" fill="{winfill}" opacity=".85"/>')
    return ''.join(out)


# ---------------------------------------------------------------- Turtle Island
TI = dict(ink="#004961", deep="#0A303E", cyan="#0088B0", rust="#A8451F",
          paper="#F3F2F2", green="#3F5D4E", moss="#55705C", stone="#7A8C8C")

ti = {}

ti["river-weir"] = svg(
    ridge(330, 70, TI["green"], 0.4), ridge(400, 46, "#33514A", 1.6),
    water(455, TI["ink"]), ripples(455, TI["cyan"]),
    ''.join(f'<rect x="{150+i*145}" y="470" width="13" height="210" fill="{TI["deep"]}"/>'
            for i in range(7)),
    f'<rect x="140" y="486" width="930" height="17" rx="6" fill="{TI["deep"]}"/>',
    f'<ellipse cx="430" cy="700" rx="52" ry="15" fill="{TI["cyan"]}" opacity=".7"/>',
    f'<ellipse cx="720" cy="770" rx="64" ry="17" fill="{TI["cyan"]}" opacity=".55"/>',
    sky=("#9FB4BB", "#D3DBD9"))

ti["coast-village"] = svg(
    ridge(300, 84, "#4A6360", 0.9), ridge(392, 44, "#37504E", 2.2),
    water(470, "#3C5A66"), ripples(470, "#5F8391", 4),
    f'<rect x="120" y="452" width="880" height="18" rx="5" fill="{TI["deep"]}"/>',
    ''.join(f'<rect x="{190+i*150}" y="470" width="14" height="110" fill="{TI["deep"]}"/>'
            for i in range(6)),
    building(300, 320, 190, 132, "#6B4A32", win=(3, 2)),
    f'<path d="M282,320 L395,262 L508,320 Z" fill="#5A3D29"/>',
    building(620, 356, 150, 96, "#6B4A32", win=(2, 1)),
    f'<path d="M604,356 L695,312 L786,356 Z" fill="#5A3D29"/>',
    sky=("#D9C9A8", "#AFBBB6"))

ti["gathering"] = svg(
    ridge(280, 60, "#6C7F6B", 0.2), water(360, "#8E9478"),
    f'<rect x="0" y="360" width="{W}" height="70" fill="#7C8468"/>',
    ''.join(figure(150 + i * 118, 620 + (i % 3) * 14, 150 + (i % 4) * 18, "#3E4A3C")
            for i in range(9)),
    ''.join(f'<rect x="{136+i*118}" y="{545+(i%3)*14}" width="28" height="14" fill="{TI["rust"]}" opacity=".9"/>'
            for i in range(0, 9, 3)),
    f'<ellipse cx="600" cy="800" rx="430" ry="30" fill="#5D6B52" opacity=".55"/>',
    sky=("#C7CFC4", "#E2E4D6"))

ti["forest-cut"] = svg(
    ridge(300, 66, "#3F5D4E", 1.1), ridge(370, 40, "#33514A", 0.3),
    f'<rect x="0" y="430" width="{W}" height="470" fill="{TI["moss"]}"/>',
    f'<path d="M540,430 L1200,430 L1200,900 L470,900 Z" fill="#8A7B5E"/>',
    ''.join(tree(60 + i * 82, 470 + (i % 3) * 18, 130 + (i % 4) * 26, "#2E4A3C")
            for i in range(6)),
    ''.join(f'<rect x="{600+i*88}" y="{520+(i%3)*40}" width="46" height="13" rx="4" fill="#6B5C42"/>'
            for i in range(6)),
    sky=("#AFC0C4", "#DCE2DC"))

ti["band-office"] = svg(
    ridge(330, 40, "#42544C", 0.7),
    f'<rect x="0" y="470" width="{W}" height="430" fill="#5C6357"/>',
    building(330, 300, 540, 172, TI["deep"], win=(5, 2), winfill="#E8C878"),
    f'<rect x="310" y="286" width="580" height="20" fill="{TI["ink"]}"/>',
    f'<rect x="560" y="392" width="80" height="80" fill="#6B4A32"/>',
    f'<rect x="120" y="440" width="14" height="120" fill="{TI["deep"]}"/>'
    f'<circle cx="127" cy="432" r="20" fill="#EAD08A" opacity=".9"/>',
    sky=("#2F4553", "#6B7C82"))

ti["salmon-run"] = svg(
    water(0, "#3E6272"), ripples(0, "#5E8798", 7),
    f'<rect x="0" y="0" width="{W}" height="120" fill="#31505E"/>',
    ''.join(f'<g transform="translate({120+i*300},{240+(i%3)*230}) rotate({-12+(i%3)*11})">'
            f'<path d="M0,0 q110,-56 220,0 q-110,56 -220,0 z" fill="{TI["rust"]}" opacity=".9"/>'
            f'<path d="M0,0 l-52,-34 l0,68 z" fill="{TI["rust"]}" opacity=".9"/>'
            f'<circle cx="182" cy="-9" r="7" fill="{TI["deep"]}" opacity=".7"/></g>'
            for i in range(5)),
    sky=("#3E6272", "#3E6272"))

ti["reservoir"] = svg(
    ridge(300, 70, "#4C5F5A", 1.4), water(430, "#456A78"),
    f'<rect x="0" y="420" width="{W}" height="46" fill="{TI["stone"]}"/>',
    f'<rect x="0" y="466" width="{W}" height="120" fill="#8D9A99"/>',
    ''.join(f'<rect x="{110+i*180}" y="466" width="92" height="120" fill="#71807F"/>'
            for i in range(6)),
    f'<rect x="0" y="586" width="{W}" height="314" fill="{TI["ink"]}"/>',
    ripples(600, TI["cyan"], 3),
    sky=("#A8B6BA", "#D6DBD9"))

ti["map-table"] = svg(
    f'<rect width="{W}" height="{H}" fill="#6B4A32"/>',
    f'<rect x="120" y="110" width="960" height="680" fill="{TI["paper"]}"/>',
    ''.join(f'<path d="M{170+i*40},760 Q{300+i*40},{500-i*30} {520+i*30},{180+i*20}" '
            f'stroke="{TI["cyan"]}" stroke-width="6" fill="none" opacity=".8"/>' for i in range(3)),
    f'<path d="M600,190 Q760,420 700,760" stroke="{TI["ink"]}" stroke-width="9" fill="none"/>',
    ''.join(f'<circle cx="{420+i*150}" cy="{300+(i%3)*160}" r="16" fill="{TI["rust"]}"/>'
            for i in range(5)),
    f'<rect x="760" y="560" width="260" height="170" fill="none" stroke="{TI["ink"]}" stroke-width="5" stroke-dasharray="16 10"/>',
    sky=("#6B4A32", "#6B4A32"))

# -------------------------------------------------------------------- Pickering
PK = dict(navy="#004961", deep="#0A303E", cyan="#0088B0", surface="#EAE9E9",
          paper="#F3F2F2", yellow="#EDBB00", brick="#8C6A52", grass="#6E7F62")

pk = {}

pk["waterfront"] = svg(
    ridge(330, 34, "#7C8C8C", 0.8, .55),
    water(400, PK["navy"]), ripples(400, PK["cyan"], 6),
    f'<rect x="0" y="392" width="{W}" height="16" fill="{PK["deep"]}"/>',
    ''.join(f'<g><path d="M{160+i*175},560 l86,0 l-16,34 l-54,0 z" fill="{PK["paper"]}"/>'
            f'<rect x="{200+i*175}" y="432" width="7" height="128" fill="{PK["paper"]}"/>'
            f'<path d="M{207+i*175},440 l54,110 l-54,0 z" fill="{PK["surface"]}"/></g>'
            for i in range(5)),
    f'<rect x="80" y="300" width="18" height="110" fill="{PK["deep"]}"/>'
    f'<rect x="66" y="286" width="46" height="18" fill="{PK["yellow"]}"/>',
    sky=("#9FB6C2", "#DDE4E4"))

pk["go-station"] = svg(
    f'<rect x="0" y="430" width="{W}" height="470" fill="#8A8F8C"/>',
    f'<rect x="0" y="430" width="{W}" height="26" fill="{PK["surface"]}"/>',
    f'<rect x="0" y="560" width="{W}" height="18" fill="#6E736F"/>',
    f'<rect x="0" y="614" width="{W}" height="18" fill="#6E736F"/>',
    building(150, 224, 900, 208, PK["paper"], win=(7, 2), winfill=PK["cyan"]),
    f'<rect x="150" y="206" width="900" height="22" fill="{PK["navy"]}"/>',
    f'<rect x="150" y="432" width="900" height="14" fill="{PK["navy"]}"/>',
    ''.join(f'<rect x="{240+i*230}" y="470" width="14" height="90" fill="{PK["deep"]}"/>'
            f'<rect x="{226+i*230}" y="456" width="42" height="16" fill="{PK["deep"]}"/>'
            for i in range(4)),
    sky=("#B7C4CB", "#E1E5E4"))

pk["council"] = svg(
    f'<rect width="{W}" height="{H}" fill="#C9BCA6"/>',
    f'<rect x="0" y="0" width="{W}" height="330" fill="#B3A48C"/>',
    f'<path d="M180,700 Q600,540 1020,700 L1020,830 Q600,690 180,830 Z" fill="{PK["brick"]}"/>',
    f'<path d="M240,690 Q600,548 960,690" stroke="#6E523E" stroke-width="10" fill="none"/>',
    ''.join(figure(280 + i * 108, 640 - abs(3 - i) * 16, 118, PK["deep"]) for i in range(7)),
    f'<circle cx="600" cy="196" r="46" fill="{PK["navy"]}"/>'
    f'<circle cx="600" cy="196" r="34" fill="{PK["surface"]}"/>'
    f'<rect x="597" y="170" width="6" height="28" rx="3" fill="{PK["navy"]}"/>'
    f'<rect x="600" y="193" width="24" height="6" rx="3" fill="{PK["navy"]}"/>',
    sky=("#B3A48C", "#B3A48C"))

pk["band-shell"] = svg(
    ridge(320, 40, "#5F7358", 0.5),
    f'<rect x="0" y="470" width="{W}" height="430" fill="{PK["grass"]}"/>',
    f'<path d="M360,470 Q600,220 840,470 Z" fill="{PK["paper"]}"/>',
    f'<path d="M410,470 Q600,268 790,470 Z" fill="{PK["surface"]}"/>',
    ''.join(figure(520 + i * 62, 452, 84, PK["deep"]) for i in range(3)),
    ''.join(figure(140 + i * 96, 760 + (i % 3) * 22, 104, "#42513F") for i in range(11)),
    sky=("#F0DCB4", "#C8CDBA"))

pk["market"] = svg(
    f'<rect x="0" y="500" width="{W}" height="400" fill="#9AA08F"/>',
    ''.join(f'<g><rect x="{100+i*230}" y="360" width="190" height="140" fill="{PK["paper"]}"/>'
            f'<path d="M{86+i*230},360 l218,0 l-22,-52 l-174,0 z" fill="{PK["cyan"] if i%2 else PK["yellow"]}"/>'
            f'<rect x="{120+i*230}" y="430" width="150" height="70" fill="{PK["surface"]}"/></g>'
            for i in range(5)),
    ''.join(figure(180 + i * 150, 700 + (i % 2) * 20, 112, PK["deep"]) for i in range(7)),
    sky=("#CBD6D8", "#EAEAE2"))

pk["library-van"] = svg(
    ridge(340, 44, "#7E8C6E", 1.2),
    f'<rect x="0" y="520" width="{W}" height="380" fill="#B7AE90"/>',
    f'<rect x="0" y="520" width="{W}" height="40" fill="#8F8A6E"/>',
    f'<rect x="270" y="330" width="560" height="190" rx="14" fill="{PK["paper"]}"/>',
    f'<rect x="270" y="330" width="560" height="52" fill="{PK["navy"]}"/>',
    f'<rect x="700" y="392" width="130" height="86" fill="{PK["cyan"]}" opacity=".55"/>',
    f'<circle cx="380" cy="530" r="46" fill="{PK["deep"]}"/><circle cx="380" cy="530" r="20" fill="#9AA3A0"/>',
    f'<circle cx="740" cy="530" r="46" fill="{PK["deep"]}"/><circle cx="740" cy="530" r="20" fill="#9AA3A0"/>',
    ''.join(tree(90 + i * 1000, 520, 150, "#4E5F44") for i in range(2)),
    sky=("#CBD8DA", "#EDE9DC"))

pk["shoreline"] = svg(
    ridge(360, 26, "#8496A0", 0.9, .5),
    water(430, "#3F6474"), ripples(430, PK["cyan"], 5),
    f'<rect x="0" y="418" width="{W}" height="18" fill="{PK["deep"]}"/>',
    ''.join(f'<rect x="{420+i*120}" y="250" width="72" height="168" fill="{PK["surface"]}"/>'
            f'<rect x="{420+i*120}" y="250" width="72" height="22" fill="#B9BFC0"/>'
            for i in range(4)),
    f'<rect x="300" y="300" width="90" height="118" fill="#C6CCCC"/>',
    f'<ellipse cx="600" cy="248" rx="230" ry="34" fill="{PK["paper"]}" opacity=".5"/>',
    sky=("#A9BAC4", "#E0E6E6"))

pk["trail"] = svg(
    ridge(310, 50, "#5D7050", 0.4),
    f'<rect x="0" y="450" width="{W}" height="450" fill="{PK["grass"]}"/>',
    f'<path d="M470,900 Q560,600 600,450 L720,450 Q680,610 760,900 Z" fill="#B4AC93"/>',
    f'<rect x="380" y="250" width="440" height="200" fill="{PK["deep"]}"/>',
    f'<rect x="380" y="250" width="440" height="26" fill="{PK["navy"]}"/>',
    f'<rect x="430" y="330" width="340" height="120" fill="#20323A"/>',
    ''.join(f'<circle cx="{470+i*110}" cy="306" r="13" fill="{PK["yellow"]}"/>' for i in range(4)),
    ''.join(tree(80 + i * 130, 470 + (i % 3) * 20, 120 + (i % 3) * 24, "#425338") for i in range(4)),
    sky=("#BCCBCC", "#E4E7DE"))


pk["industrial"] = svg(
    ridge(360, 30, "#6E7A72", 0.6, .5),
    f'<rect x="0" y="470" width="{W}" height="430" fill="#5A5F5B"/>',
    building(220, 250, 760, 220, "#3A423E"),
    f'<rect x="200" y="234" width="800" height="22" fill="#2C3330"/>',
    ''.join(f'<rect x="{270+i*150}" y="300" width="86" height="60" fill="{PK["yellow"]}" opacity=".7"/>'
            for i in range(5)),
    f'<path d="M420,250 Q520,120 620,250 Q700,150 780,250 Z" fill="#C4703A" opacity=".8"/>',
    f'<path d="M470,250 Q560,160 650,250 Z" fill="{PK["yellow"]}" opacity=".75"/>',
    ''.join(f'<rect x="{160+i*260}" y="500" width="120" height="44" rx="8" fill="{PK["paper"]}"/>'
            f'<rect x="{176+i*260}" y="486" width="34" height="16" rx="4" fill="{PK["cyan"]}"/>'
            for i in range(3)),
    sky=("#2E3A42", "#6B6257"))


def write(paper, scenes):
    d = f'assets/sites/{paper}/img'
    os.makedirs(d, exist_ok=True)
    for name, body in scenes.items():
        with open(f'{d}/{name}.svg', 'w') as f:
            f.write(body)
    print(f'{paper}: {len(scenes)} scenes')


write('turtle-island-times', ti)
write('pickering-post', pk)
