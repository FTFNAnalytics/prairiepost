---
# A Courier bundle: this front matter, the body below the second ---,
# and (optionally) one image file beside this story.md.
site: rideau-review
desk: local-news
title: Example headline for a human-approved story
lede: One or two sentences the front page will show. Written and approved by a person before Courier ever sees it.
dateline: OTTAWA
tags: example, courier
image_caption: What the featured image shows.
image_credit: Photographer or source
source: https://example.rideaureview.ca/record | The record this story reports
---
The body. Plain paragraphs like this are escaped and wrapped in <p> tags
automatically — a blank line starts a new paragraph.

If the approved copy is already HTML, start the body with a tag
(&lt;p&gt;…) and it passes through untouched; the server sanitizes every
filing with HTML Purifier either way, and the story lands as a DRAFT in
the paper's newsroom for an editor to publish.
