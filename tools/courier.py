#!/usr/bin/env python3
"""Courier — the uploading agent for human-approved stories.

Runs on the writer's machine, holds ONE ingest API key, and talks to
exactly two endpoints on any paper in the network: POST /api/ingest-media
(the featured graphic) and POST /api/ingest (the story, which always
lands as a DRAFT behind the paper's publish gate). No other capability —
no SSH, no database, no read API. Keys are minted on the CivisMedia
control room's API-keys page; the full HTTP contract is docs/api-ingest.md.

A story is a BUNDLE: one folder holding a `story.md` (front matter
between `---` fences, body below) or a `story.json`, plus at most one
image file (or none). See docs/courier.md for the format.

    export PP_INGEST_BASE=https://<any-paper-domain>
    export PP_INGEST_KEY=hermes_…            # or --key-file (0600)
    python3 tools/courier.py path/to/bundle [more/bundles…]
    python3 tools/courier.py --all path/to/folder-of-bundles
    python3 tools/courier.py --dry-run path/to/bundle

Design rules, so a double-run can never double-publish:
  * every filing carries an external_id (the bundle folder's name unless
    the front matter overrides it) — the server answers an exact re-file
    with `duplicate` instead of a copy;
  * a successful filing writes `receipt.json` into the bundle, and a
    bundle with a receipt is skipped unless --resend;
  * the key is read from the environment or a file, never from argv,
    and is never printed.

Python 3.8+, standard library only. Exit code 0 when every bundle
landed (or was already landed); otherwise the number of failed bundles.
"""

import argparse
import html
import json
import os
import re
import ssl
import sys
import time
import urllib.error
import urllib.request
import uuid
from pathlib import Path

IMAGE_EXT = {".jpg": "image/jpeg", ".jpeg": "image/jpeg", ".png": "image/png",
             ".webp": "image/webp", ".gif": "image/gif"}
FRONT_KEYS = {"site", "desk", "title", "lede", "dateline", "tags", "slug",
              "external_id", "image", "image_caption", "image_credit"}
REQUIRED = ("site", "desk", "title", "lede")
RETRY_DELAYS = (2, 4, 8)


class BundleError(Exception):
    """A defect in ONE bundle: fails that bundle, never the batch."""


def fail(msg: str):
    print("courier: " + msg, file=sys.stderr)
    sys.exit(2)


def load_key(args) -> str:
    if args.key_file:
        p = Path(args.key_file)
        if not p.is_file():
            fail(f"--key-file {p} does not exist")
        key = p.read_text(encoding="utf-8").strip()
    else:
        key = os.environ.get("PP_INGEST_KEY", "").strip()
    if not key:
        fail("no API key: set PP_INGEST_KEY or pass --key-file (never put the key on the command line)")
    return key


def parse_front_matter(text: str, bundle: Path):
    """`--- key: value … ---` then the body. Simple lines only, no YAML."""
    m = re.match(r"\A---\s*\n(.*?)\n---\s*\n?(.*)\Z", text, re.S)
    if not m:
        raise BundleError(f"{bundle}: story.md must start with a --- front-matter block")
    meta, body = {}, m.group(2)
    sources = []
    for i, line in enumerate(m.group(1).splitlines(), 1):
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        if ":" not in line:
            raise BundleError(f"{bundle}: front-matter line {i} is not `key: value`: {line!r}")
        key, _, value = line.partition(":")
        key, value = key.strip().lower(), value.strip()
        if key == "source":
            # `source: https://… | Optional title`
            url, _, title = value.partition("|")
            sources.append({"url": url.strip(), "title": title.strip()})
        elif key in FRONT_KEYS:
            meta[key] = value
        else:
            raise BundleError(f"{bundle}: unknown front-matter key {key!r} (allowed: "
                 + ", ".join(sorted(FRONT_KEYS | {"source"})) + ")")
    if sources:
        meta["sources"] = sources
    return meta, body


def body_to_html(body: str) -> str:
    """HTML passes through; plain text becomes escaped paragraphs."""
    body = body.strip()
    if body.startswith("<"):
        return body
    paras = [p.strip() for p in re.split(r"\n\s*\n", body) if p.strip()]
    return "\n".join("<p>" + html.escape(re.sub(r"\s*\n\s*", " ", p)) + "</p>" for p in paras)


def load_bundle(bundle: Path):
    """Returns (payload-without-image, image_path_or_None)."""
    md, js = bundle / "story.md", bundle / "story.json"
    if js.is_file():
        try:
            meta = json.loads(js.read_text(encoding="utf-8"))
        except json.JSONDecodeError as e:
            raise BundleError(f"{bundle}: story.json is not valid JSON: {e}")
        if not isinstance(meta, dict):
            raise BundleError(f"{bundle}: story.json must be a JSON object")
        body = str(meta.pop("body", ""))
    elif md.is_file():
        meta, body = parse_front_matter(md.read_text(encoding="utf-8"), bundle)
    else:
        raise BundleError(f"{bundle}: no story.md or story.json")

    for k in REQUIRED:
        if not str(meta.get(k, "")).strip():
            raise BundleError(f"{bundle}: front matter is missing `{k}`")
    if not body.strip():
        raise BundleError(f"{bundle}: the story has no body")

    image = None
    named = str(meta.pop("image", "")).strip()
    if named:
        image = bundle / named
        if not image.is_file():
            raise BundleError(f"{bundle}: front matter names image {named!r} but it is not in the bundle")
        if image.suffix.lower() not in IMAGE_EXT:
            raise BundleError(f"{bundle}: {named!r} is not a JPEG/PNG/WebP/GIF")
    else:
        found = sorted(p for p in bundle.iterdir() if p.suffix.lower() in IMAGE_EXT)
        if len(found) > 1:
            raise BundleError(f"{bundle}: several image files — name the featured one with `image:` in the front matter")
        image = found[0] if found else None

    payload = {
        "site": str(meta["site"]).strip(),
        "desk": str(meta["desk"]).strip(),
        "title": str(meta["title"]).strip(),
        "lede": str(meta["lede"]).strip(),
        "body": body_to_html(body),
        "external_id": str(meta.get("external_id", "")).strip() or bundle.name,
    }
    for src, dst in (("dateline", "dateline"), ("tags", "tags"), ("slug", "suggested_slug"),
                     ("image_caption", "image_caption"), ("image_credit", "image_credit")):
        if str(meta.get(src, "")).strip():
            payload[dst] = str(meta[src]).strip()
    if meta.get("sources"):
        payload["sources"] = meta["sources"]
    return payload, image


def request(base: str, path: str, key: str, data: bytes, ctype: str):
    """One POST. Returns (status, parsed-json). Raises urllib errors."""
    req = urllib.request.Request(base.rstrip("/") + path, data=data, method="POST")
    req.add_header("Authorization", "Bearer " + key)
    req.add_header("Content-Type", ctype)
    ctx = ssl.create_default_context()
    try:
        with urllib.request.urlopen(req, timeout=30, context=ctx) as resp:
            return resp.status, json.loads(resp.read().decode("utf-8", "replace"))
    except urllib.error.HTTPError as e:
        try:
            return e.code, json.loads(e.read().decode("utf-8", "replace"))
        except json.JSONDecodeError:
            return e.code, {"ok": False, "error": f"non-JSON response (HTTP {e.code})"}


def post_with_retry(base: str, path: str, key: str, data: bytes, ctype: str, label: str):
    """Retries transport errors, 429 and 5xx with backoff; returns (code, json)."""
    last = "no attempt made"
    for attempt, delay in enumerate(RETRY_DELAYS + (None,)):
        try:
            code, j = request(base, path, key, data, ctype)
        except (urllib.error.URLError, TimeoutError, ConnectionError) as e:
            last = f"transport error: {getattr(e, 'reason', e)}"
        else:
            if code == 429 or code >= 500:
                last = f"HTTP {code}: {j.get('error', '')}"
            else:
                return code, j
        if delay is None:
            break
        print(f"    {label}: {last} — retrying in {delay}s")
        time.sleep(delay)
    return 0, {"ok": False, "error": last}


def multipart(image: Path):
    boundary = "----courier" + uuid.uuid4().hex
    head = (f"--{boundary}\r\n"
            f'Content-Disposition: form-data; name="file"; filename="{image.name}"\r\n'
            f"Content-Type: {IMAGE_EXT[image.suffix.lower()]}\r\n\r\n").encode()
    tail = f"\r\n--{boundary}--\r\n".encode()
    return head + image.read_bytes() + tail, f"multipart/form-data; boundary={boundary}"


def process(bundle: Path, base: str, key: str, args) -> str:
    """Returns 'sent' | 'skipped' | 'failed'."""
    receipt = bundle / "receipt.json"
    if receipt.is_file() and not args.resend:
        print(f"  {bundle.name}: already has a receipt — skipped (use --resend to file again)")
        return "skipped"

    try:
        payload, image = load_bundle(bundle)
    except BundleError as e:
        print(f"  {bundle.name}: FAILED — {e}")
        return "failed"
    if args.dry_run:
        art = image.name if image else "none"
        print(f"  {bundle.name}: OK — site={payload['site']} desk={payload['desk']} "
              f"image={art} title={payload['title']!r}")
        return "skipped"

    if image is not None:
        size = image.stat().st_size
        if size > 8 * 1024 * 1024:
            print(f"  {bundle.name}: FAILED — {image.name} is over 8 MB")
            return "failed"
        body, ctype = multipart(image)
        code, j = post_with_retry(base, "/api/ingest-media", key, body, ctype, image.name)
        if code != 201:
            print(f"  {bundle.name}: FAILED uploading {image.name} — {j.get('error', 'HTTP %s' % code)}")
            return "failed"
        payload["image"] = j["path"]
        print(f"  {bundle.name}: image up → {j['path']}")

    code, j = post_with_retry(base, "/api/ingest", key,
                              json.dumps(payload).encode(), "application/json", "story")
    if code == 200 and j.get("duplicate"):
        print(f"  {bundle.name}: already filed earlier (server dedupe) — slug {j.get('slug')}")
    elif code == 201:
        print(f"  {bundle.name}: filed as {j.get('status')} — slug {j.get('slug')} (id {j.get('id')})")
    else:
        print(f"  {bundle.name}: FAILED filing — {j.get('error', 'HTTP %s' % code)}")
        return "failed"

    receipt.write_text(json.dumps({
        "id": j.get("id"), "slug": j.get("slug"), "status": j.get("status", "draft"),
        "site": payload["site"], "image": payload.get("image", ""),
        "filed_at": time.strftime("%Y-%m-%d %H:%M:%S"), "duplicate": bool(j.get("duplicate")),
    }, indent=2) + "\n", encoding="utf-8")
    return "sent"


def find_bundles(args) -> list:
    bundles = []
    if args.all:
        parent = Path(args.all)
        if not parent.is_dir():
            fail(f"--all {parent}: not a directory")
        bundles = [d for d in sorted(parent.iterdir())
                   if d.is_dir() and ((d / "story.md").is_file() or (d / "story.json").is_file())]
        if not bundles:
            fail(f"--all {parent}: no bundles found (a bundle is a folder with a story.md or story.json)")
    for b in args.bundles:
        p = Path(b)
        if not p.is_dir():
            fail(f"{p}: not a directory (a bundle is a folder)")
        bundles.append(p)
    if not bundles:
        fail("nothing to do: pass bundle folders, or --all <folder-of-bundles>")
    return bundles


def main() -> int:
    ap = argparse.ArgumentParser(description="File human-approved stories as drafts via the ingest API.")
    ap.add_argument("bundles", nargs="*", help="bundle folders (story.md/story.json + optional image)")
    ap.add_argument("--all", metavar="DIR", help="process every bundle folder directly under DIR")
    ap.add_argument("--base", default=os.environ.get("PP_INGEST_BASE", ""),
                    help="https://<any-paper-domain> (or env PP_INGEST_BASE)")
    ap.add_argument("--key-file", help="file holding the API key (instead of env PP_INGEST_KEY)")
    ap.add_argument("--dry-run", action="store_true", help="validate bundles, send nothing")
    ap.add_argument("--resend", action="store_true", help="process bundles even if they carry a receipt")
    args = ap.parse_args()

    bundles = find_bundles(args)
    if args.dry_run:
        key, base = "", ""
    else:
        key = load_key(args)
        base = args.base.strip()
        if not re.match(r"^https?://", base):
            fail("no base URL: set PP_INGEST_BASE or pass --base https://<any-paper-domain>")

    print(f"courier: {len(bundles)} bundle(s)" + (" — dry run" if args.dry_run else ""))
    failed = 0
    for b in bundles:
        if process(b, base, key, args) == "failed":
            failed += 1
    if failed:
        print(f"courier: {failed} bundle(s) FAILED — fix and re-run; receipts make re-runs safe")
    return min(failed, 99)


if __name__ == "__main__":
    sys.exit(main())
