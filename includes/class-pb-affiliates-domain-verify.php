<?php
/**
 * Verified referral domains per affiliate.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Domain_Verify
 */
class PB_Affiliates_Domain_Verify {

	const META = 'pb_affiliate_verified_domains';

	/**
	 * Find affiliate user id + code by matching host to a verified domain.
	 *
	 * @param string $host Lowercase host.
	 * @return array{0:int,1:string}|null
	 */
	public static function find_affiliate_by_verified_host( $host ) {
		global $wpdb;
		$host = strtolower( preg_replace( '/^www\./', '', $host ) );
		if ( '' === $host ) {
			return null;
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
				self::META
			),
			ARRAY_A
		);
		foreach ( $rows as $row ) {
			$domains = maybe_unserialize( $row['meta_value'] );
			if ( ! is_array( $domains ) ) {
				continue;
			}
			foreach ( $domains as $d ) {
				if ( empty( $d['verified'] ) || empty( $d['host'] ) ) {
					continue;
				}
				$dh = strtolower( preg_replace( '/^www\./', '', $d['host'] ) );
				if ( $dh === $host ) {
					$uid  = (int) $row['user_id'];
					$code = get_user_meta( $uid, 'pb_affiliate_code', true );
					return array( $uid, $code ? $code : '' );
				}
			}
		}
		return null;
	}

	/**
	 * Start verification: generate token.
	 *
	 * @param int    $user_id User ID.
	 * @param string $input_url URL or domain.
	 * @return string|\WP_Error Token or error.
	 */
	public static function add_pending_domain( $user_id, $input_url ) {
		$parts = wp_parse_url( $input_url );
		if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
			$host = $parts['host'];
		} elseif ( ! empty( $parts['host'] ) ) {
			$host = $parts['host'];
		} else {
			$host = preg_replace( '#^https?://#', '', $input_url );
			$host = preg_replace( '#/.*$#', '', $host );
		}
		$host = strtolower( preg_replace( '/^www\./', '', sanitize_text_field( $host ) ) );
		if ( ! $host || ! preg_match( '/^[a-z0-9.-]+$/i', $host ) ) {
			return new \WP_Error( 'invalid', __( 'Domínio inválido.', 'pb-affiliates' ) );
		}

		$token   = wp_generate_password( 32, false, false );
		$domains = get_user_meta( $user_id, self::META, true );
		if ( ! is_array( $domains ) ) {
			$domains = array();
		}
		foreach ( $domains as $d ) {
			if ( ! empty( $d['host'] ) && $d['host'] === $host ) {
				return new \WP_Error( 'duplicate', __( 'Este domínio já está na lista.', 'pb-affiliates' ) );
			}
		}
		$domains[] = array(
			'host'       => $host,
			'token'      => $token,
			'verified'   => false,
			'created_at' => current_time( 'mysql' ),
		);
		update_user_meta( $user_id, self::META, $domains );
		return $token;
	}

	/**
	 * Validate file exists at https://host/.well-known/pb-affiliate-{token}.txt
	 *
	 * @param int    $user_id User ID.
	 * @param string $host Host to check (last pending or specified).
	 * @return bool|\WP_Error
	 */
	public static function validate_domain( $user_id, $host = '' ) {
		$domains = get_user_meta( $user_id, self::META, true );
		if ( ! is_array( $domains ) || empty( $domains ) ) {
			return new \WP_Error( 'none', __( 'Nenhum domínio pendente.', 'pb-affiliates' ) );
		}
		$idx = null;
		if ( $host ) {
			$host = strtolower( preg_replace( '/^www\./', '', sanitize_text_field( $host ) ) );
			foreach ( $domains as $i => $d ) {
				if ( isset( $d['host'] ) && $d['host'] === $host ) {
					$idx = $i;
					break;
				}
			}
			if ( null === $idx ) {
				return new \WP_Error( 'not_found', __( 'Domínio não encontrado na lista.', 'pb-affiliates' ) );
			}
		} else {
			$idx = count( $domains ) - 1;
		}
		$row = $domains[ $idx ];
		if ( ! empty( $row['verified'] ) ) {
			return true;
		}
		$h     = $row['host'];
		$token = $row['token'];
		$url   = 'https://' . $h . '/.well-known/pb-affiliate-' . $token . '.txt';
		$res   = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => true,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = trim( (string) wp_remote_retrieve_body( $res ) );
		if ( 200 !== (int) $code || $body !== $token ) {
			return new \WP_Error( 'verify_fail', __( 'Não foi possível validar o arquivo no domínio.', 'pb-affiliates' ) );
		}
		$domains[ $idx ]['verified']   = true;
		$domains[ $idx ]['verified_at'] = current_time( 'mysql' );
		update_user_meta( $user_id, self::META, $domains );
		return true;
	}

	/**
	 * Remove a domain entry by host.
	 *
	 * @param int    $user_id User ID.
	 * @param string $host    Normalized host (same as stored).
	 * @return bool|\WP_Error
	 */
	public static function remove_domain( $user_id, $host ) {
		$host = strtolower( preg_replace( '/^www\./', '', sanitize_text_field( $host ) ) );
		if ( '' === $host ) {
			return new \WP_Error( 'invalid', __( 'Domínio inválido.', 'pb-affiliates' ) );
		}
		$domains = get_user_meta( $user_id, self::META, true );
		if ( ! is_array( $domains ) || empty( $domains ) ) {
			return new \WP_Error( 'none', __( 'Nenhum domínio na lista.', 'pb-affiliates' ) );
		}
		$new   = array();
		$found = false;
		foreach ( $domains as $d ) {
			if ( isset( $d['host'] ) && $d['host'] === $host ) {
				$found = true;
				continue;
			}
			$new[] = $d;
		}
		if ( ! $found ) {
			return new \WP_Error( 'not_found', __( 'Domínio não encontrado na lista.', 'pb-affiliates' ) );
		}
		update_user_meta( $user_id, self::META, $new );
		return true;
	}
}
