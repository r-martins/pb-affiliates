<?php
/**
 * Uninstall PB Afiliados.
 *
 * @package PB_Affiliates
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pagbank_affiliate_commissions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pagbank_affiliate_withdrawals" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pagbank_affiliate_click_log" );

delete_option( 'pb_affiliates_settings' );
delete_option( 'pb_affiliates_db_version' );
