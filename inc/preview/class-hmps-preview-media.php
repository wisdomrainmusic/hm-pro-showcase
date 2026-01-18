<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serve packaged media files from a demo package.
 *
 * Expected request path: /<preview_base>/<demo>/media/<rel_path>
 *
 * Files live in: <package_dir>/media/<rel_path>
 */
final class HMPS_Preview_Media {
	public static function serve( string $package_dir, string $rel_path ) : void {
		$package_dir = wp_normalize_path( $package_dir );
		$rel_path    = ltrim( str_replace( array( '\\', "\0" ), '/', $rel_path ), '/' );
		if ( ! $rel_path || false !== strpos( $rel_path, '..' ) ) {
			status_header( 400 );
			nocache_headers();
			echo 'Bad media path.';
			exit;
		}

		$file = wp_normalize_path( trailingslashit( $package_dir ) . 'media/' . $rel_path );
		if ( ! file_exists( $file ) || ! is_file( $file ) ) {
			status_header( 404 );
			nocache_headers();
			echo 'Media not found.';
			exit;
		}

		$mime = function_exists( 'mime_content_type' ) ? (string) @mime_content_type( $file ) : '';
		if ( ! $mime ) {
			$mime = 'application/octet-stream';
		}

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . (string) filesize( $file ) );
		readfile( $file );
		exit;
	}
}
