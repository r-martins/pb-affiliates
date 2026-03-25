<?php
/**
 * Simple autoload for PB Affiliates classes.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

$pb_aff_files = array(
	'class-pb-affiliates-install.php',
	'class-pb-affiliates.php',
	'class-pb-affiliates-dependencies.php',
	'class-pb-affiliates-settings.php',
	'class-pb-affiliates-emails.php',
	'class-pb-affiliates-role.php',
	'class-pb-affiliates-domain-verify.php',
	'class-pb-affiliates-tracking.php',
	'class-pb-affiliates-attribution.php',
	'class-pb-affiliates-click-log.php',
	'class-pb-affiliates-commission.php',
	'class-pb-affiliates-category-commission.php',
	'class-pb-affiliates-order.php',
	'class-pb-affiliates-coupon.php',
	'class-pb-affiliates-withdrawal.php',
	'class-pb-affiliates-split.php',
	'class-pb-affiliates-promotional-materials.php',
	'class-pb-affiliates-reports.php',
	'class-pb-affiliates-account.php',
	'class-pb-affiliates-bank-combo.php',
	'admin/class-pb-affiliates-admin-materials.php',
	'admin/class-pb-affiliates-admin-affiliates.php',
	'admin/class-pb-affiliates-admin-payments.php',
	'admin/class-pb-affiliates-admin.php',
	'admin/class-pb-affiliates-admin-settings.php',
	'admin/class-pb-affiliates-admin-user-detail.php',
	'admin/class-pb-affiliates-user-profile.php',
	'admin/class-pb-affiliates-order-meta-box.php',
	'public/class-pb-affiliates-public.php',
);

foreach ( $pb_aff_files as $file ) {
	// Classes under plugin root (e.g. public/) — not under includes/.
	if ( 0 === strpos( $file, 'public/', 0 ) ) {
		$path = PB_AFFILIATES_PATH . $file;
	} else {
		$path = PB_AFFILIATES_PATH . 'includes/' . $file;
	}
	if ( is_readable( $path ) ) {
		require_once $path;
	}
}
