<?php

use Hekmatinasser\Verta\Verta;

defined( 'ABSPATH' ) || exit;

class GFPersian_JalaliDate extends GFPersian_Core {

	public function __construct() {

		if ( $this->option( 'jalali', '1' ) != '1' ) {
			return;
		}

		add_filter( 'gform_tooltips', [ $this, 'tooltips' ] );
		add_action( 'gform_editor_js', [ $this, 'jalali_settings' ] );
		add_action( 'gform_field_standard_settings', [ $this, 'jalali_checkbox' ], 20, 2 );
		add_action( 'gform_field_standard_settings', [ $this, 'datepicker_theme' ], 21, 2 );
		add_action( 'gform_enqueue_scripts', [ $this, 'jalali_datepicker' ], 12, 2 );

		add_filter( 'gform_field_validation', [ $this, 'jalali_validator' ], 999999, 4 );
		add_filter( 'gform_predefined_choices', [ $this, 'jalali_predefined_choices' ], 1 );
	}

	/**
	 * Add tooltip about jalali option
	 *
	 * @filter gform_tooltips
	 *
	 * @param array $tooltips
	 *
	 * @return array
	 */
	public function tooltips( array $tooltips ): array {
		$tooltips['gform_activate_jalali']  = '<h6>فعالسازی تاریخ شمسی</h6>در صورتی که از چند فیلد تاریخ استفاده میکنید، فعالسازی تاریخ شمسی یکی از فیلدها کفایت میکند.<br/>تذکر : با توجه به آزمایشی بودن این قسمت ممکن است تداخل توابع سبب ناسازگاری با برخی قالب ها شود.';
		$tooltips['gform_datepicker_theme'] = '<h6>پوسته انتخاب کننده تاریخ</h6> از بین پوسته های تعریف شده جهت متمایز کردن انتخاب کننده، یکی را تنظیم کنید.';

		return $tooltips;
	}

	/**
	 * Configure jalali settings logic in date field settings
	 *
	 * @action gform_editor_js
	 *
	 * @return void
	 */
	public function jalali_settings(): void { ?>
		<script type='text/javascript'>

            function toggle_datepicker_theme(field) {
                let is_jalali_checked = field['check_jalali'] === 1;
                let is_datepicker_type = field['dateType'] === 'datepicker';

                if (!is_jalali_checked || !is_datepicker_type) {
                    jQuery('.datepicker_theme_container').hide();
                } else {
                    jQuery('.datepicker_theme_container').show();
                }
            }


            fieldSettings['date'] += ', .jalali_setting';
            jQuery(document).on('change', '#field_date_input_type, #check_jalali', function () {
                let field = GetSelectedField(); // get current field being edited
                toggle_datepicker_theme(field);
            });

            jQuery(document).bind('gform_load_field_settings', function (event, field, form) {
                jQuery('#check_jalali').prop('checked', field['check_jalali'] === 1);
                jQuery('#datepicker_theme').val(field['datepicker_theme'] || '');
            });
		</script>
		<?php
	}

	/**
	 * Add jalali option to date field
	 *
	 * @action gform_field_standard_settings
	 *
	 * @param int $position
	 * @param int $form_id
	 *
	 * @return void
	 *
	 */
	public function jalali_checkbox( int $position, int $form_id ): void {
		if ( $position == 25 ) { ?>
			<li class="jalali_setting field_setting">
				<input type="checkbox" id="check_jalali"
				       onclick="SetFieldProperty('check_jalali', jQuery(this).is(':checked') ? 1 : 0);"/>
				<label class="inline gfield_value_label" for="check_jalali" class="inline">
					فعالسازی تاریخ شمسی
					<?php gform_tooltip( "gform_activate_jalali" ) ?>
				</label>
			</li>

			<?php

		}
	}

	public function datepicker_theme( int $position, int $form_id ): void {
		if ( $position == 1225 ) { ?>

			<li class="jalali_setting field_setting datepicker_theme_container">
				<label for="datepicker_theme" class="section_label">
					<?php esc_html_e( 'پوسته', 'gravityforms' ); ?>
					<?php gform_tooltip( "gform_datepicker_theme" ) ?>
				</label>
				<select id="datepicker_theme" onchange="SetFieldProperty('datepicker_theme', jQuery(this).val());">
					<option value="">پیشفرض</option>
					<option value="dark">تیره</option>
					<option value="latoja">نقره ای</option>
					<option value="lightorang">نارنجی</option>
					<option value="melon">قرمز</option>
				</select>
			</li>
			<?php

		}
	}

	/**
	 * Load and localize jalali-datepicker when jalali option is enabled on field
	 *
	 * @action gform_enqueue_scripts
	 *
	 * @param array $form
	 * @param bool $ajax
	 *
	 * @return  void
	 */
	public function jalali_datepicker( array $form, bool $ajax ): void {

		if ( is_admin() ) {
			return;
		}

		if ( self::is_elementor() ) {
			return;
		}

		if ( ! ( wp_script_is( 'gform_datepicker_init' ) || wp_script_is( 'gform_datepicker_init', 'registered' ) ) ) {
			return;
		}

		foreach ( $form['fields'] as $field ) {

			if ( $field['type'] !== 'date' || ! rgar( $field, 'check_jalali', false ) ) {
				continue;
			}

			// Remove jquery and gform datepicker
			wp_dequeue_script( 'jquery-ui-datepicker' );
			wp_deregister_script( 'jquery-ui-datepicker' );
			wp_dequeue_script( 'gform_datepicker_init' );
			wp_deregister_script( 'gform_datepicker_init' );
			wp_dequeue_script( 'gform_datepicker_legacy' );
			wp_deregister_script( 'gform_datepicker_legacy' );

			remove_action( 'admin_enqueue_scripts', 'wp_localize_jquery_ui_datepicker', 1000 );
			remove_action( 'wp_enqueue_scripts', 'wp_localize_jquery_ui_datepicker', 1000 );

			// Register persian datepicker
			wp_enqueue_style( 'gf-persian-datepicker', GF_PERSIAN_URL . 'assets/js/datepicker/persian-datepicker.css', [], GF_PERSIAN_VERSION );
			wp_enqueue_script( 'gf-persian-datepicker', GF_PERSIAN_URL . 'assets/js/datepicker/persian-datepicker' . GFPersian_Core::minified() . '.js', [
				'jquery',
				'jquery-migrate',
				'gform_gravityforms',
			], GF_PERSIAN_VERSION, true );

			$datepicker_configuration = self::configure_date_picker( $field, $form );
			$datepicker_element_id    = '#input_' . $form['id'] . '_' . $field['id'];
			$inline_script            = 'jQuery(function ($) { $("' . $datepicker_element_id . '").persianDatepicker(' . $datepicker_configuration . ') });';

			wp_add_inline_script( 'gf-persian-datepicker', $inline_script );
		}


	}

	public static function convert_field_date_format( $date_format ): string {
		// The option is based on date field settings
		switch ( $date_format ) {
			case 'mdy':
				return '0M/0D/YYYY';
			case 'dmy':
				return '0D/0M/YYYY';
			case 'dmy_dash':
				return '0D-0M-YYYY';
			case 'dmy_dot':
				return '0D.0M.YYYY';
			case 'ymd_slash':
				return 'YYYY/0M/0D';
			case 'ymd_dash':
				return 'YYYY-0M-0D';
			case 'ymd_dot':
				return 'YYYY.0M.0D';
			default:
				return 'YYYY/0M/0D';
		}
	}


	public static function configure_date_picker( $field, $form ) {
		global $wp_locale;

		$date_format = self::convert_field_date_format( rgar( $field, 'dateFormat', 'mdy' ) );

		$theme = rgar( $field, 'datepicker_theme', 'default' );
		wp_enqueue_style( 'gf-persian-datepicker-theme-' . $theme, GF_PERSIAN_URL . 'assets/js/datepicker/persian-datepicker-' . $theme . '.css', [ 'gf-persian-datepicker' ], GF_PERSIAN_VERSION );

		return wp_json_encode(
			[
				'formatDate'        => $date_format,
				'months'            => [
					"فروردین",
					"اردیبهشت",
					"خرداد",
					"تیر",
					"مرداد",
					"شهریور",
					"مهر",
					"آبان",
					"آذر",
					"دی",
					"بهمن",
					"اسفند",
				],
				'dowTitle'          => [ "شنبه", "یکشنبه", "دوشنبه", "سه شنبه", "چهارشنبه", "پنج شنبه", "جمعه" ],
				'shortDowTitle'     => [ "ش", "ی", "د", "س", "چ", "پ", "ج" ],
				'showGregorianDate' => false,
				'persianNumbers'    => true,
				'selectedBefore'    => false,
				'selectedDate'      => null,
				'startDate'         => null,
				'endDate'           => null,
				'prevArrow'         => '◄',
				'nextArrow'         => '►',
				'theme'             => $theme,
				'alwaysShow'        => false,
				'selectableYears'   => null,
				'selectableMonths'  => [ 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 ],
				'cellWidth'         => 25, // in pixels
				'cellHeight'        => 20, // in pixels
				'fontSize'          => 13, // in pixels
				'isRTL'             => $wp_locale->is_rtl(),
				'calendarPosition'  => [
					'x' => 0,
					'y' => 0,
				],
			]
		);

	}

	/**
	 * Validate jalali submitted data from date field
	 *
	 * @filter gform_field_validation
	 *
	 * @param array $result {
	 *
	 * @type bool $is_valid
	 * @type array $message
	 *                               }
	 *
	 * @param mixed $value
	 * @param array $form
	 * @param GF_Field|array $field
	 *
	 * @return array
	 */
	public function jalali_validator( array $result, $value, array $form, $field ): array {

		if ( rgar( $field, 'type' ) !== 'date' || ! rgar( $field, 'check_jalali', false ) ) {
			return $result;
		}

		$format      = rgar( $field, 'dateFormat', 'mdy' );
		$format_name = self::convert_field_date_format( $format );
		$message     = $format_name && rgar( $field, 'dateType' ) == 'datepicker' ? sprintf( esc_html__( 'Please enter a valid date in the format (%s).', 'gravityforms' ), $format_name ) : esc_html__( 'Please enter a valid date.', 'gravityforms' );

		/*این شرط مشخص میکنه فقط اگر خطایی وجود نداشت و یا اگر خطا مربوط به ولیدیت تاریخ بود وارد بررسی شود*/
		if ( ! empty( $result['message'] ) && $message != $result['message'] ) {
			return $result;
		}

		if ( is_array( $value ) && rgempty( 0, $value ) && rgempty( 1, $value ) && rgempty( 2, $value ) ) {
			$value = null;
		}

		if ( ! empty( $value ) ) {

			$date  = GFCommon::parse_date( $value, $format );
			$day   = intval( rgar( $date, 'day' ) );
			$month = intval( rgar( $date, 'month' ) );
			$year  = intval( rgar( $date, 'year' ) );

			$result['is_valid'] = Verta::isValidDate( $year, $month, $day );;

			if ( ! $result['is_valid'] && empty( $result['message'] ) ) {
				$result['message'] = $message;
			}

		}

		return $result;
	}

	/**
	 * Add jalali month to GForm predefined choices
	 *
	 * @filter gform_predefined_choices
	 *
	 * @param array $choices
	 *
	 * @return array
	 */
	public function jalali_predefined_choices( array $choices ): array {

		$month['ماه های ایران'] = [
			'فروردین',
			'اردیبهشت',
			'خرداد',
			'تیر',
			'مرداد',
			'شهریور',
			'مهر',
			'آبان',
			'آذر',
			'دی',
			'بهمن',
			'اسفند',
		];

		return array_merge( $month, $choices );
	}

}

new GFPersian_JalaliDate();
