<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Player API
 *
 * - Showcase site: /wp-json/hmps/v1/showcase/preview
 *   Calls a runtime site to apply a package, then returns the runtime URL.
 *
 * - Runtime site: /wp-json/hmps/v1/runtime/apply
 *   Validates HMAC and runs hmpro_demo_run_apply() (from hmpro-demo-kurulum plugin).
 */
final class HMPS_Player_API {
	public static function register_routes() : void {
		register_rest_route(
			'hmps/v1',
			'/showcase/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_showcase_preview' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'hmps/v1',
			'/runtime/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_runtime_apply' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	private static function get_secret() : string {
		if ( ! class_exists( 'HMPS_Admin' ) ) {
			require_once HMPS_PLUGIN_DIR . 'inc/admin/class-hmps-admin.php';
		}
		return HMPS_Admin::get_player_secret();
	}

	private static function hmac( string $payload ) : string {
		$secret = self::get_secret();
		return hash_hmac( 'sha256', $payload, $secret );
	}

	public static function handle_showcase_preview( WP_REST_Request $req ) : WP_REST_Response {
		$slug = sanitize_title( (string) $req->get_param( 'slug' ) );
		$rt   = sanitize_key( (string) $req->get_param( 'runtime' ) );

		if ( '' === $slug ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'Missing slug.' ), 400 );
		}

		if ( ! class_exists( 'HMPS_Admin' ) ) {
			require_once HMPS_PLUGIN_DIR . 'inc/admin/class-hmps-admin.php';
		}
		$runtimes = HMPS_Admin::get_player_runtimes();
		if ( empty( $runtimes ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'No runtimes configured.' ), 400 );
		}

		// Default to first runtime if none passed.
		if ( '' === $rt || ! isset( $runtimes[ $rt ] ) ) {
			$keys = array_keys( $runtimes );
			$rt   = (string) ( $keys[0] ?? '' );
		}
		$runtime_url = (string) ( $runtimes[ $rt ] ?? '' );
		if ( '' === $runtime_url ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'Invalid runtime.' ), 400 );
		}

		$ts      = time();
		$payload = $slug . '|' . (string) $ts;
		$token   = self::hmac( $payload );

		$endpoint = rtrim( $runtime_url, '/' ) . '/wp-json/hmps/v1/runtime/apply';
		$args     = array(
			'timeout' => 120,
			'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'    => wp_json_encode(
				array(
					'slug'  => $slug,
					'ts'    => $ts,
					'token' => $token,
				)
			),
		);

		$res = wp_remote_post( $endpoint, $args );
		if ( is_wp_error( $res ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => $res->get_error_message(),
				),
				500
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 || ! is_array( $data ) || empty( $data['ok'] ) ) {
			$msg = is_array( $data ) && ! empty( $data['message'] ) ? (string) $data['message'] : 'Runtime apply failed.';
			return new WP_REST_Response( array( 'ok' => false, 'message' => $msg, 'runtime' => $runtime_url ), 500 );
		}

		return new WP_REST_Response(
			array(
				'ok'          => true,
				'runtime'     => $runtime_url,
				'redirect_to' => isset( $data['redirect_to'] ) ? (string) $data['redirect_to'] : $runtime_url,
			),
			200
		);
	}

	public static function handle_runtime_apply( WP_REST_Request $req ) : WP_REST_Response {
		$slug  = sanitize_title( (string) $req->get_param( 'slug' ) );
		$ts    = (int) $req->get_param( 'ts' );
		$token = (string) $req->get_param( 'token' );

		if ( '' === $slug || $ts <= 0 || '' === $token ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'Bad request.' ), 400 );
		}

		// Allow a small time window to prevent simple replays.
		if ( abs( time() - $ts ) > 300 ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'Token expired.' ), 403 );
		}

		$payload  = $slug . '|' . (string) $ts;
		$expected = self::hmac( $payload );
		if ( ! hash_equals( $expected, $token ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'Invalid token.' ), 403 );
		}

		// Apply via hmpro-demo-kurulum plugin.
		if ( ! function_exists( 'hmpro_demo_run_apply' ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => 'hmpro_demo_run_apply() not found. Activate hmpro-demo-kurulum plugin on runtime.',
				),
				500
			);
		}

		try {
			$result = hmpro_demo_run_apply( $slug, array( 'silent' => true ) );
		} catch ( Exception $e ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $e->getMessage() ), 500 );
		}

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 500 );
		}

		return new WP_REST_Response(
			array(
				'ok'          => true,
				'redirect_to' => home_url( '/' ),
			),
			200
		);
	}
}
