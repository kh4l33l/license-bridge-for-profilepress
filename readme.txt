=== License Bridge for ProfilePress ===
Contributors: ibrahimkh4l33l
Tags: profilepress, software license manager, licensing, memberships, updates
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.0.1
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

SLM API secrets are read from `wp-config.php` and are never stored in the
database. Add the two keys from *License Manager → Settings → General*:

`define( 'LBFP_SLM_CREATION_SECRET', 'your-license-creation-api-key' );`
`define( 'LBFP_SLM_VERIFICATION_SECRET', 'your-license-verification-api-key' );`

Then set the licensed product (its SLM item reference and update metadata) and
map each membership plan to the number of sites its license allows under
**ProfilePress → SLM Integration**.

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

= What happens when a subscription is cancelled? =

Nothing immediately. The license keeps working until the paid period actually
ends; only an *expired* subscription revokes the license (by setting its SLM
expiry date), so customers keep access for the time they paid for.

= How do customer sites receive updates? =

The plugin exposes REST endpoints that return update metadata and a gated
download URL. After verifying the license is active and the requesting domain is
registered, it streams the release ZIP you configured on the settings screen.

== Changelog ==

= 0.0.1 =
* First public release.
* Configurable licensed product (SLM item reference + update metadata).
* Auto-provisioning on order completion, renewal and expiry.
* Customer My Account Licenses tab and email placeholders.
* Seller license dashboard.
* License-gated update server and account-connection endpoints.

== Upgrade Notice ==

= 0.0.1 =
First public release.
