<?php
/**
 * Preview Snapshot Runtime Overrides
 *
 * Applies exporter snapshot JSONs (theme_mods/widgets/elementor options + CSS)
 * only while inside /{preview_base}/{demo}/... preview routes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HMPS_Preview_Snapshot {
	/** @var array<string,mixed> */
	private static $cache = array();

	/**
	 * Boot snapshot overrides for a given package dir.
	 */
	public static function boot( string $package_dir ) : void {
		if ( ! HMPS_Preview_Context::is_preview() ) {
			return;
		}

		$package_dir = wp_normalize_path( $package_dir );
		if ( ! $package_dir || ! is_dir( $package_dir ) ) {
			return;
		}

		// Cache per-request (package dir scoped).
		self::$cache['package_dir'] = $package_dir;

		// Theme mods + custom CSS.
		$theme_mods = self::read_json_file( trailingslashit( $package_dir ) . 'theme_mods.json' );
		if ( is_array( $theme_mods ) ) {
			self::$cache['theme_mods'] = $theme_mods;
			self::hook_theme_mods( $theme_mods );
			self::hook_wp_css_custom( $theme_mods );
		}

		// Widgets.
		$widgets = self::read_json_file( trailingslashit( $package_dir ) . 'widgets.json' );
		if ( is_array( $widgets ) ) {
			self::$cache['widgets'] = $widgets;
			self::hook_widgets( $widgets );
		}

		// Elementor options.
		$el_opts = self::read_json_file( trailingslashit( $package_dir ) . 'elementor_options.json' );
		if ( is_array( $el_opts ) ) {
			self::$cache['elementor_options'] = $el_opts;
			self::hook_options_map( $el_opts );
		}

		// Elementor kit snapshot contains an "options" map as well.
		$el_kit = self::read_json_file( trailingslashit( $package_dir ) . 'elementor_kit.json' );
		if ( is_array( $el_kit ) && isset( $el_kit['options'] ) && is_array( $el_kit['options'] ) ) {
			self::$cache['elementor_kit'] = $el_kit;
			self::hook_options_map( $el_kit['options'] );
		}

		// Elementor compiled CSS.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_elementor_css' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'print_snapshot_debug_meta' ), 1 );
	}

	/**
	 * Inject Elementor compiled CSS from elementor_css.zip as inline style.
	 */
	public static function enqueue_elementor_css() : void {
		if ( ! HMPS_Preview_Context::is_preview() ) {
			return;
		}

		$package_dir = isset( self::$cache['package_dir'] ) ? (string) self::$cache['package_dir'] : '';
		if ( ! $package_dir ) {
			return;
		}

		$zip_path = trailingslashit( $package_dir ) . 'elementor_css.zip';
		if ( ! file_exists( $zip_path ) ) {
			return;
		}

		$css = self::read_zip_css_concat( $zip_path );
		if ( ! $css ) {
			return;
		}

		// Avoid double-including if Elementor already printed identical CSS.
		wp_register_style( 'hmps-snapshot-elementor', false, array(), null );
		wp_enqueue_style( 'hmps-snapshot-elementor' );
		wp_add_inline_style( 'hmps-snapshot-elementor', $css );
	}

	/**
	 * Print a small meta marker for troubleshooting in preview.
	 */
	public static function print_snapshot_debug_meta() : void {
		if ( ! HMPS_Preview_Context::is_preview() ) {
			return;
		}
		$demo = HMPS_Preview_Context::demo_slug();
		if ( ! $demo ) {
			return;
		}
		echo "\n" . '<meta name="hmps-demo" content="' . esc_attr( $demo ) . '" />' . "\n";
	}

	/**
	 * Hook theme_mods option override.
	 *
	 * We return a MERGED array: snapshot values override current values.
	 */
	private static function hook_theme_mods( array $theme_mods_payload ) : void {
		$mods = isset( $theme_mods_payload['theme_mods'] ) && is_array( $theme_mods_payload['theme_mods'] )
			? $theme_mods_payload['theme_mods']
			: array();
		if ( empty( $mods ) ) {
			return;
		}

		$exported_stylesheet = isset( $theme_mods_payload['stylesheet'] ) ? sanitize_key( (string) $theme_mods_payload['stylesheet'] ) : '';
		$current_stylesheet  = sanitize_key( (string) get_stylesheet() );

		$targets = array_filter( array_unique( array( $exported_stylesheet, $current_stylesheet ) ) );
		foreach ( $targets as $stylesheet ) {
			add_filter( 'pre_option_theme_mods_' . $stylesheet, function( $value ) use ( $mods ) {
				if ( ! HMPS_Preview_Context::is_preview() ) {
					return $value;
				}
				$base = is_array( $value ) ? $value : array();
				// Snapshot overrides base.
				return array_replace( $base, $mods );
			}, 50 );
		}
	}

	/**
	 * Inject WP Custom CSS (from wp_css_custom or legacy custom_css) for the theme.
	 */
	private static function hook_wp_css_custom( array $theme_mods_payload ) : void {
		$css = '';
		if ( isset( $theme_mods_payload['wp_css_custom'] ) && is_string( $theme_mods_payload['wp_css_custom'] ) ) {
			$css = (string) $theme_mods_payload['wp_css_custom'];
		}
		if ( ! $css ) {
			return;
		}
		add_action( 'wp_head', function() use ( $css ) {
			if ( ! HMPS_Preview_Context::is_preview() ) {
				return;
			}
			echo "\n<style id=\"hmps-snapshot-wp-css\">\n" . $css . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}, 20 );
	}

	/**
	 * Hook widgets: sidebars_widgets + each widget_* option.
	 */
	private static function hook_widgets( array $widgets_payload ) : void {
		if ( isset( $widgets_payload['sidebars_widgets'] ) && is_array( $widgets_payload['sidebars_widgets'] ) ) {
			$sidebars = $widgets_payload['sidebars_widgets'];
			add_filter( 'pre_option_sidebars_widgets', function( $value ) use ( $sidebars ) {
				if ( ! HMPS_Preview_Context::is_preview() ) {
					return $value;
				}
				$base = is_array( $value ) ? $value : array();
				return array_replace( $base, $sidebars );
			}, 50 );
		}

		if ( isset( $widgets_payload['widgets'] ) && is_array( $widgets_payload['widgets'] ) ) {
			foreach ( $widgets_payload['widgets'] as $opt_name => $opt_value ) {
				if ( ! is_string( $opt_name ) || $opt_name === '' ) {
					continue;
				}
				$opt_name = sanitize_key( $opt_name );
				add_filter( 'pre_option_' . $opt_name, function( $value ) use ( $opt_value ) {
					if ( ! HMPS_Preview_Context::is_preview() ) {
						return $value;
					}
					return $opt_value;
				}, 50 );
			}
		}
	}

	/**
	 * Hook an "options map" file, where keys are option names.
	 */
	private static function hook_options_map( array $map ) : void {
		foreach ( $map as $opt_name => $opt_value ) {
			if ( ! is_string( $opt_name ) || $opt_name === '' ) {
				continue;
			}
			$opt_name = sanitize_key( $opt_name );
			add_filter( 'pre_option_' . $opt_name, function( $value ) use ( $opt_value ) {
				if ( ! HMPS_Preview_Context::is_preview() ) {
					return $value;
				}
				return $opt_value;
			}, 50 );
		}
	}

	/**
	 * Read JSON file into array.
	 *
	 * @return array<string,mixed>|array<int,mixed>|null
	 */
	private static function read_json_file( string $file ) {
		if ( ! file_exists( $file ) ) {
			return null;
		}
		$raw = file_get_contents( $file );
		if ( ! $raw ) {
			return null;
		}
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Read all .css files in a zip and concatenate.
	 */
	private static function read_zip_css_concat( string $zip_path ) : string {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}

		$zip = new ZipArchive();
		$res = $zip->open( $zip_path );
		if ( $res !== true ) {
			return '';
		}

		$css_files = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );
			$name = isset( $stat['name'] ) ? (string) $stat['name'] : '';
			if ( ! $name ) {
				continue;
			}
			if ( substr( $name, -4 ) !== '.css' ) {
				continue;
			}
			$css_files[] = $name;
		}

		// Stable order: kit first (post-<kitid>.css tends to be smaller), then other pages.
		sort( $css_files, SORT_NATURAL );

		$out = '';
		foreach ( $css_files as $name ) {
			$raw = $zip->getFromName( $name );
			if ( ! is_string( $raw ) || $raw === '' ) {
				continue;
			}
			$out .= "\n/* hmps snapshot: {$name} */\n" . $raw . "\n";
		}
		$zip->close();
		return $out;
	}
}
