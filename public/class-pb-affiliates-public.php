<?php
/**
 * Front-end: domínio, opt-in afiliado.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Public
 */
class PB_Affiliates_Public {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_affiliate_area_actions' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_domain_actions' ), 10 );
	}

	/**
	 * Opt-in, alteração de código (POST na área do afiliado).
	 */
	public static function handle_affiliate_area_actions() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		if ( ! isset( $_POST['pb_aff_area_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pb_aff_area_nonce'] ) ), 'pb_aff_area' ) ) {
			return;
		}
		$action = isset( $_POST['pb_aff_area_action'] ) ? sanitize_key( wp_unslash( $_POST['pb_aff_area_action'] ) ) : '';
		$uid    = get_current_user_id();

		if ( 'join' === $action ) {
			$notice_key  = '';
			$notice_type = 'success';

			$settings = PB_Affiliates_Settings::get();
			$terms_id = absint( $settings['terms_page_id'] ?? 0 );
			if ( $terms_id && empty( $_POST['pb_aff_accept_terms'] ) ) {
				wc_add_notice( __( 'Aceite os termos para continuar.', 'pb-affiliates' ), 'error' );
				$notice_key  = 'join_terms_required';
				$notice_type = 'error';
			} elseif ( ! PB_Affiliates_Role::user_can_opt_in_affiliate( $uid ) ) {
				wc_add_notice( __( 'Não é possível concluir o pedido.', 'pb-affiliates' ), 'error' );
				$notice_key  = 'join_not_allowed';
				$notice_type = 'error';
			} else {
				PB_Affiliates_Role::enroll_from_my_account( $uid );
				if ( PB_Affiliates_Role::user_is_pending_affiliate( $uid ) ) {
					wc_add_notice( __( 'Pedido enviado. Aguarde a aprovação da loja.', 'pb-affiliates' ), 'success' );
					$notice_key  = 'join_pending';
					$notice_type = 'success';
				} else {
					wc_add_notice( __( 'Bem-vindo ao programa de afiliados! Seu link está disponível abaixo.', 'pb-affiliates' ), 'success' );
					$notice_key  = 'join_active';
					$notice_type = 'success';
				}
			}

			$redirect_url = wc_get_account_endpoint_url( PB_Affiliates_Account::ENDPOINT );
			if ( '' !== $notice_key ) {
				$redirect_url = add_query_arg(
					array(
						'pb_aff_area_notice'      => $notice_key,
						'pb_aff_area_notice_type' => $notice_type,
					),
					$redirect_url
				);
			}
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( 'change_code' === $action ) {
			$notice_key  = '';
			$notice_type = 'success';

			if ( ! PB_Affiliates_Role::user_is_affiliate( $uid ) ) {
				wc_add_notice( __( 'Apenas afiliados ativos podem alterar o identificador.', 'pb-affiliates' ), 'error' );
				$notice_key  = 'code_not_active';
				$notice_type = 'error';
			} else {
				$new = isset( $_POST['pb_aff_new_code'] ) ? wp_unslash( $_POST['pb_aff_new_code'] ) : '';
				$new = PB_Affiliates_Attribution::sanitize_affiliate_code( $new );
				if ( strlen( $new ) < 3 ) {
					wc_add_notice( __( 'O identificador deve ter pelo menos 3 caracteres (letras minúsculas, números, _ ou -).', 'pb-affiliates' ), 'error' );
					$notice_key  = 'code_too_short';
					$notice_type = 'error';
				} elseif ( ! PB_Affiliates_Attribution::is_code_available( $new, $uid ) ) {
					wc_add_notice( __( 'Este identificador já está em uso. Escolha outro.', 'pb-affiliates' ), 'error' );
					$notice_key  = 'code_in_use';
					$notice_type = 'error';
				} else {
					update_user_meta( $uid, 'pb_affiliate_code', $new );
					wc_add_notice( __( 'Identificador atualizado.', 'pb-affiliates' ), 'success' );
					$notice_key  = 'code_updated';
					$notice_type = 'success';
				}
			}

			$redirect_url = wc_get_account_endpoint_url( PB_Affiliates_Account::ENDPOINT );
			if ( '' !== $notice_key ) {
				$redirect_url = add_query_arg(
					array(
						'pb_aff_area_notice'      => $notice_key,
						'pb_aff_area_notice_type' => $notice_type,
					),
					$redirect_url
				);
			}
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( 'request_withdrawal' === $action ) {
			$notice_key  = '';
			$notice_type = 'success';

			if ( ! PB_Affiliates_Role::user_is_affiliate( $uid ) ) {
				wc_add_notice( __( 'Apenas afiliados ativos podem solicitar saque.', 'pb-affiliates' ), 'error' );
				$notice_key  = 'withdraw_not_active';
				$notice_type = 'error';
			} else {
				$amt = isset( $_POST['pb_aff_withdraw_amount'] ) ? wp_unslash( $_POST['pb_aff_withdraw_amount'] ) : '';
				$amt = (float) wc_format_decimal( is_string( $amt ) ? $amt : '' );
				$res = PB_Affiliates_Withdrawal::request( $uid, $amt );
				if ( is_wp_error( $res ) ) {
					wc_add_notice( $res->get_error_message(), 'error' );
					$notice_key  = 'withdraw_error';
					$notice_type = 'error';
				} else {
					wc_add_notice( __( 'Pedido de saque enviado. A loja irá processar e registrar o pagamento.', 'pb-affiliates' ), 'success' );
					$notice_key  = 'withdraw_requested';
					$notice_type = 'success';
				}
			}

			$redirect_url = wc_get_account_endpoint_url( PB_Affiliates_Account::ENDPOINT );
			if ( '' !== $notice_key ) {
				$redirect_url = add_query_arg(
					array(
						'pb_aff_area_notice'      => $notice_key,
						'pb_aff_area_notice_type' => $notice_type,
					),
					$redirect_url
				);
			}
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Add domain / validate from affiliate dashboard.
	 */
	public static function handle_domain_actions() {
		if ( ! isset( $_POST['pb_aff_domain_nonce'] ) || ! is_user_logged_in() ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pb_aff_domain_nonce'] ) ), 'pb_aff_domain' ) ) {
			return;
		}
		if ( ! isset( $_POST['pb_aff_domain_action'] ) ) {
			return;
		}
		$uid = get_current_user_id();
		if ( ! PB_Affiliates_Role::user_is_affiliate( $uid ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['pb_aff_domain_action'] ) );
		if ( 'add' === $action && ! empty( $_POST['pb_aff_domain_url'] ) ) {
			$url = sanitize_text_field( wp_unslash( $_POST['pb_aff_domain_url'] ) );
			$res = PB_Affiliates_Domain_Verify::add_pending_domain( $uid, $url );
			if ( is_wp_error( $res ) ) {
				wc_add_notice( $res->get_error_message(), 'error' );
			} else {
				wc_add_notice( __( 'Site adicionado. Publique o arquivo de verificação conforme indicado na lista abaixo e clique em Validar.', 'pb-affiliates' ), 'success' );
			}
		}
		if ( 'validate' === $action ) {
			$host = isset( $_POST['pb_aff_domain_host'] ) ? sanitize_text_field( wp_unslash( $_POST['pb_aff_domain_host'] ) ) : '';
			if ( '' === $host ) {
				wc_add_notice( __( 'Indique qual domínio deseja validar.', 'pb-affiliates' ), 'error' );
			} else {
				$res = PB_Affiliates_Domain_Verify::validate_domain( $uid, $host );
				if ( is_wp_error( $res ) ) {
					wc_add_notice( $res->get_error_message(), 'error' );
				} else {
					wc_add_notice(
						__( 'Domínio validado com sucesso. Você pode remover o arquivo de verificação da pasta .well-known no seu site, se desejar.', 'pb-affiliates' ),
						'success'
					);
				}
			}
		}
		if ( 'remove' === $action ) {
			$host = isset( $_POST['pb_aff_domain_host'] ) ? sanitize_text_field( wp_unslash( $_POST['pb_aff_domain_host'] ) ) : '';
			if ( '' === $host ) {
				wc_add_notice( __( 'Indique qual site remover.', 'pb-affiliates' ), 'error' );
			} else {
				$res = PB_Affiliates_Domain_Verify::remove_domain( $uid, $host );
				if ( is_wp_error( $res ) ) {
					wc_add_notice( $res->get_error_message(), 'error' );
				} else {
					wc_add_notice( __( 'Site removido com sucesso.', 'pb-affiliates' ), 'success' );
				}
			}
		}
		wp_safe_redirect( wc_get_account_endpoint_url( PB_Affiliates_Account::ENDPOINT ) );
		exit;
	}
}
