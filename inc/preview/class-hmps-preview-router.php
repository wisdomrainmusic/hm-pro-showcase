<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once HMPS_PLUGIN_DIR . 'inc/preview/class-hmps-virtual-pages.php';
require_once HMPS_PLUGIN_DIR . 'inc/preview/class-hmps-preview-context.php';
require_once HMPS_PLUGIN_DIR . 'inc/preview/class-hmps-menu-rewrite.php';
require_once HMPS_PLUGIN_DIR . 'inc/preview/class-hmps-package-menu.php';
require_once HMPS_PLUGIN_DIR . 'inc/preview/class-hmps-preview-snapshot.php';
require_once HMPS_PLUGIN_DIR . 'inc/preview/class-hmps-preview-media.php';
require_once HMPS_PLUGIN_DIR . 'inc/preview/class-hmps-preview-products.php';

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
	 * Translate /demo/<slug>/<path> into a template resolution by demo content.
	 */
	public static function resolve_template() : void {
		$demo_slug = get_query_var( self::QV_DEMO );
		$demo_slug = $demo_slug ? sanitize_title( (string) $demo_slug ) : '';
		if ( ! $demo_slug ) {
			return;
		}

		// When in preview, rewrite theme menu links into demo scope.
		if ( HMPS_Preview_Context::is_preview() ) {
			HMPS_Menu_Rewrite::boot();
			// Override primary menu output from package menus.json (theme-agnostic).
			HMPS_Package_Menu::boot();
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

		$package_dir = wp_normalize_path( trailingslashit( (string) $settings['packages_base_dir'] ) . $demo_slug );

		// Serve packaged media files inside preview scope:
		// /<preview_base>/<demo>/media/<rel_path>
		if ( $path && 0 === strpos( $path, 'media/' ) ) {
			$rel = substr( $path, 6 );
			HMPS_Preview_Media::serve( $package_dir, $rel );
		}

		// Takeover: do NOT look up WP pages anymore.
		// We will render demo content virtually from the package files in next commits.
		$front_slug = sanitize_title( (string) ( $package['front_page_slug'] ?? '' ) );
		$resolved   = $path ? $path : ( $front_slug ? $front_slug : '' );

		// Expose to template.
		$GLOBALS['hmps_demo_slug']          = $demo_slug;
		$GLOBALS['hmps_demo_path']          = $path;
		$GLOBALS['hmps_demo_resolved']      = $resolved;
		$GLOBALS['hmps_demo_package']       = $package;
		$GLOBALS['hmps_packages_base_dir']  = (string) $settings['packages_base_dir'];

		// Apply exporter snapshot overrides in preview context.
		HMPS_Preview_Snapshot::boot( $package_dir );

		// Create virtual WooCommerce products + attachments for this demo (if present).
		HMPS_Preview_Products::boot( $package_dir, $demo_slug );

		$template = HMPS_PLUGIN_DIR . 'templates/demo-shell.php';
		if ( ! file_exists( $template ) ) {
			status_header( 500 );
			nocache_headers();
			echo esc_html__( 'Demo template missing.', 'hm-pro-showcase' );
			exit;
		}

		status_header( 200 );
		nocache_headers();
		include $template;
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
