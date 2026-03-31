<?php
/**
 * Estado do afiliado (meta) — sem papel WordPress dedicado.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Role
 */
class PB_Affiliates_Role {

	const STATUS_NONE = 'none';

	const STATUS_PENDING = 'pending';

	const STATUS_ACTIVE = 'active';

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu_items' ), 40 );
	}

	/**
	 * Item "Área do afiliado" para qualquer usuário com sessão (opt-in dentro do endpoint).
	 *
	 * @param array $items Items.
	 * @return array
	 */
	public static function account_menu_items( $items ) {
		if ( ! is_user_logged_in() ) {
			return $items;
		}
		$aff_items = array(
			'affiliate-area'  => __( 'Área do afiliado', 'pb-affiliates' ),
			'affiliate-links' => __( 'Links de afiliados', 'pb-affiliates' ),
		);
		return array_merge(
			array_slice( $items, 0, 1, true ),
			$aff_items,
			array_slice( $items, 1, null, true )
		);
	}

	/**
	 * Meta de estado do afiliado.
	 *
	 * @param int $user_id User ID.
	 * @return string none|pending|active|''
	 */
	public static function get_affiliate_status( $user_id ) {
		$st = get_user_meta( (int) $user_id, 'pb_affiliate_status', true );
		if ( '' === $st ) {
			return '';
		}
		return (string) $st;
	}

	/**
	 * Afiliado ativo (pode receber comissões).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_is_affiliate( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}
		return self::STATUS_ACTIVE === self::get_affiliate_status( $user_id );
	}

	/**
	 * Aguarda aprovação do administrador.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_is_pending_affiliate( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}
		return self::STATUS_PENDING === self::get_affiliate_status( $user_id );
	}

	/**
	 * Está no programa (ativo ou pendente).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_is_active_or_pending_affiliate( $user_id ) {
		return self::user_is_affiliate( $user_id ) || self::user_is_pending_affiliate( $user_id );
	}

	/**
	 * Pode solicitar entrada no programa (ainda não é afiliado nem pendente).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_can_opt_in_affiliate( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}
		$st = self::get_affiliate_status( $user_id );
		if ( self::STATUS_ACTIVE === $st || self::STATUS_PENDING === $st ) {
			return false;
		}
		return true;
	}

	/**
	 * Aprovar afiliado (admin).
	 *
	 * @param int $user_id User ID.
	 */
	public static function approve_affiliate( $user_id ) {
		update_user_meta( $user_id, 'pb_affiliate_status', self::STATUS_ACTIVE );
		self::ensure_affiliate_code( $user_id );
	}

	/**
	 * Rejeitar ou remover do programa (estado none).
	 *
	 * @param int $user_id User ID.
	 */
	public static function reject_affiliate( $user_id ) {
		update_user_meta( $user_id, 'pb_affiliate_status', self::STATUS_NONE );
	}

	/**
	 * Gera código público único se ainda não existir.
	 * Padrão: nome do utilizador em minúsculas (sanitizado); se colidir, sufixo numérico aleatório.
	 *
	 * @param int $user_id User ID.
	 */
	public static function ensure_affiliate_code( $user_id ) {
		if ( get_user_meta( $user_id, 'pb_affiliate_code', true ) ) {
			return;
		}
		$user_id = (int) $user_id;
		$stem    = self::default_affiliate_code_stem_for_user( $user_id );
		$code    = $stem;
		$tries   = 0;
		while ( ! PB_Affiliates_Attribution::is_code_available( $code, $user_id ) && $tries < 80 ) {
			$code = PB_Affiliates_Attribution::sanitize_affiliate_code( $stem . (string) wp_rand( 1, 999999 ) );
			++$tries;
		}
		if ( ! PB_Affiliates_Attribution::is_code_available( $code, $user_id ) ) {
			$code = PB_Affiliates_Attribution::sanitize_affiliate_code( 'aff' . wp_generate_password( 10, false ) );
		}
		$tries = 0;
		while ( strlen( $code ) < 3 && $tries < 30 ) {
			$code = PB_Affiliates_Attribution::sanitize_affiliate_code( 'aff' . wp_generate_password( 10, false ) );
			++$tries;
		}
		if ( strlen( $code ) < 3 ) {
			$code = PB_Affiliates_Attribution::sanitize_affiliate_code( 'u' . $user_id . wp_rand( 100, 999 ) );
		}
		update_user_meta( $user_id, 'pb_affiliate_code', $code );
	}

	/**
	 * Nome base para o código (já sanitizado como código de afiliado), até 34 caracteres para caber sufixo numérico.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private static function default_affiliate_code_stem_for_user( $user_id ) {
		$user_id = (int) $user_id;
		$label   = '';
		$user    = get_userdata( $user_id );

		if ( $user ) {
			$first = trim( (string) get_user_meta( $user_id, 'first_name', true ) );
			$last  = trim( (string) get_user_meta( $user_id, 'last_name', true ) );
			if ( '' !== $first || '' !== $last ) {
				$label = trim( $first . ' ' . $last );
			}
			if ( '' === $label ) {
				$label = trim( (string) $user->display_name );
			}
			if ( '' === $label ) {
				$label = trim( (string) $user->user_login );
			}
			if ( '' === $label ) {
				$label = trim( (string) $user->user_nicename );
			}
		}

		$base = PB_Affiliates_Attribution::sanitize_affiliate_code( $label );
		if ( strlen( $base ) < 3 ) {
			$base = PB_Affiliates_Attribution::sanitize_affiliate_code( 'user' . $user_id );
		}
		if ( strlen( $base ) < 3 ) {
			$base = PB_Affiliates_Attribution::sanitize_affiliate_code( 'u' . $user_id );
		}
		// Reserva espaço para até 6 dígitos no sufixo (limite 40 do sanitize).
		return strlen( $base ) > 34 ? substr( $base, 0, 34 ) : $base;
	}

	/**
	 * Entrada no programa a partir de Minha conta (sem papel WP).
	 *
	 * @param int $user_id User ID.
	 */
	public static function enroll_from_my_account( $user_id ) {
		$settings = PB_Affiliates_Settings::get();
		if ( 'auto' === ( $settings['affiliate_registration'] ?? 'auto' ) ) {
			update_user_meta( $user_id, 'pb_affiliate_status', self::STATUS_ACTIVE );
			self::ensure_affiliate_code( $user_id );
			PB_Affiliates_Emails::send_affiliate_registered( $user_id, false );
		} else {
			update_user_meta( $user_id, 'pb_affiliate_status', self::STATUS_PENDING );
			PB_Affiliates_Emails::send_affiliate_registered( $user_id, true );
		}
	}
}
