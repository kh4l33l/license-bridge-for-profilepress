# License Bridge for ProfilePress — developer notes

Seller-side plugin that runs on a membership site alongside **ProfilePress**
(Membership) and **Software License Manager (SLM)**. It provisions and manages
SLM licenses off ProfilePress subscription events, exposes license info to
customers, and serves license-gated plugin updates. It is never installed on
customer sites.

This file documents intent and non-obvious decisions. It is pushed to GitHub but
excluded from the distributable build (see `build.sh`). Keep code comments
minimal; record the "why" here instead.

## Bootstrap

`license-bridge-for-profilepress.php` defines constants, registers a PSR-4-ish
autoloader (`LicenseBridgeForProfilePress\Foo_Bar` → `includes/class-foo-bar.php`), runs the
data migration, then on `plugins_loaded` (priority 20) instantiates
`Bridge::get_instance()` only if ProfilePress is present. `Bridge` wires up every
component. The autoloader convention is why every class file is `class-*.php`
with `Class_Name` style names.

## Prefixes

One prefix throughout, derived from the name: namespace
`LicenseBridgeForProfilePress`, `LBFP_` constants, `lbfp_` option/user_meta/
action/nonce/transient names and CSS classes, and the
`license-bridge-for-profilepress/v1` REST namespace. The `Migrator` renames the
original `flowsync_lb_*` keys (from the internal FlowSync-era build) into their
`lbfp_*` equivalents on first run.

## Constants (set in `wp-config.php`)

- `LBFP_SLM_CREATION_SECRET` / `LBFP_SLM_VERIFICATION_SECRET` — required. SLM
  ships **two** API keys. Creation (`slm_create_new`, `slm_update`) and
  verification (`slm_check`, `slm_deactivate`) validate against different keys;
  sending the wrong one returns `CREATE_KEY_INVALID` / `VERIFY_KEY_INVALID`.
  `SLM_Client::secret_for()` picks the right one per action. **No code defaults —
  secrets must never live in VCS.**
- `LBFP_SLM_URL` — SLM endpoint, defaults to `home_url()` (SLM usually
  co-located). Override to target SLM on another host.
- `LBFP_ITEM_REFERENCE` — optional; pins the SLM item reference and wins over
  the admin setting so it can't drift.

## Licensed product (`Product` + settings)

Everything product-specific is configurable, not hardcoded. `Product` reads the
`lbfp_product` option: `item_reference` (scopes every SLM call — must match the
product in SLM), plus `name` / `slug` / `homepage` / `author` used to label
customer-side updates. `name` falls back to the item reference; `slug` is derived
from the name when blank. Configured under **ProfilePress → SLM Integration**.

## Subscription lifecycle (`Subscription_Handler`)

Hooks, all at priority **5**:

- `ppress_order_completed` — primary provisioning trigger.
- `ppress_subscription_activated` — fallback for flows that don't go through
  `complete_order()`. `on_activated()` is idempotent (short-circuits when a key
  already exists), so double-firing is safe.
- `ppress_subscription_post_renew` — extends expiry; provisions if no key yet.
- `ppress_subscription_expired` — revokes.

Priority 5 matters: ProfilePress's email senders (NewOrderReceipt,
RenewalOrderReceipt) hook the same actions at default priority 10. Running first
means `{{license_key}}` is resolvable when ProfilePress builds the email body.

Policy decisions:

- **Cancel does nothing.** Only `expired` (the paid period actually ended without
  renewal) revokes. A cancelled-but-not-yet-expired subscription keeps working.
- On expiry we set SLM `date_expiry` to today (not delete the license) so the
  next `slm_check` on the customer site flips status to expired. The customer
  plugin's own grace period gives a few more days before features cut off.

## License model (`License_Store`)

One license per WP user, stored in user_meta (`lbfp_license_key`,
`lbfp_subscription_id`, `lbfp_plan_id`). Plan upgrades update the existing
license's `max_allowed_domains` via `slm_update` rather than minting a second
license.

## Plan → license mapping

`lbfp_plan_map`: plan_id → max_allowed_domains. A plan that is unmapped or
mapped to `0` is treated as non-licensed and skipped silently on activation.

## Update server (`Update_Server`)

REST namespace `license-bridge-for-profilepress/v1`. SLM validates licenses but doesn't
distribute packages, so this does:

- `GET /update/check` — version metadata + a license-gated download URL for the
  WP update transient and the "View details" popup. Returns `success=false`
  (never an error object) for "no release" / license failure; the client treats
  anything non-success as "no update available" and stays quiet.
- `GET /update/download` — re-validates, then streams the ZIP.

Both gate via `slm_check`: license **active** AND the requesting **domain
registered** against it. A filesystem `zip_path` is streamed through the gated
route (so the package can live outside the web root); a configured `https://`
URL is handed back / redirected as-is, since a public file can't be gated anyway.
Release metadata lives in the `lbfp_release` option, edited on the settings
screen.

The update and OAuth routes register under every namespace returned by the
`lbfp_rest_namespaces` filter (default: the one current namespace), and the
OAuth admin-post handler under every action from `lbfp_oauth_authorize_actions`.
A site that renamed the plugin can keep serving an old REST namespace / action
to clients that haven't updated yet, by adding the legacy values via those
filters — no need to bake legacy names into the plugin.

## Account connection (`OAuth_REST`)

Short-lived (10 min) one-time codes a customer site exchanges for its SLM key.
`GET /oauth/authorize` (also reachable via `admin-post.php` for the login
redirect dance) issues a code bound to the user + normalized site host; `POST
/oauth/token` swaps code + site_url for the license key. Gated on an active
license.

## Email placeholders (`Email_Placeholders`)

Adds `{{license_key}}` and `{{license_account_url}}` to ProfilePress emails.
Quirk: `ppress_subscription_placeholders_values` does **not** pass the
SubscriptionEntity, so we resolve the user from the `{{email}}` placeholder. The
order filter does pass the order, so we use `customer_id` directly there.

## Seller dashboard (`Admin_Dashboard` + `SLM_Reader`)

Lists every provisioned license with live status, seats and installs. The bridge
owns the user→key mapping in user_meta (canonical customer set); each key is
enriched from the **co-located SLM tables read directly** via `SLM_Reader` —
bulk SQL, not N `slm_check` round-trips. Rows are cached ~120s; any license
mutation calls `Admin_Dashboard::flush_cache()`.

`SLM_Reader` is the only place coupled to SLM's physical schema. It uses SLM's
`SLM_TBL_LICENSE_KEYS` / `SLM_TBL_LIC_DOMAIN` constants, falling back to the
documented default table names (`{prefix}lic_key_tbl`,
`{prefix}lic_reg_domain_tbl`) when SLM isn't loaded, and degrades to a notice if
the tables are missing.

## Migration (`Migrator`)

This plugin was renamed from an internal FlowSync-specific build. `Migrator`
runs once (guarded by the `lbfp_migrated` option), renaming legacy data keys:
options `flowsync_lb_plan_map` / `flowsync_lb_release` and the three
`flowsync_lb_*` user_meta keys → their `lbfp_*` equivalents. The user_meta
rename is a single SQL `UPDATE` per key with a `NOT EXISTS` guard so re-runs or a
partial prior migration can't create duplicate rows. Transients (dashboard cache,
OAuth codes) are ephemeral and left to rebuild under the new keys. Safe to delete
this class once every install has migrated.

## Build

`./build.sh` zips the plugin to
`../build/license-bridge-for-profilepress/license-bridge-for-profilepress-<version>.zip`,
reading the version from the main file header and excluding dev-only files
(`graphify-out/`, `.git`, `build.sh`, `CLAUDE.md`, `README.md`, `LICENSE`, dotfiles).
