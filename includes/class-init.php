<?php

defined( 'ABSPATH' ) || exit;

/**
 * Initialize GFPersian Core
 */
class GFPersian_Init extends GFPersian_Core {

	public function __construct() {

		add_filter( 'load_textdomain_mofile', [ $this, 'load_translate' ], 10, 2 );
		add_action( 'gform_loaded', [ $this, 'load_settings' ], 5 );
		add_filter( 'gform_tooltips', [ $this, 'tooltips' ] );
		add_filter( 'gform_add_field_buttons', [ $this, 'fields_group' ] );
		add_action( 'gform_enqueue_scripts', [ $this, 'pgf_enqueue_scripts' ], 10, 2 );

		$this->include_files();
	}

	/**
	 * Loads the main general script to work with forms in frontend
	 *
	 * @param array $form
	 * @param bool $is_ajax
	 *
	 * @return void
	 */
	public function pgf_enqueue_scripts( $form, $is_ajax ) {

		wp_enqueue_script( 'pgf-general', GF_PERSIAN_URL . 'assets/js/general' . GFPersian_Core::minified() . '.js', [
			'jquery',
			'gform_gravityforms',
		], GF_PERSIAN_VERSION, true );

	}

	/**
	 * Load translation file integration
	 *
	 * @filter load_textdomain_mofile
	 *
	 * @param string $mo_file
	 * @param string $domain
	 *
	 * @return string
	 */
	public function load_translate( string $mo_file, string $domain ): string {

		if ( $this->option( 'translate', '1' ) !== '1' || get_locale() !== 'fa_IR' ) {
			return $mo_file;
		}

		$translates = [
			'gravityforms',
			'gravityformscoupons',
			'gravityformsmailchimp',
			'gravityformspolls',
			'gravityformsquiz',
			'gravityformssignature',
			'gravityformssurvey',
			'gravityformsuserregistration',
			'gravityformsauthorizenet',
			'gravityformsaweber',
			'gravityformscampaignmonitor',
			'gravityformspaypalpaymentspro',
			'gravityformsfreshbooks',
			'gravityformspaypal',
			'gravityformspaypalpro',
			'gravityformstwilio',
			'gravityformsstripe',
			'gravityformszapier',
			'sticky-list',
			'gf-limit',
		];

		if ( in_array( $domain, $translates ) ) {
			$mo_file = dirname( plugin_dir_path( __FILE__ ) ) . "/languages/$domain/$domain-fa_IR.mo";
		}

		return $mo_file;
	}

	/**
	 * Load GForms settings
	 *
	 * @action gform_loaded
	 *
	 * @return void
	 */
	public function load_settings(): void {

		if ( method_exists( 'GFForms', 'include_addon_framework' ) ) {

			GFForms::include_addon_framework();
			GFAddOn::register( 'GFPersian_Settings' );

			require_once( 'class-settings.php' );
		}
	}

	/**
	 * Show tooltip for Persian GForm
	 *
	 * @filter gform_tooltips
	 *
	 * @param array $tooltips
	 *
	 * @return array
	 */
	public function tooltips( array $tooltips ): array {
		$tooltips['form_gf_persian_fields'] = '<h6>گرویتی فرم فارسی</h6>فیلدهای برنامه نویسی شده توسط گرویتی فرم فارسی به مرور اینجا اضافه خواهند شد.';

		return $tooltips;
	}

	/**
	 * Add Persian GForm field groups
	 *
	 * @filter gform_add_field_buttons
	 *
	 * @param array $field_groups
	 *
	 * @return array
	 */
	public function fields_group( array $field_groups ): array {
		$group = 'gf_persian_fields';

		if ( ! function_exists( 'wp_list_pluck' ) || ! in_array( $group, wp_list_pluck( $field_groups, 'name' ) ) ) {
			$field_groups[] = [
				'name'   => $group,
				'label'  => 'فیلد های گرویتی فرم فارسی',
				'fields' => [],
			];
		}

		return $field_groups;
	}

	/**
	 * Load project files
	 *
	 * @return void
	 */
	private function include_files(): void {
		include_once 'class-version.php';
		include_once 'class-install.php';
		include_once 'class-admin.php';
		include_once 'class-address.php';
		include_once 'class-payments.php';
		include_once 'class-snippets.php';
		include_once 'class-merge-tag.php';
		include_once 'class-currencies.php';
		include_once 'class-jalali-date.php';
		include_once 'class-live-preview.php';
		include_once 'class-transaction-id.php';
		include_once 'class-multi-page-navi.php';
		include_once 'class-national-id.php';
		include_once 'class-notice.php';

		/**
		 * Load SMS feature files
		 */
		include_once 'sms/class-sms.php';
		require_once 'sms/class-db.php';
		require_once 'sms/class-feeds-list.php';
		require_once 'sms/class-feeds.php';
		require_once 'sms/class-sent-list.php';
		require_once 'sms/class-sent.php';
		require_once 'sms/class-entry.php';
		require_once 'sms/class-sender.php';
		require_once 'sms/gateways/class-gateway.php';

		// Load all files in gateways path
		foreach ( GFPersian_SMS::$gateways_files as $gateway_file ) {
			require_once $gateway_file;
		}

		// Fields
		require_once 'sms/class-verification-field.php';
		require_once 'sms/class-wp-sms-integrate-field.php';
	}

}

new GFPersian_Init();