<?php

use Gravity_Forms\Gravity_Forms\Settings\Fields;
use Gravity_Forms\Gravity_Forms\Settings\Fields\Base;
use Gravity_Forms\Gravity_Forms\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFPersian_SMS extends GFPersian_Core {
	public array $no_conflict_styles = [];
	public array $no_conflict_scripts = [];
	/**
	 * @var bool check if routing field setting loaded
	 */
	public bool $loaded_notification_routing_field = false;
	/**
	 * @var array contains all gateways
	 */
	public static array $gateways_list;

	/**
	 * @var array contains all gateways path
	 */
	public static array $gateways_files;

	public function __construct() {
		$sms_gateway_files    = glob( __DIR__ . '/gateways/class-*.php' );
		self::$gateways_files = array_filter( $sms_gateway_files, function ( $file ) {
			return basename( $file ) !== 'class-gateway.php';
		} );

		// It depends on GForm classes (Sets GForm namespace too!)
		add_filter( 'gform_addon_navigation', [ $this, 'add_submenu' ], 20 );
		add_action( 'admin_menu', [ $this, 'register_sent_table_page' ] );
		add_action( 'admin_footer', [ $this, 'add_scripts' ], 100 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ], 100 );

		// Notification
		add_action( "gform_form_settings_page_sms_notification", [ $this, 'sms_notification_page' ], 100 );
		add_filter( 'gform_form_settings_menu', [ $this, 'sms_notification_tab' ], 10, 2 );
		add_action( 'wp_ajax_rg_update_sms_notification_active', [ $this, 'update_sms_notification_active' ] );
		add_filter( 'gform_form_post_get_meta', [ $this, 'remove_sms_notification' ], 10, 2 );

		add_action( 'wp_ajax_gf_resend_notifications', [ $this, 'resend_notifications' ] );
	}

	/**
	 * List out all forms which has enabled sms notifications
	 *
	 * @reaturn array
	 */
	public static function get_active_forms(): array {
		$active_forms = [];
		$forms        = GFAPI::get_forms();

		foreach ( $forms as $form ) {

			if ( ! isset( $form['notifications'] ) ) {
				continue;
			}

			foreach ( $form['notifications'] as $notification ) {
				if ( isset( $notification['type'] ) && 'sms' === $notification['type'] && isset( $notification['isActive'] ) && $notification['isActive'] ) {
					$active_forms[] = $form;
					break;
				}

			}

		}

		return $active_forms;
	}

	/**
	 * Get sms notifications from form
	 *
	 * @param string $event
	 * @param array $form
	 *
	 * @return array
	 */
	public static function get_notifications( string $event, array $form ): array {
		if ( rgempty( 'notifications', $form ) ) {
			return [];
		}

		$notifications = [];
		foreach ( $form['notifications'] as $notification ) {
			$notification_event = rgar( $notification, 'event' );
			$notification_type  = rgar( $notification, 'type' );
			$omit_from_resend   = [ 'form_saved', 'form_save_phone_requested' ];
			if ( 'sms' === $notification_type && $notification_event == $event || ( $event == 'resend_notifications' && ! in_array( $notification_event, $omit_from_resend ) ) ) {
				$notifications[] = $notification;
			}
		}

		return $notifications;
	}

	/**
	 * Get the list of notification to send
	 *
	 * @param string $event
	 * @param array $form
	 * @param mixed $lead
	 *
	 * @return array
	 */
	public static function get_notifications_to_send( string $event, array $form, $lead ): array {
		$notifications         = self::get_notifications( $event, $form );
		$notifications_to_send = [];

		foreach ( $notifications as $notification ) {
			if ( GFCommon::evaluate_conditional_logic( rgar( $notification, 'conditionalLogic' ), $form, $lead ) ) {
				$notifications_to_send[] = $notification;
			}
		}

		return $notifications_to_send;
	}

	/**
	 * Returns the page id based on url query parameters
	 *
	 * @return string
	 */
	public static function get_page(): string {
		$page = GFForms::get_page_query_arg();

		if ( $page == 'gf_edit_forms' && rgget( 'view' ) == 'settings' && rgget( 'subview' ) == 'sms_notification' && rgget( 'sid' ) ) {
			return 'sms_notification_edit';
		}

		if ( $page == 'gf_edit_forms' && rgget( 'view' ) == 'settings' && rgget( 'subview' ) == 'sms_notification' && rgget( 'sid' ) ) {
			return 'sms_notification_edit';
		}

		if ( $page == 'gf_edit_forms' && rgget( 'view' ) == 'settings' && rgget( 'subview' ) == 'sms_notification' ) {
			return 'sms_notification_list';
		}

		return '';
	}


	/**
	 * Remove SMS notification from Notification list
	 *
	 * @filter gform_form_post_get_meta
	 *
	 * @param array $form
	 * @paramn int $form_id
	 *
	 * @return array
	 */
	public function remove_sms_notification( array $form, ?int $form_id ): array {

		if ( rgget( 'subview' ) === 'notification' && isset( $form['notifications'] ) && is_array( $form['notifications'] ) ) {

			foreach ( $form['notifications'] as $key => $notification ) {

				if ( isset( $notification['type'] ) && $notification['type'] === 'sms' ) {
					unset( $form['notifications'][ $key ] );
				}

			}

		}

		return $form;
	}

	/**
	 * Updates the notification status (active/inactive).
	 *
	 * Called via AJAX.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFFormsModel::update_notification_active()
	 */
	public static function update_sms_notification_active() {
		check_ajax_referer( 'rg_update_sms_notification_active', 'rg_update_sms_notification_active' );

		if ( GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			GFFormsModel::update_notification_active( $_POST['form_id'], $_POST['notification_id'], $_POST['is_active'] );
		} else {
			wp_die( - 1, 403 );
		}
	}


	/**
	 * Add SMS notification tab to form settings
	 *
	 * @param array $setting_tabs
	 * @param int $form_id
	 *
	 * @return array
	 */
	public function sms_notification_tab( array $setting_tabs, $form_id ): array {
		$setting_tabs['32'] = [
			'name'         => 'sms_notification',
			'label'        => __( 'اعلانات پیامکی', 'gravityforms' ),
			'icon'         => 'gform-icon--mail',
			'query'        => [ 'sid' => null ],
			'capabilities' => [ 'gravityforms_edit_forms' ],
		];

		return $setting_tabs;
	}

	public function sms_notification_page() {
		require_once 'class-notification.php';
		require_once 'class-notification-routing.php';
		GFPersian_SMS_Notification::notification_page();
	}


	public function admin_enqueue_scripts( $hook ) {
		$scripts = [];
		$page    = self::get_page();
		switch ( $page ) {

			case 'sms_notification_list':
				$scripts = [
					'gform_forms',
					'gform_json',
					'gform_form_admin',
					'gform_gravityforms_admin',
					'sack',
				];
				break;

			case 'sms_notification_new':
			case 'sms_notification_edit':
				$scripts = [
					'gform_settings_dependencies',
					'gform_simplebar',
					'jquery-ui-autocomplete',
					'gform_gravityforms',
					'gform_gravityforms_admin',
					'gform_placeholder',
					'gform_form_admin',
					'gform_forms',
					'gform_json',
					'sack',
				];
				break;

		}

		if ( GFForms::page_supports_add_form_button() ) {

			wp_enqueue_script( 'gform_shortcode_ui' );
			wp_enqueue_style( 'gform_shortcode_ui' );
			wp_localize_script( 'gform_shortcode_ui', 'gfShortcodeUIData', [
				'shortcodes'      => GFForms::get_shortcodes(),
				'previewNonce'    => wp_create_nonce( 'gf-shortcode-ui-preview' ),

				/**
				 * Allows the enabling (false) or disabling (true) of a shortcode preview of a form
				 *
				 * @param bool $preview_disabled Defaults to true.  False to enable.
				 */
				'previewDisabled' => apply_filters( 'gform_shortcode_preview_disabled', true ),
				'strings'         => [
					'pleaseSelectAForm'   => wp_strip_all_tags( __( 'Please select a form.', 'gravityforms' ) ),
					'errorLoadingPreview' => wp_strip_all_tags( __( 'Failed to load the preview for this form.', 'gravityforms' ) ),
				]
			] );

		}

		if ( $page === 'form_editor' ) {

			$form_id      = filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT );
			$form_strings = [
				'requiredIndicator' => GFFormsModel::get_required_indicator( $form_id ),
				'defaultSubmit'     => __( 'Submit', 'gravityforms' ),
			];
			wp_localize_script( 'gform_form_editor', 'gform_form_strings', $form_strings );
			wp_enqueue_media();

		}

		if ( GFForms::has_members_plugin() && GFForms::get_page_query_arg() === 'roles' ) {
			wp_enqueue_style( 'gform_dashicons' );
		}

		if ( empty( $scripts ) ) {
			return;
		}

		foreach ( $scripts as $script ) {
			wp_enqueue_script( $script );
		}

		GFCommon::localize_gform_gravityforms_multifile();
		GFCommon::localize_legacy_check( 'gform_layout_editor' );

		/************************/
		// TODO: We have to load settings scripts only in add or edit notification pages.
		$settings = new Settings();
		// Enqueue scripts.
		foreach ( $settings->scripts() as $script ) {

			// Add to no-conflict scripts array.
			if ( ! in_array( $script['handle'], $this->no_conflict_scripts ) ) {
				$this->no_conflict_scripts[] = $script['handle'];
			}

			// Enqueue script.
			wp_enqueue_script(
				$script['handle'],
				rgar( $script, 'src', false ),
				rgar( $script, 'deps', [] ),
				rgar( $script, 'version', false ),
				rgar( $script, 'in_footer', false )
			);

			// Localize script strings.
			if ( rgar( $script, 'strings' ) ) {
				wp_localize_script( $script['handle'], $script['handle'] . '_strings', $script['strings'] );
			}

			if ( isset( $script['callback'] ) && is_callable( $script['callback'] ) ) {
				call_user_func( $script['callback'], $this );
			}

		}

		// Enqueue styles.
		foreach ( $settings->styles() as $style ) {


			// Add to no-conflict styles array.
			if ( ! in_array( $style['handle'], $this->no_conflict_styles ) ) {
				$this->no_conflict_styles[] = $style['handle'];
			}

			// Enqueue style.
			wp_enqueue_style(
				$style['handle'],
				rgar( $style, 'src', false ),
				rgar( $style, 'deps', [] ),
				rgar( $style, 'version', false ),
				rgar( $style, 'media', 'all' )
			);

		}

	}

	/**
	 * Add SMS settings to gravity form configuration
	 *
	 * @param array $submenus
	 *
	 * @return array
	 */
	public function add_submenu( array $submenus ): array {
		$submenus[] = [
			"name"       => "gfpersian_sms",
			"label"      => 'پیامک',
			"callback"   => [ GFPersian_SMS_Feeds::class, 'show_feeds_table' ],
			"permission" => 'manage_options'
		];

		return $submenus;
	}


	/**
	 * Get sent table link
	 *
	 * @return void
	 */
	public function get_sent_table_link(): void {
		$url = admin_url( 'admin.php?page=gfpersian_sent_table' );
		echo esc_url( $url );
	}

	/**
	 * Add sent table page
	 *
	 * @return void
	 */
	public function register_sent_table_page(): void {
		add_submenu_page(
			'gf_persian_sms',
			'پیامک های ارسال شده',
			'',
			'manage_options',
			'gfpersian_sent_table',
			[ GFPersian_SMS_Sent::class, 'show_sent_table' ]
		);
	}

	/**
	 * Get SMS general configuration options as array
	 * Short keys, Customization layer
	 *
	 * @return array
	 */
	public static function get_options(): array {
		$options = [
			'ws'       => self::_option( 'sms_gateway', 'none' ),
			'username' => self::_option( 'sms_username', '' ),
			'password' => self::_option( 'sms_password', '' ),
			'from'     => self::_option( 'sms_from_numbers', '' ),
			'code'     => self::_option( 'sms_country_code', '' ),
		];

		$options['from_array']   = explode( ',', $options['from'], );
		$options['from_default'] = ! empty( $options['from_array'] ) ? $options['from_array'][0] : '';

		return $options;
	}

	/**
	 * Process file name to generate gateway class name
	 * Converts: class-PGFLOG.php => GFPersian_SMS_PGFLOG
	 *
	 * @param string $filename
	 *
	 * @return string
	 */
	public static function get_gateway_class_from_file( string $filename ): string {
		$name_part = str_replace( [ 'class-', '.php' ], '', $filename );

		return 'GFPersian_SMS_' . $name_part;
	}

	/**
	 * Create array 'fqcn'=>'name' from sms gateways classes in directory
	 *
	 * @return array
	 */
	public static function get_gateways_list(): array {
		self::$gateways_list = [];

		foreach ( self::$gateways_files as $file_path ) {
			$file_name = basename( $file_path );
			// Skip abstract parent class
			if ( 'class-gateway.php' == $file_name ) {
				continue;
			}

			$class_name = self::get_gateway_class_from_file( $file_name );

			if ( ! method_exists( $class_name, 'name' ) ) {
				continue;
			}

			self::$gateways_list[ $class_name ] = $class_name::name();
		}


		return self::$gateways_list;
	}


	/**
	 * Create choices for sms general settings
	 *
	 * @return array suitable for GF Options
	 */
	public static function create_gateway_choices(): array {
		$choices       = [];
		$gateways_list = self::get_gateways_list();

		if ( empty( $gateways_list ) ) {
			$choices[] = [
				'label' => 'درگاه پیامکی تعریف نشده.',
				'value' => 'none'
			];

			return $choices;
		}

		foreach ( $gateways_list as $class => $name ) {
			$choices[] = [
				'label' => $name,
				'value' => $class
			];
		}

		return $choices;
	}

	/**
	 * Create choices from fields
	 *
	 * @param array $form
	 * @param string $type optional field type filtering
	 *
	 * @return array
	 */
	public function create_form_field_choices( array $form, string $type = '' ): array {
		$field_choices[] = [ 'value' => '', 'label' => 'انتخاب زمینه' ];

		foreach ( $form['fields'] as $field ) {

			if ( ! empty( $field['label'] ) ) {

				if ( ! empty( $type ) && $field['type'] !== $type ) {
					continue;
				}

				$field_choices[] = [
					'value' => $field['id'],
					'label' => $field['label'],
				];
			}

		}

		return $field_choices;
	}


	/**
	 * Extract merge tags in a form
	 *
	 * @param array $form
	 *
	 * @return array
	 */
	public function extract_form_merge_tags( array $form ): array {
		$merge_tags = [];

		foreach ( $form['fields'] as $field ) {

			$label = $field->label ?? 'بدون عنوان';
			$id    = $field->id;
			// Complex fields
			if ( ! empty( $field->inputs ) ) {
				foreach ( $field->inputs as $input ) {
					$sub_id    = $input['id'];
					$sub_label = $input['label'] ?? 'بخش اضافی';

					$merge_tags[ $sub_label ] = "{{$label} ({$sub_label}):{$sub_id}}";
				}
			} else {
				// Simple fields
				$merge_tags[ $label ] = "{{$label}:{$id}}";
			}
		}

		return $merge_tags;
	}

	/**
	 * Create suitable array for GForm settings api from merge tags
	 *
	 * @param array $form
	 *
	 * @return array
	 */
	public function create_merge_tags_choices( array $form ): array {
		$merge_tags = $this->extract_form_merge_tags( $form );

		$choices = [ [ 'value' => '', 'label' => '-- انتخاب تگ جایگذاری --' ] ];

		foreach ( $merge_tags as $label ) {
			$choices[] = [ 'value' => $label, 'label' => $label ];
		}

		return $choices;
	}

	/**
	 * Get notification metadata
	 *
	 * @param array $notification
	 * @param array $form
	 * @param string $meta
	 * @param mixed $default
	 *
	 */
	public static function get_notification_meta( array $notification, array $form, string $meta, $default = '' ) {

		if ( empty( $notification['id'] ) || empty( $form['id'] ) ) {
			return $default;
		}

		$form_metas         = GFFormsModel::get_form_meta( $form['id'] );
		$form_notifications = $form_metas['notifications'];

		if ( empty( $form_notifications[ $notification['id'] ] ) ) {
			return $default;
		}

		$form_notification_meta = $form_notifications[ $notification['id'] ];

		if ( empty( $form_notification_meta[ $meta ] ) ) {
			return $default;
		}

		return $form_notification_meta[ $meta ];
	}


	/**
	 * Create choices field options from sender number
	 *
	 * @param array $form
	 *
	 * @return array
	 */
	public static function create_from_numbers_choices( array $form ): array {
		$choices      = [ [ 'value' => '', 'label' => '-- انتخاب شماره فرستنده --', 'disable' => true ] ];
		$from_numbers = GFPersian_Core::_option( 'sms_from_numbers', '' );

		if ( empty( $from_numbers ) ) {
			return $choices;
		}

		$from_numbers = explode( ',', $from_numbers );

		foreach ( $from_numbers as $from ) {
			$choices[] = [ 'value' => $from, 'label' => $from ];
		}

		return $choices;
	}

	/**
	 * Add custom scripts related to the form settings
	 *
	 * @action admin_footer
	 *
	 * @return void
	 */
	public function add_scripts(): void {
		$screen = get_current_screen();

		if ( ! $screen || $screen->id !== 'toplevel_page_gf_edit_forms' ) {
			return;
		}

		?>
		<script>
            document.addEventListener("DOMContentLoaded", function () {

                const elements = [
                    {
                        select: document.querySelector("[name='_gform_setting_adminMessageSelectMergeTags']"),
                        textarea: document.querySelector("[name='_gform_setting_gf_sms_admin_message']")
                    },
                    {
                        select: document.querySelector("[name='_gform_setting_clientMessageSelectMergeTags']"),
                        textarea: document.querySelector("[name='_gform_setting_gf_sms_client_message']")
                    }
                ];

                elements.forEach(({select, textarea}) => {
                    if (select && textarea) {
                        select.addEventListener("change", function () {
                            if (this.value) {
                                textarea.value += " " + this.value;
                                this.value = "";
                            }
                        });
                    }
                });


            });
		</script>
		<?php
	}

	/*TODO : Resending notifications with sms, its now working only with email*/
	/**
	 * Resends failed notifications
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFCommon::send_notification()
	 */
	public static function resend_notifications() {

		check_admin_referer( 'gf_resend_notifications', 'gf_resend_notifications' );
		$form_id = absint( rgpost( 'formId' ) );
		$leads   = rgpost( 'leadIds' ); // may be a single ID or an array of IDs

		if ( 0 == $leads ) {

			// get all the lead ids for the current filter / search
			$filter = rgpost( 'filter' );
			$search = rgpost( 'search' );
			$star   = $filter == 'star' ? 1 : null;
			$read   = $filter == 'unread' ? 0 : null;
			$status = in_array( $filter, [ 'trash', 'spam' ] ) ? $filter : 'active';

			$search_criteria['status'] = $status;

			if ( $star ) {
				$search_criteria['field_filters'][] = [ 'key' => 'is_starred', 'value' => (bool) $star ];
			}

			if ( ! is_null( $read ) ) {
				$search_criteria['field_filters'][] = [ 'key' => 'is_read', 'value' => (bool) $read ];
			}

			$search_field_id = rgpost( 'fieldId' );

			if ( isset( $_POST['fieldId'] ) && $_POST['fieldId'] !== '' ) {
				$key            = $search_field_id;
				$val            = $search;
				$strpos_row_key = strpos( $search_field_id, '|' );

				if ( $strpos_row_key !== false ) { //multi-row
					$key_array = explode( '|', $search_field_id );
					$key       = $key_array[0];
					$val       = $key_array[1] . ':' . $val;
				}

				$search_criteria['field_filters'][] = [
					'key'      => $key,
					'operator' => rgempty( 'operator', $_POST ) ? 'is' : rgpost( 'operator' ),
					'value'    => $val,
				];

			}

			$leads = GFFormsModel::search_lead_ids( $form_id, $search_criteria );

		} else {
			$leads = ! is_array( $leads ) ? [ $leads ] : $leads;
		}

		/**
		 * Filters the notifications to be re-sent
		 *
		 * @param array $form_meta The Form Object
		 * @param array $leads The entry IDs
		 *
		 * @since Unknown
		 *
		 */
		$form = gf_apply_filters( [
			'gform_before_resend_notifications',
			$form_id
		], RGFormsModel::get_form_meta( $form_id ), $leads );

		if ( empty( $leads ) || empty( $form ) ) {
			esc_html_e( 'There was an error while resending the notifications.', 'gravityforms' );
			die();
		};

		$notifications = json_decode( rgpost( 'notifications' ) );

		if ( ! is_array( $notifications ) ) {
			die( esc_html__( 'No notifications have been selected. Please select a notification to be sent.', 'gravityforms' ) );
		}

		if ( ! rgempty( 'sendTo', $_POST ) && ! GFCommon::is_valid_email_list( rgpost( 'sendTo' ) ) ) {
			die( sprintf( esc_html__( 'The %sSend To%s email address provided is not valid.', 'gravityforms' ), '<strong>', '</strong>' ) );
		}

		foreach ( $leads as $lead_id ) {

			$lead = RGFormsModel::get_lead( $lead_id );

			foreach ( $notifications as $notification_id ) {

				$notification = $form['notifications'][ $notification_id ];

				if ( ! $notification ) {
					continue;
				}

				if ( rgpost( 'sendTo' ) ) {
					$notification['to']     = rgpost( 'sendTo' );
					$notification['toType'] = 'phone';
				}

				/**
				 * Allow the resend notification email to be skipped
				 *
				 * @param bool $abort_email Should we prevent this email being sent?
				 * @param array $notification The current notification object.
				 * @param array $form The current form object.
				 * @param array $lead The current entry object.
				 *
				 * @since 2.3
				 *
				 */
				$abort_email = apply_filters( 'gform_disable_resend_notification', false, $notification, $form, $lead );

				if ( ! $abort_email ) {
					GFCommon::send_notification( $notification, $form, $lead );
				}

				/**
				 * Fires after the current notification processing is finished
				 *
				 * @param array $notification The current notification object.
				 * @param array $form The current form object.
				 * @param array $lead The current entry object.
				 *
				 * @since 2.3
				 *
				 */
				do_action( 'gform_post_resend_notification', $notification, $form, $lead );
			}

		}

		/**
		 * Fires after the resend notifications processing is finished
		 *
		 * @param array $form The current form object.
		 * @param array $lead The current entry object.
		 *
		 * @since 2.3
		 *
		 */
		do_action( 'gform_post_resend_all_notifications', $form, $lead );

		die();
	}

}

new GFPersian_SMS();