<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HMPS_Menu_Rewrite {
	public static function boot() : void {
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'rewrite_menu_items' ), 20, 2 );
		add_filter( 'wp_nav_menu_items', array( __CLASS__, 'append_demo_menu_fallback' ), 30, 2 );
	}

	public static function rewrite_menu_items( $items, $args ) {
		if ( ! HMPS_Preview_Context::is_preview() || ! is_array( $items ) ) {
			return $items;
		}

		foreach ( $items as $item ) {
			if ( isset( $item->url ) && is_string( $item->url ) ) {
				$item->url = HMPS_Preview_Context::rewrite_url_to_demo( $item->url );
			}
		}
		return $items;
	}

	// Eğer tema menüsü demo page'leri göstermiyorsa, demo içinde minimum gezilebilir menü ekler.
	public static function append_demo_menu_fallback( $items_html, $args ) {
		if ( ! HMPS_Preview_Context::is_preview() ) {
			return $items_html;
		}

		// Sadece primary menüde ekleyelim (tema farkına göre değişir; geniş uyumluluk için her menüde değil)
		// İstersen burada $args->theme_location kontrolünü açarız.
		$demo_slug = HMPS_Preview_Context::demo_slug();
		if ( ! $demo_slug ) {
			return $items_html;
		}

		$pages = HMPS_Virtual_Pages::load_pages_map( HMPS_Virtual_Pages::package_dir_from_globals() );
		if ( empty( $pages ) ) {
			return $items_html;
		}

		// İlk 6 sayfayı menüye koy (ana-sayfa, iletisim, hakkimizda gibi)
		$max   = 6;
		$count = 0;
		$html  = '';

		foreach ( $pages as $slug => $p ) {
			$url   = home_url( '/' . HMPS_Preview_Context::preview_base() . '/' . $demo_slug . '/' . $slug . '/' );
			$title = ! empty( $p['title'] ) ? $p['title'] : $slug;

			$html .= '<li class="menu-item hmps-demo-item"><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></li>';
			$count++;
			if ( $count >= $max ) {
				break;
			}
		}

		if ( $html ) {
			$items_html .= $html;
		}

		return $items_html;
	}
}
