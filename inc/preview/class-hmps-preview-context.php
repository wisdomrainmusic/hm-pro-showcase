<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HMPS_Preview_Context {
	public static function is_preview() : bool {
		return (bool) get_query_var( 'hmps_demo' );
	}

	public static function demo_slug() : string {
		return sanitize_title( (string) get_query_var( 'hmps_demo' ) );
	}

	public static function preview_base() : string {
		$opt  = get_option( 'hmps_settings', array() );
		$slug = isset( $opt['preview_base_slug'] ) ? (string) $opt['preview_base_slug'] : 'demo';
		$slug = sanitize_title( $slug );
		return $slug ? $slug : 'demo';
	}

	public static function demo_base_url() : string {
		$demo = self::demo_slug();
		if ( ! $demo ) {
			return home_url( '/' );
		}
		return home_url( '/' . self::preview_base() . '/' . $demo . '/' );
	}

	public static function showcase_url() : string {
		$opt  = get_option( 'hmps_settings', array() );
		$path = isset( $opt['showcase_path'] ) ? (string) $opt['showcase_path'] : '/a1/';
		$path = '/' . ltrim( $path, '/' );
		return home_url( $path );
	}

	public static function rewrite_url_to_demo( string $url ) : string {
		$demo = self::demo_slug();
		if ( ! $demo ) {
			return $url;
		}

		// ignore admin/assets
		if ( preg_match( '#/(wp-admin|wp-login\.php|wp-content|wp-includes)/#i', $url ) ) {
			return $url;
		}

		$home = rtrim( home_url( '/' ), '/' );

		// already this demo
		if ( preg_match( '#/' . preg_quote( self::preview_base(), '#' ) . '/' . preg_quote( $demo, '#' ) . '/#i', $url ) ) {
			return $url;
		}

		// if it points to another demo slug, normalize to current demo.
		$base = preg_quote( self::preview_base(), '#' );
		if ( preg_match( '#/' . $base . '/([^/]+)/#i', $url, $m ) ) {
			$other = sanitize_title( (string) ( $m[1] ?? '' ) );
			if ( $other && $other !== $demo ) {
				$url = preg_replace( '#/' . $base . '/' . preg_quote( $other, '#' ) . '/#i', '/' . self::preview_base() . '/' . $demo . '/', $url, 1 );
			}
		}

		// absolute same-site -> make relative first
		if ( strpos( $url, $home ) === 0 ) {
			$url = substr( $url, strlen( $home ) );
			if ( '' === $url ) {
				$url = '/';
			}
		}

		// root-relative internal
		if ( strpos( $url, '/' ) === 0 ) {
			$u = ltrim( $url, '/' );
			return home_url( '/' . self::preview_base() . '/' . $demo . '/' . $u );
		}

		return $url;
	}
}
