<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preview Router Core
 *
 * URL format:
 *   /{preview_base_slug}/{demo-slug}/
 *   /{preview_base_slug}/{demo-slug}/{inner-path}
 *
 * Rewrite:
 *   ^demo/([^/]+)/?(.*)?$ -> index.php?hmps_demo=$1&hmps_path=$2
 *
 * Then we translate request into WordPress native routing by setting pagename.
 * Woo/endpoint rewriting will be handled in later commits.
 */
final class HMPS_Preview_Router {
	const QV_DEMO = 'hmps_demo';
	const QV_PATH = 'hmps_path';

	/**
	 * Activation hook: add rewrite and flush.
	 */
	public static function activate() : void {
		self::register();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation hook: flush rewrite.
	 */
	public static function deactivate() : void {
		flush_rewrite_rules();
	}

	/**
	 * Register rewrite rule using current settings preview_base_slug.
	 */
	public static function register() : void {
		$preview_base = self::get_preview_base_slug();
		if ( ! $preview_base ) {
			$preview_base = 'demo';
		}

		// Example: ^demo/([^/]+)/?(.*)?$
		add_rewrite_rule(
			'^' . preg_quote( $preview_base, '#' ) . '/([^/]+)/?(.*)?$',
			'index.php?' . self::QV_DEMO . '=$matches[1]&' . self::QV_PATH . '=$matches[2]',
			'top'
		);
	}

	public static function register_query_vars( array $vars ) : array {
		$vars[] = self::QV_DEMO;
		$vars[] = self::QV_PATH;
		return $vars;
	}

	/**
	 * Translate /demo/<slug>/<path> into a WP-native request.
	 *
	 * For Commit 5 we map to "pagename" so pages load correctly.
	 * Later commits will extend this to Woo endpoints, post links, term links, etc.
	 */
	public static function filter_request( array $query_vars ) : array {
		$demo = isset( $query_vars[ self::QV_DEMO ] ) ? sanitize_title( (string) $query_vars[ self::QV_DEMO ] ) : '';
		if ( ! $demo ) {
			return $query_vars;
		}

		// Load settings + package to find front_page_slug.
		$settings = self::get_settings_safe();
		$repo     = new HMPS_Package_Repository( (string) $settings['packages_base_dir'] );
		$pkg      = $repo->get_package( $demo );

		if ( ! is_array( $pkg ) ) {
			// Unknown demo -> 404.
			$query_vars['error'] = '404';
			return $query_vars;
		}

		$path = isset( $query_vars[ self::QV_PATH ] ) ? (string) $query_vars[ self::QV_PATH ] : '';
		$path = trim( $path );
		$path = ltrim( $path, '/' );
		$path = rtrim( $path, '/' );

		if ( '' === $path ) {
			$front = (string) ( $pkg['front_page_slug'] ?? '' );
			$front = sanitize_title( $front );
			$path  = $front ? $front : '';
		}

		// Mark demo context globally (used later for link rewriting/cookies).
		$GLOBALS['hmps_demo_active']  = $demo;
		$GLOBALS['hmps_demo_package'] = $pkg;

		// Core translation: map to a page path.
		// This supports nested pages like "shop/checkout" (as a pagename),
		// but Woo endpoints will need additional handling later.
		if ( $path ) {
			$query_vars['pagename'] = $path;
		} else {
			// If no path and no front page slug, fallback to home.
			// We do this by setting 'pagename' empty and letting WP load front page.
			unset( $query_vars['pagename'] );
		}

		// Prevent accidental canonical redirect away from our demo base (handled later more deeply).
		add_filter( 'redirect_canonical', array( __CLASS__, 'maybe_disable_canonical' ), 10, 2 );

		return $query_vars;
	}

	public static function maybe_disable_canonical( $redirect_url, $requested_url ) {
		$demo = self::current_demo_slug();
		if ( $demo ) {
			return false;
		}
		return $redirect_url;
	}

	public static function current_demo_slug() : string {
		return isset( $GLOBALS['hmps_demo_active'] ) ? (string) $GLOBALS['hmps_demo_active'] : '';
	}

	private static function get_preview_base_slug() : string {
		$s = self::get_settings_safe();
		$base = isset( $s['preview_base_slug'] ) ? (string) $s['preview_base_slug'] : 'demo';
		$base = sanitize_title( $base );
		return $base ? $base : 'demo';
	}

	private static function get_settings_safe() : array {
		// Reuse admin settings structure if available.
		if ( ! class_exists( 'HMPS_Admin' ) ) {
			require_once HMPS_PLUGIN_DIR . 'inc/admin/class-hmps-admin.php';
		}
		return HMPS_Admin::get_settings();
	}
}
