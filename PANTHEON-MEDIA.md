# Pantheon media gateway

PrairiePost is the first provider for Pantheon's media procurement module.
The integration is a request desk, not a publishing or spending API.

## Contract

The bundled Apache and development-router rules map `/api/media` to
`media-gateway.php` and keep the query string. An nginx deployment must add
the equivalent route. All responses use schema version `2026-08-27`.

| Method | Query | Scope | Result |
|---|---|---|---|
| GET | `action=catalog` | `catalog` | 15 newspaper properties, hub excluded, three products per paper |
| POST | `action=submit` | `submit` | idempotent editorial, sponsored, or display request |
| GET | `action=request&id=cmr_…` | `status` | current state and quote, when present |
| POST | `action=cancel&id=cmr_…` | `cancel` | cancellation request |
| GET | `action=metrics&id=cmr_…` | `metrics` | normalized delivery metrics once an output exists |

`Authorization: Bearer …` is mandatory. POST submission also sends the same
key in the payload and `X-Idempotency-Key` header.

## Credential setup

Run on the Civis host after migration 17:

```bash
php tools/make-media-client.php create --name pantheon \
  --scopes catalog,submit,status,cancel,metrics
```

The command displays the token once and stores only its SHA-256 hash. Put the
raw value into Pantheon's server-only `MEDIA_PROVIDER_CONFIG_JSON`; never add
it to either repository or a database setting.

## Human workflow

The Civis hub gains **Submissions** in its control-room navigation.

- Editorial pitches may be accepted into one story-idea queue per requested
  paper. No post is created or published.
- Sponsored-post and display-ad requests may be reviewed, changed, declined,
  or quoted by an administrator.
- A quote is not an order. This release intentionally exposes no paid-order,
  campaign-launch, post-publication, or spend endpoint.
- Tri Cities Torch is marked sandbox and no-charge in the catalog, but its
  requests still require human review and cannot go live automatically.

## Deployment

1. Deploy the release to the shared code directory through the normal immutable
   release process; first request runs migration 17.
2. Confirm `/api/media` reaches `media-gateway.php`. The normal Civis deploy
   script installs the nginx route; Apache uses the bundled `.htaccess`. The
   gateway itself returns 404 on every host except the Civis hub.
3. Issue a scoped token and configure Pantheon.
4. Verify the catalog reports 15 properties and excludes `civismedia`.
5. Submit one Tri Cities Torch sandbox request and confirm it appears in the
   control room without creating a post, campaign, or ad row.
6. Run `php tests/run.php` and the 15-property front/admin smoke suite before
   promoting the release.
