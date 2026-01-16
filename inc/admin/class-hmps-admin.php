<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HMPS_Admin {
	const CAPABILITY = 'manage_options';

	/**
	 * Register top-level menu + submenus.
	 */
	public static function register_menu() : void {
		$parent_slug = 'hmps_showcase';

		add_menu_page(
			__( 'Showcase', 'hm-pro-showcase' ),
			__( 'Showcase', 'hm-pro-showcase' ),
			self::CAPABILITY,
			$parent_slug,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-screenoptions',
			58
		);

		add_submenu_page(
			$parent_slug,
			__( 'Dashboard', 'hm-pro-showcase' ),
			__( 'Dashboard', 'hm-pro-showcase' ),
			self::CAPABILITY,
			$parent_slug,
			array( __CLASS__, 'render_dashboard' )
		);

		add_submenu_page(
			$parent_slug,
			__( 'Categories', 'hm-pro-showcase' ),
			__( 'Categories', 'hm-pro-showcase' ),
			self::CAPABILITY,
			'hmps_categories',
			array( __CLASS__, 'render_categories' )
		);

		add_submenu_page(
			$parent_slug,
			__( 'Cache', 'hm-pro-showcase' ),
			__( 'Cache', 'hm-pro-showcase' ),
			self::CAPABILITY,
			'hmps_cache',
			array( __CLASS__, 'render_cache' )
		);

		add_submenu_page(
			$parent_slug,
			__( 'Diagnostics', 'hm-pro-showcase' ),
			__( 'Diagnostics', 'hm-pro-showcase' ),
			self::CAPABILITY,
			'hmps_diagnostics',
			array( __CLASS__, 'render_diagnostics' )
		);

		add_submenu_page(
			$parent_slug,
			__( 'Settings', 'hm-pro-showcase' ),
			__( 'Settings', 'hm-pro-showcase' ),
			self::CAPABILITY,
			'hmps_settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Settings API registration.
	 */
	public static function register_settings() : void {
		register_setting(
			'hmps_settings_group',
			'hmps_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'hmps_settings_main',
			__( 'Core Settings', 'hm-pro-showcase' ),
			static function() {
				echo '<p>' . esc_html__( 'Basic configuration for package reading and demo preview base path.', 'hm-pro-showcase' ) . '</p>';
			},
			'hmps_settings'
		);

		add_settings_field(
			'packages_base_dir',
			__( 'Packages base directory', 'hm-pro-showcase' ),
			array( __CLASS__, 'field_packages_base_dir' ),
			'hmps_settings',
			'hmps_settings_main'
		);

		add_settings_field(
			'preview_base_slug',
			__( 'Preview base slug', 'hm-pro-showcase' ),
			array( __CLASS__, 'field_preview_base_slug' ),
			'hmps_settings',
			'hmps_settings_main'
		);
	}

	public static function get_settings() : array {
		$defaults = array(
			'packages_base_dir' => '',
			'preview_base_slug' => 'demo',
		);
		$opt = get_option( 'hmps_settings', array() );
		if ( ! is_array( $opt ) ) {
			$opt = array();
		}
		$merged = array_merge( $defaults, $opt );

		// Fill base dir if empty.
		if ( empty( $merged['packages_base_dir'] ) ) {
			$uploads = wp_upload_dir();
			$base    = isset( $uploads['basedir'] ) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
			$merged['packages_base_dir'] = trailingslashit( $base ) . 'hmps-packages';
		}

		return $merged;
	}

	public static function sanitize_settings( $input ) : array {
		$input = is_array( $input ) ? $input : array();

		$out = self::get_settings();

		if ( isset( $input['packages_base_dir'] ) ) {
			$dir = wp_unslash( (string) $input['packages_base_dir'] );
			$dir = trim( $dir );
			// Keep as filesystem path; normalize slashes.
			$dir = wp_normalize_path( $dir );
			$out['packages_base_dir'] = $dir;
		}

		if ( isset( $input['preview_base_slug'] ) ) {
			$slug = wp_unslash( (string) $input['preview_base_slug'] );
			$slug = sanitize_title( $slug );
			$out['preview_base_slug'] = $slug ? $slug : 'demo';
		}

		return $out;
	}

	public static function field_packages_base_dir() : void {
		$s = self::get_settings();
		printf(
			'<input type="text" class="regular-text" name="hmps_settings[packages_base_dir]" value="%s" placeholder="%s" />',
			esc_attr( $s['packages_base_dir'] ),
			esc_attr__( 'e.g. /var/www/.../wp-content/uploads/hmps-packages', 'hm-pro-showcase' )
		);
		echo '<p class="description">' . esc_html__( 'Filesystem directory where demo packages live (each package is a folder).', 'hm-pro-showcase' ) . '</p>';
	}

	public static function field_preview_base_slug() : void {
		$s = self::get_settings();
		printf(
			'<input type="text" class="regular-text" name="hmps_settings[preview_base_slug]" value="%s" placeholder="demo" />',
			esc_attr( $s['preview_base_slug'] )
		);
		echo '<p class="description">' . esc_html__( 'Base URL slug for demo preview routes. Example: /demo/{package-slug}/', 'hm-pro-showcase' ) . '</p>';
	}

	/**
	 * Pages
	 */
	public static function render_dashboard() : void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Access denied.', 'hm-pro-showcase' ) );
		}

		$s    = self::get_settings();
		$repo = new HMPS_Package_Repository( $s['packages_base_dir'] );
		$list = $repo->list_packages();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'HM Pro Showcase', 'hm-pro-showcase' ) . '</h1>';
		echo '<p>' . esc_html__( 'Plugin is active. Package reading is enabled in this commit.', 'hm-pro-showcase' ) . '</p>';

		echo '<h2 style="margin-top:16px;">' . esc_html__( 'Detected Packages', 'hm-pro-showcase' ) . '</h2>';

		if ( empty( $list ) ) {
			echo '<p>' . esc_html__( 'No packages found yet. Create a folder under the Packages base directory with a demo.json file.', 'hm-pro-showcase' ) . '</p>';
		} else {
			echo '<table class="widefat striped" style="max-width: 1100px;">';
			echo '<thead><tr>';
			echo '<th style="width:180px;">' . esc_html__( 'Slug', 'hm-pro-showcase' ) . '</th>';
			echo '<th>' . esc_html__( 'Title', 'hm-pro-showcase' ) . '</th>';
			echo '<th style="width:220px;">' . esc_html__( 'Categories', 'hm-pro-showcase' ) . '</th>';
			echo '<th style="width:220px;">' . esc_html__( 'Front page slug', 'hm-pro-showcase' ) . '</th>';
			echo '<th style="width:120px;">' . esc_html__( 'Cover', 'hm-pro-showcase' ) . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';

			foreach ( $list as $pkg ) {
				$cats       = isset( $pkg['categories'] ) && is_array( $pkg['categories'] ) ? implode( ', ', $pkg['categories'] ) : '';
				$front      = (string) ( $pkg['front_page_slug'] ?? '' );
				$cover_url  = (string) ( $pkg['cover']['url'] ?? '' );
				$cover_file = (string) ( $pkg['cover']['file'] ?? '' );

				echo '<tr>';
				echo '<td><code>' . esc_html( (string) ( $pkg['slug'] ?? '' ) ) . '</code></td>';
				echo '<td>' . esc_html( (string) ( $pkg['title'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( $cats ) . '</td>';
				echo '<td><code>' . esc_html( $front ) . '</code></td>';
				echo '<td>';
				if ( $cover_url ) {
					echo '<img src="' . esc_url( $cover_url ) . '" alt="" style="width:72px;height:auto;border-radius:6px;display:block;" />';
				} elseif ( $cover_file ) {
					echo '<code>' . esc_html( $cover_file ) . '</code>';
				} else {
					echo '<span style="opacity:.7;">' . esc_html__( 'none', 'hm-pro-showcase' ) . '</span>';
				}
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody>';
			echo '</table>';
		}

		echo '</div>';
	}

	public static function render_categories() : void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Access denied.', 'hm-pro-showcase' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Categories', 'hm-pro-showcase' ) . '</h1>';
		echo '<p>' . esc_html__( 'Category dictionary manager will be implemented in Commit 3.', 'hm-pro-showcase' ) . '</p>';
		echo '</div>';
	}

	public static function render_cache() : void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Access denied.', 'hm-pro-showcase' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Cache', 'hm-pro-showcase' ) . '</h1>';
		echo '<p>' . esc_html__( 'Cache controls will be implemented in a later commit.', 'hm-pro-showcase' ) . '</p>';
		echo '</div>';
	}

	public static function render_diagnostics() : void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Access denied.', 'hm-pro-showcase' ) );
		}

		$s = self::get_settings();
		$dir = $s['packages_base_dir'];
		$exists = is_dir( $dir );
		$writable = $exists ? is_writable( $dir ) : false;

		// Package quick stats.
		$repo  = new HMPS_Package_Repository( $dir );
		$list  = $repo->list_packages();
		$count = is_array( $list ) ? count( $list ) : 0;
		$base_url = '';
		if ( method_exists( $repo, '__construct' ) ) {
			// base_url is internal; we can infer from first cover url if present.
			foreach ( $list as $pkg ) {
				if ( ! empty( $pkg['cover']['url'] ) ) {
					$base_url = preg_replace( '#/[^/]+/[^/]+$#', '', (string) $pkg['cover']['url'] );
					break;
				}
			}
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Diagnostics', 'hm-pro-showcase' ) . '</h1>';

		echo '<table class="widefat striped" style="max-width: 900px;">';
		echo '<tbody>';
		echo '<tr><th style="width:260px;">' . esc_html__( 'Plugin version', 'hm-pro-showcase' ) . '</th><td>' . esc_html( HMPS_VERSION ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Packages base directory', 'hm-pro-showcase' ) . '</th><td><code>' . esc_html( $dir ) . '</code></td></tr>';
		echo '<tr><th>' . esc_html__( 'Directory exists', 'hm-pro-showcase' ) . '</th><td>' . esc_html( $exists ? 'yes' : 'no' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Directory writable', 'hm-pro-showcase' ) . '</th><td>' . esc_html( $writable ? 'yes' : 'no' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Packages detected', 'hm-pro-showcase' ) . '</th><td>' . esc_html( (string) $count ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Preview base slug', 'hm-pro-showcase' ) . '</th><td><code>' . esc_html( $s['preview_base_slug'] ) . '</code></td></tr>';
		echo '</tbody>';
		echo '</table>';

		echo '<p style="margin-top:12px;">' . esc_html__( 'Rewrite/router checks will appear after Commit 5.', 'hm-pro-showcase' ) . '</p>';
		echo '</div>';
	}

	public static function render_settings() : void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Access denied.', 'hm-pro-showcase' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Settings', 'hm-pro-showcase' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'hmps_settings_group' );
		do_settings_sections( 'hmps_settings' );
		submit_button();
		echo '</form>';
		echo '</div>';
	}
}
