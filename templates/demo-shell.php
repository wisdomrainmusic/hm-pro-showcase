<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// $slug = demo slug (already in template context).

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
	$page = HMPS_Virtual_Pages::render_page_html( $package_dir, $page_slug, $slug );
}

// Render using active theme header/footer for visual consistency.
get_header();
?>

<meta name="robots" content="noindex,nofollow" />

<div style="max-width:1200px;margin:24px auto;padding:0 18px;">
	<?php
	// Showcase page (front list) – default /a1/ but can be overridden later via setting.
	$showcase_url = HMPS_Preview_Context::showcase_url();
	// Exit should return to showcase list.
	$exit_url = $showcase_url;
	?>
	<div style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:14px;">
		<div>
			<div style="font-size:13px;color:#666;margin-bottom:4px;">
				<?php echo esc_html( (string) ( $pkg['title'] ?? $slug ) ); ?>
			</div>
			<div style="font-size:18px;line-height:1.2;font-weight:600;margin:0;">
				<?php echo esc_html( $page['found'] ? ( $page['title'] ? $page['title'] : $page_slug ) : $page_slug ); ?>
			</div>
		</div>
		<div style="display:flex;gap:10px;align-items:center;">
			<a href="<?php echo esc_url( $exit_url ); ?>" data-hmps-exit style="text-decoration:none;border:1px solid #ddd;padding:8px 12px;border-radius:10px;background:#fff;">
				<?php echo esc_html__( 'Exit', 'hm-pro-showcase' ); ?>
			</a>
		</div>
	</div>

	<script>
	(function(){
		// Hard lock: any internal navigation must stay inside the active demo scope.
		var ACTIVE_DEMO = <?php echo wp_json_encode( (string) $slug ); ?>;
		var PREVIEW_BASE = <?php echo wp_json_encode( '/' . HMPS_Preview_Context::preview_base_slug() . '/' ); ?>; // e.g. "/showcase/"

		function isSkippableHref(href){
			if(!href) return true;
			href = String(href);
			if(href[0] === '#') return true;
			var lower = href.toLowerCase();
			if(lower.indexOf('mailto:') === 0) return true;
			if(lower.indexOf('tel:') === 0) return true;
			if(lower.indexOf('javascript:') === 0) return true;
			return false;
		}

		function isAdminPath(pathname){
			return /^\/(wp-admin|wp-login\.php)\b/i.test(pathname || '');
		}

		function normalizeToActiveDemo(urlObj){
			// Keep query/hash
			var path = urlObj.pathname || '/';
			var search = urlObj.search || '';
			var hash = urlObj.hash || '';

			// Never rewrite admin URLs
			if(isAdminPath(path)) return null;

			// If already in /showcase/<demo>/... but demo differs, replace it
			if(path.indexOf(PREVIEW_BASE) === 0){
				// path like /showcase/<something>/rest
				var rest = path.substring(PREVIEW_BASE.length); // "<something>/rest"
				var parts = rest.split('/').filter(Boolean);
				if(parts.length >= 1){
					parts[0] = ACTIVE_DEMO;
					return PREVIEW_BASE + parts.join('/') + '/' + search + hash;
				}
				// /showcase/ only -> go to active demo root
				return PREVIEW_BASE + ACTIVE_DEMO + '/' + search + hash;
			}

			// Any other same-origin path => force into /showcase/<demo>/<path>
			path = path.replace(/^\/+/, ''); // trim leading slashes
			return PREVIEW_BASE + ACTIVE_DEMO + '/' + path + search + hash;
		}

		document.addEventListener('click', function(ev){
			var a = ev.target && ev.target.closest ? ev.target.closest('a') : null;
			if(!a) return;

			// Existing close handler (Exit) should work as-is.
			if(a.hasAttribute('data-hmps-exit')) return;

			var href = a.getAttribute('href');
			if(isSkippableHref(href)) return;

			// Let new-tab / modifier clicks behave normally
			if(ev.defaultPrevented) return;
			if(ev.button && ev.button !== 0) return;
			if(ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;
			if(a.target && a.target !== '' && a.target !== '_self') return;
			if(a.hasAttribute('download')) return;

			var url;
			try {
				url = new URL(href, window.location.href);
			} catch(e){
				return;
			}

			// Only same-origin should be forced into demo scope
			if(url.origin !== window.location.origin) return;

			var next = normalizeToActiveDemo(url);
			if(!next) return;

			// If already correct, allow default navigation
			if(next === (url.pathname + url.search + url.hash)) return;

			ev.preventDefault();
			window.location.assign(next);
		}, true);

		// If this demo is running inside the Showcase modal iframe, let the parent close the modal.
		function isInIframe(){
			try { return window.self !== window.top; } catch(e){ return true; }
		}
		if(!isInIframe()) return;

		function sendClose(mode){
			try {
				window.parent.postMessage({ hmps: 'close', mode: mode || 'close' }, '*');
			} catch(e) {}
		}

		document.addEventListener('click', function(ev){
			var a = ev.target && ev.target.closest ? ev.target.closest('a') : null;
			if(!a) return;
			if(a.hasAttribute('data-hmps-exit')){
				ev.preventDefault();
				sendClose('showcase');
			}
		}, true);
	})();
	</script>

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
