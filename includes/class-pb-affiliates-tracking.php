<?php
/**
 * Cookie and URL parameter tracking (front-end via Ajax — compatível com cache de página inteira).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Tracking
 */
class PB_Affiliates_Tracking {

	const COOKIE_NAME = 'pb_affiliate_ref';

	/**
	 * Ação Ajax registrada em admin-ajax (POST field `action`).
	 */
	const AJAX_ACTION = 'pb_aff_track';

	/**
	 * Nonce para o pedido de tracking.
	 */
	const AJAX_NONCE_ACTION = 'pb_aff_track';

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_track_referral' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_track_referral' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_tracker' ), 20 );
	}

	/**
	 * Script que lê a URL atual e document.referrer e chama admin-ajax (não passa por HTML em cache).
	 */
	public static function enqueue_frontend_tracker() {
		if ( is_admin() ) {
			return;
		}
		if ( ! apply_filters( 'pb_affiliates_enqueue_tracking_script', true ) ) {
			return;
		}
		$settings = PB_Affiliates_Settings::get();
		$param    = sanitize_key( $settings['referral_param'] ?? 'pid' );

		wp_register_script(
			'pb-affiliates-track',
			PB_AFFILIATES_URL . 'assets/js/affiliate-track.js',
			array(),
			PB_AFFILIATES_VERSION,
			true
		);
		wp_enqueue_script( 'pb-affiliates-track' );
		wp_localize_script(
			'pb-affiliates-track',
			'pbAffiliateTrack',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::AJAX_NONCE_ACTION ),
				'param'   => $param,
				'action'  => self::AJAX_ACTION,
			)
		);
	}

	/**
	 * Recebe código (parâmetro na URL) e/ou referrer; define cookie HttpOnly se houver match.
	 */
	public static function ajax_track_referral() {
		check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce' );

		nocache_headers();

		$code         = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$referrer_url = isset( $_POST['referrer_url'] ) ? esc_url_raw( wp_unslash( $_POST['referrer_url'] ) ) : '';
		$page_url     = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';

		$code = is_string( $code ) ? trim( $code ) : '';
		if ( strlen( $referrer_url ) > 2048 ) {
			$referrer_url = substr( $referrer_url, 0, 2048 );
		}
		if ( strlen( $page_url ) > 2048 ) {
			$page_url = substr( $page_url, 0, 2048 );
		}

		if ( '' === $code && '' === $referrer_url ) {
			wp_send_json_success(
				array(
					'skipped' => true,
					'reason'  => 'empty',
				)
			);
		}

		if ( '' === $page_url || ! self::is_allowed_page_url_for_tracking( $page_url ) ) {
			wp_send_json_error( array( 'message' => 'invalid_page_url' ), 403 );
		}

		if ( class_exists( 'PB_Affiliates_Click_Log', false ) ) {
			PB_Affiliates_Click_Log::set_tracking_context( $page_url, $referrer_url );
		}
		try {
			$result = self::attempt_capture_from_inputs( $code, $referrer_url );
		} finally {
			if ( class_exists( 'PB_Affiliates_Click_Log', false ) ) {
				PB_Affiliates_Click_Log::clear_tracking_context();
			}
		}

		wp_send_json_success( $result );
	}

	/**
	 * Mesma prioridade do fluxo antigo em PHP: parâmetro na URL, depois host verificado no referrer.
	 *
	 * @param string $code         Valor do parâmetro de indicação (ex.: código público).
	 * @param string $referrer_url URL completa típica de document.referrer.
	 * @return array{matched: bool, via: string}
	 */
	public static function attempt_capture_from_inputs( $code, $referrer_url ) {
		$out = array(
			'matched' => false,
			'via'     => '',
		);
		if ( is_string( $code ) && $code !== '' ) {
			$aff_id = PB_Affiliates_Attribution::get_affiliate_id_by_code( $code );
			if ( $aff_id && PB_Affiliates_Role::user_is_affiliate( $aff_id ) ) {
				self::set_cookie( $aff_id, $code, 'cookie_param' );
				$out['matched'] = true;
				$out['via']     = 'cookie_param';
				return $out;
			}
		}

		if ( is_string( $referrer_url ) && $referrer_url !== '' ) {
			$parts = wp_parse_url( $referrer_url );
			$host  = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
			if ( $host ) {
				$match = PB_Affiliates_Domain_Verify::find_affiliate_by_verified_host( $host );
				if ( $match ) {
					list( $aff_id, $aff_code ) = $match;
					self::set_cookie( $aff_id, $aff_code, 'referrer' );
					$out['matched'] = true;
					$out['via']     = 'referrer';
				}
			}
		}

		return $out;
	}

	/**
	 * Garante que `page_url` é da mesma loja (evita abuso do endpoint).
	 *
	 * @param string $url URL sanitizada.
	 * @return bool
	 */
	private static function is_allowed_page_url_for_tracking( $url ) {
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return false;
		}
		$h = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $h ) || '' === $h ) {
			return false;
		}
		$h = strtolower( trim( $h ) );
		if ( 0 === strpos( $h, 'www.' ) ) {
			$h = substr( $h, 4 );
		}

		$bases = array_unique(
			array_filter(
				array(
					home_url( '/' ),
					site_url( '/' ),
				)
			)
		);
		foreach ( $bases as $base ) {
			$b = wp_parse_url( $base, PHP_URL_HOST );
			if ( ! is_string( $b ) || '' === $b ) {
				continue;
			}
			$b = strtolower( trim( $b ) );
			if ( 0 === strpos( $b, 'www.' ) ) {
				$b = substr( $b, 4 );
			}
			if ( $h === $b ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Set cookie with first/last logic.
	 *
	 * @param int    $affiliate_id Affiliate user ID.
	 * @param string $code Code.
	 * @param string $via Via.
	 */
	public static function set_cookie( $affiliate_id, $code, $via ) {
		$settings = PB_Affiliates_Settings::get();
		$days     = absint( $settings['cookie_days'] ?? 30 );
		$mode     = $settings['attribution'] ?? 'last';

		$referer_host = '';
		if ( class_exists( 'PB_Affiliates_Click_Log' ) ) {
			$referer_host = (string) PB_Affiliates_Click_Log::get_referer_host_for_log();
		}

		$payload = array(
			'id'           => (int) $affiliate_id,
			'code'         => $code,
			'via'          => sanitize_key( $via ),
			'ts'           => time(),
			'referer_host' => substr( $referer_host, 0, 190 ),
		);

		$existing = self::get_cookie_data();
		if ( 'first' === $mode && $existing && ! empty( $existing['id'] ) ) {
			return;
		}

		$secure = is_ssl();
		setcookie(
			self::COOKIE_NAME,
			wp_json_encode( $payload ),
			time() + ( $days * DAY_IN_SECONDS ),
			COOKIEPATH ? COOKIEPATH : '/',
			COOKIE_DOMAIN,
			$secure,
			true
		);
		$_COOKIE[ self::COOKIE_NAME ] = wp_json_encode( $payload ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( class_exists( 'PB_Affiliates_Click_Log' ) ) {
			PB_Affiliates_Click_Log::log( (int) $affiliate_id, $via );
		}
	}

	/**
	 * @return array|null
	 */
	public static function get_cookie_data() {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return null;
		}
		$raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		$dec = json_decode( $raw, true );
		return is_array( $dec ) ? $dec : null;
	}
}
