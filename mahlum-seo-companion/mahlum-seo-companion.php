<?php
/**
 * Plugin Name: Mahlum SEO Companion
 * Plugin URI: https://mahluminnovations.com/wordpress-plugin
 * Description: Companion plugin for the Mahlum AI SEO platform. Exposes Yoast / Rank Math meta in the REST API and adds an authenticated endpoint for injecting JSON-LD schema blocks into posts.
 * Version: 1.0.0
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: Mahlum Innovations
 * Author URI: https://mahluminnovations.com
 * License: MIT
 * Update URI: https://mahluminnovations.com/api/wordpress/plugin/info
 * Text Domain: mahlum-seo-companion
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MAHLUM_SEO_COMPANION_VERSION', '1.0.0');
define('MAHLUM_SEO_COMPANION_META_KEY', '_mahlum_jsonld');
define('MAHLUM_SEO_COMPANION_UPDATE_URL', 'https://mahluminnovations.com/api/wordpress/plugin/info');

/**
 * Whitelist Yoast and Rank Math meta keys for REST so the platform's
 * existing /wp/v2/posts/:id meta payload actually persists.
 */
function mahlum_seo_companion_register_meta() {
    $keys = array(
        '_yoast_wpseo_title'      => 'string',
        '_yoast_wpseo_metadesc'   => 'string',
        'rank_math_title'         => 'string',
        'rank_math_description'   => 'string',
    );

    $post_types = get_post_types(array('public' => true), 'names');
    foreach ($post_types as $post_type) {
        foreach ($keys as $key => $type) {
            register_post_meta($post_type, $key, array(
                'type'         => $type,
                'single'       => true,
                'show_in_rest' => true,
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ));
        }

        // Internal storage for JSON-LD blocks (not exposed via REST list).
        register_post_meta($post_type, MAHLUM_SEO_COMPANION_META_KEY, array(
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => false,
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));
    }
}
add_action('init', 'mahlum_seo_companion_register_meta');

/**
 * Register REST routes.
 */
function mahlum_seo_companion_register_routes() {
    register_rest_route('mahlum/v1', '/inject-jsonld', array(
        'methods'             => 'POST',
        'callback'            => 'mahlum_seo_companion_inject_jsonld',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
        'args' => array(
            'post_id' => array('required' => true, 'type' => 'integer'),
            'jsonld'  => array('required' => true),
        ),
    ));

    register_rest_route('mahlum/v1', '/status', array(
        'methods'             => 'GET',
        'callback'            => function () {
            return array(
                'ok'      => true,
                'plugin'  => 'mahlum-seo-companion',
                'version' => MAHLUM_SEO_COMPANION_VERSION,
            );
        },
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'mahlum_seo_companion_register_routes');

/**
 * Handle POST /mahlum/v1/inject-jsonld.
 */
function mahlum_seo_companion_inject_jsonld(WP_REST_Request $request) {
    $post_id = (int) $request->get_param('post_id');
    $jsonld  = $request->get_param('jsonld');

    if ($post_id <= 0 || !get_post($post_id)) {
        return new WP_Error('mahlum_invalid_post', 'Post not found', array('status' => 404));
    }

    if (!current_user_can('edit_post', $post_id)) {
        return new WP_Error('mahlum_forbidden', 'Insufficient permissions for this post', array('status' => 403));
    }

    if (is_string($jsonld)) {
        $decoded = json_decode($jsonld, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('mahlum_invalid_jsonld', 'jsonld must be a JSON object or valid JSON string', array('status' => 400));
        }
        $jsonld = $decoded;
    }

    if (!is_array($jsonld) || empty($jsonld)) {
        return new WP_Error('mahlum_invalid_jsonld', 'jsonld must be a non-empty object', array('status' => 400));
    }

    // NOTE: do NOT use JSON_UNESCAPED_SLASHES here. Default behaviour escapes
    // forward slashes which prevents a stored "</script>" sequence from breaking
    // out of the inline <script type="application/ld+json"> tag on render.
    $encoded = wp_json_encode($jsonld, JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        return new WP_Error('mahlum_invalid_jsonld', 'jsonld could not be encoded', array('status' => 400));
    }

    update_post_meta($post_id, MAHLUM_SEO_COMPANION_META_KEY, $encoded);

    return rest_ensure_response(array(
        'ok'      => true,
        'post_id' => $post_id,
        'bytes'   => strlen($encoded),
    ));
}

/**
 * Output the stored JSON-LD block on the front-end via wp_head.
 */
function mahlum_seo_companion_render_jsonld() {
    if (!is_singular()) {
        return;
    }
    $post_id = get_queried_object_id();
    if (!$post_id) {
        return;
    }
    $stored = get_post_meta($post_id, MAHLUM_SEO_COMPANION_META_KEY, true);
    if (!$stored) {
        return;
    }
    // Validate it is still proper JSON before printing.
    $decoded = json_decode($stored, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return;
    }
    // Re-encode with default slash escaping so a payload value containing
    // "</script>" cannot break out of the inline script tag (privilege-bound
    // XSS hardening — Editors can call inject-jsonld via REST).
    $safe = wp_json_encode($decoded, JSON_UNESCAPED_UNICODE);
    if ($safe === false) {
        return;
    }
    // Belt-and-braces: explicitly neutralise any closing tag sequences in case
    // a future change re-enables JSON_UNESCAPED_SLASHES.
    $safe = str_replace(array('</', '<!--', '-->'), array('<\/', '<\u0021--', '--\u003e'), $safe);
    echo "\n<script type=\"application/ld+json\" data-mahlum=\"1\">" . $safe . "</script>\n";
}
add_action('wp_head', 'mahlum_seo_companion_render_jsonld', 20);

/**
 * Lightweight self-hosted update check.
 * Pings the platform for the latest version metadata and surfaces an update
 * notice in WP Admin if a newer version is published.
 */
function mahlum_seo_companion_check_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }
    $plugin_file = plugin_basename(__FILE__);
    $response = wp_remote_get(MAHLUM_SEO_COMPANION_UPDATE_URL, array('timeout' => 6));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return $transient;
    }
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($body) || empty($body['version']) || empty($body['download_url'])) {
        return $transient;
    }
    if (version_compare($body['version'], MAHLUM_SEO_COMPANION_VERSION, '>')) {
        $transient->response[$plugin_file] = (object) array(
            'slug'        => 'mahlum-seo-companion',
            'plugin'      => $plugin_file,
            'new_version' => $body['version'],
            'package'     => $body['download_url'],
            'url'         => 'https://mahluminnovations.com/wordpress-plugin',
        );
    }
    return $transient;
}
add_filter('site_transient_update_plugins', 'mahlum_seo_companion_check_update');
