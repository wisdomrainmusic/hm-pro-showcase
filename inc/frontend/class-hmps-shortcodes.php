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
	}

	/**
	 * Build preview URL (Commit 5 router will make it real).
	 */
	private static function preview_url( string $preview_base_slug, string $package_slug ) : string {
		$preview_base_slug = sanitize_title( $preview_base_slug );
		$package_slug      = sanitize_title( $package_slug );
		$path              = '/' . trim( $preview_base_slug, '/' ) . '/' . $package_slug . '/';
		return home_url( $path );
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
			$out[] = array(
				'slug'        => (string) ( $row['slug'] ?? $slug ),
				'label'       => (string) ( $row['label'] ?? $slug ),
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

		// Build a fallback list from packages if dict is empty (still Turkish labels best-effort).
		if ( empty( $cats ) ) {
			$seen = array();
			foreach ( $packages as $p ) {
				$pcats = isset( $p['categories'] ) && is_array( $p['categories'] ) ? $p['categories'] : array();
				foreach ( $pcats as $c ) {
					$slug = sanitize_title( (string) $c );
					if ( ! $slug || isset( $seen[ $slug ] ) ) {
						continue;
					}
					$seen[ $slug ] = true;
					$label = str_replace( array( '-', '_' ), ' ', $slug );
					$label = ucwords( $label );
					$cats[] = array(
						'slug'        => $slug,
						'label'       => $label,
						'description' => '',
						'order'       => 0,
					);
				}
			}
		}

		// Decide default category: first enabled category, else all.
		$default_cat = ! empty( $cats ) ? (string) $cats[0]['slug'] : 'all';

		ob_start();
		?>
		<div class="hmps-showcase" data-default-cat="<?php echo esc_attr( $default_cat ); ?>">
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
						$preview   = self::preview_url( (string) $settings['preview_base_slug'], $slug );
						?>
						<article
							class="hmps-card"
							role="listitem"
							data-title="<?php echo esc_attr( mb_strtolower( $title ) ); ?>"
							data-cats="<?php echo esc_attr( $cats_attr ); ?>"
							data-order="0"
						>
							<a class="hmps-media" href="<?php echo esc_url( $preview ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
								<?php if ( $cover_url ) : ?>
									<img src="<?php echo esc_url( $cover_url ); ?>" alt="" loading="lazy" />
								<?php else : ?>
									<div class="hmps-cover-fallback">Kapak yok</div>
								<?php endif; ?>
							</a>

							<div class="hmps-body">
								<div class="hmps-title"><?php echo esc_html( $title ); ?></div>
								<?php if ( $desc ) : ?>
									<div class="hmps-desc"><?php echo esc_html( $desc ); ?></div>
								<?php endif; ?>

								<div class="hmps-actions">
									<a class="hmps-btn hmps-preview-open" href="<?php echo esc_url( $preview ); ?>" data-preview-url="<?php echo esc_url( $preview ); ?>">Önizle</a>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="hmps-modal" aria-hidden="true">
				<div class="hmps-modal__backdrop" data-hmps-close></div>
				<div class="hmps-modal__panel" role="dialog" aria-modal="true" aria-label="Demo Önizleme">
					<button type="button" class="hmps-modal__close" data-hmps-close aria-label="Kapat">×</button>
					<iframe class="hmps-modal__frame" title="Demo Preview" loading="lazy"></iframe>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
