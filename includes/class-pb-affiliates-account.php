<?php
/**
 * WooCommerce My Account: endpoint pb-affiliate.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Account
 */
class PB_Affiliates_Account {

	const ENDPOINT = 'affiliate-area';

	const ENDPOINT_LINKS = 'affiliate-links';

	const ENDPOINT_REPORTS = 'affiliate-reports';

	const ENDPOINT_MATERIALS = 'affiliate-materials';

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ), 0 );
		// Necessário para is_wc_endpoint_url( 'affiliate-area' ) e parse_request do WC; sem isto o endpoint só existe no rewrite.
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'register_wc_query_vars' ) );
		// Prioridade 100: após WooCommerce e muitos temas (10–99), sem competir com tudo no 999.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_account_styles' ), 100 );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT_LINKS . '_endpoint', array( __CLASS__, 'render_links' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT_REPORTS . '_endpoint', array( __CLASS__, 'render_reports' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT_MATERIALS . '_endpoint', array( __CLASS__, 'render_promo_materials' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu_items' ), 20 );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT_LINKS . '_title', array( __CLASS__, 'affiliate_links_endpoint_title' ), 10, 3 );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT_MATERIALS . '_title', array( __CLASS__, 'promo_materials_endpoint_title' ), 10, 3 );
		add_action( 'woocommerce_edit_account_form', array( __CLASS__, 'edit_account_payment_fields' ) );
		add_action( 'woocommerce_save_account_details', array( __CLASS__, 'save_account_payment_fields' ), 10, 1 );
	}

	/**
	 * Register endpoint.
	 */
	public static function add_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_PAGES );
		add_rewrite_endpoint( self::ENDPOINT_LINKS, EP_PAGES );
		add_rewrite_endpoint( self::ENDPOINT_REPORTS, EP_PAGES );
		add_rewrite_endpoint( self::ENDPOINT_MATERIALS, EP_PAGES );
	}

	/**
	 * Estilos da área do afiliado (tabela de domínios, botões).
	 */
	public static function enqueue_account_styles() {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			return;
		}
		if ( ! self::is_affiliate_account_endpoint() && ! self::is_affiliate_links_endpoint() && ! self::is_affiliate_reports_endpoint() && ! self::is_affiliate_materials_endpoint() ) {
			return;
		}
		/**
		 * Permitir que temas desativem esta folha e carreguem estilos próprios.
		 *
		 * @param bool $load Whether to enqueue pb-aff-account.
		 */
		if ( ! apply_filters( 'pb_affiliates_enqueue_account_styles', true ) ) {
			return;
		}
		$deps = array();
		if ( wp_style_is( 'woocommerce-general', 'registered' ) ) {
			$deps[] = 'woocommerce-general';
		}
		wp_enqueue_style(
			'pb-aff-account',
			PB_AFFILIATES_URL . 'assets/css/account-affiliate.css',
			$deps,
			PB_AFFILIATES_VERSION
		);

		if ( self::is_affiliate_reports_endpoint() || self::is_affiliate_account_endpoint() ) {
			$chart_src = apply_filters(
				'pb_affiliates_reports_chart_script',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'
			);
			wp_enqueue_script(
				'pb-aff-chartjs',
				$chart_src,
				array(),
				'4.4.1',
				true
			);
		}

		if ( self::is_affiliate_links_endpoint() ) {
			$pb_lb_uid = get_current_user_id();
			if ( $pb_lb_uid && PB_Affiliates_Role::user_is_affiliate( $pb_lb_uid ) ) {
				$pb_lb_settings = PB_Affiliates_Settings::get();
				$pb_lb_param    = sanitize_key( $pb_lb_settings['referral_param'] ?? 'pid' );
				$pb_lb_code     = (string) get_user_meta( $pb_lb_uid, 'pb_affiliate_code', true );
				$pb_lb_home     = home_url( '/' );
				$pb_lb_host     = wp_parse_url( $pb_lb_home, PHP_URL_HOST );
				$pb_lb_host     = is_string( $pb_lb_host ) ? strtolower( $pb_lb_host ) : '';

				wp_enqueue_script(
					'pb-aff-link-builder',
					PB_AFFILIATES_URL . 'assets/js/account-link-builder.js',
					array(),
					PB_AFFILIATES_VERSION,
					true
				);
				wp_localize_script(
					'pb-aff-link-builder',
					'pbAffLinkBuilder',
					array(
						'referralParam' => $pb_lb_param,
						'affiliateCode' => $pb_lb_code,
						'homeUrl'       => $pb_lb_home,
						'siteHost'      => $pb_lb_host,
						'i18n'          => array(
							'needUrl'    => __( 'Cole um URL da loja.', 'pb-affiliates' ),
							'notOurSite' => __( 'Use apenas endereços deste site.', 'pb-affiliates' ),
							'invalidUrl' => __( 'URL inválido. Ex.: https://… ou um caminho que comece com /', 'pb-affiliates' ),
							'copied'     => __( 'Copiado!', 'pb-affiliates' ),
						),
					)
				);

				if ( class_exists( 'PB_Affiliates_Zip1', false ) && PB_Affiliates_Zip1::is_enabled() ) {
					$pb_zip1_long = add_query_arg( $pb_lb_param, rawurlencode( $pb_lb_code ), $pb_lb_home );
					wp_enqueue_script(
						'pb-aff-zip1',
						PB_AFFILIATES_URL . 'assets/js/account-zip1.js',
						array(),
						PB_AFFILIATES_VERSION,
						true
					);
					wp_localize_script(
						'pb-aff-zip1',
						'pbAffZip1',
						array(
							'createUrl'     => PB_Affiliates_Zip1::api_create_url(),
							'longUrl'       => $pb_zip1_long,
							'referralParam' => $pb_lb_param ? $pb_lb_param : 'pid',
							'affiliateCode' => $pb_lb_code,
							'i18n'          => array(
								'busy'       => __( 'Gerando…', 'pb-affiliates' ),
								'copy'       => __( 'Copiar link curto', 'pb-affiliates' ),
								'copied'     => __( 'Copiado!', 'pb-affiliates' ),
								'stats'      => __( 'Estatísticas no zip1.io', 'pb-affiliates' ),
								'replace'    => __( 'Gerar outro link curto', 'pb-affiliates' ),
								'err'        => __( 'Não foi possível gerar o link curto.', 'pb-affiliates' ),
								'conflict'   => __( 'Este alias já está em uso no zip1.io. Escolha outro ou deixe em branco.', 'pb-affiliates' ),
								'ratelimit'  => __( 'Limite de pedidos ao zip1.io. Aguarde um minuto e tente novamente.', 'pb-affiliates' ),
								'badAlias'   => __( 'Alias inválido: use 3–16 caracteres (letras, números, hífens) ou um emoji curto.', 'pb-affiliates' ),
								'noLongUrl'  => __( 'URL de indicação indisponível. Recarregue a página.', 'pb-affiliates' ),
								'fallback'   => __( 'Não foi possível concluir. Tente novamente.', 'pb-affiliates' ),
								'badLongUrl' => __( 'Indique um URL completo válido (https://…).', 'pb-affiliates' ),
							),
						)
					);
				}
			}
		}
	}

	/**
	 * Se estamos no endpoint da área do afiliado (compatível com is_wc_endpoint_url após register_wc_query_vars).
	 *
	 * @return bool
	 */
	public static function is_affiliate_account_endpoint() {
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( self::ENDPOINT ) ) {
			return true;
		}
		global $wp;
		return isset( $wp->query_vars[ self::ENDPOINT ] );
	}

	/**
	 * Endpoint links (identificador + domínios).
	 *
	 * @return bool
	 */
	public static function is_affiliate_links_endpoint() {
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( self::ENDPOINT_LINKS ) ) {
			return true;
		}
		global $wp;
		return isset( $wp->query_vars[ self::ENDPOINT_LINKS ] );
	}

	/**
	 * Endpoint relatórios (cliques + pedidos).
	 *
	 * @return bool
	 */
	public static function is_affiliate_reports_endpoint() {
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( self::ENDPOINT_REPORTS ) ) {
			return true;
		}
		global $wp;
		return isset( $wp->query_vars[ self::ENDPOINT_REPORTS ] );
	}

	/**
	 * @param array $vars Vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::ENDPOINT;
		$vars[] = self::ENDPOINT_LINKS;
		$vars[] = self::ENDPOINT_REPORTS;
		$vars[] = self::ENDPOINT_MATERIALS;
		return $vars;
	}

	/**
	 * Registra o endpoint na lista do WooCommerce (chave = identificador, valor = slug na URL).
	 *
	 * @param array $query_vars Mapa endpoint => query var.
	 * @return array
	 */
	public static function register_wc_query_vars( $query_vars ) {
		$query_vars[ self::ENDPOINT ]           = self::ENDPOINT;
		$query_vars[ self::ENDPOINT_LINKS ]     = self::ENDPOINT_LINKS;
		$query_vars[ self::ENDPOINT_REPORTS ]   = self::ENDPOINT_REPORTS;
		$query_vars[ self::ENDPOINT_MATERIALS ] = self::ENDPOINT_MATERIALS;
		return $query_vars;
	}

	/**
	 * Endpoint materiais promocionais.
	 *
	 * @return bool
	 */
	public static function is_affiliate_materials_endpoint() {
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( self::ENDPOINT_MATERIALS ) ) {
			return true;
		}
		global $wp;
		return isset( $wp->query_vars[ self::ENDPOINT_MATERIALS ] );
	}

	/**
	 * Item de menu Minha conta (só com materiais e afiliado ativo).
	 *
	 * @param array $items Itens.
	 * @return array
	 */
	public static function account_menu_items( $items ) {
		if ( ! self::user_should_see_promo_materials_menu() ) {
			return $items;
		}
		$inserted = false;
		$out      = array();
		foreach ( $items as $key => $label ) {
			$out[ $key ] = $label;
			if ( self::ENDPOINT_REPORTS === $key ) {
				$out[ self::ENDPOINT_MATERIALS ] = __( 'Materiais promocionais', 'pb-affiliates' );
				$inserted                        = true;
			}
		}
		if ( ! $inserted ) {
			$out[ self::ENDPOINT_MATERIALS ] = __( 'Materiais promocionais', 'pb-affiliates' );
		}
		return $out;
	}

	/**
	 * @return bool
	 */
	private static function user_should_see_promo_materials_menu() {
		$uid = get_current_user_id();
		if ( ! $uid || ! PB_Affiliates_Role::user_is_affiliate( $uid ) ) {
			return false;
		}
		return PB_Affiliates_Promotional_Materials::has_displayable_materials();
	}

	/**
	 * Título da página do endpoint links.
	 *
	 * @return string
	 */
	public static function affiliate_links_endpoint_title( $title, $endpoint, $action ) {
		if ( '' !== (string) $title ) {
			return (string) $title;
		}
		return __( 'Links de afiliados', 'pb-affiliates' );
	}

	/**
	 * Título da página do endpoint materiais.
	 *
	 * @return string
	 */
	public static function promo_materials_endpoint_title( $title, $endpoint, $action ) {
		if ( '' !== (string) $title ) {
			return (string) $title;
		}
		return __( 'Materiais promocionais', 'pb-affiliates' );
	}

	/**
	 * Dashboard content.
	 */
	public static function render() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			echo '<p>' . esc_html__( 'É necessário estar logado.', 'pb-affiliates' ) . '</p>';
			return;
		}

		// Este endpoint depende de `wc_add_notice()` + redirects e também pode ser servido por caches de página.
		// Para garantir que a resposta posterior mostre as notices e não venha do cache, evitamos caching aqui.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}

		if ( PB_Affiliates_Role::user_is_pending_affiliate( $user_id ) ) {
			include PB_AFFILIATES_PATH . 'templates/my-account/affiliate-pending.php';
			return;
		}

		if ( ! PB_Affiliates_Role::user_is_affiliate( $user_id ) ) {
			include PB_AFFILIATES_PATH . 'templates/my-account/affiliate-opt-in.php';
			return;
		}

		$summary                     = PB_Affiliates_Reports::get_affiliate_dashboard_totals( $user_id );
		$commission_rate_description = PB_Affiliates_Commission::get_commission_rate_description_for_dashboard( $user_id );
		$pb_aff_payment_mode         = PB_Affiliates_Settings::get()['payment_mode'] ?? 'manual';
		$pb_aff_withdraw_balance     = PB_Affiliates_Withdrawal::get_available_balance( $user_id );
		$pb_aff_withdraw_pending     = PB_Affiliates_Withdrawal::has_pending_request( $user_id );
		$pb_aff_min_withdrawal       = (float) ( PB_Affiliates_Settings::get()['manual_min_withdrawal'] ?? 0 );
		$pb_aff_paid_withdrawals     = PB_Affiliates_Withdrawal::get_paid_withdrawals_for_affiliate( $user_id, 50 );
		$pb_aff_show_payments_received = true;
		if ( 'split' === $pb_aff_payment_mode ) {
			$pb_aff_show_payments_received = ! empty( $pb_aff_paid_withdrawals )
				|| PB_Affiliates_Reports::affiliate_has_paid_commissions_in_year( $user_id, null );
		}
		$pb_aff_has_promo_materials   = PB_Affiliates_Promotional_Materials::has_displayable_materials();
		$pb_aff_materials_url         = wc_get_account_endpoint_url( self::ENDPOINT_MATERIALS );
		$pb_aff_pagbank_account_id    = (string) get_user_meta( $user_id, 'pb_affiliate_pagbank_account_id', true );
		$pb_aff_split_receipt_ready   = self::affiliate_has_valid_split_pagbank_account( $user_id );
		$pb_aff_bank_dashboard_lines  = self::get_bank_detail_lines_for_dashboard( $user_id );

		$pb_dash_days = isset( $_GET['pb_dash'] ) ? absint( wp_unslash( $_GET['pb_dash'] ) ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $pb_dash_days, array( 7, 14, 30, 90 ), true ) ) {
			$pb_dash_days = 30;
		}
		$pb_aff_period_bundle  = PB_Affiliates_Reports::get_affiliate_dashboard_period_bundle( $user_id, $pb_dash_days );
		$pb_aff_dash_chart     = PB_Affiliates_Reports::get_affiliate_click_chart_series( $user_id, $pb_dash_days );
		$pb_aff_clicks_alltime = PB_Affiliates_Reports::count_clicks_for_affiliate( $user_id );
		$pb_aff_nav_active     = 'dashboard';

		include PB_AFFILIATES_PATH . 'templates/my-account/affiliate-dashboard.php';
	}

	/**
	 * Links de afiliado: identificador, URL de indicação, domínios de referência.
	 */
	public static function render_links() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			echo '<p>' . esc_html__( 'É necessário estar logado.', 'pb-affiliates' ) . '</p>';
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}

		if ( PB_Affiliates_Role::user_is_pending_affiliate( $user_id ) ) {
			include PB_AFFILIATES_PATH . 'templates/my-account/affiliate-pending.php';
			return;
		}

		if ( ! PB_Affiliates_Role::user_is_affiliate( $user_id ) ) {
			include PB_AFFILIATES_PATH . 'templates/my-account/affiliate-opt-in.php';
			return;
		}

		$settings = PB_Affiliates_Settings::get();
		$param    = sanitize_key( $settings['referral_param'] ?? 'pid' );
		$code     = get_user_meta( $user_id, 'pb_affiliate_code', true );
		$link     = add_query_arg( $param, rawurlencode( (string) $code ), home_url( '/' ) );

		$affiliate_domains = get_user_meta( $user_id, PB_Affiliates_Domain_Verify::META, true );
		if ( ! is_array( $affiliate_domains ) ) {
			$affiliate_domains = array();
		}

		$pb_aff_has_promo_materials = PB_Affiliates_Promotional_Materials::has_displayable_materials();
		$pb_aff_materials_url       = wc_get_account_endpoint_url( self::ENDPOINT_MATERIALS );
		$pb_aff_nav_active          = 'links';
		$pb_aff_zip1_enabled = PB_Affiliates_Zip1::is_enabled();

		include PB_AFFILIATES_PATH . 'templates/my-account/affiliate-links.php';
	}

	/**
	 * Materiais promocionais (lista + download).
	 */
	public static function render_promo_materials() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			echo '<p>' . esc_html__( 'É necessário estar logado.', 'pb-affiliates' ) . '</p>';
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}
		if ( ! PB_Affiliates_Role::user_is_affiliate( $user_id ) ) {
			echo '<p>' . esc_html__( 'Materiais disponíveis apenas para afiliados ativos.', 'pb-affiliates' ) . '</p>';
			return;
		}
		$pb_aff_promo_materials      = PB_Affiliates_Promotional_Materials::get_items_for_affiliate_display();
		$pb_aff_has_promo_materials  = true;
		$pb_aff_materials_url        = wc_get_account_endpoint_url( self::ENDPOINT_MATERIALS );
		$pb_aff_nav_active           = 'materials';
		include PB_AFFILIATES_PATH . 'templates/my-account/affiliate-materials.php';
	}

	/**
	 * Relatórios: cliques e pedidos atribuídos.
	 */
	public static function render_reports() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			echo '<p>' . esc_html__( 'É necessário estar logado.', 'pb-affiliates' ) . '</p>';
			return;
		}

		// Evita que relatórios em `Minha conta` sejam armazenados por caches de página.
		// Embora as notices sejam mais relevantes no endpoint principal, isto reduz efeitos colaterais.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}

		if ( ! PB_Affiliates_Role::user_is_affiliate( $user_id ) ) {
			echo '<p>' . esc_html__( 'Relatórios disponíveis apenas para afiliados ativos.', 'pb-affiliates' ) . '</p>';
			return;
		}

		// hit_at usa current_time( 'mysql' ) — mesmo fuso que wp_date.
		$since7_local  = wp_date( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
		$since30_local = wp_date( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );

		$clicks_7d  = PB_Affiliates_Reports::count_clicks_since( $user_id, $since7_local );
		$clicks_30d = PB_Affiliates_Reports::count_clicks_since( $user_id, $since30_local );

		$chart_days = isset( $_GET['pb_ch'] ) ? absint( wp_unslash( $_GET['pb_ch'] ) ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $chart_days, array( 7, 14, 30, 90 ), true ) ) {
			$chart_days = 30;
		}

		$per_page     = (int) apply_filters( 'pb_affiliates_reports_per_page', 15 );
		$per_page     = max( 5, min( 50, $per_page ) );
		$clicks_page  = isset( $_GET['pb_c'] ) ? max( 1, absint( wp_unslash( $_GET['pb_c'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orders_page  = isset( $_GET['pb_o'] ) ? max( 1, absint( wp_unslash( $_GET['pb_o'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$chart_series     = PB_Affiliates_Reports::get_affiliate_click_chart_series( $user_id, $chart_days );
		$clicks_total     = PB_Affiliates_Reports::count_clicks_for_affiliate( $user_id );
		$clicks_max_pages = max( 1, (int) ceil( $clicks_total / $per_page ) );
		$clicks_page      = min( $clicks_page, $clicks_max_pages );
		$recent_hits      = PB_Affiliates_Reports::get_recent_clicks_paged( $user_id, $per_page, $clicks_page );

		$orders_data      = PB_Affiliates_Reports::get_affiliate_orders_paginated( $user_id, $per_page, $orders_page );
		$orders_total     = $orders_data['total'];
		$orders_max_pages = max( 1, (int) $orders_data['max_pages'] );
		if ( $orders_page > $orders_max_pages ) {
			$orders_page  = $orders_max_pages;
			$orders_data  = PB_Affiliates_Reports::get_affiliate_orders_paginated( $user_id, $per_page, $orders_page );
		}
		$orders = $orders_data['orders'];

		$pb_aff_has_promo_materials = PB_Affiliates_Promotional_Materials::has_displayable_materials();
		$pb_aff_materials_url       = wc_get_account_endpoint_url( self::ENDPOINT_MATERIALS );
		$pb_aff_nav_active          = 'reports';

		include PB_AFFILIATES_PATH . 'templates/my-account/affiliate-reports.php';
	}

	/**
	 * Whether the user may edit affiliate payment fields on the account form.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_can_edit_payment_fields( $user_id ) {
		if ( ! $user_id ) {
			return false;
		}
		return PB_Affiliates_Role::user_is_affiliate( $user_id );
	}

	/**
	 * Account ID PagBank no formato aceito pelo split de afiliados.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function affiliate_has_valid_split_pagbank_account( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}
		$raw = (string) get_user_meta( $user_id, 'pb_affiliate_pagbank_account_id', true );
		return class_exists( 'PB_Affiliates_Split', false ) && PB_Affiliates_Split::is_valid_account_id( $raw );
	}

	/**
	 * Exibe CPF/CNPJ (só dígitos ou com máscara) de forma resumida no painel.
	 *
	 * @param string $stored Valor em user meta.
	 * @return string
	 */
	public static function mask_tax_id_for_dashboard( $stored ) {
		$digits = preg_replace( '/\D/', '', (string) $stored );
		$len    = strlen( $digits );
		if ( $len <= 4 ) {
			return $digits;
		}
		return str_repeat( '•', $len - 4 ) . substr( $digits, -4 );
	}

	/**
	 * Linhas de dados bancários para o painel (documento parcialmente mascarado).
	 *
	 * @param int $user_id User ID.
	 * @return array<int, string>
	 */
	public static function get_bank_detail_lines_for_dashboard( $user_id ) {
		$lines = PB_Affiliates_Withdrawal::get_bank_detail_lines( (int) $user_id );
		$doc   = preg_replace( '/\D/', '', (string) get_user_meta( (int) $user_id, 'pb_affiliate_bank_document', true ) );
		if ( '' === $doc ) {
			return $lines;
		}
		foreach ( $lines as $i => $line ) {
			if ( false !== strpos( $line, $doc ) ) {
				$lines[ $i ] = sprintf(
					/* translators: %s: masked tax id */
					__( 'CPF/CNPJ: %s', 'pb-affiliates' ),
					self::mask_tax_id_for_dashboard( $doc )
				);
				break;
			}
		}
		return $lines;
	}

	/**
	 * CPF/CNPJ guardado só com dígitos (máscara só no front).
	 *
	 * @param mixed $value Valor enviado no POST.
	 * @return string
	 */
	public static function sanitize_document_digits( $value ) {
		return preg_replace( '/\D/', '', (string) $value );
	}

	/**
	 * PagBank help URL for affiliates (obtain Account ID).
	 *
	 * @return string
	 */
	public static function get_pagbank_account_id_help_url() {
		$url = 'https://ws.pbintegracoes.com/pspro/v7/connect/account-id/authorize';
		/**
		 * Filter URL shown next to the PagBank Account ID field for affiliates.
		 *
		 * @param string $url Default developer docs URL.
		 */
		return apply_filters( 'pb_affiliates_pagbank_account_id_help_url', $url );
	}

	/**
	 * Extra fields on WooCommerce → Minha conta → Detalhes da conta.
	 */
	public static function edit_account_payment_fields() {
		$user_id = get_current_user_id();
		if ( ! self::user_can_edit_payment_fields( $user_id ) ) {
			return;
		}

		$mode = PB_Affiliates_Settings::get()['payment_mode'] ?? 'manual';

		if ( 'split' === $mode ) {
			$val = (string) get_user_meta( $user_id, 'pb_affiliate_pagbank_account_id', true );
			$help = self::get_pagbank_account_id_help_url();
			?>
			<h3><?php esc_html_e( 'Recebimento de comissões (PagBank)', 'pb-affiliates' ); ?></h3>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="pb_affiliate_pagbank_account_id"><?php esc_html_e( 'Account ID PagBank', 'pb-affiliates' ); ?></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="pb_affiliate_pagbank_account_id" id="pb_affiliate_pagbank_account_id" autocomplete="off" value="<?php echo esc_attr( $val ); ?>" placeholder="ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" pattern="ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}" />
			</p>
			<p class="form-row form-row-wide">
				<a href="<?php echo esc_url( $help ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Qual é meu Account ID?', 'pb-affiliates' ); ?></a>
			</p>
			<?php
			return;
		}

		$bank_code = (string) get_user_meta( $user_id, 'pb_affiliate_bank_code', true );
		$agency    = (string) get_user_meta( $user_id, 'pb_affiliate_bank_agency', true );
		$account   = (string) get_user_meta( $user_id, 'pb_affiliate_bank_account', true );
		$document  = (string) get_user_meta( $user_id, 'pb_affiliate_bank_document', true );
		?>
		<h3><?php esc_html_e( 'Dados bancários (recebimento de comissões)', 'pb-affiliates' ); ?></h3>
		<?php PB_Affiliates_Bank_Combo::render_bank_code_field( $bank_code, 'front' ); ?>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="pb_affiliate_bank_agency"><?php esc_html_e( 'Agência', 'pb-affiliates' ); ?></label>
			<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="pb_affiliate_bank_agency" id="pb_affiliate_bank_agency" value="<?php echo esc_attr( $agency ); ?>" autocomplete="off" />
		</p>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="pb_affiliate_bank_account"><?php esc_html_e( 'Conta e dígito', 'pb-affiliates' ); ?></label>
			<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="pb_affiliate_bank_account" id="pb_affiliate_bank_account" value="<?php echo esc_attr( $account ); ?>" autocomplete="off" />
		</p>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="pb_affiliate_bank_document"><?php esc_html_e( 'CPF ou CNPJ', 'pb-affiliates' ); ?></label>
			<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="pb_affiliate_bank_document" id="pb_affiliate_bank_document" value="<?php echo esc_attr( $document ); ?>" maxlength="18" autocomplete="off" />
		</p>
		<?php
	}

	/**
	 * Persist affiliate payment fields from the edit account form.
	 *
	 * @param int $user_id User ID.
	 */
	public static function save_account_payment_fields( $user_id ) {
		if ( ! self::user_can_edit_payment_fields( $user_id ) ) {
			return;
		}
		if ( empty( $_POST['save-account-details-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['save-account-details-nonce'] ) ), 'save_account_details' ) ) {
			return;
		}

		$mode = PB_Affiliates_Settings::get()['payment_mode'] ?? 'manual';

		if ( 'split' === $mode ) {
			if ( ! isset( $_POST['pb_affiliate_pagbank_account_id'] ) ) {
				return;
			}
			$raw = sanitize_text_field( wp_unslash( $_POST['pb_affiliate_pagbank_account_id'] ) );
			if ( '' === $raw ) {
				update_user_meta( $user_id, 'pb_affiliate_pagbank_account_id', '' );
				return;
			}
			if ( ! PB_Affiliates_Split::is_valid_account_id( $raw ) ) {
				wc_add_notice( __( 'Account ID PagBank inválido. Use o formato ACCO_ seguido do UUID (ex.: ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx).', 'pb-affiliates' ), 'error' );
				return;
			}
			update_user_meta( $user_id, 'pb_affiliate_pagbank_account_id', $raw );
			return;
		}

		$fields = array(
			'pb_affiliate_bank_code'     => 'pb_affiliate_bank_code',
			'pb_affiliate_bank_agency'   => 'pb_affiliate_bank_agency',
			'pb_affiliate_bank_account'  => 'pb_affiliate_bank_account',
			'pb_affiliate_bank_document' => 'pb_affiliate_bank_document',
		);
		foreach ( $fields as $post_key => $meta_key ) {
			if ( ! isset( $_POST[ $post_key ] ) ) {
				continue;
			}
			$raw = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
			if ( 'pb_affiliate_bank_document' === $post_key ) {
				update_user_meta( $user_id, $meta_key, self::sanitize_document_digits( $raw ) );
			} else {
				update_user_meta( $user_id, $meta_key, $raw );
			}
		}
	}
}
