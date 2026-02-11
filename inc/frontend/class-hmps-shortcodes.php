<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HMPS_Shortcodes {
	/**
	 * Cached map of category slug => label.
	 *
	 * @var array<string, string>|null
	 */
	private static $cat_label_map = null;
	public static function register() : void {
		add_shortcode( 'hmps_showcase', array( __CLASS__, 'render_showcase' ) );
		add_shortcode( 'hmps_demo', array( __CLASS__, 'render_single_demo' ) );
		// Back-compat alias if you decide to keep old name.
		add_shortcode( 'hmpro_showcase', array( __CLASS__, 'render_showcase' ) );
	}

	/**
	 * Render a single package card (shared by showcase + single demo).
	 *
	 * @param array  $p    Package data.
	 * @param string $slug Package slug.
	 */
	private static function render_card( array $p, string $slug ) : void {
		$title       = isset( $p['title'] ) ? (string) $p['title'] : '';
		$slug_in    = sanitize_title( $slug );
		if ( '' === $slug_in && isset( $p['slug'] ) ) {
			$slug_in = sanitize_title( (string) $p['slug'] );
		}
		$title_raw  = isset( $p['title'] ) ? (string) $p['title'] : '';
		$title      = trim( $title_raw );

		// Robust fallback: if title is missing OR looks like a slug (e.g. kadin-giyim-demo-1-soft-rose), show a human label.
		$title_is_sluglike = ( '' !== $title ) && ( sanitize_title( $title ) === $title );
		if ( '' === $title || $title_is_sluglike ) {
			$base = $slug_in ? $slug_in : sanitize_title( $title_raw );
			$title = $base ? self::slug_to_label( $base ) : (string) $title_raw;
		}
		if ( '' === trim( $title ) ) {
			$title = $slug_in ? $slug_in : (string) $slug;
		}
		$description = isset( $p['description'] ) ? (string) $p['description'] : '';
		$cats        = isset( $p['cats'] ) ? (array) $p['cats'] : ( isset( $p['categories'] ) ? (array) $p['categories'] : array() );
		$cats_str    = implode( ' ', array_map( 'sanitize_title', $cats ) );
		$cover_url   = '';
		if ( isset( $p['cover_url'] ) ) {
			$cover_url = (string) $p['cover_url'];
		} elseif ( isset( $p['cover'] ) ) {
			if ( is_array( $p['cover'] ) ) {
				$cover_url = (string) ( $p['cover']['url'] ?? '' );
			} else {
				$cover_url = (string) $p['cover'];
			}
		}
		$runtime_url = isset( $p['runtime_url'] ) ? (string) $p['runtime_url'] : ( isset( $p['runtime'] ) ? (string) $p['runtime'] : '' );
		$covers = array();
		if ( isset( $p['covers'] ) && is_array( $p['covers'] ) ) {
			$covers = array_values( array_filter( array_map( 'strval', (array) $p['covers'] ) ) );
		}
		if ( empty( $covers ) && $cover_url ) {
			$covers = array( $cover_url );
		}
		$covers_json = $covers ? wp_json_encode( $covers ) : '[]';
		$order       = isset( $p['order'] ) ? (int) $p['order'] : 0;

		?>
		<article
			class="hmps-card"
			role="listitem"
			data-title="<?php echo esc_attr( mb_strtolower( $title ) ); ?>"
			data-cats="<?php echo esc_attr( $cats_str ); ?>"
			data-order="<?php echo esc_attr( (string) $order ); ?>"
		>
			<div class="hmps-card-media" data-images="<?php echo esc_attr( (string) $covers_json ); ?>">
				<?php if ( $cover_url ) : ?>
					<img class="hmps-cover" src="<?php echo esc_url( $cover_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" decoding="async" />
					<button type="button" class="hmps-media-open" aria-label="İncele">İncele</button>
					<button type="button" class="hmps-media-prev" aria-label="Önceki görsel">‹</button>
					<button type="button" class="hmps-media-next" aria-label="Sonraki görsel">›</button>
				<?php else : ?>
					<div class="hmps-cover hmps-cover--empty" aria-hidden="true"></div>
				<?php endif; ?>
			</div>

			<div class="hmps-card-body">
				<h3 class="hmps-card-title"><?php echo esc_html( $title ); ?></h3>

				<?php if ( ! empty( $cats ) ) : ?>
					<div class="hmps-card-meta">
						<?php
						$map          = self::get_category_label_map();
						$cats_display = array();
						foreach ( $cats as $c ) {
							$cs = sanitize_title( (string) $c );
							if ( '' === $cs ) {
								continue;
							}
							$cats_display[] = $map[ $cs ] ?? self::slug_to_label( $cs );
						}
						echo esc_html( implode( ' • ', array_map( 'sanitize_text_field', $cats_display ) ) );
						?>
					</div>
				<?php endif; ?>

				<?php if ( '' !== trim( $description ) ) : ?>
					<div class="hmps-card-desc">
						<?php echo wp_kses_post( wpautop( $description ) ); ?>
					</div>
				<?php endif; ?>

				<div class="hmps-actions">
					<button
						type="button"
						class="hmps-preview"
						data-slug="<?php echo esc_attr( $slug ); ?>"
						data-cover="<?php echo esc_url( $cover_url ); ?>"
						<?php if ( $runtime_url ) : ?>
							data-runtime-url="<?php echo esc_url( $runtime_url ); ?>"
						<?php endif; ?>
					>Canlı Önizle</button>
				</div>
			</div>
		</article>
		<?php
	}

	private static function enqueue_assets() : void {
		$css_path = HMPS_PLUGIN_DIR . 'assets/css/hmps-showcase.css';
		$js_path  = HMPS_PLUGIN_DIR . 'assets/js/hmps-showcase.js';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : HMPS_VERSION;
		$js_ver   = file_exists( $js_path ) ? (string) filemtime( $js_path ) : HMPS_VERSION;

		wp_register_style(
			'hmps-showcase',
			HMPS_PLUGIN_URL . 'assets/css/hmps-showcase.css',
			array(),
			$css_ver
		);
		wp_register_script(
			'hmps-showcase',
			HMPS_PLUGIN_URL . 'assets/js/hmps-showcase.js',
			array(),
			$js_ver,
			true
		);

		wp_enqueue_style( 'hmps-showcase' );
		wp_enqueue_script( 'hmps-showcase' );

		// Localize runtime preview endpoint + runtime keys.
		if ( ! class_exists( 'HMPS_Admin' ) ) {
			require_once HMPS_PLUGIN_DIR . 'inc/admin/class-hmps-admin.php';
		}
		$runtimes = HMPS_Admin::get_player_runtimes();
		wp_localize_script(
			'hmps-showcase',
			'HMPS_SHOWCASE',
			array(
				'previewEndpoint' => esc_url_raw( rest_url( 'hmps/v1/showcase/preview' ) ),
				'runtimeKeys'     => array_values( array_keys( $runtimes ) ),
				'coverUrl'        => HMPS_PLUGIN_URL . 'assets/images/cover.jpg',
			)
		);
	}

	private static function slug_to_label( string $slug ) : string {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return '';
		}
		$label = str_replace( array( '-', '_' ), ' ', $slug );
		return ucwords( $label );
	}

	/**
	 * Get category label map (slug => label). Uses admin settings if available.
	 *
	 * @return array<string, string>
	 */
	private static function get_category_label_map() : array {
		if ( null !== self::$cat_label_map ) {
			return self::$cat_label_map;
		}
		self::$cat_label_map = array();
		if ( ! class_exists( 'HMPS_Admin' ) ) {
			require_once HMPS_PLUGIN_DIR . 'inc/admin/class-hmps-admin.php';
		}
		$dict = HMPS_Admin::get_category_dict();
		foreach ( (array) $dict as $slug => $row ) {
			$slug_final = sanitize_title( (string) ( $row['slug'] ?? $slug ) );
			if ( '' === $slug_final ) {
				continue;
			}
			$label_final = (string) ( $row['label'] ?? '' );
			if ( '' === trim( $label_final ) ) {
				$label_final = self::slug_to_label( $slug_final );
			}
			self::$cat_label_map[ $slug_final ] = $label_final;
		}
		return self::$cat_label_map;
	}

	private static function get_enabled_categories() : array {
		if ( ! class_exists( 'HMPS_Admin' ) ) {
			// Admin class not loaded on frontend, but constant method used in same plugin.
			require_once HMPS_PLUGIN_DIR . 'inc/admin/class-hmps-admin.php';
		}

		$dict = HMPS_Admin::get_category_dict();
		$out  = array();

		foreach ( $dict as $slug => $row ) {
			$enabled = (int) ( $row['enabled'] ?? 1 );
			if ( 1 !== $enabled ) {
				continue;
			}
			$slug_final  = sanitize_title( (string) ( $row['slug'] ?? $slug ) );
			$label_final = (string) ( $row['label'] ?? '' );
			if ( '' === trim( $label_final ) ) {
				$label_final = self::slug_to_label( $slug_final );
			}
			if ( '' === $slug_final || '' === trim( $label_final ) ) {
				continue;
			}
			$out[] = array(
				'slug'        => $slug_final,
				'label'       => $label_final,
				'description' => (string) ( $row['description'] ?? '' ),
				'order'       => (int) ( $row['order'] ?? 0 ),
			);
		}

		usort(
			$out,
			static function( $a, $b ) {
				$ao = (int) ( $a['order'] ?? 0 );
				$bo = (int) ( $b['order'] ?? 0 );
				if ( $ao === $bo ) {
					return strcmp( (string) ( $a['label'] ?? '' ), (string) ( $b['label'] ?? '' ) );
				}
				return ( $ao < $bo ) ? -1 : 1;
			}
		);

		return $out;
	}

	/**
	 * Shortcode: [hmps_demo slug="..."]
	 * Renders a single package card in "hero" mode (centered + wider).
	 */
	public static function render_single_demo( $atts = array() ) : string {
		self::enqueue_assets();

		$atts = shortcode_atts(
			array(
				'slug' => '',
			),
			(array) $atts,
			'hmps_demo'
		);

		$slug = sanitize_text_field( (string) $atts['slug'] );
		if ( ! $slug ) {
			return '';
		}

		if ( ! class_exists( 'HMPS_Admin' ) ) {
			require_once HMPS_PLUGIN_DIR . 'inc/admin/class-hmps-admin.php';
		}

		$settings = HMPS_Admin::get_settings();
		$repo     = new HMPS_Package_Repository( (string) $settings['packages_base_dir'] );
		$p        = $repo->get_package( $slug );
		if ( empty( $p ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="hmps-single-demo hmps-showcase-root">
			<div class="hmps-grid" role="list">
				<?php self::render_card( (array) $p, $slug ); ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode: [hmps_showcase]
	 */
	public static function render_showcase( $atts = array() ) : string {
		self::enqueue_assets();

		$atts = shortcode_atts(
			array(
				'per_page' => 12,
				'paging'   => 'loadmore', // loadmore | pagination
				'cat'      => '', // optional: kadin-giyim, erkek-giyim, etc. (empty = all)
			),
			(array) $atts,
			'hmps_showcase'
		);

		$per_page = (int) $atts['per_page'];
		if ( $per_page < 1 ) {
			$per_page = 12;
		}
		$paging = sanitize_key( (string) $atts['paging'] );
		if ( ! in_array( $paging, array( 'loadmore', 'pagination', 'none' ), true ) ) {
			$paging = 'loadmore';
		}

		if ( ! class_exists( 'HMPS_Admin' ) ) {
			require_once HMPS_PLUGIN_DIR . 'inc/admin/class-hmps-admin.php';
		}

		$settings = HMPS_Admin::get_settings();
		$repo     = new HMPS_Package_Repository( (string) $settings['packages_base_dir'] );
		$packages = $repo->list_packages();

		// Optional: server-side filter packages by category slug.
		$requested_cat = sanitize_title( (string) $atts['cat'] );
		if ( '' !== $requested_cat && 'all' !== $requested_cat ) {
			$packages = array_values(
				array_filter(
					$packages,
					static function ( $p ) use ( $requested_cat ) {
						$pcats = isset( $p['categories'] ) && is_array( $p['categories'] ) ? $p['categories'] : array();
						$pcats = array_map( 'sanitize_title', array_map( 'strval', $pcats ) );
						return in_array( $requested_cat, $pcats, true );
					}
				)
			);
		}

		$cats = self::get_enabled_categories();

		// Always merge-in categories discovered from packages so tabs never "sapıt".
		$seen = array();
		foreach ( $cats as $c ) {
			$seen[ sanitize_title( (string) ( $c['slug'] ?? '' ) ) ] = true;
		}
		foreach ( $packages as $p ) {
			$pcats = isset( $p['categories'] ) && is_array( $p['categories'] ) ? $p['categories'] : array();
			foreach ( $pcats as $cslug ) {
				$cslug = sanitize_title( (string) $cslug );
				if ( '' === $cslug || isset( $seen[ $cslug ] ) ) {
					continue;
				}
				$seen[ $cslug ] = true;
				$cats[] = array(
					'slug'        => $cslug,
					'label'       => self::slug_to_label( $cslug ),
					'description' => '',
					'order'       => 0,
				);
			}
		}

		// Decide default category: prefer requested cat, else first enabled, else all.
		$default_cat = ( '' !== $requested_cat && 'all' !== $requested_cat ) ? $requested_cat : ( ! empty( $cats ) ? (string) $cats[0]['slug'] : 'all' );

		ob_start();
		?>
		<div
			class="hmps-showcase-root hmps-showcase"
			data-default-cat="<?php echo esc_attr( $default_cat ); ?>"
			data-per-page="<?php echo esc_attr( (string) $per_page ); ?>"
			data-paging="<?php echo esc_attr( (string) $paging ); ?>"
		>
			<div class="hmps-toolbar">
				<div class="hmps-tabs" role="tablist" aria-label="Showcase Kategorileri">
					<button type="button" class="hmps-tab is-active" data-cat="all">Tümü</button>
					<?php foreach ( $cats as $c ) : ?>
						<button type="button" class="hmps-tab" data-cat="<?php echo esc_attr( (string) $c['slug'] ); ?>">
							<?php echo esc_html( (string) $c['label'] ); ?>
						</button>
					<?php endforeach; ?>
				</div>

				<div class="hmps-controls">
					<input
						type="search"
						class="hmps-search"
						placeholder="Demo ara..."
						aria-label="Demo ara"
					/>
					<select class="hmps-sort" aria-label="Sırala">
						<option value="order">Sıraya göre</option>
						<option value="title">İsme göre</option>
					</select>
				</div>
			</div>

			<?php if ( empty( $packages ) ) : ?>
				<div class="hmps-empty">
					<?php
					// Hint path: show "uploads/<dir>" if the configured base dir is within uploads.
					$uploads   = wp_upload_dir();
					$uploads_d = isset( $uploads['basedir'] ) ? wp_normalize_path( $uploads['basedir'] ) : wp_normalize_path( WP_CONTENT_DIR . '/uploads' );
					$base_dir  = isset( $settings['packages_base_dir'] ) ? wp_normalize_path( (string) $settings['packages_base_dir'] ) : '';
					$hint_dir  = 'uploads/hmpro-demo-packages';
					if ( $base_dir && 0 === strpos( $base_dir, $uploads_d ) ) {
						$rel      = ltrim( substr( $base_dir, strlen( $uploads_d ) ), '/\\' );
						$hint_dir = 'uploads/' . ( $rel ? $rel : 'hmpro-demo-packages' );
					} elseif ( $base_dir ) {
						$hint_dir = $base_dir;
					}
					echo esc_html( sprintf( 'Henüz demo paketi bulunamadı. Lütfen %s altına bir paket ekleyin.', $hint_dir ) );
					?>
				</div>
			<?php else : ?>
				<div class="hmps-grid" role="list">
					<?php foreach ( $packages as $p ) : ?>
						<?php
						$slug = isset( $p['slug'] ) ? (string) $p['slug'] : '';
						self::render_card( (array) $p, $slug );
						?>
					<?php endforeach; ?>
				</div>

				<div class="hmps-pager" aria-label="Sayfalama"></div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
