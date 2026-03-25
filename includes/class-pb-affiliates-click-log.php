<?php
/**
 * Registro de visitas atribuídas (cliques / referência URL ou referer).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Click_Log
 */
class PB_Affiliates_Click_Log {

	/**
	 * Valor armazenado em `referer_host` quando o Referer é o mesmo host público da loja (navegação interna).
	 */
	const REFERER_HOST_LOCAL = '__local__';

	/**
	 * Contexto opcional (pedido Ajax de tracking): URL da página vista e referer enviado pelo browser.
	 *
	 * @var array{visited_url?: string, referrer_raw?: string}|null
	 */
	private static $tracking_context = null;

	/**
	 * Cabeçalhos comuns onde o IP do cliente aparece atrás de proxy / CDN (ordem de prioridade).
	 *
	 * @return array<int, string>
	 */
	protected static function client_ip_server_keys() {
		return array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_TRUE_CLIENT_IP',
			'HTTP_X_ENVOY_EXTERNAL_ADDRESS',
			'HTTP_FASTLY_CLIENT_IP',
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_CLIENT_IP',
		);
	}

	/**
	 * Melhor esforço do IP do visitante (Cloudflare, nginx, X-Forwarded-For, REMOTE_ADDR).
	 *
	 * Filtro: `pb_affiliates_click_log_client_ip` — recebe o IP escolhido e a lista de candidatos.
	 *
	 * @return string IPv4/IPv6 ou string vazia.
	 */
	public static function get_client_ip() {
		$candidates = array();
		foreach ( self::client_ip_server_keys() as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}
			$raw = wp_unslash( $_SERVER[ $key ] );
			$raw = trim( (string) $raw );
			if ( '' === $raw ) {
				continue;
			}
			if ( 'HTTP_X_FORWARDED_FOR' === $key || 'HTTP_CLIENT_IP' === $key ) {
				$parts = preg_split( '/[\s,]+/', $raw );
				foreach ( (array) $parts as $p ) {
					$p = trim( (string) $p );
					if ( '' !== $p ) {
						$candidates[] = $p;
					}
				}
			} else {
				$candidates[] = $raw;
			}
		}

		$chosen = '';
		foreach ( $candidates as $ip ) {
			$ip = trim( $ip );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				$chosen = $ip;
				break;
			}
		}

		if ( '' === $chosen && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				$chosen = $ip;
			}
		}

		return (string) apply_filters( 'pb_affiliates_click_log_client_ip', $chosen, $candidates );
	}

	/**
	 * URL pedida neste hit (esquema + host da requisição + REQUEST_URI).
	 *
	 * @return string
	 */
	public static function get_visited_url() {
		if ( is_array( self::$tracking_context ) && ! empty( self::$tracking_context['visited_url'] ) ) {
			$url = esc_url_raw( (string) self::$tracking_context['visited_url'] );
			if ( strlen( $url ) > 2048 ) {
				$url = substr( $url, 0, 2048 );
			}
			return (string) apply_filters( 'pb_affiliates_click_log_visited_url', $url );
		}
		$url = '';
		if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
			$host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
			$uri  = '/';
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$uri = wp_unslash( $_SERVER['REQUEST_URI'] );
				$uri = str_replace( array( "\0", "\r", "\n" ), '', (string) $uri );
				$uri = '' !== $uri ? $uri : '/';
			}
			$scheme = is_ssl() ? 'https' : 'http';
			$url    = $scheme . '://' . $host . $uri;
			if ( strlen( $url ) > 2048 ) {
				$url = substr( $url, 0, 2048 );
			}
		}

		/**
		 * URL guardada no log de cliques.
		 *
		 * @param string $url URL calculada.
		 */
		return (string) apply_filters( 'pb_affiliates_click_log_visited_url', $url );
	}

	/**
	 * Host do HTTP Referer para o log (minúsculas, sem "www."). Vazio se ausente ou inválido.
	 * Igual ao host de `home_url()` grava REFERER_HOST_LOCAL.
	 *
	 * @return string
	 */
	public static function get_referer_host_for_log() {
		$raw = '';
		if ( is_array( self::$tracking_context ) && isset( self::$tracking_context['referrer_raw'] ) && (string) self::$tracking_context['referrer_raw'] !== '' ) {
			$raw = (string) self::$tracking_context['referrer_raw'];
		} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$raw = (string) wp_unslash( $_SERVER['HTTP_REFERER'] );
		}
		return self::normalized_log_host_from_referer_raw( $raw );
	}

	/**
	 * Define contexto para um hit de tracking via Ajax (não usar $_SERVER do pedido admin-ajax).
	 *
	 * @param string $visited_url  URL da página (ex.: location.href).
	 * @param string $referrer_raw Valor típico de document.referrer ou string vazia.
	 */
	public static function set_tracking_context( $visited_url, $referrer_raw ) {
		self::$tracking_context = array(
			'visited_url'  => is_string( $visited_url ) ? $visited_url : '',
			'referrer_raw' => is_string( $referrer_raw ) ? $referrer_raw : '',
		);
	}

	/**
	 * Remove o contexto de tracking Ajax.
	 */
	public static function clear_tracking_context() {
		self::$tracking_context = null;
	}

	/**
	 * Host normalizado a partir de uma URL de referência (cabeçalho ou document.referrer).
	 *
	 * @param string $raw URL bruta.
	 * @return string Host normalizado, REFERER_HOST_LOCAL, ou vazio.
	 */
	private static function normalized_log_host_from_referer_raw( $raw ) {
		$raw = str_replace( array( "\0", "\r", "\n" ), '', (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		$parsed = wp_parse_url( $raw );
		if ( empty( $parsed['host'] ) || ! is_string( $parsed['host'] ) ) {
			return '';
		}
		$host = strtolower( trim( $parsed['host'] ) );
		if ( '' === $host ) {
			return '';
		}
		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}
		$host = substr( $host, 0, 190 );

		$store_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( is_string( $store_host ) && '' !== $store_host ) {
			$store_host = strtolower( trim( $store_host ) );
			if ( 0 === strpos( $store_host, 'www.' ) ) {
				$store_host = substr( $store_host, 4 );
			}
			if ( $host === $store_host ) {
				return self::REFERER_HOST_LOCAL;
			}
		}

		/**
		 * Host normalizado do Referer antes de gravar no log.
		 *
		 * @param string $host Host após normalização ou REFERER_HOST_LOCAL.
		 */
		return (string) apply_filters( 'pb_affiliates_click_log_referer_host', $host );
	}

	/**
	 * Grava um evento (após definir cookie de afiliado).
	 *
	 * @param int    $affiliate_id ID do afiliado.
	 * @param string $via          cookie_param, referrer, etc.
	 */
	public static function log( $affiliate_id, $via ) {
		$affiliate_id = (int) $affiliate_id;
		if ( $affiliate_id <= 0 || ! PB_Affiliates_Role::user_is_affiliate( $affiliate_id ) ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'pagbank_affiliate_click_log';

		$ip           = substr( self::get_client_ip(), 0, 45 );
		$url          = self::get_visited_url();
		$referer_host = substr( self::get_referer_host_for_log(), 0, 190 );

		$wpdb->insert(
			$table,
			array(
				'affiliate_id' => $affiliate_id,
				'hit_at'       => current_time( 'mysql' ),
				'via'          => substr( sanitize_key( $via ), 0, 32 ),
				'client_ip'    => $ip,
				'visited_url'  => $url,
				'referer_host' => $referer_host,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
