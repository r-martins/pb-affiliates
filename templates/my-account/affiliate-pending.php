<?php
/**
 * Afiliado aguardando aprovação.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'wc_print_notices' ) ) {
	wc_print_notices();
}
?>
<p><?php esc_html_e( 'Seu pedido para participar do programa de afiliados está aguardando aprovação da loja. Você receberá um e-mail quando for analisado.', 'pb-affiliates' ); ?></p>
