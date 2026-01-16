<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HMPS_Virtual_Pages {
	/**
	 * Load pages.json from a package directory and return a map keyed by slug.
	 *
	 * Supports multiple key styles because exports may vary:
	 * - slug / post_name / name
	 * - title / post_title
	 * - content / post_content / html
	 */
	public static function load_pages_map( string $package_dir ) : array {
		$package_dir = wp_normalize_path( $package_dir );
		$file        = trailingslashit( $package_dir ) . 'pages.json';
		if ( ! file_exists( $file ) ) {
			return array();
		}

		$json = file_get_contents( $file );
		if ( ! $json ) {
			return array();
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		// Some exports may wrap as { pages: [...] }
		$pages = $data;
		if ( isset( $data['pages'] ) && is_array( $data['pages'] ) ) {
			$pages = $data['pages'];
		}

		$map = array();
		foreach ( $pages as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$slug = '';
			if ( isset( $p['slug'] ) ) {
				$slug = (string) $p['slug'];
			} elseif ( isset( $p['post_name'] ) ) {
				$slug = (string) $p['post_name'];
			} elseif ( isset( $p['name'] ) ) {
				$slug = (string) $p['name'];
			}
			$slug = sanitize_title( $slug );
			if ( ! $slug ) {
				continue;
			}

			$title = '';
			if ( isset( $p['title'] ) ) {
				$title = (string) $p['title'];
			} elseif ( isset( $p['post_title'] ) ) {
				$title = (string) $p['post_title'];
			}

			$content = '';
			if ( isset( $p['content'] ) ) {
				$content = (string) $p['content'];
			} elseif ( isset( $p['post_content'] ) ) {
				$content = (string) $p['post_content'];
			} elseif ( isset( $p['html'] ) ) {
				$content = (string) $p['html'];
			}

			$map[ $slug ] = array(
				'slug'    => $slug,
				'title'   => $title,
				'content' => $content,
			);
		}

		return $map;
	}

	/**
	 * Render a virtual page from pages.json.
	 */
	public static function render_page_html( string $package_dir, string $page_slug ) : array {
		$page_slug = sanitize_title( $page_slug );
		$map       = self::load_pages_map( $package_dir );

		if ( empty( $map[ $page_slug ] ) ) {
			return array(
				'found'   => false,
				'title'   => '',
				'content' => '',
			);
		}

		$title   = (string) ( $map[ $page_slug ]['title'] ?? '' );
		$content = (string) ( $map[ $page_slug ]['content'] ?? '' );

		// Let WP format shortcodes/content like normal pages.
		// (We will add internal link rewriting in the next commit.)
		$content = do_shortcode( $content );
		$content = apply_filters( 'the_content', $content );

		return array(
			'found'   => true,
			'title'   => $title,
			'content' => $content,
		);
	}
}
