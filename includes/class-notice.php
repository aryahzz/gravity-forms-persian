<?php
/**
 * Developer : MahdiY
 * Web Site  : MahdiY.IR
 * E-Mail    : M@hdiY.IR
 */

defined( 'ABSPATH' ) || exit;

class GFPersian_Notice {

	public function __construct() {
		add_action( 'admin_notices', [ $this, 'admin_notices' ], 5 );
		add_action( 'wp_ajax_persian_gf_dismiss_notice', [ $this, 'dismiss_notice' ] );
	}

	public function admin_notices() {

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( $this->is_dismiss( 'all' ) ) {
			return;
		}

		foreach ( $this->notices() as $notice ) {

			if ( $notice['condition'] == false || $this->is_dismiss( $notice['id'] ) ) {
				continue;
			}

			$dismissible = $notice['dismiss'] ? 'is-dismissible' : '';

			$notice_id      = esc_attr( $notice['id'] );
			$notice_content = strip_tags( $notice['content'], '<p><a><b><img><ul><ol><li>' );

			printf( '<div class="notice persian_gf_notice notice-success %s" id="persian_gf_%s"><p>%s</p></div>', $dismissible, $notice_id, $notice_content );

			break;
		}

		?>
		<script type="text/javascript">
            jQuery(document).ready(function ($) {

                jQuery(document.body).on('click', '.notice-dismiss', function () {

                    let notice = jQuery(this).closest('.persian_gf_notice');
                    notice = notice.attr('id');

                    if (notice !== undefined && notice.indexOf('persian_gf_') !== -1) {

                        notice = notice.replace('persian_gf_', '');

                        jQuery.ajax({
                            url: "<?php echo admin_url( 'admin-ajax.php' ) ?>",
                            type: 'post',
                            data: {
                                notice: notice,
                                action: 'persian_gf_dismiss_notice',
                                nonce: "<?php echo wp_create_nonce( 'persian_gf_dismiss_notice' ); ?>"
                            }
                        });
                    }

                });

            });
		</script>
		<?php
	}

	public function notices(): array {
		global $pagenow;

		$page    = sanitize_text_field( $_GET['page'] ?? null );
		$view    = sanitize_text_field( $_GET['view'] ?? null );
		$subview = sanitize_text_field( $_GET['subview'] ?? null );

		$has_gateland         = is_plugin_active( 'gateland/gateland.php' );
		$gateland_install_url = admin_url( 'plugin-install.php?tab=plugin-information&plugin=gateland' );

		$notices = [
			[
				'id'        => 'gateland_dashboard',
				'content'   => sprintf( '<b>افزونه درگاه پرداخت هوشمند «گیت لند»:</b> با گیت‌لند می‌توانید فرم‌های گرویتی فرمز را به بیش از ۳۴ درگاه پرداخت (واسط و مستقیم) متصل کنید: <a href="%s" target="_blank">نصب سریع و رایگان از مخزن وردپرس</a>', $gateland_install_url ),
				'condition' => ! $has_gateland,
				'dismiss'   => 6 * MONTH_IN_SECONDS,
			],
		];

		$_notices = get_option( 'persian_gf_notices', [] );

		foreach ( $_notices['notices'] ?? [] as $_notice ) {

			$_notice['condition'] = 1;

			$rules = $_notice['rules'];

			if ( isset( $rules['pagenow'] ) && $rules['pagenow'] != $pagenow ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['page'] ) && $rules['page'] != $page ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['view'] ) && $rules['view'] != $view ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['subview'] ) && $rules['subview'] != $subview ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['active'] ) && is_plugin_inactive( $rules['active'] ) ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['inactive'] ) && is_plugin_active( $rules['inactive'] ) ) {
				$_notice['condition'] = 0;
			}

			unset( $_notice['rules'] );

			array_unshift( $notices, $_notice );
		}

		return $notices;
	}

	public function dismiss_notice() {

		check_ajax_referer( 'persian_gf_dismiss_notice', 'nonce' );

		$this->set_dismiss( $_POST['notice'] );

		die();
	}

	public function set_dismiss( string $notice_id ) {

		$notices = wp_list_pluck( $this->notices(), 'dismiss', 'id' );

		if ( isset( $notices[ $notice_id ] ) && $notices[ $notice_id ] ) {
			update_option( 'persian_gf_dismiss_notice_' . $notice_id, time() + intval( $notices[ $notice_id ] ), 'yes' );
			update_option( 'persian_gf_dismiss_notice_all', time() + DAY_IN_SECONDS );
		}
	}

	public function is_dismiss( $notice_id ): bool {
		return intval( get_option( 'persian_gf_dismiss_notice_' . $notice_id ) ) >= time();
	}

}

new GFPersian_Notice();