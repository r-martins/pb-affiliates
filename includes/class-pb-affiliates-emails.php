<?php
/**
 * Transactional emails (wc_mail).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Emails
 */
class PB_Affiliates_Emails {

	/**
	 * @param int  $user_id User ID.
	 * @param bool $pending Pending approval.
	 */
	public static function send_affiliate_registered( $user_id, $pending = false ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$to = $user->user_email;
		/* translators: %s: site name */
		$subject = $pending
			? sprintf( __( '[%s] Cadastro de afiliado recebido', 'pb-affiliates' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) )
			: sprintf( __( '[%s] Bem-vindo ao programa de afiliados', 'pb-affiliates' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );

		$heading = $pending
			? __( 'Cadastro de afiliado', 'pb-affiliates' )
			: __( 'Programa de afiliados', 'pb-affiliates' );

		ob_start();
		include PB_AFFILIATES_PATH . 'templates/emails/plain/affiliate-registered.php';
		$body = ob_get_clean();
		if ( ! $body ) {
			$body = self::default_registered_body( $user, $pending );
		}
		self::send( $to, $subject, $body, $heading );
	}

	/**
	 * @param WC_Order $order Order.
	 * @param int      $affiliate_id Affiliate ID.
	 * @param float    $amount Commission amount.
	 */
	public static function send_new_sale( $order, $affiliate_id, $amount ) {
		$user = get_userdata( $affiliate_id );
		if ( ! $user ) {
			return;
		}
		$to = $user->user_email;
		/* translators: %s: order number */
		$subject = sprintf(
			__( '[%1$s] Nova comissão — pedido %2$s', 'pb-affiliates' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$order->get_order_number()
		);
		$body = sprintf(
			// translators: 1: amount HTML, 2: order number, 3: affiliate code.
			__( 'Olá, você tem uma nova comissão de %1$s no pedido %2$s. Código de afiliado: %3$s.', 'pb-affiliates' ),
			wp_kses_post( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ),
			esc_html( $order->get_order_number() ),
			esc_html( (string) $order->get_meta( '_pb_affiliate_code' ) )
		);
		self::send( $to, $subject, $body, __( 'Nova comissão', 'pb-affiliates' ) );
	}

	/**
	 * @param int    $affiliate_id Affiliate ID.
	 * @param float  $amount       Amount.
	 * @param string $proof_notes  Comprovante / referência (opcional), ex.: após saque manual no admin.
	 */
	public static function send_commission_paid( $affiliate_id, $amount, $proof_notes = '' ) {
		$user = get_userdata( $affiliate_id );
		if ( ! $user ) {
			return;
		}
		$to = $user->user_email;
		$subject = sprintf(
			__( '[%s] Comissão paga', 'pb-affiliates' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$body = sprintf(
			'<p>%s</p>',
			sprintf(
				/* translators: %s: formatted amount */
				__( 'Sua comissão de %s foi marcada como paga.', 'pb-affiliates' ),
				wp_kses_post( wc_price( $amount, array( 'currency' => get_woocommerce_currency() ) ) )
			)
		);
		$proof_notes = is_string( $proof_notes ) ? trim( $proof_notes ) : '';
		if ( $proof_notes !== '' ) {
			$body .= '<p><strong>' . esc_html__( 'Detalhes do comprovante / referência informados pela loja:', 'pb-affiliates' ) . '</strong></p>';
			$body .= '<p style="white-space:pre-wrap;">' . esc_html( $proof_notes ) . '</p>';
		}
		self::send( $to, $subject, $body, __( 'Comissão paga', 'pb-affiliates' ) );
	}

	/**
	 * @param \WP_User $user User.
	 * @param bool     $pending Pending.
	 * @return string
	 */
	protected static function default_registered_body( $user, $pending ) {
		if ( $pending ) {
			return sprintf(
				__( 'Olá %s, seu cadastro de afiliado foi recebido e aguarda aprovação.', 'pb-affiliates' ),
				$user->display_name
			);
		}
		return sprintf(
			__( 'Olá %s, seu cadastro de afiliado está ativo.', 'pb-affiliates' ),
			$user->display_name
		);
	}

	/**
	 * Envia e-mail com modelo WooCommerce (cabeçalho, rodapé e CSS inline da loja).
	 *
	 * @param string $to             Destinatário.
	 * @param string $subject        Assunto.
	 * @param string $message_inner  Corpo (texto ou HTML limitado); será passado por woocommerce_email_*.
	 * @param string $email_heading  Título exibido no bloco do cabeçalho do WC.
	 */
	protected static function send( $to, $subject, $message_inner, $email_heading = '' ) {
		$subject_dec = wp_specialchars_decode( $subject, ENT_QUOTES );
		if ( function_exists( 'wc_mail' ) && function_exists( 'WC' ) && WC()->mailer() ) {
			$heading = '' !== $email_heading ? $email_heading : wp_strip_all_tags( $subject_dec );
			$wrapped = WC()->mailer()->wrap_message( $heading, $message_inner );
			wc_mail( $to, $subject_dec, $wrapped );
			return;
		}
		wp_mail( $to, $subject_dec, $message_inner, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}
}
