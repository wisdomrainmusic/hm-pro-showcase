<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pkg  = isset( $GLOBALS['hmps_demo_package'] ) && is_array( $GLOBALS['hmps_demo_package'] ) ? $GLOBALS['hmps_demo_package'] : array();
$slug = isset( $GLOBALS['hmps_demo_slug'] ) ? (string) $GLOBALS['hmps_demo_slug'] : '';
$path = isset( $GLOBALS['hmps_demo_path'] ) ? (string) $GLOBALS['hmps_demo_path'] : '';

// Render using active theme header/footer for visual consistency.
get_header();
?>

<meta name="robots" content="noindex,nofollow" />

<div style="max-width:1100px;margin:24px auto;padding:18px;">
	<div style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;">
		<div>
			<h1 style="margin:0;font-size:22px;line-height:1.2;">
				<?php echo esc_html( (string) ( $pkg['title'] ?? $slug ) ); ?>
			</h1>
			<p style="margin:6px 0 0;color:#666;">
				<?php echo esc_html( 'Preview mode (virtual router active)' ); ?>
			</p>
		</div>
		<div style="display:flex;gap:10px;align-items:center;">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="text-decoration:none;border:1px solid #ddd;padding:8px 12px;border-radius:10px;">
				<?php echo esc_html__( 'Exit', 'hm-pro-showcase' ); ?>
			</a>
			<button type="button" onclick="history.back()" style="border:1px solid #ddd;padding:8px 12px;border-radius:10px;background:#fff;cursor:pointer;">
				<?php echo esc_html__( 'Back', 'hm-pro-showcase' ); ?>
			</button>
		</div>
	</div>

	<hr style="margin:18px 0;border:none;border-top:1px solid #eee;">

	<p style="margin:0 0 10px;">
		<strong><?php echo esc_html__( 'Demo slug:', 'hm-pro-showcase' ); ?></strong>
		<code><?php echo esc_html( $slug ); ?></code>
	</p>
	<p style="margin:0 0 10px;">
		<strong><?php echo esc_html__( 'Inner path:', 'hm-pro-showcase' ); ?></strong>
		<code><?php echo esc_html( $path ? $path : '/' ); ?></code>
	</p>

	<div style="margin-top:14px;padding:14px;border:1px dashed #bbb;border-radius:14px;background:#fafafa;">
		<p style="margin:0;">
			<?php echo esc_html__( 'Router takeover is working. Next commit will render pages.json and internal links inside /demo/<slug>/...', 'hm-pro-showcase' ); ?>
		</p>
	</div>
</div>

<?php
get_footer();
?>
