<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create temporary WooCommerce products + attachments from a demo package.
 *
 * This allows Elementor/Woo widgets (product tabs, grids, etc.) to work in preview,
 * because they run real WP_Query queries against the DB.
 */
final class HMPS_Preview_Products {
	/**
	 * Boot product layer for a demo.
	 */
	public static function boot( string $package_dir, string $demo_slug ) : void {
		$demo_slug   = sanitize_title( $demo_slug );
		$package_dir = wp_normalize_path( $package_dir );
		if ( ! $demo_slug || ! $package_dir ) {
			return;
		}
		// Only if WooCommerce product post type exists.
		if ( ! post_type_exists( 'product' ) ) {
			return;
		}

		self::ensure_attachments_from_media_json( $package_dir, $demo_slug );
		self::ensure_products_from_products_json( $package_dir, $demo_slug );

		// In preview, ensure product queries show only demo products.
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_product_queries_to_demo' ), 9 );
		add_filter( 'wp_get_attachment_url', array( __CLASS__, 'filter_preview_attachment_url' ), 10, 2 );
		add_filter( 'wp_get_attachment_image_src', array( __CLASS__, 'filter_preview_attachment_image_src' ), 10, 4 );
	}

	/**
	 * Limit all frontend product queries to the active demo.
	 */
	public static function filter_product_queries_to_demo( WP_Query $q ) : void {
		if ( is_admin() ) {
			return;
		}
		$demo_slug = isset( $GLOBALS['hmps_demo_slug'] ) ? sanitize_title( (string) $GLOBALS['hmps_demo_slug'] ) : '';
		if ( ! $demo_slug ) {
			return;
		}

		$post_type = $q->get( 'post_type' );
		if ( empty( $post_type ) ) {
			return;
		}
		$types = is_array( $post_type ) ? $post_type : array( $post_type );
		if ( ! in_array( 'product', $types, true ) ) {
			return;
		}

		$meta_query   = (array) $q->get( 'meta_query' );
		$meta_query[] = array(
			'key'     => '_hmps_demo_slug',
			'value'   => $demo_slug,
			'compare' => '=',
		);
		$q->set( 'meta_query', $meta_query );
	}

	public static function filter_preview_attachment_url( string $url, int $attachment_id ) : string {
		$demo_slug = isset( $GLOBALS['hmps_demo_slug'] ) ? sanitize_title( (string) $GLOBALS['hmps_demo_slug'] ) : '';
		if ( ! $demo_slug ) {
			return $url;
		}
		$att_demo = (string) get_post_meta( $attachment_id, '_hmps_demo_slug', true );
		if ( $att_demo !== $demo_slug ) {
			return $url;
		}
		$preview_url = (string) get_post_meta( $attachment_id, '_hmps_preview_url', true );
		return $preview_url ? $preview_url : $url;
	}

	public static function filter_preview_attachment_image_src( $image, int $attachment_id, $size, bool $icon ) {
		if ( empty( $image ) || ! is_array( $image ) ) {
			return $image;
		}
		$demo_slug = isset( $GLOBALS['hmps_demo_slug'] ) ? sanitize_title( (string) $GLOBALS['hmps_demo_slug'] ) : '';
		if ( ! $demo_slug ) {
			return $image;
		}
		$att_demo = (string) get_post_meta( $attachment_id, '_hmps_demo_slug', true );
		if ( $att_demo !== $demo_slug ) {
			return $image;
		}
		$preview_url = (string) get_post_meta( $attachment_id, '_hmps_preview_url', true );
		if ( $preview_url ) {
			$image[0] = $preview_url;
		}
		return $image;
	}

	private static function ensure_attachments_from_media_json( string $package_dir, string $demo_slug ) : void {
		$file = trailingslashit( $package_dir ) . 'media.json';
		if ( ! file_exists( $file ) ) {
			return;
		}
		$json = file_get_contents( $file );
		$data = $json ? json_decode( $json, true ) : null;
		if ( ! is_array( $data ) || empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return;
		}

		$opt = get_option( 'hmps_preview_attachments', array() );
		$opt = is_array( $opt ) ? $opt : array();
		if ( ! isset( $opt[ $demo_slug ] ) || ! is_array( $opt[ $demo_slug ] ) ) {
			$opt[ $demo_slug ] = array();
		}

		$base = rtrim( home_url( '/' ), '/' ) . '/' . HMPS_Preview_Context::preview_base_slug() . '/' . $demo_slug . '/media/';

		foreach ( $data['items'] as $it ) {
			$old_id = isset( $it['old_id'] ) ? (int) $it['old_id'] : 0;
			$rel    = isset( $it['rel_path'] ) ? (string) $it['rel_path'] : '';
			if ( $old_id <= 0 || ! $rel ) {
				continue;
			}
			if ( isset( $opt[ $demo_slug ][ $old_id ] ) && get_post( (int) $opt[ $demo_slug ][ $old_id ] ) ) {
				continue;
			}

			$preview_url = $base . ltrim( $rel, '/' );
			$title       = basename( $rel );

			$att_id = wp_insert_post(
				array(
					'post_type'   => 'attachment',
					'post_status' => 'inherit',
					'post_title'  => sanitize_text_field( $title ),
					'guid'        => $preview_url,
				),
				true
			);
			if ( is_wp_error( $att_id ) ) {
				continue;
			}
			$att_id = (int) $att_id;
			update_post_meta( $att_id, '_hmps_demo_slug', $demo_slug );
			update_post_meta( $att_id, '_hmps_old_attachment_id', $old_id );
			update_post_meta( $att_id, '_hmps_preview_url', $preview_url );
			// Leave _wp_attached_file empty; we serve via preview URL filters.

			$opt[ $demo_slug ][ $old_id ] = $att_id;
		}

		update_option( 'hmps_preview_attachments', $opt, false );
	}

	private static function ensure_products_from_products_json( string $package_dir, string $demo_slug ) : void {
		$file = trailingslashit( $package_dir ) . 'products.json';
		if ( ! file_exists( $file ) ) {
			return;
		}
		$json = file_get_contents( $file );
		$data = $json ? json_decode( $json, true ) : null;
		if ( ! is_array( $data ) || empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return;
		}

		$att_map = self::get_attachment_map( $demo_slug );

		$opt = get_option( 'hmps_preview_products', array() );
		$opt = is_array( $opt ) ? $opt : array();
		if ( ! isset( $opt[ $demo_slug ] ) || ! is_array( $opt[ $demo_slug ] ) ) {
			$opt[ $demo_slug ] = array();
		}

		foreach ( $data['items'] as $p ) {
			$slug = isset( $p['slug'] ) ? sanitize_title( (string) $p['slug'] ) : '';
			if ( ! $slug ) {
				continue;
			}
			if ( isset( $opt[ $demo_slug ][ $slug ] ) && get_post( (int) $opt[ $demo_slug ][ $slug ] ) ) {
				continue;
			}

			$title   = isset( $p['title'] ) ? (string) $p['title'] : $slug;
			$content = isset( $p['content'] ) ? (string) $p['content'] : '';
			$excerpt = isset( $p['excerpt'] ) ? (string) $p['excerpt'] : '';
			$status  = 'publish'; // must be publish so Woo widgets query sees it.

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'product',
					'post_status'  => $status,
					'post_title'   => sanitize_text_field( $title ),
					'post_name'    => $slug,
					'post_content' => $content,
					'post_excerpt' => $excerpt,
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				continue;
			}
			$post_id = (int) $post_id;

			update_post_meta( $post_id, '_hmps_demo_slug', $demo_slug );
			update_post_meta( $post_id, '_hmps_is_preview_product', 1 );

			// Basic pricing meta.
			$regular = isset( $p['regular_price'] ) ? (string) $p['regular_price'] : '';
			$sale    = isset( $p['sale_price'] ) ? (string) $p['sale_price'] : '';
			$price   = isset( $p['price'] ) ? (string) $p['price'] : ( $sale ? $sale : $regular );
			if ( $regular !== '' ) {
				update_post_meta( $post_id, '_regular_price', $regular );
			}
			if ( $sale !== '' ) {
				update_post_meta( $post_id, '_sale_price', $sale );
			}
			if ( $price !== '' ) {
				update_post_meta( $post_id, '_price', $price );
			}

			$stock_status = isset( $p['stock_status'] ) ? (string) $p['stock_status'] : 'instock';
			update_post_meta( $post_id, '_stock_status', $stock_status );
			update_post_meta( $post_id, '_manage_stock', ( isset( $p['manage_stock'] ) && 'yes' === $p['manage_stock'] ) ? 'yes' : 'no' );
			if ( isset( $p['stock'] ) && $p['stock'] !== '' ) {
				update_post_meta( $post_id, '_stock', (string) $p['stock'] );
			}

			// SKU.
			if ( isset( $p['sku'] ) && $p['sku'] !== '' ) {
				update_post_meta( $post_id, '_sku', (string) $p['sku'] );
			}

			// Product type.
			wp_set_object_terms( $post_id, 'simple', 'product_type', false );

			// Categories/tags.
			if ( ! empty( $p['product_cat'] ) && is_array( $p['product_cat'] ) ) {
				$cats = array();
				foreach ( $p['product_cat'] as $c ) {
					$c = sanitize_title( (string) $c );
					if ( ! $c ) {
						continue;
					}
					$term = term_exists( $c, 'product_cat' );
					if ( ! $term ) {
						$term = wp_insert_term( $c, 'product_cat' );
					}
					if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
						$cats[] = (int) $term['term_id'];
					}
				}
				if ( $cats ) {
					wp_set_object_terms( $post_id, $cats, 'product_cat', false );
				}
			}
			if ( ! empty( $p['product_tag'] ) && is_array( $p['product_tag'] ) ) {
				$tags = array();
				foreach ( $p['product_tag'] as $t ) {
					$t = sanitize_title( (string) $t );
					if ( $t ) {
						$tags[] = $t;
					}
				}
				if ( $tags ) {
					wp_set_object_terms( $post_id, $tags, 'product_tag', false );
				}
			}

			// Images.
			$featured_old = isset( $p['featured_image_id'] ) ? (int) $p['featured_image_id'] : 0;
			if ( $featured_old && isset( $att_map[ $featured_old ] ) ) {
				update_post_meta( $post_id, '_thumbnail_id', (int) $att_map[ $featured_old ] );
			}
			if ( ! empty( $p['gallery_image_ids'] ) && is_array( $p['gallery_image_ids'] ) ) {
				$g = array();
				foreach ( $p['gallery_image_ids'] as $gid ) {
					$gid = (int) $gid;
					if ( $gid && isset( $att_map[ $gid ] ) ) {
						$g[] = (int) $att_map[ $gid ];
					}
				}
				if ( $g ) {
					update_post_meta( $post_id, '_product_image_gallery', implode( ',', $g ) );
				}
			}

			$opt[ $demo_slug ][ $slug ] = $post_id;
		}

		update_option( 'hmps_preview_products', $opt, false );
	}

	private static function get_attachment_map( string $demo_slug ) : array {
		$opt = get_option( 'hmps_preview_attachments', array() );
		$opt = is_array( $opt ) ? $opt : array();
		if ( isset( $opt[ $demo_slug ] ) && is_array( $opt[ $demo_slug ] ) ) {
			return $opt[ $demo_slug ];
		}
		return array();
	}
}
