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
 * Then we translate request into a template resolution.
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
	 * Determine the expected WP page slug for a demo+resolved slug.
	 * Primary: <demo>-<slug> (namespaced)
	 * Fallback: <slug> (for legacy imports until namespacing is enabled)
	 */
	private static function find_demo_page( string $demo_slug, string $resolved_slug ) {
		$demo_slug     = sanitize_title( $demo_slug );
		$resolved_slug = sanitize_title( $resolved_slug );
		if ( ! $resolved_slug ) {
			return null;
		}

		// Namespaced first
		$page_slug = sanitize_title( $demo_slug . '-' . $resolved_slug );
		$page      = get_page_by_path( $page_slug, OBJECT, 'page' );
		if ( $page ) {
			return $page;
		}

		// Legacy fallback (no namespacing yet)
		$page = get_page_by_path( $resolved_slug, OBJECT, 'page' );
		if ( $page ) {
			return $page;
		}

		return null;
	}

	/**
	 * Translate /demo/<slug>/<path> into a template resolution by demo content.
	 */
	public static function resolve_template() : void {
		$demo_slug = get_query_var( self::QV_DEMO );
		$demo_slug = $demo_slug ? sanitize_title( (string) $demo_slug ) : '';
		if ( ! $demo_slug ) {
			return;
		}

		$path = get_query_var( self::QV_PATH );
		$path = $path ? trim( (string) $path, '/' ) : '';

		$settings = self::get_settings_safe();
		$repo     = new HMPS_Package_Repository( (string) $settings['packages_base_dir'] );
		$package  = $repo->get_package( $demo_slug );
		if ( ! is_array( $package ) ) {
			status_header( 404 );
			nocache_headers();
			echo esc_html__( 'Demo package not found.', 'hm-pro-showcase' );
			exit;
		}

		if ( $path ) {
			$resolved_slug = sanitize_title( $path );
		} else {
			$front_slug    = sanitize_title( (string) ( $package['front_page_slug'] ?? '' ) );
			$resolved_slug = $front_slug ? $front_slug : 'ana-sayfa';
		}

		$page = self::find_demo_page( $demo_slug, $resolved_slug );
		if ( ! $page ) {
			status_header( 404 );
			nocache_headers();
			echo esc_html__( 'Demo page not found.', 'hm-pro-showcase' );
			exit;
		}

		// Force WP main query to load this page.
		global $wp_query;
		$wp_query->init();
		$wp_query->is_home       = false;
		$wp_query->is_front_page = false;
		$wp_query->is_page       = true;
		$wp_query->is_singular   = true;
		$wp_query->is_404        = false;

		$wp_query->queried_object    = $page;
		$wp_query->queried_object_id = (int) $page->ID;
		$wp_query->post              = $page;
		$wp_query->posts             = array( $page );
		$wp_query->post_count        = 1;
		$wp_query->found_posts       = 1;
		$wp_query->max_num_pages     = 1;

		global $post;
		$post = $page;
		setup_postdata( $post );

		$template = get_page_template();
		if ( ! $template ) {
			$template = get_single_template();
		}
		if ( ! $template ) {
			$template = get_index_template();
		}

		if ( $template ) {
			include $template;
		} else {
			echo apply_filters( 'the_content', $page->post_content );
		}
		exit;
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
