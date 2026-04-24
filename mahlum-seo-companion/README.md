# Mahlum SEO Companion

Companion WordPress plugin for the [Mahlum AI SEO platform](https://mahluminnovations.com/wordpress-plugin).

## What it does

This small plugin enables the **"Apply to WordPress"** workflow in the Mahlum dashboard:

1. Whitelists the standard Yoast (`_yoast_wpseo_title`, `_yoast_wpseo_metadesc`) and Rank Math (`rank_math_title`, `rank_math_description`) post-meta keys for the WordPress REST API, so the platform can persist titles and descriptions via `/wp/v2/posts/:id`.
2. Adds an authenticated REST endpoint at `POST /wp-json/mahlum/v1/inject-jsonld`. It stores a JSON-LD block per post and prints it inside `<script type="application/ld+json">` in `wp_head` on the front-end.
3. Exposes `GET /wp-json/mahlum/v1/status` so the dashboard can verify the plugin is installed and active.

It does **not** override Yoast or Rank Math output. If those plugins are active they continue to manage their own schema; this endpoint only adds platform-supplied JSON-LD on top.

## Install

The easiest way is to download the latest zip from the dashboard:

1. Sign in to the [Mahlum dashboard](https://mahluminnovations.com) and open the **Connect WordPress** card.
2. Click **Install companion plugin** to download the zip (or grab it from the [Releases page](https://github.com/MahlumInnovationsLLC/mahlum-seo-companion/releases)).
3. In WP Admin go to **Plugins → Add New → Upload Plugin** and select the zip.
4. Activate **Mahlum SEO Companion**.
5. Make sure the WordPress user you use in the dashboard has at least the **Editor** role.
6. In the Mahlum dashboard, click **Verify** on the connection.

Full install guide: <https://mahluminnovations.com/wordpress-plugin>

## Auto-update

The plugin self-updates from a Mahlum-hosted version manifest at `/api/wordpress/plugin/info`. New releases are also published here on GitHub and tagged `vX.Y.Z`.

## Issues

Please file bugs and feature requests in [GitHub Issues](https://github.com/MahlumInnovationsLLC/mahlum-seo-companion/issues).

## License

MIT
