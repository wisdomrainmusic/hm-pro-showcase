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

		$loc = isset( $args->theme_location ) ? (string) $args->theme_location : '';

		$package_dir = HMPS_Virtual_Pages::package_dir_from_globals();
		$demo_slug   = HMPS_Preview_Context::demo_slug();
		if ( ! $package_dir || ! $demo_slug ) {
			return $items_html;
		}

		$menu_tree = self::load_menu_tree( $package_dir, $demo_slug, $loc );
		if ( empty( $menu_tree ) ) {
			// Fallback: build a simple flat menu from pages.json.
			$menu_tree = self::fallback_from_pages( $package_dir, $demo_slug );
		}
		if ( empty( $menu_tree ) ) {
			return $items_html;
		}

		$html = self::render_tree_html( $menu_tree, $demo_slug );
		return $html ? $html : $items_html;
	}

	/**
	 * Load menu tree from exporter menus.json.
	 *
	 * Supported exporter formats:
	 *  1) Simple list: [ {title,path}, ... ]
	 *  2) Named lists: { primary:[...], header:[...], ... }
	 *  3) Full WP export: [ { slug, name, items:[{menu_item_parent,title,url,object_slug,...},...] }, ... ]
	 */
	public static function load_menu_tree( string $package_dir, string $demo_slug, string $theme_location = '' ) : array {
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

		// Exporter format #3: array of menus with {slug,items}.
		if ( isset( $data[0] ) && is_array( $data[0] ) && isset( $data[0]['items'] ) ) {
			return self::tree_from_wp_export( $data, $package_dir, $demo_slug, $theme_location );
		}

		// Format #2: associative named lists.
		if ( self::is_assoc( $data ) ) {
			$items = null;
			if ( isset( $data['primary'] ) && is_array( $data['primary'] ) ) {
				$items = $data['primary'];
			} elseif ( isset( $data['header'] ) && is_array( $data['header'] ) ) {
				$items = $data['header'];
			} elseif ( isset( $data['menu'] ) && is_array( $data['menu'] ) ) {
				$items = $data['menu'];
			} else {
				foreach ( $data as $value ) {
					if ( is_array( $value ) ) {
						$items = $value;
						break;
					}
				}
			}
			return is_array( $items ) ? self::tree_from_simple_items( $items ) : array();
		}

		// Format #1: numeric list.
		return self::tree_from_simple_items( $data );
	}

	/**
	 * Build a flat tree from simple items.
	 */
	private static function tree_from_simple_items( array $items ) : array {
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
				'id'       => 0,
				'parent'   => 0,
				'title'    => $title,
				'url'      => $path,
				'target'   => '',
				'children' => array(),
			);
		}
		return $out;
	}

	/**
	 * Build a tree from full WP menu export.
	 */
	private static function tree_from_wp_export( array $menus, string $package_dir, string $demo_slug, string $theme_location ) : array {
		$selected_menu_slug = '';
		if ( $theme_location ) {
			$selected_menu_slug = self::map_location_to_menu_slug( $package_dir, $theme_location );
		}
		if ( ! $selected_menu_slug ) {
			// Fallback: pick the first menu.
			$selected_menu_slug = isset( $menus[0]['slug'] ) ? (string) $menus[0]['slug'] : '';
		}

		$menu_items = array();
		foreach ( $menus as $menu ) {
			if ( ! is_array( $menu ) ) {
				continue;
			}
			$slug = isset( $menu['slug'] ) ? (string) $menu['slug'] : '';
			if ( $selected_menu_slug && $slug && $slug !== $selected_menu_slug ) {
				continue;
			}
			if ( isset( $menu['items'] ) && is_array( $menu['items'] ) ) {
				$menu_items = $menu['items'];
				break;
			}
		}

		if ( empty( $menu_items ) ) {
			return array();
		}

		// Normalize nodes.
		$nodes = array();
		foreach ( $menu_items as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$id     = isset( $it['ID'] ) ? (int) $it['ID'] : 0;
			$parent = isset( $it['menu_item_parent'] ) ? (int) $it['menu_item_parent'] : 0;
			$order  = isset( $it['menu_order'] ) ? (int) $it['menu_order'] : 0;
			$title  = isset( $it['title'] ) ? (string) $it['title'] : '';
			$target = isset( $it['target'] ) ? (string) $it['target'] : '';

			$url = '';
			if ( isset( $it['url'] ) ) {
				$url = (string) $it['url'];
			}
			if ( ! $url && isset( $it['object_slug'] ) ) {
				$url = (string) $it['object_slug'];
			}
			if ( $title === '' ) {
				continue;
			}

			$nodes[ $id ] = array(
				'id'       => $id,
				'parent'   => $parent,
				'order'    => $order,
				'title'    => $title,
				'url'      => $url,
				'target'   => $target,
				'children' => array(),
			);
		}

		if ( empty( $nodes ) ) {
			return array();
		}

		// Attach children.
		$tree = array();
		foreach ( $nodes as $id => $node ) {
			$parent_id = (int) $node['parent'];
			if ( $parent_id && isset( $nodes[ $parent_id ] ) ) {
				$nodes[ $parent_id ]['children'][] = $node;
				continue;
			}
			$tree[] = $node;
		}

		self::sort_tree_by_order( $tree );
		return $tree;
	}

	private static function sort_tree_by_order( array &$tree ) : void {
		usort( $tree, function( $a, $b ) {
			$oa = isset( $a['order'] ) ? (int) $a['order'] : 0;
			$ob = isset( $b['order'] ) ? (int) $b['order'] : 0;
			if ( $oa === $ob ) {
				return (int) ( $a['id'] ?? 0 ) <=> (int) ( $b['id'] ?? 0 );
			}
			return $oa <=> $ob;
		} );
		foreach ( $tree as &$node ) {
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				self::sort_tree_by_order( $node['children'] );
			}
		}
	}

	private static function map_location_to_menu_slug( string $package_dir, string $theme_location ) : string {
		$file = trailingslashit( $package_dir ) . 'menu-locations.json';
		if ( ! file_exists( $file ) ) {
			return '';
		}
		$raw = file_get_contents( $file );
		if ( ! $raw ) {
			return '';
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		// Exact match.
		if ( isset( $data[ $theme_location ] ) ) {
			return sanitize_title( (string) $data[ $theme_location ] );
		}
		// Heuristic fallbacks.
		foreach ( $data as $loc => $menu_slug ) {
			if ( ! is_string( $loc ) || ! is_string( $menu_slug ) ) {
				continue;
			}
			if ( stripos( $theme_location, (string) $loc ) !== false ) {
				return sanitize_title( (string) $menu_slug );
			}
		}
		return '';
	}

	private static function render_tree_html( array $tree, string $demo_slug ) : string {
		$html = '';
		foreach ( $tree as $node ) {
			$html .= self::render_node_html( $node, $demo_slug );
		}
		return $html;
	}

	private static function render_node_html( array $node, string $demo_slug ) : string {
		$title = isset( $node['title'] ) ? (string) $node['title'] : '';
		$url   = isset( $node['url'] ) ? (string) $node['url'] : '';
		if ( $title === '' ) {
			return '';
		}

		$link = self::normalize_menu_url( $demo_slug, $url );
		$target_attr = '';
		$target = isset( $node['target'] ) ? (string) $node['target'] : '';
		if ( $target ) {
			$target_attr = ' target="' . esc_attr( $target ) . '"';
		}

		$out = '<li class="menu-item hmps-package-menu-item">';
		$out .= '<a href="' . esc_url( $link ) . '"' . $target_attr . '>' . esc_html( $title ) . '</a>';
		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			$out .= '<ul class="sub-menu">';
			foreach ( $node['children'] as $child ) {
				if ( is_array( $child ) ) {
					$out .= self::render_node_html( $child, $demo_slug );
				}
			}
			$out .= '</ul>';
		}
		$out .= '</li>';
		return $out;
	}

	private static function normalize_menu_url( string $demo_slug, string $url ) : string {
		$url = trim( $url );
		if ( $url === '' ) {
			return '#';
		}

		// If already an absolute URL, rewrite into demo scope when internal.
		if ( preg_match( '#^https?://#i', $url ) ) {
			return HMPS_Preview_Context::rewrite_url_to_demo( $url );
		}

		// Root-relative internal path.
		if ( strpos( $url, '/' ) === 0 ) {
			return HMPS_Preview_Context::rewrite_url_to_demo( home_url( $url ) );
		}

		// Treat as slug/path.
		return self::to_demo_url( $demo_slug, $url );
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
				'id'       => 0,
				'parent'   => 0,
				'title'    => $title,
				'url'      => $slug,
				'target'   => '',
				'children' => array(),
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
