# License Bridge for ProfilePress

A seller-side WordPress plugin that bridges [ProfilePress](https://profilepress.com/)
paid memberships to the [Software License Manager](https://wordpress.org/plugins/software-license-manager/)
(SLM). It runs on your membership site — **not** on customer sites.

It automates the full license lifecycle for a plugin or theme you sell:

- **Auto-provisioning** — issues an SLM license when a membership subscription is
  activated, and updates `max_allowed_domains` / expiry on upgrade, renewal and expiry.
- **Email + My Account** — adds `{{license_key}}` and `{{license_account_url}}`
  placeholders to ProfilePress emails and a **Licenses** tab to the
  `[profilepress-my-account]` page, where customers can see and deactivate their sites.
- **Seller dashboard** — one screen listing every provisioned license with live
  status, seats used and the customer sites each license is installed on.
- **Licensed updates** — serves version metadata and gated ZIP downloads to
  licensed customer sites, so SLM-licensed plugins/themes can auto-update.
- **Account connection (OAuth-style)** — issues short-lived codes a customer site
  can exchange for its license key.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- ProfilePress (with Membership)
- Software License Manager (co-located on the same site)

## Installation

1. Copy this folder into `wp-content/plugins/license-bridge-for-profilepress`.
2. Activate **License Bridge for ProfilePress** in *Plugins*.
3. Add the SLM secrets to `wp-config.php` (see below).
4. Configure the licensed product and plan mapping under
   **ProfilePress → SLM Integration**.

## Configuration

### Secrets (required, in `wp-config.php`)

Secrets are **never** stored in the plugin or the database. Copy the two API keys
from *License Manager → Settings → General*:

```php
define( 'LBFP_SLM_CREATION_SECRET', 'your-license-creation-api-key' );
define( 'LBFP_SLM_VERIFICATION_SECRET', 'your-license-verification-api-key' );
```

Optional overrides:

```php
// Point at an SLM install on a different host (defaults to this site).
define( 'LBFP_SLM_URL', 'https://licenses.example.com' );

// Pin the SLM item reference instead of setting it in the admin UI.
define( 'LBFP_ITEM_REFERENCE', 'My Pro Plugin' );
```

### Admin settings (**ProfilePress → SLM Integration**)

- **Licensed product** — the SLM *item reference* (must match the product in SLM)
  plus the name/slug/homepage/author used to label customer-side updates.
- **Plan → license mapping** — map each membership plan to the number of sites its
  license should allow. A plan mapped to `0` (or unmapped) is not licensed.
- **Plugin release** — the version, ZIP path/URL and changelog served to licensed
  sites. Use an absolute filesystem path to keep the package outside the web root.

## Building a distributable ZIP

```bash
./build.sh
```

Writes `../build/license-bridge-for-profilepress/license-bridge-for-profilepress-<version>.zip`,
excluding development files (`graphify-out/`, `.git`, `build.sh`, etc.).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
