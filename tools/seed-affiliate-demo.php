#!/usr/bin/env php
<?php
// phpcs:ignoreFile -- CLI development utility; excluded from distribution ZIP (.distignore).

/**
 * Popula dados de demonstração: cliques (`pagbank_affiliate_click_log`) e pedidos WooCommerce
 * com meta `_pb_affiliate_id` / `_pb_affiliate_code` para códigos indicados.
 *
 * Uso (raiz WordPress, ex.: pasta `woocommerce/`):
 *
 *   php wp-content/plugins/pb-affiliates/tools/seed-affiliate-demo.php
 *
 * Docker (ajuste serviço/caminho):
 *
 *   docker compose exec php php /var/www/html/wp-content/plugins/pb-affiliates/tools/seed-affiliate-demo.php
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

$pb_ref_param = 'pid';
if ( class_exists( 'PB_Affiliates_Settings' ) ) {
	$pb_ref_param = sanitize_key( PB_Affiliates_Settings::get()['referral_param'] ?? 'pid' );
}

if ( ! function_exists( 'wc_create_order' ) ) {
	fwrite( STDERR, "WooCommerce não está ativo.\n" );
	exit( 1 );
}

global $wpdb;

$codes_config = array(
	'pbseed_joao'  => array( 'clicks' => 55, 'orders' => 12 ),
	'pbseed_maria' => array( 'clicks' => 48, 'orders' => 10 ),
);

// Só valores que o tracking do plugin define (parâmetro URL ou domínio verificado).
$via_pool  = array( 'cookie_param', 'referrer' );
$fake_ips  = array( '177.12.34.10', '187.45.20.111', '45.160.90.2', '2001:db8::1', '10.0.0.22' );
$path_pool = array( '/', '/loja/', '/product/exemplo/', '/categoria/produtos/' );

/**
 * Resolve user ID pelo código pb_affiliate_code.
 *
 * @param string $code Código.
 * @return int 0 se não existir.
 */
$resolve_affiliate_user = static function ( $code ) {
	global $wpdb;
	$code = strtolower( trim( (string) $code ) );
	if ( '' === $code ) {
		return 0;
	}
	$uid = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND LOWER(meta_value) = %s LIMIT 1",
			'pb_affiliate_code',
			$code
		)
	);
	return $uid ? (int) $uid : 0;
};

/**
 * Primeiro produto publicado com preço > 0, senão qualquer simples.
 *
 * @return int Product ID ou 0.
 */
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

$product_id = $find_product_id();

/**
 * Cria pedido com atribuição ao afiliado.
 *
 * @param int    $aff_id      User ID.
 * @param string $aff_code    Código.
 * @param int    $created_ts  Unix timestamp.
 * @param string $status      Estad WC.
 * @param int    $product_id  Produto ou 0 (taxa única).
 * @return int Order ID ou 0.
 */
$create_order = static function ( $aff_id, $aff_code, $created_ts, $status, $product_id ) use ( $via_pool ) {
	try {
		$order = new WC_Order();
	} catch ( \Exception $e ) {
		fwrite( STDERR, 'Erro ao instanciar pedido: ' . $e->getMessage() . "\n" );
		return 0;
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

	$order->update_meta_data( '_pb_affiliate_id', (int) $aff_id );
	$order->update_meta_data( '_pb_affiliate_code', $aff_code );
	$order->update_meta_data( '_pb_attribution_source', $via_pool[ array_rand( $via_pool ) ] );
	$order->calculate_totals();
	$order->set_status( $status );
	$order->save();

	return (int) $order->get_id();
};

$log_table = $wpdb->prefix . 'pagbank_affiliate_click_log';
$now       = time();
$inserted_clicks  = 0;
$inserted_orders  = 0;
$statuses         = array( 'pending', 'processing', 'on-hold', 'completed' );

foreach ( $codes_config as $code => $cfg ) {
	$uid = $resolve_affiliate_user( $code );
	if ( ! $uid ) {
		fwrite( STDERR, "Ignorado: não existe usuário com pb_affiliate_code = {$code}\n" );
		continue;
	}

	$st = get_user_meta( $uid, 'pb_affiliate_status', true );
	if ( 'active' !== (string) $st ) {
		fwrite( STDERR, "Aviso: usuário #{$uid} ({$code}) não está com estado 'active' (atual: " . ( $st !== '' ? $st : '(vazio)' ) . "). Relatórios podem bloquear a visualização.\n" );
	}

	$n_clicks = (int) $cfg['clicks'];
	for ( $i = 0; $i < $n_clicks; $i++ ) {
		$day_offset = wp_rand( 0, 89 );
		$second     = wp_rand( 0, 86400 - 1 );
		$hit_ts     = $now - ( $day_offset * DAY_IN_SECONDS ) - $second;
		$hit_at     = wp_date( 'Y-m-d H:i:s', $hit_ts );

		$path = $path_pool[ array_rand( $path_pool ) ];
		$url  = ( '/' === $path ) ? trailingslashit( home_url() ) : home_url( $path );
		if ( 0 === wp_rand( 0, 2 ) ) {
			$url = add_query_arg(
				array(
					$pb_ref_param => $code,
					'utm_source'  => 'seed',
				),
				$url
			);
		}
		$url = substr( $url, 0, 2048 );

		$ok = $wpdb->insert(
			$log_table,
			array(
				'affiliate_id' => $uid,
				'hit_at'       => $hit_at,
				'via'          => substr( sanitize_key( $via_pool[ array_rand( $via_pool ) ] ), 0, 32 ),
				'client_ip'    => substr( $fake_ips[ array_rand( $fake_ips ) ], 0, 45 ),
				'visited_url'  => $url,
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
		if ( $ok ) {
			++$inserted_clicks;
		}
	}

	$n_orders = (int) $cfg['orders'];
	for ( $j = 0; $j < $n_orders; $j++ ) {
		$day_offset = wp_rand( 0, 75 );
		$second     = wp_rand( 0, 86400 - 1 );
		$created_ts = $now - ( $day_offset * DAY_IN_SECONDS ) - $second;
		$status     = $statuses[ array_rand( $statuses ) ];
		$oid        = $create_order( $uid, $code, $created_ts, $status, $product_id );
		if ( $oid ) {
			++$inserted_orders;
		}
	}

	echo "Seed concluído para {$code} (user #{$uid}): {$n_clicks} cliques inseridos, até {$n_orders} pedidos tentados.\n";
}

echo "\nResumo: {$inserted_clicks} linhas de clique, {$inserted_orders} pedidos criados.\n";
exit( 0 );
