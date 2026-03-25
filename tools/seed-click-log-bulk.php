#!/usr/bin/env php
<?php
/**
 * Insere um grande volume de registros fictícios em `pagbank_affiliate_click_log`
 * para testar desempenho dos relatórios admin (agrupamentos, intervalos de data, etc.).
 *
 * Uso (raiz do WordPress, pasta onde está o wp-load.php):
 *
 *   php wp-content/plugins/pb-affiliates/tools/seed-click-log-bulk.php
 *   php wp-content/plugins/pb-affiliates/tools/seed-click-log-bulk.php --count=20000 --days=180
 *   php wp-content/plugins/pb-affiliates/tools/seed-click-log-bulk.php --count=50000 --yes
 *
 * Docker (ex.: este repositório monta `./woocommerce` em `/var/www/html`):
 *
 *   docker compose exec wordpress php /var/www/html/wp-content/plugins/pb-affiliates/tools/seed-click-log-bulk.php --yes
 *
 * Opções:
 *   --count=N   Quantidade de linhas (padrão 20000, máximo 500000).
 *   --days=N    Espalhar `hit_at` nos últimos N dias (padrão 120).
 *   --chunk=N   Tamanho de cada INSERT em lote (padrão 400, máx. 2000).
 *   --dry-run   Só mostra quantos afiliados foram encontrados e sai.
 *   --yes       Não pedir confirmação.
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

$longopts = array( 'count:', 'days:', 'chunk:', 'dry-run', 'yes' );
$opts     = getopt( '', $longopts );

$count  = isset( $opts['count'] ) ? (int) $opts['count'] : 20000;
$count  = max( 1, min( 500000, $count ) );
$days   = isset( $opts['days'] ) ? (int) $opts['days'] : 120;
$days   = max( 1, min( 3650, $days ) );
$chunk  = isset( $opts['chunk'] ) ? (int) $opts['chunk'] : 400;
$chunk  = max( 50, min( 2000, $chunk ) );
$dry    = array_key_exists( 'dry-run', $opts );
$assume = array_key_exists( 'yes', $opts );

require $wp_root . '/wp-load.php';

global $wpdb;

$pb_ref_param = 'pid';
if ( class_exists( 'PB_Affiliates_Settings' ) ) {
	$pb_ref_param = sanitize_key( PB_Affiliates_Settings::get()['referral_param'] ?? 'pid' );
}

$table = $wpdb->prefix . 'pagbank_affiliate_click_log';

$aff_ids = $wpdb->get_col(
	"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'pb_affiliate_status' AND meta_value = 'active'"
);
if ( empty( $aff_ids ) ) {
	$aff_ids = $wpdb->get_col(
		"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'pb_affiliate_code' AND meta_value != '' LIMIT 100"
	);
}
$aff_ids = array_map( 'intval', (array) $aff_ids );
$aff_ids = array_values( array_filter( $aff_ids ) );

if ( empty( $aff_ids ) ) {
	fwrite( STDERR, "Nenhum afiliado encontrado (pb_affiliate_status=active ou pb_affiliate_code). Crie afiliados antes.\n" );
	exit( 1 );
}

$codes_by_id = array();
foreach ( $aff_ids as $aid ) {
	$codes_by_id[ $aid ] = (string) get_user_meta( $aid, 'pb_affiliate_code', true );
	if ( '' === $codes_by_id[ $aid ] ) {
		$codes_by_id[ $aid ] = 'aff' . $aid;
	}
}

echo 'Afiliados no pool: ' . count( $aff_ids ) . "\n";
echo "Tabela: {$table}\n";
echo "Planejado: {$count} registros, últimos {$days} dias, lotes de {$chunk}.\n";

if ( $dry ) {
	echo "(dry-run: nada foi inserido)\n";
	exit( 0 );
}

if ( ! $assume ) {
	echo "Confirma inserção? Digite yes: ";
	$line = '';
	if ( PHP_VERSION_ID >= 70200 && function_exists( 'readline' ) ) {
		$line = readline( '' );
	} else {
		$line = fgets( STDIN );
	}
	$line = strtolower( trim( (string) $line ) );
	if ( 'yes' !== $line && 'sim' !== $line ) {
		echo "Cancelado.\n";
		exit( 0 );
	}
}

$via_pool = array( 'cookie_param', 'referrer', 'coupon', 'unknown' );
$ref_pool = array(
	'',
	'google.com',
	'facebook.com',
	'instagram.com',
	'bing.com',
	'duckduckgo.com',
	'blog.referencia.test',
	'parceiro-ofertas.org',
	'email-tracker.local',
	'qr-landing.site',
	'midiasocial.example',
	'forum.dev',
	PB_Affiliates_Click_Log::REFERER_HOST_LOCAL,
);
$path_pool = array(
	'/',
	'/loja/',
	'/shop/',
	'/produto/amostra/',
	'/categoria/destaques/',
	'/promocoes/',
	'/checkout/',
);
$ip4_octets = static function () {
	return wp_rand( 1, 223 ) . '.' . wp_rand( 0, 255 ) . '.' . wp_rand( 0, 255 ) . '.' . wp_rand( 1, 254 );
};

$tz    = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
$end   = new DateTimeImmutable( 'now', $tz );
$start = $end->modify( '-' . $days . ' days' );
$span  = max( 1, $end->getTimestamp() - $start->getTimestamp() );

$home = trailingslashit( home_url() );

$inserted = 0;
$t0       = microtime( true );

while ( $inserted < $count ) {
	$n_batch = min( $chunk, $count - $inserted );
	$values  = array();

	for ( $i = 0; $i < $n_batch; $i++ ) {
		$aid = $aff_ids[ wp_rand( 0, count( $aff_ids ) - 1 ) ];
		$ts  = $start->getTimestamp() + wp_rand( 0, $span );
		$d   = ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( $tz );
		$hit_at = $d->format( 'Y-m-d H:i:s' );

		$via = $via_pool[ wp_rand( 0, count( $via_pool ) - 1 ) ];
		$ip  = ( 0 === wp_rand( 0, 9 ) )
			? sprintf(
				'2001:db8:%x:%x:%x:%x:%x:%x',
				wp_rand( 0, 65535 ),
				wp_rand( 0, 65535 ),
				wp_rand( 0, 65535 ),
				wp_rand( 0, 65535 ),
				wp_rand( 0, 65535 ),
				wp_rand( 0, 65535 )
			)
			: $ip4_octets();

		$path = $path_pool[ wp_rand( 0, count( $path_pool ) - 1 ) ];
		$url  = ( '/' === $path ) ? $home : $home . ltrim( $path, '/' );
		$url  = strtok( $url, '?' );
		if ( 'cookie_param' === $via || ( 'coupon' === $via && 0 === wp_rand( 0, 1 ) ) ) {
			$url = add_query_arg( $pb_ref_param, $codes_by_id[ $aid ], $url );
		}
		$url = substr( $url, 0, 2048 );

		$ref = $ref_pool[ wp_rand( 0, count( $ref_pool ) - 1 ) ];
		if ( 'cookie_param' === $via && '' === $ref && 0 === wp_rand( 0, 2 ) ) {
			$ref = $ref_pool[ wp_rand( 1, count( $ref_pool ) - 1 ) ];
		}

		$values[] = '(' . (int) $aid . ", '" . esc_sql( $hit_at ) . "', '" . esc_sql( substr( sanitize_key( $via ), 0, 32 ) )
			. "', '" . esc_sql( substr( $ip, 0, 45 ) ) . "', '" . esc_sql( $url ) . "', '" . esc_sql( substr( $ref, 0, 190 ) ) . "')";
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- montagem controlada + esc_sql.
	$sql = "INSERT INTO `{$table}` (affiliate_id, hit_at, via, client_ip, visited_url, referer_host) VALUES " . implode( ',', $values );
	$ok  = $wpdb->query( $sql );
	if ( false === $ok ) {
		fwrite( STDERR, "Erro no lote após {$inserted} linhas: " . $wpdb->last_error . "\n" );
		exit( 1 );
	}

	$inserted += $n_batch;
	if ( 0 === ( $inserted % ( $chunk * 25 ) ) || $inserted === $count ) {
		echo "… {$inserted} / {$count}\n";
	}
}

$sec = round( microtime( true ) - $t0, 2 );
echo "Concluído: {$inserted} registros em {$sec} s (~" . round( $inserted / max( 0.001, $sec ) ) . " linhas/s).\n";

exit( 0 );
