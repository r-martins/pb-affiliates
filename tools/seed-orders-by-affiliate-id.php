#!/usr/bin/env php
<?php
// phpcs:ignoreFile -- CLI development utility; excluded from distribution ZIP (.distignore).

/**
 * Cria pedidos WooCommerce de teste com atribuição e registro de comissão para um afiliado (ID de usuário).
 *
 * Uso (raiz WP em `woocommerce/`):
 *   php wp-content/plugins/pb-affiliates/tools/seed-orders-by-affiliate-id.php 30 40
 *
 * Docker:
 *   docker compose exec wordpress php /var/www/html/wp-content/plugins/pb-affiliates/tools/seed-orders-by-affiliate-id.php 30 40
 *
 * @package PB_Affiliates
 */

if ( php_sapi_name() !== 'cli' ) {
	fwrite( STDERR, "Execute apenas via CLI.\n" );
	exit( 1 );
}

$wp_root = dirname( __DIR__, 4 );
if ( ! is_readable( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "wp-load.php não encontrado em: {$wp_root}\n" );
	exit( 1 );
}

require $wp_root . '/wp-load.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

if ( ! function_exists( 'wc_create_order' ) || ! class_exists( 'PB_Affiliates_Commission', false ) ) {
	fwrite( STDERR, "WooCommerce ou PB Afiliados não está carregado.\n" );
	exit( 1 );
}

$aff_id = isset( $argv[1] ) ? absint( $argv[1] ) : 0;
$count  = isset( $argv[2] ) ? max( 1, absint( $argv[2] ) ) : 40;

if ( $aff_id <= 0 ) {
	fwrite( STDERR, "Uso: php seed-orders-by-affiliate-id.php <user_id> [quantidade]\n" );
	exit( 1 );
}

$user = get_userdata( $aff_id );
if ( ! $user ) {
	fwrite( STDERR, "Usuário #{$aff_id} não existe.\n" );
	exit( 1 );
}

$aff_code = (string) get_user_meta( $aff_id, 'pb_affiliate_code', true );
if ( '' === $aff_code ) {
	$aff_code = 'seed_' . $aff_id;
}

if ( ! PB_Affiliates_Role::user_is_affiliate( $aff_id ) ) {
	fwrite( STDERR, "Aviso: usuário #{$aff_id} não está com estado de afiliado ativo; create_commission_for_order pode não registrar comissão.\n" );
}

$find_product_id = static function () {
	$ids = wc_get_products(
		array(
			'limit'   => 5,
			'status'  => 'publish',
			'type'    => array( 'simple' ),
			'return'  => 'ids',
			'orderby' => 'rand',
		)
	);
	foreach ( (array) $ids as $pid ) {
		$p = wc_get_product( $pid );
		if ( $p && $p->is_purchasable() ) {
			return (int) $pid;
		}
	}
	return isset( $ids[0] ) ? (int) $ids[0] : 0;
};

$via_pool    = array( 'cookie_param', 'referrer', 'coupon' );
$product_id  = $find_product_id();
$now         = time();
$created     = 0;
$commissions = 0;

add_filter( 'pb_affiliates_send_new_sale_email', '__return_false', 999 );

for ( $j = 0; $j < $count; $j++ ) {
	$day_offset = wp_rand( 0, 75 );
	$second     = wp_rand( 0, 86400 - 1 );
	$created_ts = $now - ( $day_offset * DAY_IN_SECONDS ) - $second;

	try {
		$order = new WC_Order();
	} catch ( \Exception $e ) {
		fwrite( STDERR, 'Erro ao instanciar pedido: ' . $e->getMessage() . "\n" );
		continue;
	}

	$order->set_currency( get_woocommerce_currency() );
	$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );

	$d = new WC_DateTime();
	$d->setTimestamp( $created_ts );
	$order->set_date_created( $d );

	if ( $product_id > 0 ) {
		$product = wc_get_product( $product_id );
		if ( $product ) {
			$item = new WC_Order_Item_Product();
			$item->set_product_id( $product_id );
			$item->set_quantity( 1 );
			$price = (float) $product->get_price();
			if ( $price <= 0 ) {
				$price = 49.90;
			}
			$item->set_subtotal( $price );
			$item->set_total( $price );
			$order->add_item( $item );
		}
	}

	if ( ! count( $order->get_items() ) ) {
		$fee = new WC_Order_Item_Fee();
		$fee->set_name( 'Item seed' );
		$fee->set_amount( 59.90 );
		$fee->set_total( 59.90 );
		$order->add_item( $fee );
	}

	$order->set_billing_email( $user->user_email );
	$order->set_billing_first_name( $user->first_name ? $user->first_name : 'Teste' );
	$order->set_billing_last_name( $user->last_name ? $user->last_name : 'Seed' );

	$order->update_meta_data( '_pb_affiliate_id', $aff_id );
	$order->update_meta_data( '_pb_affiliate_code', $aff_code );
	$order->update_meta_data( '_pb_attribution_source', $via_pool[ array_rand( $via_pool ) ] );
	$order->calculate_totals();
	$order->set_status( 'completed' );
	$order->save();

	$oid = (int) $order->get_id();
	if ( ! $oid ) {
		continue;
	}

	++$created;

	$order = wc_get_order( $oid );
	if ( $order ) {
		PB_Affiliates_Commission::create_commission_for_order( $order );
		if ( $order->get_meta( '_pb_commission_recorded' ) ) {
			++$commissions;
		}
	}
}

remove_filter( 'pb_affiliates_send_new_sale_email', '__return_false', 999 );

echo "Concluído: afiliado #{$aff_id}, {$created} pedidos criados, {$commissions} comissões registradas.\n";
exit( 0 );
