<?php
/**
 * Plugin Name: گرویتی فرم فارسی
 * Plugin URI: https://wordpress.org/plugins/persian-gravity-forms
 * Description: بسته کامل فارسی و بومی ساز گرویتی فرم برای ایرانیان - به همراه امکانات جانبی
 * Version: 3.0.0
 * Author: گرویتی فرم فارسی
 * Author URI: https://profiles.wordpress.org/persianscript
 *
 * License URI:  https://www.gnu.org/licenses/gpl-3.0.html
 * License:      GPLv3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'GF_PERSIAN_VERSION' ) ) {
	define( 'GF_PERSIAN_VERSION', '3.0.0' );
}

if ( ! defined( 'GF_PERSIAN_SLUG' ) ) {
	define( 'GF_PERSIAN_SLUG', 'persian' );
}

if ( ! defined( 'GF_PERSIAN_DIR' ) ) {
	define( 'GF_PERSIAN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'GF_PERSIAN_URL' ) ) {
	define( 'GF_PERSIAN_URL', plugins_url( '', __FILE__ ) . '/' );
}

if ( ! defined( 'GF_PERSIAN_FILE' ) ) {
	define( 'GF_PERSIAN_FILE', __FILE__ );
}

if ( ! defined( 'GF_PERSIAN_REQUIRED_GF_VERSION' ) ) {
	define( 'GF_PERSIAN_REQUIRED_GF_VERSION', '2.9.1' );
}


if ( ! class_exists( GFCommon::class ) || ! property_exists( GFCommon::class, 'version' ) || version_compare( GFCommon::$version, GF_PERSIAN_REQUIRED_GF_VERSION, '<' ) ) {

	add_action( 'admin_notices', function () {
		?>
		<div class="notice notice-error">
			<p><b>هشدار: </b>
				<?php
				printf(
					'فعالسازی «افزونه گرویتی فرم فارسی» انجام نشد. افزونه گرویتی فرم، بروز یا فعال نمی‌باشد، لطفا <b>نسخه %s یا بالاتر</b> آن را نصب و فعالسازی نمایید.',
					GF_PERSIAN_REQUIRED_GF_VERSION
				);
				?>
			</p>
		</div>
		<?php
	} );

	return;
}


require_once 'vendor/autoload.php';
require_once 'includes/class-core.php';
require_once 'includes/class-init.php';

register_activation_hook( GF_PERSIAN_FILE, function () {
	file_put_contents( GF_PERSIAN_DIR . '/.activated', '' );
} );
