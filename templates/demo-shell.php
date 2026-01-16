<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pkg  = isset( $GLOBALS['hmps_demo_package'] ) && is_array( $GLOBALS['hmps_demo_package'] ) ? $GLOBALS['hmps_demo_package'] : array();
$slug = isset( $GLOBALS['hmps_demo_slug'] ) ? (string) $GLOBALS['hmps_demo_slug'] : '';
$path = isset( $GLOBALS['hmps_demo_path'] ) ? (string) $GLOBALS['hmps_demo_path'] : '';
$resolved = isset( $GLOBALS['hmps_demo_resolved'] ) ? (string) $GLOBALS['hmps_demo_resolved'] : '';
$base_dir = isset( $GLOBALS['hmps_packages_base_dir'] ) ? (string) $GLOBALS['hmps_packages_base_dir'] : '';

$package_dir = '';
if ( $base_dir && $slug ) {
	$package_dir = wp_normalize_path( trailingslashit( $base_dir ) . $slug );
}

// Determine which page slug to render.
// If resolved empty, fallback to package front_page_slug or 'ana-sayfa'.
$front_slug = sanitize_title( (string) ( $pkg['front_page_slug'] ?? '' ) );
$page_slug  = $resolved ? sanitize_title( $resolved ) : ( $front_slug ? $front_slug : 'ana-sayfa' );

$page = array(
	'found'   => false,
	'title'   => '',
	'content' => '',
);
if ( $package_dir && is_dir( $package_dir ) ) {
	$page = HMPS_Virtual_Pages::render_page_html( $package_dir, $page_slug );
}

// Render using active theme header/footer for visual consistency.
get_header();
?>

<meta name="robots" content="noindex,nofollow" />

<div style="max-width:1200px;margin:24px auto;padding:0 18px;">
	<div style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:14px;">
		<div>
			<div style="font-size:13px;color:#666;margin-bottom:4px;">
				<?php echo esc_html( (string) ( $pkg['title'] ?? $slug ) ); ?>
			</div>
			<h1 style="margin:0;font-size:24px;line-height:1.2;">
				<?php echo esc_html( $page['found'] ? ( $page['title'] ? $page['title'] : $page_slug ) : $page_slug ); ?>
			</h1>
		</div>
		<div style="display:flex;gap:10px;align-items:center;">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="text-decoration:none;border:1px solid #ddd;padding:8px 12px;border-radius:10px;background:#fff;">
				<?php echo esc_html__( 'Exit', 'hm-pro-showcase' ); ?>
			</a>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="text-decoration:none;border:1px solid #ddd;padding:8px 12px;border-radius:10px;background:#fff;">
				<?php echo esc_html__( 'Showcase', 'hm-pro-showcase' ); ?>
			</a>
		</div>
	</div>

	<?php if ( ! $page['found'] ) : ?>
		<div style="padding:14px;border:1px dashed #bbb;border-radius:14px;background:#fafafa;">
			<p style="margin:0 0 8px;">
				<?php echo esc_html__( 'pages.json page not found for this route.', 'hm-pro-showcase' ); ?>
			</p>
			<p style="margin:0;">
				<strong><?php echo esc_html__( 'Demo slug:', 'hm-pro-showcase' ); ?></strong>
				<code><?php echo esc_html( $slug ); ?></code>
				&nbsp;|&nbsp;
				<strong><?php echo esc_html__( 'Page slug:', 'hm-pro-showcase' ); ?></strong>
				<code><?php echo esc_html( $page_slug ); ?></code>
			</p>
		</div>
	<?php else : ?>
		<div class="hmps-demo-content">
			<?php
			// Content already filtered via the_content. Print as-is.
			echo $page['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
?>
