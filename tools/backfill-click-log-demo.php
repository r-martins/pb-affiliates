#!/usr/bin/env php
<?php
/**
 * Corrige dados de demonstração no log de cliques:
 * - Preenche client_ip e visited_url vazios com valores de exemplo.
 * - Normaliza `via` para valores que o plugin usa na prática: cookie_param | referrer
 *   (remove link, qr, etc.).
 *
 * Opcional: alinha meta `_pb_attribution_source` em pedidos com link/qr.
 *
 * Uso (raiz WP):
 *   php wp-content/plugins/pb-affiliates/tools/backfill-click-log-demo.php
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

global $wpdb;

$pb_ref_param = 'pid';
if ( class_exists( 'PB_Affiliates_Settings' ) ) {
	$pb_ref_param = sanitize_key( PB_Affiliates_Settings::get()['referral_param'] ?? 'pid' );
}

$table       = $wpdb->prefix . 'pagbank_affiliate_click_log';
$allowed_via = array( 'cookie_param', 'referrer' );
$fake_ips    = array( '177.12.34.10', '187.45.20.111', '45.160.90.2', '2001:db8::1', '10.0.0.22' );
$path_pool   = array( '/', '/loja/', '/product/exemplo/', '/categoria/produtos/' );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$rows = $wpdb->get_results( "SELECT id, affiliate_id, client_ip, visited_url, via FROM {$table}" );

$updated_rows = 0;
foreach ( (array) $rows as $row ) {
	$id            = (int) $row->id;
	$affiliate_id  = (int) $row->affiliate_id;
	$client_ip     = isset( $row->client_ip ) ? trim( (string) $row->client_ip ) : '';
	$visited_url   = isset( $row->visited_url ) ? trim( (string) $row->visited_url ) : '';
	$via           = isset( $row->via ) ? (string) $row->via : '';
	$via_normalized = in_array( $via, $allowed_via, true ) ? $via : ( 0 === ( $id % 2 ) ? 'cookie_param' : 'referrer' );

	$code = (string) get_user_meta( $affiliate_id, 'pb_affiliate_code', true );
	if ( '' === $code ) {
		$code = 'aff';
	}

	if ( '' === $client_ip ) {
		$client_ip = $fake_ips[ $id % count( $fake_ips ) ];
	}

	if ( '' === $visited_url ) {
		$path = $path_pool[ $id % count( $path_pool ) ];
		$url  = ( '/' === $path ) ? trailingslashit( home_url() ) : home_url( $path );
		if ( 'cookie_param' === $via_normalized ) {
			$url = add_query_arg( $pb_ref_param, $code, $url );
		}
		$visited_url = substr( $url, 0, 2048 );
	}

	if (
		trim( (string) $row->client_ip ) === $client_ip
		&& trim( (string) $row->visited_url ) === $visited_url
		&& $via === $via_normalized
	) {
		continue;
	}

	$ok = $wpdb->update(
		$table,
		array(
			'client_ip'   => substr( $client_ip, 0, 45 ),
			'visited_url' => $visited_url,
			'via'         => $via_normalized,
		),
		array( 'id' => $id ),
		array( '%s', '%s', '%s' ),
		array( '%d' )
	);
	if ( false !== $ok ) {
		++$updated_rows;
	}
}

echo "Log de cliques: {$updated_rows} registros atualizados.\n";

// Pedidos: meta _pb_attribution_source com valores inventados pelo seed antigo.
$map_orders = $wpdb->update(
	$wpdb->postmeta,
	array( 'meta_value' => 'cookie_param' ),
	array(
		'meta_key'   => '_pb_attribution_source',
		'meta_value' => 'link',
	),
	array( '%s' ),
	array( '%s', '%s' )
);
$map_orders2 = $wpdb->update(
	$wpdb->postmeta,
	array( 'meta_value' => 'referrer' ),
	array(
		'meta_key'   => '_pb_attribution_source',
		'meta_value' => 'qr',
	),
	array( '%s' ),
	array( '%s', '%s' )
);

$orders_meta = (int) $map_orders + (int) $map_orders2;

$om_table = $wpdb->prefix . 'wc_orders_meta';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $om_table ) ) === $om_table ) {
	$orders_meta += (int) $wpdb->update(
		$om_table,
		array( 'meta_value' => 'cookie_param' ),
		array(
			'meta_key'   => '_pb_attribution_source',
			'meta_value' => 'link',
		),
		array( '%s' ),
		array( '%s', '%s' )
	);
	$orders_meta += (int) $wpdb->update(
		$om_table,
		array( 'meta_value' => 'referrer' ),
		array(
			'meta_key'   => '_pb_attribution_source',
			'meta_value' => 'qr',
		),
		array( '%s' ),
		array( '%s', '%s' )
	);
}

if ( $orders_meta > 0 ) {
	echo "Pedidos (postmeta/HPOS): {$orders_meta} meta _pb_attribution_source normalizadas (link→cookie_param, qr→referrer).\n";
}

exit( 0 );
