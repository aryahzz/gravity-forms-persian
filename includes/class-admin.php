<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFPersian_Admin extends GFPersian_Core {

	public function __construct() {
		add_filter( 'gform_print_styles', [ $this, 'print_styles' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_styles' ], 999 );
		add_filter( 'gform_noconflict_styles', [ $this, 'noconflict_styles' ] );
		add_action( 'wp_dashboard_setup', [ $this, 'dashboard_rss_widget' ] );
		add_action( 'admin_footer', [ $this, 'hide_license' ], 9999 );
		add_action( 'admin_head', [ $this, 'admin_general_style' ], 999 );
	}


	/**
	 * Load general inline admin styles to work with plugin elements
	 *
	 * @actino admin_head
	 *
	 * @return void
	 */
	public function admin_general_style(): void {
		?>
		<style id="persian_gravity_forms_general_style">
            #gf_dashboard_message a:last-child {
                float: left !important;
            }
		</style>
		<?php
	}

	/**
	 * Load admin rtl style when it's only a GForm showing
	 *
	 * @action admin_enqueue_scripts
	 *
	 * @return void
	 */
	public function admin_styles(): void {

		if ( ! $this->is_gravity_page() ) {
			return;
		}

		if ( is_rtl() && $this->option( 'rtl_admin', '1' ) == '1' ) {
			wp_register_style( 'gform_admin_rtl_style', GF_PERSIAN_URL . 'assets/css/rtl-admin.css' );
			wp_enqueue_style( 'gform_admin_rtl_style' );
		}

		$this->font_styles();
	}

	/**
	 * @filter gform_print_styles
	 *
	 * @param bool $print false Set to true if style should be printed.
	 * @param array $form The Form object
	 *
	 * @return array of registered style hooks
	 */
	public function print_styles( bool $print, array $form ): array {
		$styles = [];

		if ( ! is_rtl() || $this->option( 'rtl_admin', '1' ) !== '1' ) {
			return $styles;
		}

		$styles[] = $style = 'gform_print_rtl_style';
		wp_register_style( $style, GF_PERSIAN_URL . 'assets/css/rtl-print.css' );

		return array_merge( $styles, $this->font_styles() );
	}

	/**
	 * Register, Enqueue and returns the list of font handles
	 *
	 * @param bool $only_face Set if only font face should be loaded or load with admin font applier
	 *
	 * @return array
	 */
	private function font_styles( bool $only_face = false ): array {
		$styles = [];
		$font   = $this->option( 'font_admin', 'vazir' );

		if ( empty( $font ) ) {
			return $styles;
		}

		$styles[] = $style = 'gform_admin_font_face';

		wp_register_style( $style, GF_PERSIAN_URL . "assets/css/font-face-{$font}.css" );
		wp_enqueue_style( $style );

		if ( ! $only_face ) {
			$styles[] = $style = 'gform_print_font_style';
			wp_register_style( $style, GF_PERSIAN_URL . "assets/css/font-admin.css" );
			wp_enqueue_style( $style );
		}

		return $styles;
	}

	/**
	 * Define no conflict style hooks for GForm
	 *
	 * @filter gform_noconflict_styles
	 *
	 * @param array $styles
	 *
	 * @return array
	 */
	public function noconflict_styles( array $styles ): array {
		return array_merge( $styles, [
			'gform_print_rtl_style',
			'gform_admin_rtl_style',
			'gform_admin_font_face',
			'gform_print_font_style'
		] );
	}

	/**
	 * Add last news rss of Persian GForm into admin area
	 *
	 * @action wp_dashboard_setup
	 *
	 * @return void
	 */
	public function dashboard_rss_widget(): void {

		if ( ! current_user_can( 'manage_options' ) || $this->option( 'rss_widget', '1' ) !== '1' ) {
			return;
		}

		add_meta_box( 'GFPersian_RSS', 'آخرین مطالب گرویتی فرم فارسی', [ $this, 'callback_rss_widget' ], 'dashboard', 'side', 'low' );
	}

	/**
	 * RSS meta box html output
	 *
	 * @return void
	 */
	public function callback_rss_widget(): void {
		$font  = $this->font_styles( true );
		$style = ! empty( $font ) ? 'style="font-family: GFPersian;"' : '';

		?>
		<div class="rss-widget" <?php echo esc_attr( $style ); ?>>
			<?php wp_widget_rss_output( [
				'url'          => 'https://gravityforms.ir/feed/',
				'items'        => 2,
				'show_summary' => 1,
				'show_author'  => 1,
				'show_date'    => 1
			] ); ?>
			<div style="border-top: 1px solid #e7e7e7; padding-top: 12px !important; font-size: 13px; height: 20px;">

				<img src="<?php echo GF_PERSIAN_URL ?>assets/images/logo.png" width="30" height="auto"
				     alt="گرویتی فرم فارسی"
				     style="float: right; margin: -10px 1px 0 10px"/>

				<a href="https://gravityforms.ir" target="_blank" title="گرویتی فرم فارسی">
					مشاهده وب سایت گرویتی فرم فارسی
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Hide deactivated license GForm banner
	 *
	 * @action admin_footer
	 *
	 * @return void
	 */
	public function hide_license(): void {

		if ( ! $this->is_gravity_page() || $this->option( 'hide_lic', '0' ) !== '1' ) {
			return;
		}

		?>
		<script type="text/javascript">
            jQuery(document).ready(function ($) {
                $('img').each(function () {
                    if (this.title.indexOf("licensed") !== -1 || this.alt.indexOf("licensed") !== -1)
                        $(this).hide().parent("a").hide().parent("div").hide();
                });
            });
		</script>
		<?php
	}

}

new GFPersian_Admin();