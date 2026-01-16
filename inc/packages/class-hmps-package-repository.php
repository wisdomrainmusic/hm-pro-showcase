<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once HMPS_PLUGIN_DIR . 'inc/packages/schema.php';

/**
 * Reads demo packages from filesystem.
 *
 * Each package is a folder inside settings['packages_base_dir'].
 * Expected file: demo.json
 * Optional: cover.jpg|jpeg|png|webp (or demo.json "cover")
 */
final class HMPS_Package_Repository {
	/** @var string */
	private $base_dir;

	/** @var string */
	private $base_url;

	public function __construct( string $base_dir ) {
		$this->base_dir = wp_normalize_path( untrailingslashit( $base_dir ) );

		$uploads = wp_upload_dir();
		// If base_dir is inside uploads basedir, create URL by replacing prefix.
		$this->base_url = '';
		if ( isset( $uploads['basedir'], $uploads['baseurl'] ) ) {
			$basedir = wp_normalize_path( untrailingslashit( (string) $uploads['basedir'] ) );
			$baseurl = untrailingslashit( (string) $uploads['baseurl'] );

			if ( 0 === strpos( $this->base_dir, $basedir ) ) {
				$rel            = ltrim( substr( $this->base_dir, strlen( $basedir ) ), '/' );
				$this->base_url = $baseurl . ( $rel ? '/' . $rel : '' );
			}
		}
	}

	/**
	 * List packages found in base_dir.
	 *
	 * @return array<int, array>
	 */
	public function list_packages() : array {
		if ( ! is_dir( $this->base_dir ) ) {
			return array();
		}

		$items = glob( $this->base_dir . '/*', GLOB_ONLYDIR );
		if ( ! is_array( $items ) ) {
			return array();
		}

		$packages = array();
		foreach ( $items as $dir ) {
			$pkg = $this->read_package_from_dir( (string) $dir );
			if ( $pkg ) {
				$packages[] = $pkg;
			}
		}

		usort(
			$packages,
			static function( $a, $b ) {
				return strcmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) );
			}
		);

		return $packages;
	}

	/**
	 * Get a single package by slug.
	 *
	 * @param string $slug
	 * @return array|null
	 */
	public function get_package( string $slug ) {
		$slug = sanitize_title( $slug );
		if ( ! $slug ) {
			return null;
		}

		$dir = $this->base_dir . '/' . $slug;
		if ( ! is_dir( $dir ) ) {
			return null;
		}

		return $this->read_package_from_dir( $dir );
	}

	/**
	 * Read demo.json and normalize.
	 *
	 * @param string $dir
	 * @return array|null
	 */
	private function read_package_from_dir( string $dir ) {
		$dir = wp_normalize_path( untrailingslashit( $dir ) );
		if ( ! is_dir( $dir ) ) {
			return null;
		}

		$slug = basename( $dir );
		$slug = sanitize_title( $slug );
		if ( ! $slug ) {
			return null;
		}

		$demo_json = $dir . '/demo.json';
		if ( ! file_exists( $demo_json ) ) {
			// Skip dirs without demo.json
			return null;
		}

		$raw_json = file_get_contents( $demo_json );
		if ( false === $raw_json ) {
			return null;
		}

		$data = json_decode( $raw_json, true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		$title       = isset( $data['title'] ) ? (string) $data['title'] : $slug;
		$description = isset( $data['description'] ) ? (string) $data['description'] : '';

		$categories = array();
		if ( isset( $data['categories'] ) && is_array( $data['categories'] ) ) {
			$categories = $data['categories'];
		} elseif ( isset( $data['category'] ) ) {
			// Allow legacy single category.
			$categories = array( $data['category'] );
		}

		$front_page_slug = '';
		if ( isset( $data['front_page_slug'] ) ) {
			$front_page_slug = (string) $data['front_page_slug'];
		} elseif ( isset( $data['front_page'] ) ) {
			$front_page_slug = (string) $data['front_page'];
		}

		$cover_file = '';
		if ( isset( $data['cover'] ) && is_string( $data['cover'] ) ) {
			$cover_file = (string) $data['cover'];
		}

		$cover = $this->resolve_cover( $dir, $cover_file );

		$pkg = array(
			'slug'            => $slug,
			'title'           => $title,
			'description'     => $description,
			'categories'      => $categories,
			'front_page_slug' => $front_page_slug,
			'cover'           => $cover,
			'paths'           => array(
				'dir'        => $dir,
				'demo_json'  => $demo_json,
				'cover_file' => $cover['file'] ? ( $dir . '/' . $cover['file'] ) : '',
			),
			'meta'            => isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : array(),
		);

		return HMPS_Package_Schema::normalize( $pkg );
	}

	/**
	 * Resolve cover image file and URL.
	 *
	 * If $cover_from_json is provided and exists, use it.
	 * Otherwise try cover.(jpg|jpeg|png|webp) or cover-1 etc? (not yet)
	 *
	 * @param string $dir
	 * @param string $cover_from_json
	 * @return array{file:string,url:string}
	 */
	public function resolve_cover( string $dir, string $cover_from_json = '' ) : array {
		$dir = wp_normalize_path( untrailingslashit( $dir ) );

		$candidates      = array();
		$cover_from_json = trim( $cover_from_json );
		if ( $cover_from_json !== '' ) {
			// If they provided "cover.jpg" etc.
			$candidates[] = ltrim( $cover_from_json, '/\\' );
		}

		// Default autodetect names.
		$candidates = array_merge(
			$candidates,
			array(
				'cover.jpg',
				'cover.jpeg',
				'cover.png',
				'cover.webp',
			)
		);

		$found_file = '';
		foreach ( $candidates as $rel ) {
			$abs = $dir . '/' . $rel;
			if ( file_exists( $abs ) && is_file( $abs ) ) {
				$found_file = $rel;
				break;
			}
		}

		$url = '';
		if ( $found_file && $this->base_url ) {
			// Build url: base_url/{slug}/{file}
			$slug = basename( $dir );
			$url  = $this->base_url . '/' . rawurlencode( $slug ) . '/' . rawurlencode( $found_file );
		}

		return array(
			'file' => $found_file,
			'url'  => $url,
		);
	}
}
