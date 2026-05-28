=== MaxtDesign Disable REST API ===
Contributors: maxtdesign
Tags: rest api, security, disable rest api, json api, api control
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Full control over your WordPress REST API. Block, restrict, or whitelist endpoints per user role. Lightweight, fast, zero frontend footprint.

== Description ==

**MaxtDesign Disable REST API** gives you complete control over who can access your WordPress REST API and which endpoints are available.

By default, WordPress exposes a REST API to the public, which can reveal usernames, post data, and site structure to anyone. This plugin lets you lock down the REST API for unauthenticated visitors while keeping it fully functional for logged-in users and the plugins that need it.

= Key Features =

* **One-click disable** — Block all REST API access for unauthenticated users with a single toggle.
* **Endpoint whitelisting** — Auto-discovers all registered REST API endpoints and lets you whitelist specific ones, even when the API is disabled.
* **Per-role access control** — Restrict REST API access for specific user roles with individual endpoint whitelists.
* **Smart defaults** — Automatically detects Contact Form 7 and WooCommerce and whitelists their required endpoints on activation.
* **Zero frontend footprint** — No CSS, JavaScript, or HTTP requests are added to your frontend. Ever.
* **Lightweight** — No database queries on frontend requests. Uses a single autoloaded option.
* **Import/Export** — Transfer settings between sites with JSON export and import.
* **Clean uninstall** — Removes all plugin data when deleted. Leaves no trace.

= How It Works =

The plugin uses the `rest_authentication_errors` filter — the correct, modern WordPress approach — to intercept REST API requests early in the lifecycle, before any endpoint logic executes. This means blocked requests have virtually zero performance impact.

= Built for Performance =

This plugin follows the MaxtDesign performance-first philosophy:

* Zero frontend asset loading (no CSS, no JS, no HTTP requests)
* Admin assets load only on the plugin's own settings page
* Single autoloaded database option — no extra queries
* Filter fires before endpoint logic — blocked requests are fast

== Installation ==

1. Upload the `maxtdesign-disable-rest-api` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings > REST API Control** to configure.
4. The REST API is blocked for unauthenticated users by default. Adjust the whitelist as needed.

== Frequently Asked Questions ==

= Will this break my site? =

No. The plugin only affects REST API requests. Your website's frontend, admin dashboard, and all standard WordPress functionality remain completely unaffected. Logged-in users have full REST API access by default.

= Does this work with Contact Form 7? =

Yes. Contact Form 7 requires the REST API for form submissions. The plugin automatically detects CF7 on activation and whitelists its endpoints. If you activate CF7 after this plugin, simply check the `contact-form-7` namespace in the endpoint whitelist.

= Does this work with WooCommerce? =

Yes. The plugin automatically detects WooCommerce on activation and whitelists the Store API endpoints (`wc/store`) needed for cart and checkout blocks. The WooCommerce admin API endpoints are available to logged-in users by default.

= What happens when I deactivate the plugin? =

Your REST API returns to normal WordPress behavior — fully open. Your settings are preserved so they'll be restored if you reactivate. Settings are only deleted when you **delete** the plugin through the WordPress admin.

= Does this affect the WordPress block editor (Gutenberg)? =

No. The block editor uses the REST API as a logged-in user, which has full access by default. Your editing experience is completely unaffected.

= Can I restrict specific user roles? =

Yes. The Per-Role Controls section lets you restrict REST API access for individual roles (subscriber, contributor, author, editor, etc.) and configure a custom endpoint whitelist for each restricted role.

= Does this work with custom REST API endpoints? =

Yes. The plugin auto-discovers all registered REST API endpoints, including those from themes and other plugins. Any custom endpoints will appear in the whitelist tree.

= How do I transfer settings to another site? =

Use the Export Settings button to download a JSON file, then use Import Settings on the other site to upload it.

== Screenshots ==

1. Global settings — one-click toggle to disable REST API for unauthenticated users.
2. Endpoint whitelist — auto-discovered endpoints with collapsible namespace tree.
3. Per-role controls — restrict REST API access for individual user roles.
4. Import/Export — easily transfer settings between sites.

== Changelog ==

= 1.0.1 =
* Compatibility: confirmed against WordPress 7.0 ("Armstrong").
* Hardening: import-settings now validates uploads with `is_uploaded_file()` and reads the temp file directly instead of mis-sanitising the server-generated path.
* Hardening: activation hook defensively loads `wp-admin/includes/plugin.php` before calling `is_plugin_active()` so WP-CLI and multisite bulk-activate paths can't fatal.
* Cleanup: removed the now-unnecessary `load_plugin_textdomain()` call. WordPress.org handles translation loading automatically since WP 4.6, and the just-in-time loader added in 6.7 made the explicit call dead code.

= 1.0.0 =
* Initial release.
* Global REST API toggle for unauthenticated users.
* Auto-discovery of all registered REST API endpoints.
* Endpoint whitelisting with collapsible namespace tree.
* Per-role REST API access controls.
* Smart defaults for Contact Form 7 and WooCommerce.
* Custom error message for blocked requests.
* Settings import/export as JSON.
* Clean uninstall — removes all plugin data.

== Upgrade Notice ==

= 1.0.1 =
WordPress 7.0 compatibility confirmed. Hardens settings import and the activation path. Recommended for all users.

= 1.0.0 =
Initial release. Take full control of your WordPress REST API.
