<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HMPS_Player_API {
	public static function run_apply( WP_REST_Request $request ) : WP_REST_Response {
		$slug = sanitize_title( (string) $request->get_param( 'slug' ) );
		if ( '' === $slug ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'Missing slug.' ), 400 );
		}

		try {
			$result = hmpro_demo_run_apply( $slug, array( 'silent' => true ) );
		} catch ( Exception $e ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $e->getMessage() ), 500 );
		}

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 500 );
		}

		// hmpro_demo_run_apply() returns an array with ok=false on failure (not WP_Error).
		if ( is_array( $result ) ) {
			if ( isset( $result['ok'] ) && false === (bool) $result['ok'] ) {
				$msg = isset( $result['error'] ) ? (string) $result['error'] : 'Apply failed.';
				return new WP_REST_Response( array( 'ok' => false, 'message' => $msg ), 500 );
			}
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
