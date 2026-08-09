# The Prairie Post — brand package v1.0

Built from one photograph. Every colour in the system has a pixel coordinate in
the source image, except one, which is declared as an exception in the guide.

## Files

| File | What it is |
| --- | --- |
| `prairie-post-brand-guide.html` | The full guide — palette, type, logo rules, section colours, homepage mock, voice, tokens. Open in a browser. Self-contained. |
| `prairie-post-tokens.css` | Drop-in CSS custom properties, type roles, and the horizon rule component. |
| `prairie-post-logo-primary.svg` | Primary horizontal lockup — wordmark on the rule, with tagline. Outlined; no font needed. |
| `prairie-post-logo-reversed.svg` | Same lockup knocked out of Shelterbelt green. |
| `prairie-post-logo-stacked.svg` | Stacked lockup with the farmstead. Use under 360 px and in print. |
| `prairie-post-mark.svg` | App mark / social avatar. Use at 32 px and above. |
| `prairie-post-mark-small.svg` | Reduced mark for favicons and anything under 32 px. |

All wordmarks are outlined paths, so the SVGs render identically everywhere
without loading a font.

## Type

| Role | Face | Licence |
| --- | --- | --- |
| Display | Archivo (variable, width 68 / weight 800) | SIL OFL |
| Body | Newsreader | SIL OFL |
| Utility | IBM Plex Mono | SIL OFL |

All three are free to self-host. Serving them from your own domain rather than
Google's CDN is faster and avoids a third-party request on every page.

## The two rules easiest to lose in a build

1. The horizon is **4 px of ink plus a 1 px hairline two pixels below it** — not
   a single border.
2. **Border radius is zero everywhere**, including buttons, cards, inputs and
   images. A rounded corner reads as a different paper.

## Colour quick reference

```
Shelterbelt   #17301C   primary ink, masthead, rule
Quarter       #3F5A22   agriculture
Field         #58651C   charts, data
Fencepost     #6F5535   community
Stubble       #7A661F   business & markets
Bin Red       #9C3B22   opinion, live, corrections
Big Sky       #2F6C99   links, interactive
Noon Sky      #77B2D6   weather, sky fields          — fill only
High Sky      #A9CDE4   tints, hover beds            — fill only
Straw         #B99A45   highlight fills, print spot  — fill only
Board         #C4C0B4   hairlines                    — never text
Cloudbank     #F1F2F0   page background
```

Contrast is measured against Cloudbank. Anything under 4.5:1 is a fill and must
never carry body text.
