<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HMPS_Virtual_Pages {
	/** @var array<string,int> */
	private static $preview_posts_cache = array();

	/** @var array<string,array<int,string>> */
	private static $media_url_map_cache = array();

	public static function package_dir_from_globals() : string {
		$slug = isset( $GLOBALS['hmps_demo_slug'] ) ? (string) $GLOBALS['hmps_demo_slug'] : '';
		$base = isset( $GLOBALS['hmps_packages_base_dir'] ) ? (string) $GLOBALS['hmps_packages_base_dir'] : '';
		if ( ! $slug || ! $base ) {
			return '';
		}
		return wp_normalize_path( trailingslashit( $base ) . $slug );
	}

	public static function preview_base_slug() : string {
		$opt  = get_option( 'hmps_settings', array() );
		$slug = isset( $opt['preview_base_slug'] ) ? (string) $opt['preview_base_slug'] : 'demo';
		$slug = sanitize_title( $slug );
		return $slug ? $slug : 'demo';
	}

	public static function rewrite_internal_links( string $html, string $demo_slug ) : string {
		$demo_slug = sanitize_title( $demo_slug );
		if ( ! $demo_slug || ! $html ) {
			return $html;
		}

		$base = '/' . self::preview_base_slug() . '/' . $demo_slug . '/';

		// Rewrite root-relative href="/x/" to href="/demo/<slug>/x/"
		$html = preg_replace_callback(
			'#href=(["\'])(/[^"\']*)\1#i',
			function( $m ) use ( $base ) {
				$q = $m[1];
				$u = $m[2];

				// Keep assets and admin URLs untouched.
				if ( preg_match( '#^/(wp-admin|wp-login\.php|wp-content|wp-includes)/#i', $u ) ) {
					return 'href=' . $q . $u . $q;
				}

				// Avoid double-prefixing if already in preview base.
				if ( 0 === strpos( $u, $base ) ) {
					return 'href=' . $q . $u . $q;
				}

				$u = ltrim( $u, '/' );
				return 'href=' . $q . $base . $u . $q;
			},
			$html
		);

		// Rewrite site absolute URLs to demo URLs (same host).
		// Example: href="https://example.com/shop/" => href="/demo/<slug>/shop/"
		$home = home_url( '/' );
		$home = rtrim( $home, '/' );
		$html = preg_replace_callback(
			'#href=("|\')(' . preg_quote( $home, '#' ) . ')(/[^"\']*)("|\')#i',
			function( $m ) use ( $base ) {
				$q    = $m[1];
				$path = $m[3];
				if ( preg_match( '#^/(wp-admin|wp-login\.php|wp-content|wp-includes)/#i', $path ) ) {
					return 'href=' . $q . $m[2] . $path . $q;
				}
				if ( 0 === strpos( $path, $base ) ) {
					return 'href=' . $q . $m[2] . $path . $q;
				}
				return 'href=' . $q . $base . ltrim( $path, '/' ) . $q;
			},
			$html
		);

		// If a link already points to another demo within the showcase base,
		// force it back into the current demo.
		$html = preg_replace(
			'#href=("|\')/(?:' . preg_quote( self::preview_base_slug(), '#' ) . ')/[^/]+/#i',
			'href=$1/' . self::preview_base_slug() . '/' . $demo_slug . '/',
			$html
		);

		return $html;
	}

	/**
	 * Try to detect the source site URL (e.g. localhost/demo-test-2)
	 * so we can rewrite absolute URLs back into the preview scope.
	 */
	private static function get_source_site_url( string $package_dir ) : string {
		$package_dir = wp_normalize_path( $package_dir );
		$file        = trailingslashit( $package_dir ) . 'media.json';
		if ( ! file_exists( $file ) ) {
			return '';
		}
		$json = file_get_contents( $file );
		if ( ! $json ) {
			return '';
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		$base = isset( $data['source_upload_baseurl'] ) ? (string) $data['source_upload_baseurl'] : '';
		if ( ! $base ) {
			return '';
		}
		// Convert uploads base to site root.
		$base = rtrim( $base, '/' );
		$base = preg_replace( '#/wp-content/uploads$#i', '', $base );
		return (string) $base;
	}

	private static function rewrite_exported_absolute_urls( string $html, string $package_dir, string $demo_slug ) : string {
		$src = self::get_source_site_url( $package_dir );
		if ( ! $src ) {
			return $html;
		}
		$src  = rtrim( $src, '/' );
		$home = rtrim( home_url( '/' ), '/' );
		if ( ! $home ) {
			return $html;
		}
		// First, map exported site to current site.
		$html = str_replace( $src . '/', $home . '/', $html );
		// Rewrite uploads/media URLs into preview-served media.
		$html = self::rewrite_uploads_to_preview_media( $html, $package_dir, $demo_slug );
		// Then, keep navigation inside demo scope.
		return self::rewrite_internal_links( $html, $demo_slug );
	}

	private static function rewrite_uploads_to_preview_media( string $html, string $package_dir, string $demo_slug ) : string {
		$demo_slug = sanitize_title( $demo_slug );
		if ( ! $demo_slug ) {
			return $html;
		}
		$file = trailingslashit( wp_normalize_path( $package_dir ) ) . 'media.json';
		if ( ! file_exists( $file ) ) {
			return $html;
		}
		$json = file_get_contents( $file );
		if ( ! $json ) {
			return $html;
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return $html;
		}
		$src_uploads = isset( $data['source_upload_baseurl'] ) ? rtrim( (string) $data['source_upload_baseurl'], '/' ) : '';
		$dst_base    = rtrim( home_url( '/' ), '/' ) . '/' . self::preview_base_slug() . '/' . $demo_slug . '/media';
		if ( ! $src_uploads ) {
			return $html;
		}

		// Replace exported uploads base with preview media route.
		$html = str_replace( $src_uploads . '/', $dst_base . '/', $html );

		// Also rewrite current-site uploads base (in case content already partially rewritten).
		$up = wp_upload_dir();
		if ( isset( $up['baseurl'] ) && $up['baseurl'] ) {
			$cur = rtrim( (string) $up['baseurl'], '/' );
			$html = str_replace( $cur . '/', $dst_base . '/', $html );
		}

		return $html;
	}

	private static function media_id_to_url_map( string $package_dir, string $demo_slug ) : array {
		$demo_slug  = sanitize_title( $demo_slug );
		$package_dir = wp_normalize_path( $package_dir );
		$cache_key  = $demo_slug . '::' . $package_dir;
		if ( isset( self::$media_url_map_cache[ $cache_key ] ) ) {
			return (array) self::$media_url_map_cache[ $cache_key ];
		}

		$map  = array();
		$file = trailingslashit( $package_dir ) . 'media.json';
		if ( file_exists( $file ) ) {
			$json = file_get_contents( $file );
			$data = $json ? json_decode( $json, true ) : null;
			if ( is_array( $data ) && ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
				$base = rtrim( home_url( '/' ), '/' ) . '/' . self::preview_base_slug() . '/' . $demo_slug . '/media/';
				foreach ( $data['items'] as $it ) {
					$old = isset( $it['old_id'] ) ? (int) $it['old_id'] : 0;
					$rel = isset( $it['rel_path'] ) ? (string) $it['rel_path'] : '';
					if ( $old > 0 && $rel ) {
						$map[ $old ] = $base . ltrim( $rel, '/' );
					}
				}
			}
		}

		self::$media_url_map_cache[ $cache_key ] = $map;
		return $map;
	}

	private static function patch_elementor_media_urls( string $elementor_json, string $package_dir, string $demo_slug ) : string {
		$elementor_json = (string) $elementor_json;
		$decoded        = json_decode( $elementor_json, true );
		if ( ! is_array( $decoded ) ) {
			return $elementor_json;
		}
		$map = self::media_id_to_url_map( $package_dir, $demo_slug );
		if ( ! $map ) {
			return $elementor_json;
		}

		$walker = function( &$node ) use ( &$walker, $map ) {
			if ( is_array( $node ) ) {
				// Patch Elementor media control arrays: {id:123, url:""}
				if ( isset( $node['id'] ) && is_numeric( $node['id'] ) ) {
					$id = (int) $node['id'];
					if ( $id > 0 && isset( $map[ $id ] ) ) {
						if ( empty( $node['url'] ) ) {
							$node['url'] = $map[ $id ];
						}
						// Prevent attachment lookups.
						$node['id'] = 0;
					}
				}
				foreach ( $node as $k => &$v ) {
					$walker( $v );
				}
			}
		};
		$walker( $decoded );

		$out = wp_json_encode( $decoded );
		return $out ? $out : $elementor_json;
	}

	/**
	 * Load pages.json from a package directory and return a map keyed by slug.
	 *
	 * Supports multiple key styles because exports may vary:
	 * - slug / post_name / name
	 * - title / post_title
	 * - content / post_content / html
	 * - meta (optional)
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

			$meta = array();
			if ( isset( $p['meta'] ) && is_array( $p['meta'] ) ) {
				$meta = $p['meta'];
			}

			$map[ $slug ] = array(
				'slug'    => $slug,
				'title'   => $title,
				'content' => $content,
				'meta'    => $meta,
			);
		}

		return $map;
	}

	/**
	 * Render a virtual page from pages.json.
	 */
	public static function render_page_html( string $package_dir, string $page_slug, string $demo_slug ) : array {
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
		$meta    = isset( $map[ $page_slug ]['meta' ] ) && is_array( $map[ $page_slug ]['meta' ] ) ? $map[ $page_slug ]['meta' ] : array();

		// Elementor pages: render via a temporary preview post so Elementor can hydrate widgets.
		$rendered = '';
		if ( ! empty( $meta['_elementor_data'] ) && class_exists( '\\Elementor\\Plugin' ) ) {
			$post_id = self::ensure_preview_post( $demo_slug, $page_slug, $title, $content, $meta, $package_dir );
			if ( $post_id ) {
				try {
					$rendered = (string) \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post_id, true );
				} catch ( \Throwable $e ) {
					$rendered = '';
				}
			}
		}
		if ( $rendered ) {
			$content = $rendered;
		} else {
			// Let WP format shortcodes/content like normal pages.
			$content = do_shortcode( $content );
			$content = apply_filters( 'the_content', $content );
		}

		// Rewrite exported absolute URLs (demo-test-2 etc.) and keep navigation inside demo scope.
		$content = self::rewrite_exported_absolute_urls( $content, $package_dir, $demo_slug );

		return array(
			'found'   => true,
			'title'   => $title,
			'content' => $content,
		);
	}

	/**
	 * Create or reuse a hidden preview post for Elementor rendering.
	 */
	private static function ensure_preview_post( string $demo_slug, string $page_slug, string $title, string $content, array $meta, string $package_dir ) : int {
		$demo_slug = sanitize_title( $demo_slug );
		$page_slug = sanitize_title( $page_slug );
		if ( ! $demo_slug || ! $page_slug ) {
			return 0;
		}

		$cache_key = $demo_slug . '::' . $page_slug;
		if ( isset( self::$preview_posts_cache[ $cache_key ] ) ) {
			return (int) self::$preview_posts_cache[ $cache_key ];
		}

		// Persistent mapping across requests.
		$opt = get_option( 'hmps_preview_posts', array() );
		$opt = is_array( $opt ) ? $opt : array();
		if ( isset( $opt[ $cache_key ] ) ) {
			$pid = (int) $opt[ $cache_key ];
			if ( $pid > 0 && get_post( $pid ) ) {
				self::$preview_posts_cache[ $cache_key ] = $pid;
				return $pid;
			}
		}

		$post_name = 'hmps-preview-' . $demo_slug . '-' . $page_slug;
		$postarr   = array(
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => $title ? $title : $page_slug,
			'post_name'    => sanitize_title( $post_name ),
			'post_content' => $content,
		);
		$pid = wp_insert_post( $postarr, true );
		if ( is_wp_error( $pid ) ) {
			return 0;
		}
		$pid = (int) $pid;

		update_post_meta( $pid, '_hmps_is_preview', 1 );
		update_post_meta( $pid, '_hmps_demo_slug', $demo_slug );
		update_post_meta( $pid, '_hmps_page_slug', $page_slug );

		// Apply Elementor meta fields if present.
		// NOTE: _elementor_page_settings is critical for layout (full-width), padding, background, header/footer settings, etc.
		foreach ( array( '_elementor_data', '_elementor_page_settings', '_elementor_edit_mode', '_elementor_template_type', '_elementor_version', '_wp_page_template' ) as $k ) {
			if ( ! isset( $meta[ $k ] ) ) {
				continue;
			}
			$val = $meta[ $k ];
			if ( '_elementor_data' === $k ) {
				$val = self::patch_elementor_media_urls( (string) $val, $package_dir, $demo_slug );
			}
			update_post_meta( $pid, $k, $val );
		}

		$opt[ $cache_key ] = $pid;
		update_option( 'hmps_preview_posts', $opt, false );

		self::$preview_posts_cache[ $cache_key ] = $pid;
		return $pid;
	}
}
