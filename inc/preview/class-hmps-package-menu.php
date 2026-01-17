<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HMPS_Package_Menu {
	/**
	 * Boot: override theme menu output in preview mode.
	 */
	public static function boot() : void {
		add_filter( 'wp_nav_menu_items', array( __CLASS__, 'override_menu_html' ), 5, 2 );
	}

	/**
	 * Override theme menu HTML with package menu (menus.json) while in demo preview.
	 */
	public static function override_menu_html( $items_html, $args ) {
		if ( ! HMPS_Preview_Context::is_preview() ) {
			return $items_html;
		}

		// Only override "primary-like" menus. (Theme-agnostic heuristic)
		$loc        = isset( $args->theme_location ) ? (string) $args->theme_location : '';
		$is_primary = in_array( $loc, array( 'primary', 'main', 'menu-1', 'header', 'top', 'primary-menu' ), true );

		// If theme doesn't set location, still try (many themes do). We keep it permissive.
		if ( $loc && ! $is_primary ) {
			return $items_html;
		}

		$package_dir = HMPS_Virtual_Pages::package_dir_from_globals();
		$demo_slug   = HMPS_Preview_Context::demo_slug();
		if ( ! $package_dir || ! $demo_slug ) {
			return $items_html;
		}

		$menu_items = self::load_menu_items( $package_dir );
		if ( empty( $menu_items ) ) {
			// Fallback: build from pages.json
			$menu_items = self::fallback_from_pages( $package_dir, $demo_slug );
		}

		if ( empty( $menu_items ) ) {
			return $items_html;
		}

		$html = '';
		foreach ( $menu_items as $it ) {
			$title = isset( $it['title'] ) ? (string) $it['title'] : '';
			$path  = isset( $it['path'] ) ? (string) $it['path'] : '';
			if ( $title === '' || $path === '' ) {
				continue;
			}

			$url   = self::to_demo_url( $demo_slug, $path );
			$html .= '<li class="menu-item hmps-package-menu-item"><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></li>';
		}

		return $html ? $html : $items_html;
	}

	/**
	 * Expected menus.json (flexible):
	 * {
	 *   "primary": [ {"title":"Ana Sayfa","path":"/"}, {"title":"İletişim","path":"iletisim"} ],
	 *   "header":  [ ... ]
	 * }
	 * OR
	 * [ {"title":"Ana Sayfa","path":"/"}, ... ]
	 */
	public static function load_menu_items( string $package_dir ) : array {
		$file = trailingslashit( $package_dir ) . 'menus.json';
		if ( ! file_exists( $file ) ) {
			return array();
		}

		$raw = file_get_contents( $file );
		if ( ! $raw ) {
			return array();
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		// If associative (has 'primary' or 'header'), pick primary first.
		if ( self::is_assoc( $data ) ) {
			if ( isset( $data['primary'] ) && is_array( $data['primary'] ) ) {
				return self::normalize_items( $data['primary'] );
			}
			if ( isset( $data['header'] ) && is_array( $data['header'] ) ) {
				return self::normalize_items( $data['header'] );
			}
			if ( isset( $data['menu'] ) && is_array( $data['menu'] ) ) {
				return self::normalize_items( $data['menu'] );
			}
			// If unknown key structure, try first array value.
			foreach ( $data as $value ) {
				if ( is_array( $value ) ) {
					return self::normalize_items( $value );
				}
			}
			return array();
		}

		// Numeric array
		return self::normalize_items( $data );
	}

	private static function normalize_items( array $items ) : array {
		$out = array();
		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$title = isset( $it['title'] ) ? (string) $it['title'] : ( isset( $it['label'] ) ? (string) $it['label'] : '' );
			$path  = isset( $it['path'] ) ? (string) $it['path'] : ( isset( $it['slug'] ) ? (string) $it['slug'] : '' );
			if ( $title === '' || $path === '' ) {
				continue;
			}
			$out[] = array(
				'title' => $title,
				'path'  => $path,
			);
		}
		return $out;
	}

	private static function fallback_from_pages( string $package_dir, string $demo_slug ) : array {
		$pages = HMPS_Virtual_Pages::load_pages_map( $package_dir );
		if ( empty( $pages ) ) {
			return array();
		}

		$out = array();
		$max = 12;
		foreach ( $pages as $slug => $page ) {
			$title = ! empty( $page['title'] ) ? (string) $page['title'] : $slug;
			$out[] = array(
				'title' => $title,
				'path'  => $slug,
			);
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return $out;
	}

	private static function to_demo_url( string $demo_slug, string $path ) : string {
		$path = trim( $path );
		if ( $path === '' || $path === '/' ) {
			return HMPS_Preview_Context::demo_base_url();
		}
		$path = ltrim( $path, '/' );
		return home_url( '/' . HMPS_Preview_Context::preview_base() . '/' . $demo_slug . '/' . $path . '/' );
	}

	private static function is_assoc( array $arr ) : bool {
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}
}
