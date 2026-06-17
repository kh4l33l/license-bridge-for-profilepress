=== License Bridge for ProfilePress ===
Contributors: ibrahimkh4l33l
Tags: profilepress, software license manager, licensing, memberships, updates
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Issue and manage Software License Manager licenses from ProfilePress memberships, and serve license-gated plugin updates to customers.

== Description ==

License Bridge for ProfilePress connects **ProfilePress** (Membership) to the
**Software License Manager (SLM)** plugin so you can sell a licensed plugin or
theme through ProfilePress subscriptions. It runs on your own membership/store
site — it is never installed on customer sites.

It automates the whole license lifecycle:

* **Auto-provisioning** — issues an SLM license when a subscription is activated, and updates the allowed-site count and expiry on upgrade, renewal and expiry.
* **Customer-facing license info** — adds `{{license_key}}` and `{{license_account_url}}` placeholders to ProfilePress emails, plus a **Licenses** tab in the `[profilepress-my-account]` page where customers can view their key, see the sites it is active on, and deactivate a site to free a seat.
* **Seller dashboard** — one screen listing every provisioned license with live status, seats used, and the customer sites each license is installed on.
* **License-gated updates** — serves version metadata and ZIP downloads to licensed customer sites so an SLM-licensed plugin/theme can auto-update. Packages can be streamed from outside the web root and are only delivered after the license + domain are verified.
* **Account connection** — issues short-lived codes a customer site can exchange for its license key (an OAuth-style connect flow).

= Requirements =

* [ProfilePress](https://wordpress.org/plugins/profilepress/) with Membership.
* [Software License Manager](https://wordpress.org/plugins/software-license-manager/), normally on the same site.

= Configuration =

All configuration is read from `wp-config.php` — SLM API secrets are never stored
in the database or the plugin files. SLM ships **two separate API keys** (License
Manager → Settings → General); the plugin needs both:

`define( 'LBFP_SLM_CREATION_SECRET', 'your-license-creation-api-key' );`
`define( 'LBFP_SLM_VERIFICATION_SECRET', 'your-license-verification-api-key' );`

* `LBFP_SLM_CREATION_SECRET` (required) — the License Creation API key. Issues and updates licenses when subscriptions activate, renew, upgrade, or expire.
* `LBFP_SLM_VERIFICATION_SECRET` (required) — the License Verification API key. Validates, activates, and deactivates licenses, and gates updates (My Account tab, dashboard, update server, account connection).
* `LBFP_SLM_URL` (optional) — SLM endpoint; defaults to this site. Set it only if SLM runs on another host.
* `LBFP_ITEM_REFERENCE` (optional) — pins the SLM item reference, overriding the admin setting.

The two keys are different values — don't swap them or reuse one for both. Then
set the licensed product and map each membership plan to its allowed site count
under **ProfilePress → SLM Integration**.

== Installation ==

1. Install and activate ProfilePress (with Membership) and Software License Manager.
2. Upload the `license-bridge-for-profilepress` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
3. Activate **License Bridge for ProfilePress**.
4. Add `LBFP_SLM_CREATION_SECRET` and `LBFP_SLM_VERIFICATION_SECRET` to `wp-config.php` (see Description).
5. Go to **ProfilePress → SLM Integration** to set the licensed product, map plans to allowed sites, and publish your release package.

== Frequently Asked Questions ==

= Does this run on my customers' sites? =

No. It is a seller-side plugin that runs on your membership/store site alongside
ProfilePress and Software License Manager. Customers only receive a license key
and updates.

= Where are my SLM API secrets stored? =

Only in `wp-config.php`, via the `LBFP_SLM_CREATION_SECRET` and
`LBFP_SLM_VERIFICATION_SECRET` constants. They are never written to the database
or to the plugin files.

= Can SLM run on a different site? =

Yes. By default the plugin talks to SLM on the same site (`home_url()`). Define
`LBFP_SLM_URL` in `wp-config.php` to point at SLM on another host.

= What is the difference between the two SLM API keys? =

Software License Manager uses two secrets. The Creation key
(`LBFP_SLM_CREATION_SECRET`) issues and updates licenses; the Verification key
(`LBFP_SLM_VERIFICATION_SECRET`) checks, activates, and deactivates them and
gates updates. They are different values — set each in its own constant, and do
not swap them.

= What happens when a subscription is cancelled? =

Nothing immediately. The license keeps working until the paid period actually
ends; only an *expired* subscription revokes the license (by setting its SLM
expiry date), so customers keep access for the time they paid for.

= How do customer sites receive updates? =

The plugin exposes REST endpoints that return update metadata and a gated
download URL. After verifying the license is active and the requesting domain is
registered, it streams the release ZIP you configured on the settings screen.

== Changelog ==

= 0.0.2 =
* The settings screen now names and checks both required wp-config secrets (LBFP_SLM_CREATION_SECRET and LBFP_SLM_VERIFICATION_SECRET) and warns if either is missing. Removed the unused LBFP_SLM_SECRET constant.
* Added the lbfp_rest_namespaces and lbfp_oauth_authorize_actions filters, so a renamed install can keep serving an older REST namespace / OAuth action to clients that have not updated yet.

= 0.0.1 =
* First public release.
* Configurable licensed product (SLM item reference + update metadata).
* Auto-provisioning on order completion, renewal and expiry.
* Customer My Account Licenses tab and email placeholders.
* Seller license dashboard.
* License-gated update server and account-connection endpoints.

== Upgrade Notice ==

= 0.0.2 =
Set both LBFP_SLM_CREATION_SECRET and LBFP_SLM_VERIFICATION_SECRET in wp-config.php; the unused LBFP_SLM_SECRET constant has been removed.

= 0.0.1 =
First public release.
