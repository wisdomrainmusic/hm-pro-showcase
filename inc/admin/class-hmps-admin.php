<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HMPS_Admin {
	const CAPABILITY = 'manage_options';
	const OPT_CATEGORY_DICT = 'hmps_category_dict';

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

		// Fill base dir if empty OR invalid.
		$dir = isset( $merged['packages_base_dir'] ) ? (string) $merged['packages_base_dir'] : '';
		$dir = wp_normalize_path( trim( $dir ) );

		if ( empty( $dir ) || ! is_dir( $dir ) ) {
			$uploads = wp_upload_dir();
			$base    = isset( $uploads['basedir'] ) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
			$merged['packages_base_dir'] = trailingslashit( wp_normalize_path( $base ) ) . 'hmpro-demo-packages';
		} else {
			$merged['packages_base_dir'] = $dir;
		}

		// Ensure directory exists (safe no-op if already exists).
		if ( ! is_dir( $merged['packages_base_dir'] ) ) {
			wp_mkdir_p( $merged['packages_base_dir'] );
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

		// Ensure directory exists on save.
		if ( ! empty( $out['packages_base_dir'] ) && ! is_dir( $out['packages_base_dir'] ) ) {
			wp_mkdir_p( $out['packages_base_dir'] );
		}

		if ( isset( $input['preview_base_slug'] ) ) {
			$slug = wp_unslash( (string) $input['preview_base_slug'] );
			$slug = sanitize_title( $slug );
			$out['preview_base_slug'] = $slug ? $slug : 'demo';
		}

		return $out;
	}

	/**
	 * CATEGORY DICTIONARY
	 *
	 * Stored as array keyed by slug:
	 * [
	 *   'kadin-giyim' => ['slug'=>'kadin-giyim','label'=>'Women Fashion','description'=>'...','order'=>10,'enabled'=>1],
	 * ]
	 */
	public static function get_category_dict() : array {
		$dict = get_option( self::OPT_CATEGORY_DICT, array() );
		if ( ! is_array( $dict ) ) {
			$dict = array();
		}

		// Normalize.
		$out = array();
		foreach ( $dict as $slug => $row ) {
			if ( is_array( $row ) ) {
				$row_slug = isset( $row['slug'] ) ? (string) $row['slug'] : (string) $slug;
				$row_slug = sanitize_title( $row_slug );
				if ( ! $row_slug ) {
					continue;
				}
				$out[ $row_slug ] = array(
					'slug'        => $row_slug,
					'label'       => isset( $row['label'] ) ? (string) $row['label'] : $row_slug,
					'description' => isset( $row['description'] ) ? (string) $row['description'] : '',
					'order'       => isset( $row['order'] ) ? (int) $row['order'] : 0,
					'enabled'     => isset( $row['enabled'] ) ? (int) (bool) $row['enabled'] : 1,
				);
			}
		}

		// Sort by order then label.
		uasort(
			$out,
			static function( $a, $b ) {
				$ao = (int) ( $a['order'] ?? 0 );
				$bo = (int) ( $b['order'] ?? 0 );
				if ( $ao === $bo ) {
					return strcmp( (string) ( $a['label'] ?? '' ), (string) ( $b['label'] ?? '' ) );
				}
				return ( $ao < $bo ) ? -1 : 1;
			}
		);

		return $out;
	}

	private static function update_category_dict( array $dict ) : void {
		update_option( self::OPT_CATEGORY_DICT, $dict, false );
	}

	private static function category_label_default( string $slug ) : string {
		$slug = sanitize_title( $slug );
		if ( ! $slug ) {
			return '';
		}
		$label = str_replace( array( '-', '_' ), ' ', $slug );
		$label = ucwords( $label );
		return $label;
	}

	/**
	 * Handle POST actions for Categories screen.
	 */
	private static function handle_categories_post() : void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		if ( empty( $_POST['hmps_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		check_admin_referer( 'hmps_categories_action', 'hmps_nonce' );

		$action = sanitize_key( (string) wp_unslash( $_POST['hmps_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( 'save' === $action ) {
			$rows = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? $_POST['rows'] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$new  = array();

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$slug = isset( $row['slug'] ) ? sanitize_title( (string) wp_unslash( $row['slug'] ) ) : '';
				if ( ! $slug ) {
					continue;
				}
				$label = isset( $row['label'] ) ? sanitize_text_field( (string) wp_unslash( $row['label'] ) ) : self::category_label_default( $slug );
				$desc  = isset( $row['description'] ) ? sanitize_text_field( (string) wp_unslash( $row['description'] ) ) : '';
				$order = isset( $row['order'] ) ? (int) $row['order'] : 0;
				$en    = isset( $row['enabled'] ) ? 1 : 0;

				$new[ $slug ] = array(
					'slug'        => $slug,
					'label'       => $label,
					'description' => $desc,
					'order'       => $order,
					'enabled'     => $en,
				);
			}

			self::update_category_dict( $new );
			add_settings_error( 'hmps_categories', 'hmps_saved', __( 'Categories saved.', 'hm-pro-showcase' ), 'updated' );
			return;
		}

		if ( 'discover' === $action ) {
			$s    = self::get_settings();
			$repo = new HMPS_Package_Repository( $s['packages_base_dir'] );
			$list = $repo->list_packages();

			$dict  = self::get_category_dict();
			$added = 0;

			foreach ( $list as $pkg ) {
				$cats = isset( $pkg['categories'] ) && is_array( $pkg['categories'] ) ? $pkg['categories'] : array();
				foreach ( $cats as $c ) {
					$slug = sanitize_title( (string) $c );
					if ( ! $slug ) {
						continue;
					}
					if ( isset( $dict[ $slug ] ) ) {
						continue;
					}
					$dict[ $slug ] = array(
						'slug'        => $slug,
						'label'       => self::category_label_default( $slug ),
						'description' => '',
						'order'       => 0,
						'enabled'     => 1,
					);
					$added++;
				}
			}

			// Save unsorted; getter sorts for display.
			self::update_category_dict( $dict );
			add_settings_error(
				'hmps_categories',
				'hmps_discovered',
				sprintf(
					/* translators: %d number of categories */
					__( 'Discovery complete. Added %d new categories.', 'hm-pro-showcase' ),
					(int) $added
				),
				'updated'
			);
			return;
		}
	}

	public static function field_packages_base_dir() : void {
		$s = self::get_settings();
		printf(
			'<input type="text" class="regular-text" name="hmps_settings[packages_base_dir]" value="%s" placeholder="%s" />',
			esc_attr( $s['packages_base_dir'] ),
			esc_attr__( 'e.g. /var/www/.../wp-content/uploads/hmpro-demo-packages', 'hm-pro-showcase' )
		);
		echo '<p class="description">' . esc_html__( 'Single filesystem directory where demo packages live (each package is a folder with demo.json). This directory is used for BOTH showcase listing and demo preview.', 'hm-pro-showcase' ) . '</p>';
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

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			self::handle_categories_post();
		}

		$dict = self::get_category_dict();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Categories', 'hm-pro-showcase' ) . '</h1>';

		settings_errors( 'hmps_categories' );

		echo '<p>' . esc_html__( 'Manage category dictionary used by the Showcase UI. You can discover missing categories from installed packages.', 'hm-pro-showcase' ) . '</p>';

		// Action buttons.
		echo '<form method="post" style="margin: 12px 0 16px 0;">';
		wp_nonce_field( 'hmps_categories_action', 'hmps_nonce' );
		echo '<input type="hidden" name="hmps_action" value="discover" />';
		submit_button( __( 'Discover from packages', 'hm-pro-showcase' ), 'secondary', 'submit', false );
		echo '</form>';

		// Table editor.
		echo '<form method="post">';
		wp_nonce_field( 'hmps_categories_action', 'hmps_nonce' );
		echo '<input type="hidden" name="hmps_action" value="save" />';

		echo '<table class="widefat striped" style="max-width: 1200px;">';
		echo '<thead><tr>';
		echo '<th style="width:180px;">' . esc_html__( 'Slug', 'hm-pro-showcase' ) . '</th>';
		echo '<th style="width:260px;">' . esc_html__( 'Label', 'hm-pro-showcase' ) . '</th>';
		echo '<th>' . esc_html__( 'Description', 'hm-pro-showcase' ) . '</th>';
		echo '<th style="width:110px;">' . esc_html__( 'Order', 'hm-pro-showcase' ) . '</th>';
		echo '<th style="width:110px;">' . esc_html__( 'Enabled', 'hm-pro-showcase' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody id="hmps-cat-rows">';

		$i = 0;
		foreach ( $dict as $slug => $row ) {
			$label = (string) ( $row['label'] ?? '' );
			$desc  = (string) ( $row['description'] ?? '' );
			$order = (int) ( $row['order'] ?? 0 );
			$en    = (int) ( $row['enabled'] ?? 1 );

			echo '<tr>';
			echo '<td><input type="text" name="rows[' . esc_attr( (string) $i ) . '][slug]" value="' . esc_attr( $slug ) . '" class="regular-text" /></td>';
			echo '<td><input type="text" name="rows[' . esc_attr( (string) $i ) . '][label]" value="' . esc_attr( $label ) . '" class="regular-text" /></td>';
			echo '<td><input type="text" name="rows[' . esc_attr( (string) $i ) . '][description]" value="' . esc_attr( $desc ) . '" class="regular-text" style="width:100%;" /></td>';
			echo '<td><input type="number" name="rows[' . esc_attr( (string) $i ) . '][order]" value="' . esc_attr( (string) $order ) . '" class="small-text" /></td>';
			echo '<td><label><input type="checkbox" name="rows[' . esc_attr( (string) $i ) . '][enabled]" value="1" ' . checked( 1, $en, false ) . ' /> ' . esc_html__( 'On', 'hm-pro-showcase' ) . '</label></td>';
			echo '</tr>';
			$i++;
		}

		// New row template (empty).
		echo '<tr>';
		echo '<td><input type="text" name="rows[' . esc_attr( (string) $i ) . '][slug]" value="" class="regular-text" placeholder="new-category" /></td>';
		echo '<td><input type="text" name="rows[' . esc_attr( (string) $i ) . '][label]" value="" class="regular-text" placeholder="New Category" /></td>';
		echo '<td><input type="text" name="rows[' . esc_attr( (string) $i ) . '][description]" value="" class="regular-text" style="width:100%;" placeholder="" /></td>';
		echo '<td><input type="number" name="rows[' . esc_attr( (string) $i ) . '][order]" value="0" class="small-text" /></td>';
		echo '<td><label><input type="checkbox" name="rows[' . esc_attr( (string) $i ) . '][enabled]" value="1" checked /> ' . esc_html__( 'On', 'hm-pro-showcase' ) . '</label></td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';

		echo '<p style="margin-top:12px;">';
		echo esc_html__( 'Tip: Click "Discover from packages" to auto-add missing category slugs found in demo.json files.', 'hm-pro-showcase' );
		echo '</p>';

		submit_button( __( 'Save Categories', 'hm-pro-showcase' ) );
		echo '</form>';

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
		$dir = (string) $s['packages_base_dir'];
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

		// Rewrite diagnostics (Commit 5+).
		$rewrite_rules = get_option( 'rewrite_rules', array() );
		$preview_base  = sanitize_title( (string) $s['preview_base_slug'] );
		if ( ! $preview_base ) {
			$preview_base = 'demo';
		}
		$pattern = '^' . $preview_base . '/([^/]+)/?(.*)?$';
		$rewrite_ok = is_array( $rewrite_rules ) && array_key_exists( $pattern, $rewrite_rules );

		$sample_demo = '';
		if ( ! empty( $list ) && ! empty( $list[0]['slug'] ) ) {
			$sample_demo = sanitize_title( (string) $list[0]['slug'] );
		}
		$sample_url = $sample_demo ? home_url( '/' . $preview_base . '/' . $sample_demo . '/' ) : '';

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
		echo '<tr><th>' . esc_html__( 'Preview rewrite active', 'hm-pro-showcase' ) . '</th><td>' . esc_html( $rewrite_ok ? 'yes' : 'no' ) . '</td></tr>';
		if ( $sample_url ) {
			echo '<tr><th>' . esc_html__( 'Sample preview URL', 'hm-pro-showcase' ) . '</th><td><a href="' . esc_url( $sample_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $sample_url ) . '</a></td></tr>';
		}
		echo '</tbody>';
		echo '</table>';

		echo '<p style="margin-top:12px;">' . esc_html__( 'If rewrite is not active, re-save Permalinks or deactivate/activate the plugin to flush rules.', 'hm-pro-showcase' ) . '</p>';
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
