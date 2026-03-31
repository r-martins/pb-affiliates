<?php
/**
 * Email plain: affiliate registered.
 *
 * @package PB_Affiliates
 * @var WP_User $user User.
 * @var bool    $pending Pending.
 */

defined( 'ABSPATH' ) || exit;

if ( $pending ) {
	echo esc_html(
		sprintf(
			/* translators: %s: user display name */
			__( 'Olá %s, seu cadastro de afiliado foi recebido e aguarda aprovação.', 'pb-affiliates' ),
			$user->display_name
		)
	);
} else {
	echo esc_html(
		sprintf(
			/* translators: %s: user display name */
			__( 'Olá %s, seu cadastro de afiliado está ativo.', 'pb-affiliates' ),
			$user->display_name
		)
	);
}
