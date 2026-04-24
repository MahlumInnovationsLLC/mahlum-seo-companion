=== Mahlum SEO Companion ===
Contributors: mahluminnovations
Tags: seo, json-ld, schema, yoast, rank-math, ai
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT

Companion plugin for the Mahlum AI SEO platform. Lets the platform persist Yoast / Rank Math meta fields and inject JSON-LD schema blocks when you click "Apply to WordPress".

== Description ==

This plugin does two small things, both required for the "Apply to WordPress" workflow in the Mahlum dashboard:

1. Whitelists the standard Yoast (`_yoast_wpseo_title`, `_yoast_wpseo_metadesc`) and Rank Math (`rank_math_title`, `rank_math_description`) post-meta keys for the WordPress REST API, so the platform's existing `/wp/v2/posts/:id` meta payload can persist titles and descriptions.
2. Adds an authenticated REST endpoint at `POST /wp-json/mahlum/v1/inject-jsonld`. It stores a JSON-LD block per post and prints it inside `<script type="application/ld+json">` in `wp_head` on the front-end.

It does **not** override Yoast or Rank Math output. If those plugins are active they continue to manage their own schema; this endpoint only adds platform-supplied JSON-LD on top.

== Installation ==

1. Download the plugin zip from your Mahlum dashboard ("Connect WordPress" card → "Install companion plugin").
2. In WP Admin go to **Plugins → Add New → Upload Plugin** and select the zip.
3. Activate **Mahlum SEO Companion**.
4. Make sure the WordPress user you use in the dashboard has at least the **Editor** role (so it can edit posts and update post meta).
5. In the Mahlum dashboard, click **Verify** on the connection. The "Apply to WordPress" actions will now succeed.

== Uninstall ==

Deactivating the plugin removes the REST routes immediately. Stored JSON-LD blocks remain in `wp_postmeta` under the `_mahlum_jsonld` key until you delete them manually.

== Changelog ==

= 1.0.0 =
* Initial release.
* Whitelists Yoast / Rank Math meta keys for REST.
* Adds `POST /mahlum/v1/inject-jsonld` and `GET /mahlum/v1/status`.
* Renders stored JSON-LD on the front-end via `wp_head`.
* Supports auto-update via Mahlum-hosted version manifest.
