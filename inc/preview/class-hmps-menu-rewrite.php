<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HMPS_Menu_Rewrite {
	public static function boot() : void {
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'rewrite_menu_items' ), 20, 2 );
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
}
