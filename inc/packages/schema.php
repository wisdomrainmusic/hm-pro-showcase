<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalized package schema helper.
 */
final class HMPS_Package_Schema {
	/**
	 * Build a normalized package array with safe defaults.
	 *
	 * @param array $raw
	 * @return array
	 */
	public static function normalize( array $raw ) : array {
		$defaults = array(
			'slug'            => '',
			'title'           => '',
			'description'     => '',
			'categories'      => array(),
			'front_page_slug' => '',
			'cover'           => array(
				'file' => '',
				'url'  => '',
			),
			'covers'          => array(),
			'paths'           => array(
				'dir'        => '',
				'demo_json'  => '',
				'cover_file' => '',
			),
			'meta'            => array(),
		);

		$out = array_merge( $defaults, $raw );

		$out['slug']            = sanitize_title( (string) $out['slug'] );
		$out['title']           = (string) $out['title'];
		$out['description']     = (string) $out['description'];
		$out['front_page_slug'] = sanitize_title( (string) $out['front_page_slug'] );

		if ( ! is_array( $out['categories'] ) ) {
			$out['categories'] = array();
		}
		$out['categories'] = array_values(
			array_filter(
				array_map(
					static function( $c ) {
						return sanitize_title( (string) $c );
					},
					$out['categories']
				)
			)
		);

		if ( ! is_array( $out['cover'] ) ) {
			$out['cover'] = $defaults['cover'];
		}
		$out['cover']['file'] = (string) ( $out['cover']['file'] ?? '' );
		$out['cover']['url']  = (string) ( $out['cover']['url'] ?? '' );

		if ( ! is_array( $out['covers'] ) ) {
			$out['covers'] = array();
		}
		$out['covers'] = array_values(
			array_filter(
				array_map(
					static function( $u ) {
						return is_string( $u ) ? trim( $u ) : '';
					},
					$out['covers']
				)
			)
		);

		return $out;
	}
}
