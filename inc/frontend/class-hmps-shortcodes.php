<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HMPS_Shortcodes {
	public static function register() : void {
		add_shortcode( 'hmps_showcase', array( __CLASS__, 'render_showcase' ) );
		// Back-compat alias if you decide to keep old name.
		add_shortcode( 'hmpro_showcase', array( __CLASS__, 'render_showcase' ) );
	}

	private static function enqueue_assets() : void {
		wp_register_style(
			'hmps-showcase',
			HMPS_PLUGIN_URL . 'assets/css/hmps-showcase.css',
			array(),
			HMPS_VERSION
		);
		wp_register_script(
			'hmps-showcase',
			HMPS_PLUGIN_URL . 'assets/js/hmps-showcase.js',
			array(),
			HMPS_VERSION,
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
	 * Shortcode: [hmps_showcase]
	 */
	public static function render_showcase( $atts = array() ) : string {
		self::enqueue_assets();

		if ( ! class_exists( 'HMPS_Admin' ) ) {
			require_once HMPS_PLUGIN_DIR . 'inc/admin/class-hmps-admin.php';
		}

		$settings = HMPS_Admin::get_settings();
		$repo     = new HMPS_Package_Repository( (string) $settings['packages_base_dir'] );
		$packages = $repo->list_packages();

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

		// Decide default category: first enabled category, else all.
		$default_cat = ! empty( $cats ) ? (string) $cats[0]['slug'] : 'all';

		ob_start();
		?>
		<div class="hmps-showcase-root hmps-showcase" data-default-cat="<?php echo esc_attr( $default_cat ); ?>">
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
					Henüz demo paketi bulunamadı. Lütfen uploads/hmps-packages altına bir paket ekleyin.
				</div>
			<?php else : ?>
				<div class="hmps-grid" role="list">
					<?php foreach ( $packages as $p ) : ?>
						<?php
						$slug  = (string) ( $p['slug'] ?? '' );
						$title = (string) ( $p['title'] ?? $slug );
						$desc  = (string) ( $p['description'] ?? '' );
						$pcats = isset( $p['categories'] ) && is_array( $p['categories'] ) ? $p['categories'] : array();
						$cats_attr = implode(
							' ',
							array_values(
								array_filter(
									array_map(
										static function( $c ) {
											return sanitize_title( (string) $c );
										},
										$pcats
									)
								)
							)
						);
						$cover_url = (string) ( $p['cover']['url'] ?? '' );
						$preview   = '';
						?>
						<article
							class="hmps-card"
							role="listitem"
							data-title="<?php echo esc_attr( mb_strtolower( $title ) ); ?>"
							data-cats="<?php echo esc_attr( $cats_attr ); ?>"
							data-order="0"
						>
							<div class="hmps-media" aria-label="<?php echo esc_attr( $title ); ?>">
								<?php if ( $cover_url ) : ?>
									<img src="<?php echo esc_url( $cover_url ); ?>" alt="" loading="lazy" />
								<?php else : ?>
									<div class="hmps-cover-fallback">Kapak yok</div>
								<?php endif; ?>
							</div>

							<div class="hmps-body">
								<div class="hmps-title"><?php echo esc_html( $title ); ?></div>
								<?php if ( $desc ) : ?>
									<div class="hmps-desc"><?php echo esc_html( $desc ); ?></div>
								<?php endif; ?>

								<div class="hmps-actions">
									<button type="button" class="hmps-preview" data-slug="<?php echo esc_attr( $slug ); ?>">Önizle</button>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
