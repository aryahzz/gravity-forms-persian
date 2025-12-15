<?php

use Gravity_Forms\Gravity_Forms\Settings\Fields;
use Gravity_Forms\Gravity_Forms\Settings\Settings;

class_exists( 'GFForms' ) || die();

/**
 * Class GFPersian_SMS_Notification
 * Handles notifications within Gravity Forms
 */
class GFPersian_SMS_Notification {

	use Redirects_On_Save;

	/**
	 * Defines the fields that support notifications.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @var array Array of field types.
	 */
	private static array $supported_fields = [
		'checkbox',
		'radio',
		'select',
		'text',
		'website',
		'textarea',
		'email',
		'hidden',
		'number',
		'phone',
		'multiselect',
		'post_title',
		'post_tags',
		'post_custom_field',
		'post_content',
		'post_excerpt',
	];

	/**
	 * Stores the current instance of the Settings renderer.
	 *
	 * @since 2.5
	 *
	 * @var false|Settings
	 */
	private static $_settings_renderer = false;

	/**
	 * Gets a notification based on a Form Object and a notification ID.
	 *
	 * @param array|null $form The Form Object.
	 * @param string|int|null $notification_id The notification ID.
	 *
	 * @return array The Notification Object.
	 * @since  Unknown
	 * @access private
	 *
	 */
	private static function get_notification( $form, $notification_id ): array {

		if ( ! isset( $form['notifications'] ) ) {
			return [];
		}

		foreach ( $form['notifications'] as $id => $notification ) {
			if ( $id == $notification_id && ( isset( $notification['type'] ) && $notification['type'] == 'sms' ) ) {
				return $notification;
			}
		}

		return [];
	}

	/**
	 * Displays the Notification page.
	 *
	 * If the notification ID is passed, the Notification Edit page is displayed.
	 * Otherwise, the Notification List page is displayed.
	 *
	 * @return void
	 * @uses GFPersian_SMS_Notification::notification_edit_page()
	 * @uses GFPersian_SMS_Notification::notification_list_page()
	 *
	 * @since  Unknown
	 * @access public
	 *
	 */
	public static function notification_page() {

		$form_id         = rgget( 'id' );
		$notification_id = rgget( 'sid' );

		if ( ! rgblank( $notification_id ) ) {
			// Edit or Add new sms notification
			self::notification_edit_page( $form_id, $notification_id );
		} else {
			// Notifications table
			self::notification_list_page( $form_id );
		}
	}

	/**
	 * Builds the Notification Edit page.
	 *
	 * @access public
	 *
	 * @used-by GFPersian_SMS_Notification::notification_page()
	 *
	 * @param int $form_id The ID of the form that the notification belongs to
	 * @param int $notification_id The ID of the notification being edited
	 *
	 * @return void
	 * @uses    GFFormsModel::get_form_meta()
	 * @uses    GFPersian_SMS_Notification::get_notification()
	 * @uses    GFPersian_SMS_Notification::validate_notification
	 * @uses    GFFormsModel::sanitize_conditional_logic()
	 * @uses    GFFormsModel::trim_conditional_logic_values_from_element()
	 * @uses    GFFormsModel::save_form_notifications()
	 * @uses    GFCommon::add_message()
	 * @uses    GFCommon::json_decode()
	 * @uses    GFCommon::add_error_message()
	 * @uses    GFFormSettings::page_header()
	 * @uses    GFPersian_SMS_Notification::get_notification_ui_settings()
	 * @uses    SCRIPT_DEBUG
	 * @uses    GFFormsModel::get_entry_meta()
	 * @uses    GFFormSettings::output_field_scripts()
	 * @uses    GFFormSettings::page_footer()
	 *
	 */
	public static function notification_edit_page( $form_id, $notification_id ) {

		// Form ID isn't valid (it's either deleted or just not an existing form ID)
		if ( ! GFAPI::form_id_exists( $form_id ) ) {
			GFCommon::log_error( __METHOD__ . '(): Invalid Form ID: ' . $form_id );
			wp_die( 'Invalid Form ID' );
		}

		GFFormSettings::page_header( esc_html__( 'اعلانات پیامکی', 'gravityforms' ) );

		if ( ! self::get_settings_renderer() ) {
			self::initialize_settings_renderer();
		}

		// Render settings.
		self::get_settings_renderer()->render();

		GFFormSettings::page_footer();

	}

	/**
	 * Get Notification settings fields.
	 *
	 * @param array $notification Notification being edited.
	 * @param array $form The Form object.
	 *
	 * @return array
	 * @since 2.5
	 */
	private static function settings_fields( $notification, $form ) {

		// Get notification events.
		$events = self::get_notification_events( $form );

		// Prepare notification events as choices.
		$events_choices = [];
		foreach ( $events as $name => $label ) {
			$events_choices[] = [
				'label' => $label,
				'value' => $name,
			];
		}

		/**
		 * Disable the From Phone warning.
		 *
		 * @param bool $disable_from_warning Should the From Phone warning be disabled?
		 *
		 * @since 2.4.13
		 *
		 */
		$disable_from_warning = gf_apply_filters( [ 'gform_notification_disable_from_warning', $form['id'], rgar( $notification, 'id' ) ], false );

		$from_phone_warning = '';

		// Prepare From Phone warning.
		if ( ! $disable_from_warning && self::get_settings_renderer()->get_value( 'service' ) === 'wordpress' ) {

			// Get From Phone address.
			$from_address = self::get_settings_renderer()->get_value( 'from' );

			// Determine if From Phone is invalid
			$is_invalid_from_phone = ! empty( $from_address ) && ! self::is_valid_notification_phone( $from_address );

			// Display warning message if not using an phone address containing the site domain or {admin_phone}.
			if ( ! $is_invalid_from_phone && ! self::is_site_domain_in_from( $from_address ) ) {
				$from_phone_warning = sprintf(
					'<div class="alert warning" role="alert" style="">%s</div>',
					sprintf(
						esc_html__( 'Warning! Using a third-party phone in the From Phone field may prevent your notification from being delivered. It is best to use a phone with the same domain as your website. %sMore details in our documentation.%s', 'gravityforms' ),
						'<a href="https://docs.gravityforms.com/troubleshooting-notifications/#use-a-valid-from-address" target="_blank" >',
						'</a>'
					)
				);
			}

		}

		// Prepare To Type field.
		if ( 'hidden' === rgar( $notification, 'toType' ) ) {
			$to_type = [
				'name'          => 'toType',
				'type'          => 'hidden',
				'default_value' => 'hidden',
			];
		} else {
			$to_type = [
				'name'          => 'toType',
				'label'         => esc_html__( 'Send To', 'gravityforms' ),
				'tooltip'       => gform_tooltip( 'sms_notification_send_to_phone', null, true ),
				'type'          => 'radio',
				'horizontal'    => true,
				'choices'       => [
					[
						'label' => esc_html__( 'ثبت شماره تلفن', 'gravityforms' ),
						'value' => 'phone',
					],
					[
						'label' => esc_html__( 'Select a Field', 'gravityforms' ),
						'value' => 'field',
					],
					[
						'label'   => esc_html__( 'Configure Routing', 'gravityforms' ),
						'value'   => 'routing',
						'tooltip' => gform_tooltip( 'sms_notification_send_to_routing', null, true ),
					],
				],
				'default_value' => 'phone',
			];
		}

		$fields = [
			[
				'title'  => esc_html__( 'اعلانات پیامکی', 'gravityforms' ),
				'fields' => [
					[
						'name'          => 'type',
						'type'          => 'hidden',
						'default_value' => 'sms',
					],
					[
						'name'          => 'isActive',
						'type'          => 'hidden',
						'default_value' => true,
					],
					[
						'name'     => 'name',
						'label'    => esc_html__( 'Name', 'gravityforms' ),
						'type'     => 'text',
						'required' => true,
					],
					[
						'name'    => 'event',
						'label'   => esc_html__( 'Event', 'gravityforms' ),
						'tooltip' => gform_tooltip( 'sms_notification_event', null, true ),
						'type'    => 'select',
						'choices' => $events_choices,
						'hidden'  => count( $events_choices ) === 1,
					],
					$to_type,
					[
						'name'                => 'toPhone',
						'label'               => esc_html__( 'شماره دریافت کننده', 'gravityforms' ),
						'type'                => 'text',
						'required'            => true,
						'default_value'       => '{admin_phone}',
						'dependency'          => [
							'live'   => true,
							'fields' => [
								[
									'field'  => 'toType',
									'values' => [ 'phone' ],
								],
							],
						],
						'validation_callback' => function ( $field, $value ) {

							// Determine if valid.
							$is_valid = GFPersian_SMS_Notification::is_valid_notification_phone( $value );

							// Get filter parameters.
							$to_type  = GFPersian_SMS_Notification::get_settings_renderer()->get_value( 'toType' );
							$to_field = GFPersian_SMS_Notification::get_settings_renderer()->get_value( 'toField' );

							/**
							 * Allows overriding of the notification destination validation
							 *
							 * @param bool $is_valid True if valid. False, otherwise.
							 * @param string $gform_notification_to_type The type of destination.
							 * @param string $gform_notification_to_phone The destination phone number, if available.
							 * @param string $gform_notification_to_field The field that is being used for the notification, if available.
							 *
							 * @since Unknown
							 *
							 */
							$is_valid = apply_filters( 'gform_is_valid_notification_to', $is_valid, $to_type, $value, $to_field );

							if ( ! $is_valid ) {
								$field->set_error( __( 'Please enter a valid phone address.', 'gravityforms' ) );
							}

						},
					],
					[
						'name'                => 'toField',
						'label'               => esc_html__( 'Send To Field', 'gravityforms' ),
						'type'                => 'field_select',
						'required'            => true,
						'args'                => [ 'input_types' => [ 'phone' ] ],
						'no_choices'          => esc_html__( 'Your form does not have a phone field. Add a phone field to your form and try again.', 'gravityforms' ),
						'fields_callback'     => [ self::class, 'append_filtered_notification_phone_fields' ],
						'dependency'          => [
							'live'   => true,
							'fields' => [
								[
									'field'  => 'toType',
									'values' => [ 'field' ],
								],
							],
						],
						'validation_callback' => function ( $field, $value ) {

							// Get filter parameters.
							$to_type  = GFPersian_SMS_Notification::get_settings_renderer()->get_value( 'toType' );
							$to_phone = GFPersian_SMS_Notification::get_settings_renderer()->get_value( 'toPhone' );

							/**
							 * Allows overriding of the notification destination validation
							 *
							 * @param bool $is_valid True if valid. False, otherwise.
							 * @param string $gform_notification_to_type The type of destination.
							 * @param string $gform_notification_to_phone The destination phone number, if available.
							 * @param string $gform_notification_to_field The field that is being used for the notification, if available.
							 *
							 * @since Unknown
							 *
							 */
							$is_valid = apply_filters( 'gform_is_valid_notification_to', ! empty( $value ), $to_type, $to_phone, $value );

							if ( ! $is_valid ) {
								$field->set_error( __( 'Please select a Phone Number field.', 'gravityforms' ) );
							}

						},
					],
					[
						'name'       => 'routing',
						'type'       => 'sms_notification_routing',
						'dependency' => [
							'live'   => true,
							'fields' => [
								[
									'field'  => 'toType',
									'values' => [ 'routing' ],
								],
							],
						],
					],
					[
						'name'    => 'from',
						'label'   => esc_html__( 'از (ارسال کننده)', 'gravityforms' ),
						'tooltip' => gform_tooltip( 'sms_notification_from_phone', null, true ),
						'type'    => 'select',
						'choices' => GFPersian_SMS::create_from_numbers_choices( $form ),
						'class'   => 'mt-position-right mt-hide_all_fields',

					],

					[
						'name'       => 'message',
						'label'      => esc_html__( 'Message', 'gravityforms' ),
						'type'       => 'textarea',
						'class'      => 'merge-tag-support',
						'use_editor' => false,
						'required'   => true,
					],
					[
						'name'    => 'disableAutoformat',
						'label'   => esc_html__( 'Auto-formatting', 'gravityforms' ),
						'tooltip' => gform_tooltip( 'sms_notification_autoformat', null, true ),
						'type'    => 'checkbox',
						'hidden'  => true,
						'value'   => true,
						'choices' => [
							[
								'name'  => 'disableAutoformat',
								'label' => esc_html__( 'Disable auto-formatting', 'gravityforms' ),
							],
						],
					],
					[
						'name'        => 'conditionalLogic',
						'label'       => esc_html__( 'منطق شرطی', 'gravityforms ' ),
						'type'        => 'conditional_logic',
						'object_type' => 'notification',
						'checkbox'    => [
							'label'  => esc_html__( 'فعالسازی منطق شرطی', 'gravityforms' ),
							'hidden' => false,
						],
					],
					[
						'type'  => 'save',
						'value' => esc_html__( 'بروزرسانی اعلان پیامکی', 'gravityforms' ),
					],
				],
			],
		];

		/**
		 * Filters the Notification settings fields before they are displayed.
		 *
		 * @param array $fields Form settings fields.
		 * @param array $form Form Object.
		 *
		 * @since 2.5
		 *
		 */
		$fields = gf_apply_filters( [ 'gform_notification_settings_fields', $form['id'] ], $fields, $notification, $form );

		return $fields;

	}

	/**
	 * Pass the field choices for the select field through the gform_phone_fields_notification_admin filter to allow
	 * third-parties to add or remove arbitrary fields.
	 *
	 * @param array $fields The form fields to be used as choices.
	 * @param array $form The form belonging to the notification being configured.
	 *
	 * @return array
	 * @since 2.5.7
	 *
	 */
	public static function append_filtered_notification_phone_fields( $fields, $form ) {
		return gf_apply_filters( [ 'gform_phone_fields_notification_admin', $form['id'] ], $fields, $form );
	}

	// # SETTINGS RENDERER ---------------------------------------------------------------------------------------------

	/**
	 * Initialize Plugin Settings fields renderer.
	 *
	 * @since 2.5
	 */
	public static function initialize_settings_renderer() {

		if ( ! class_exists( 'GFFormSettings' ) ) {

			require_once( GFCommon::get_base_path() . '/form_settings.php' );
		}

		$form_id         = rgget( 'id' );
		$notification_id = rgget( 'sid' );

		if ( ! rgempty( 'gform_notification_id' ) ) {
			$notification_id = rgpost( 'gform_notification_id' );
		}

		$form = GFFormsModel::get_form_meta( $form_id );

		/**
		 * Filters the form to be used in the notification page
		 *
		 * @param array $form The Form Object
		 * @param int $notification_id The notification ID
		 *
		 * @since 1.8.6
		 *
		 */

		$form = gf_apply_filters( [ 'gform_form_notification_page', $form_id ], $form, $notification_id );

		$notification = ! $notification_id ? [] : self::get_notification( $form, $notification_id );

		// Prepare initial values.
		$initial_notification                                          = $notification;
		$initial_notification['toPhone']                               = rgar( $notification, 'toType' ) === 'phone' ? rgar( $notification, 'to' ) : '';
		$initial_notification['toField']                               = rgar( $notification, 'toType' ) === 'field' ? rgar( $notification, 'to' ) : '';
		$initial_notification['notification_conditional_logic']        = is_array( rgar( $notification, 'conditionalLogic' ) );
		$initial_notification['notification_conditional_logic_object'] = rgar( $notification, 'conditionalLogic' );

		// Initialize new settings renderer.

		$renderer = new Gravity_Forms\Gravity_Forms\Settings\Settings(
			[
				'initial_values' => $initial_notification,
				'save_callback'  => function ( $values ) use ( &$notification, &$form, &$notification_id ) {

					// Determine if new notification.
					$is_new_notification = empty( $notification ) || empty( $notification_id );

					// Set notification ID.
					if ( $is_new_notification ) {
						$notification_id = $notification['id'] = uniqid();
					}

					// Removing legacy (pre-1.7) admin/user notification property.
					unset( $notification['type'] );

					// Save values to the confirmation object in advance so non-custom values will be rewritten when we apply values below.
					$notification = GFFormSettings::save_changed_form_settings_fields( $notification, $values );

					$notification['name']  = rgar( $values, 'name' );
					$notification['event'] = rgar( $values, 'event' );

					$notification['toType'] = rgar( $values, 'toType' );
					$notification['to']     = rgar( $values, 'toType' ) === 'phone' ? rgar( $values, 'toPhone' ) : ( rgar( $values, 'toType' ) === 'field' ? rgar( $values, 'toField' ) : '' );

					$notification['from'] = rgar( $values, 'from' );

					$notification['message'] = rgar( $values, 'message' );

					$notification['disableAutoformat'] = (bool) rgar( $values, 'disableAutoformat' );

					$notification['type'] = 'sms';

					// Set the conditional logic object, and clear it if conditional logic is disabled
					$conditionalLogicObject           = rgar( $values, 'notification_conditional_logic_object' );
					$notification['conditionalLogic'] = rgar( $values, 'notification_conditional_logic' ) && is_array( $conditionalLogicObject ) ? GFFormsModel::sanitize_conditional_logic( $conditionalLogicObject ) : null;

					if ( isset( $values['routing'] ) && ! empty( $values['routing'] ) ) {
						$routing_logic           = [ 'rules' => $values['routing'] ];
						$routing_logic           = GFFormsModel::sanitize_conditional_logic( $routing_logic );
						$notification['routing'] = $routing_logic['rules'];
					}

					$notification = GFCommon::fix_notification_routing( $notification );

					/**
					 * Filters the notification before it is saved
					 *
					 * @param array $form The Form Object.
					 * @param bool $is_new_notification True if it is a new notification.  False otherwise.
					 *
					 * @param array $notification The Notification Object.
					 *
					 * @since 1.7
					 *
					 */
					$notification = gf_apply_filters( [
						'gform_pre_sms_notification_save',
						$form['id'],
					], $notification, $form, $is_new_notification );

					// Save notification.

					$notification                              = GFFormsModel::trim_conditional_logic_values_from_element( $notification, $form );
					$form['notifications'][ $notification_id ] = $notification;

					GFFormsModel::save_form_notifications( $form['id'], $form['notifications'] );


					self::$_saved_item_id = $notification_id;
				},
				'before_fields'  => function () use ( &$form, $form_id, &$notification, $notification_id ) {
					?>

					<script type="text/javascript">

                        gform.addFilter('gform_merge_tags', 'MaybeAddSaveLinkMergeTag');

                        function MaybeAddSaveLinkMergeTag(mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option) {
                            var event = document.getElementById('event').value;
                            if (event === 'form_saved' || event === 'form_save_phone_requested') {
                                mergeTags['other'].tags.push({
                                    tag: '{save_link}',
                                    label: <?php echo json_encode( esc_html__( 'Save & Continue Link', 'gravityforms' ) ); ?>
                                });
                                mergeTags['other'].tags.push({
                                    tag: '{save_token}',
                                    label: <?php echo json_encode( esc_html__( 'Save & Continue Token', 'gravityforms' ) ); ?>
                                });
                            }
                            return mergeTags;
                        }

						<?php
						if ( empty( $form['notifications'] ) ) {
							$form['notifications'] = [];
						}

						$entry_meta = GFFormsModel::get_entry_meta( $form_id );
						/**
						 * Filters the entry meta when notification conditional logic is being edited
						 *
						 * @param array $entry_meta The Entry meta
						 * @param array $form The Form Object
						 * @param int $notification_id The notification ID
						 *
						 * @since 1.7.6
						 *
						 */
						$entry_meta = apply_filters( 'gform_entry_meta_conditional_logic_notifications', $entry_meta, $form, $notification_id );

						?>

                        var form = <?php echo json_encode( $form ) ?>;
                        var current_notification = <?php echo GFCommon::json_encode( $notification ) ?>;
                        var entry_meta = <?php echo GFCommon::json_encode( $entry_meta ) ?>;

                        jQuery(function () {
                            ToggleConditionalLogic(true, 'notification');
                        });

						<?php GFFormSettings::output_field_scripts() ?>

					</script>

					<?php
				},
				'after_fields'   => function () use ( &$notification_id ) {
					printf( '<input type="hidden" id="gform_notification_id" name="gform_notification_id" value="%s" />', esc_attr( $notification_id ) );
				}
			]
		);


		// Save renderer to class.
		self::set_settings_renderer( $renderer );


		// Define settings fields.
		self::get_settings_renderer()->set_fields( self::settings_fields( $notification, $form ) );

		if ( self::is_save_redirect( 'sid' ) ) {
			self::get_settings_renderer()->set_save_message_after_redirect();
		}

		// Process save callback.
		if ( self::get_settings_renderer()->is_save_postback() ) {
			self::get_settings_renderer()->process_postback();
			self::redirect_after_valid_save( 'sid' );
		}


	}

	/**
	 * Gets the current instance of Settings handling settings rendering.
	 *
	 * @return false|Settings
	 * @since 2.5
	 *
	 */
	public static function get_settings_renderer() {
		return self::$_settings_renderer;
	}

	/**
	 * Sets the current instance of Settings handling settings rendering.
	 *
	 * @param Settings $renderer Settings renderer.
	 *
	 * @return bool|WP_Error
	 * @since 2.5
	 *
	 */
	private static function set_settings_renderer( $renderer ) {

		// Ensure renderer is an instance of Settings
		if ( ! is_a( $renderer, 'Gravity_Forms\Gravity_Forms\Settings\Settings' ) ) {
			return new WP_Error( 'Renderer must be an instance of Gravity_Forms\Gravity_Forms\Settings\Settings.' );
		}

		self::$_settings_renderer = $renderer;

		return true;

	}

	// # NOTIFICATION LIST ---------------------------------------------------------------------------------------------

	/**
	 * Displays the notification list page
	 *
	 * @param int $form_id The form ID to list notifications on.
	 *
	 * @return void
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by self::notification_page()
	 * @uses    self::maybe_process_notification_list_action()
	 * @uses    GFFormsModel::get_form_meta()
	 * @uses    GFFormSettings::page_header()
	 * @uses    GFNotificationTable::__construct()
	 * @uses    GFNotificationTable::prepare_items()
	 * @uses    GFNotificationTable::display()
	 * @uses    GFFormSettings::page_footer()
	 *
	 */
	public static function notification_list_page( $form_id ) {

		// Handle form actions
		self::maybe_process_notification_list_action();


		$form = GFFormsModel::get_form_meta( $form_id );

		$notification_table = new GFPersian_SMS_Notification_Table( $form );
		$notification_table->prepare_items();

		GFFormSettings::page_header();
		?>

		<div class="gform-settings-panel">
			<header class="gform-settings-panel__header">
				<h4 class="gform-settings-panel__title"><?php esc_html_e( 'اعلانات پیامکی', 'gravityforms' ); ?></h4>
			</header>

			<div class="gform-settings-panel__content">

				<form id="notification_list_form" method="post">

					<?php
					$notification_table->display();
					wp_nonce_field( 'gform_notification_list_action', 'gform_notification_list_action' );
					?>

					<input id="action_argument" name="action_argument" type="hidden"/>
					<input id="action" name="action" type="hidden"/>

				</form>

			</div>

		</div>

		<script type="text/javascript">
            function ToggleActive(btn, notification_id) {
                var is_active = jQuery(btn).hasClass('gform-status--active');

                jQuery.ajax(
                    {
                        url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'rg_update_sms_notification_active',
                            rg_update_sms_notification_active: '<?php echo wp_create_nonce( 'rg_update_sms_notification_active' ); ?>',
                            form_id: '<?php echo intval( $form_id ); ?>',
                            notification_id: notification_id,
                            is_active: is_active ? 0 : 1,
                        },
                        success: function () {
                            if (is_active) {
                                setToggleInactive();
                            } else {
                                setToggleActive();
                            }
                        },
                        error: function () {
                            if (!is_active) {
                                setToggleInactive();
                            } else {
                                setToggleActive();
                            }

                            alert('<?php echo esc_js( __( 'Ajax error while updating form', 'gravityforms' ) ); ?>');
                        }
                    }
                );

                function setToggleInactive() {
                    jQuery(btn).removeClass('gform-status--active').addClass('gform-status--inactive').find('.gform-status-indicator-status').html( <?php echo wp_json_encode( esc_attr__( 'Inactive', 'gravityforms' ) ); ?> );
                }

                function setToggleActive() {
                    jQuery(btn).removeClass('gform-status--inactive').addClass('gform-status--active').find('.gform-status-indicator-status').html( <?php echo wp_json_encode( esc_attr__( 'Active', 'gravityforms' ) ); ?> );
                }

            }

		</script>

		<?php
		GFFormSettings::page_footer();
	}

	/**
	 * Processes a notification list action if needed.
	 *
	 * @return void
	 * @uses    self::delete_notification()
	 * @uses    self::duplicate_notification()
	 * @uses    GFCommon::add_message()
	 * @uses    GFCommon::add_error_message()
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by self::notification_list_page()
	 */
	public static function maybe_process_notification_list_action() {

		if ( empty( $_POST ) || ! check_admin_referer( 'gform_notification_list_action', 'gform_notification_list_action' ) ) {
			return;
		}

		$action    = rgpost( 'action' );
		$object_id = rgpost( 'action_argument' );

		switch ( $action ) {
			case 'delete':
				$notification_deleted = self::delete_notification( $object_id, rgget( 'id' ) );
				if ( $notification_deleted ) {
					GFCommon::add_message( esc_html__( 'اعلان پیامکی حذف شد.', 'gravityforms' ) );
				} else {
					GFCommon::add_error_message( esc_html__( 'There was an issue deleting this notification.', 'gravityforms' ) );
				}
				break;
			case 'duplicate':
				$notification_duplicated = self::duplicate_notification( $object_id, rgget( 'id' ) );
				if ( $notification_duplicated ) {
					GFCommon::add_message( esc_html__( 'اعلان پیامکی کپی شد.', 'gravityforms' ) );
				} else {
					GFCommon::add_error_message( esc_html__( 'There was an issue duplicating this notification.', 'gravityforms' ) );
				}
				break;
		}

	}

	/**
	 * Get the notification events for the current form.
	 *
	 * @param array $form The current Form Object.
	 *
	 * @return array Notification events available within the form.
	 * @since  Unknown
	 * @access public
	 *
	 */
	public static function get_notification_events( $form ) {
		$notification_events = [ 'form_submission' => esc_html__( 'Form is submitted', 'gravityforms' ) ];
		if ( rgars( $form, 'save/enabled' ) ) {
			$notification_events['form_saved']                = esc_html__( 'Form is saved', 'gravityforms' );
			$notification_events['form_save_phone_requested'] = esc_html__( 'Save and continue SMS is requested', 'gravityforms' );
		}

		$notification_events['complete_payment']    = esc_html( 'پرداخت موفق' );
		$notification_events['fail_payment']        = esc_html( 'پرداخت ناموفق' );

		/**
		 * Allow custom notification events to be added.
		 *
		 * @param array $notification_events The notification events.
		 * @param array $form The current form.
		 *
		 * @since Unknown
		 *
		 */
		return apply_filters( 'gform_sms_notification_events', $notification_events, $form );
	}

	/**
	 * Validates phone number within notification.
	 *
	 * @param $text String containing comma-separated phone number.
	 *
	 * @return bool True if valid. Otherwise, false.
	 * @since  Unknown
	 * @access private
	 *
	 */
	public static function is_valid_notification_phone( string $text ): bool {
		if ( empty( $text ) ) {
			return false;
		}

		$phones = explode( ',', $text );
		foreach ( $phones as $phone ) {
			$phone = trim( $phone );

			$is_valid_phone = preg_match( '/^\+?[0-9\s\-\(\)]{7,20}$/', $phone );

			$is_variable = preg_match( '/\{.*?\}/', $phone );

			if ( ! $is_valid_phone && ! $is_variable ) {
				return false;
			}
		}

		return true;
	}


	/**
	 * Checks if notification from phone is using the site domain.
	 *
	 * @param string $from_phone phone address to check.
	 *
	 * @return bool
	 * @since  2.4.12
	 *
	 */
	private static function is_site_domain_in_from( $from_phone ) {

		if ( strpos( $from_phone, '{admin_phone}' ) !== false ) {
			$admin_user = get_user_by( 'email', get_bloginfo( 'admin_email' ) );
			$from_phone = get_user_meta( $admin_user->ID, 'phone_number', true );
		}

		return self::phone_domain_matches( $from_phone );

	}

	public static function phone_domain_matches( $phone_number, $prefix = '' ): bool {

		GFCommon::log_debug( __METHOD__ . '(): Phone number: ' . $phone_number );

		if ( empty( $phone_number ) || ! preg_match( '/^\+?[0-9\s\-\(\)]+$/', $phone_number ) ) {
			GFCommon::log_debug( __METHOD__ . '(): Invalid phone number format.' );

			return false;
		}

		if ( empty( $prefix ) ) {
			$prefix = get_option( 'admin_phone_prefix', '+98' );
		}

		GFCommon::log_debug( __METHOD__ . '(): Expected prefix: ' . $prefix );

		$normalized_phone = preg_replace( '/[\s\-\(\)]+/', '', $phone_number );

		$matches = ( strpos( $normalized_phone, $prefix ) === 0 );

		GFCommon::log_debug( __METHOD__ . '(): Prefix matches? ' . var_export( $matches, true ) );

		return $matches;
	}

	/**
	 * Gets supported routing field types.
	 *
	 * @return array $field_types Supported field types.
	 * @uses self::$supported_fields()
	 *
	 * @since  Unknown
	 * @access public
	 *
	 */
	public static function get_routing_field_types() {
		/**
		 * Filters the field types supported by notification routing
		 *
		 * @param array GFNotification::$supported_fields Currently supported field types.
		 *
		 * @since 1.9.6
		 *
		 */
		$field_types = apply_filters( 'gform_routing_field_types', self::$supported_fields );

		return $field_types;
	}

	/**
	 * Gets a dropdown list of available post categories
	 *
	 * @since  Unknown
	 * @access public
	 */
	public static function get_post_category_values() {

		$id       = 'routing_value_' . rgpost( 'ruleIndex' );
		$selected = rgempty( 'selectedValue' ) ? 0 : rgpost( 'selectedValue' );

		$dropdown = wp_dropdown_categories( [ 'class' => 'gfield_routing_select gfield_routing_value_dropdown gfield_category_dropdown', 'orderby' => 'name', 'id' => $id, 'selected' => $selected, 'hierarchical' => true, 'hide_empty' => 0, 'echo' => false ] );
		die( $dropdown );
	}

	/**
	 * Delete a form notification
	 *
	 * @param int $notification_id The notification ID to delete
	 * @param int|array $form_id Can pass a form ID or a form object
	 *
	 * @return int|false The result from $wpdb->query deletion
	 * @uses GFFormsModel::save_form_notifications()
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFFormsModel::get_form_meta()
	 * @uses GFFormsModel::flush_current_forms()
	 */
	public static function delete_notification( $notification_id, $form_id ) {

		if ( ! $form_id ) {
			return false;
		}

		$form = ! is_array( $form_id ) ? GFFormsModel::get_form_meta( $form_id ) : $form_id;

		/**
		 * Fires before a notification is deleted.
		 *
		 * @param array $form ['notification'][$notification_id] The notification being deleted.
		 * @param array $form The Form Object that the notification is being deleted from.
		 *
		 * @since Unknown
		 *
		 */
		do_action( 'gform_pre_notification_deleted', $form['notifications'][ $notification_id ], $form );

		unset( $form['notifications'][ $notification_id ] );

		// Clear Form cache so next retrieval of form meta will reflect deleted notification
		GFFormsModel::flush_current_forms();

		return GFFormsModel::save_form_notifications( $form['id'], $form['notifications'] );
	}

	/**
	 * Duplicates a form notification.
	 *
	 * @param int $notification_id The notification ID to duplicate.
	 * @param int|array $form_id The ID of the form or Form Object that contains the notification.
	 *
	 * @return int|false The result from $wpdb->query after duplication
	 * @uses GFFormsModel::flush_current_forms()
	 * @uses GFFormsModel::save_form_notifications()
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFFormsModel::get_form_meta()
	 * @uses self::is_unique_name()
	 */
	public static function duplicate_notification( $notification_id, $form_id ) {

		if ( ! $form_id ) {
			return false;
		}

		$form = ! is_array( $form_id ) ? GFFormsModel::get_form_meta( $form_id ) : $form_id;

		$new_notification = $form['notifications'][ $notification_id ];
		$name             = rgar( $new_notification, 'name' );
		$new_id           = uniqid();

		$count    = 2;
		$new_name = $name . ' - Copy 1';
		while ( ! self::is_unique_name( $new_name, $form['notifications'] ) ) {
			$new_name = $name . " - Copy $count";
			$count ++;
		}
		$new_notification['name'] = $new_name;
		$new_notification['id']   = $new_id;
		unset( $new_notification['isDefault'] );
		if ( $new_notification['toType'] == 'hidden' ) {
			$new_notification['toType'] = 'phone';
		}

		// Removing legacy (pre-1.7) admin/user notification property.
		unset( $new_notification['type'] );

		$new_notification = GFCommon::fix_notification_routing( $new_notification );

		$form['notifications'][ $new_id ] = $new_notification;

		// Clear form cache so next retrieval of form meta will return duplicated notification
		RGFormsModel::flush_current_forms();

		return RGFormsModel::save_form_notifications( $form['id'], $form['notifications'] );
	}

	/**
	 * Checks if a notification name is unique.
	 *
	 * @param string $name The name to check.
	 * @param array $notifications The notifications to check against.
	 *
	 * @return bool Returns true if unique.  Otherwise, false.
	 * @since  Unknown
	 * @access public
	 *
	 */
	public static function is_unique_name( $name, $notifications ) {

		foreach ( $notifications as $notification ) {
			if ( strtolower( rgar( $notification, 'name' ) ) == strtolower( $name ) ) {
				return false;
			}
		}

		return true;
	}

}

// Include WP_List_Table.
require_once( ABSPATH . '/wp-admin/includes/class-wp-list-table.php' );

/**
 * Class GFNotificationTable.
 *
 * Extends WP_List_Table to display the notifications list.
 *
 * @uses WP_List_Table
 */
class GFPersian_SMS_Notification_Table extends WP_List_Table {

	/**
	 * Contains the Form Object.
	 *
	 * Passed when calling the class.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @var array
	 */
	public $form;

	/**
	 * Contains the notification events for the form.
	 *
	 * Generated in the constructor based on the passed Form Object.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @var array
	 */
	public $notification_events;

	/**
	 * GFNotificationTable constructor.
	 *
	 * Sets required class properties and defines the list table columns.
	 *
	 * @param array $form The Form Object to use.
	 *
	 * @uses  self::get_notification_events()
	 * @uses  GFNotificationTable::$form
	 * @uses  GFNotificationTable::$notification_events
	 * @uses  WP_List_Table::__construct()
	 *
	 * @since  Unknown
	 * @access public
	 *
	 */
	public function __construct( $form ) {
		$this->form                = $form;
		$this->notification_events = GFPersian_SMS_Notification::get_notification_events( $form );

		$columns = [
			'cb'      => '',
			'name'    => esc_html__( 'Name', 'gravityforms' ),
			'message' => esc_html__( 'Message', 'gravityforms' )
		];

		if ( count( $this->notification_events ) > 1 ) {
			$columns['event'] = esc_html__( 'Event', 'gravityforms' );
		}

		$this->_column_headers = [
			$columns,
			[],
			[ 'name' => [ 'name', false ] ],
			'name',
		];

		parent::__construct();

	}

	/**
	 * Prepares the list items for displaying.
	 *
	 * @return void
	 * @uses WP_List_Table::$items
	 * @uses GFNotificationTable::$form
	 *
	 * @since  Unknown
	 * @access public
	 *
	 */
	public function prepare_items() {

		if ( ! isset( $this->form['notifications'] ) ) {
			$this->items = [];

			return;
		}

		$this->items = array_filter( $this->form['notifications'], function ( $notification ) {
			return isset( $notification['type'] ) && $notification['type'] === 'sms';
		} );


		switch ( rgget( 'orderby' ) ) {

			case 'name':

				// Sort notification alphabetically.
				usort( $this->items, [ $this, 'sort_notifications' ] );

				// Reverse sort.
				if ( 'desc' === rgget( 'order' ) ) {
					$this->items = array_reverse( $this->items );
				}

				break;

			default:
				break;

		}

	}

	/**
	 * Sort notifications alphabetically.
	 *
	 * @param array $a First notification to compare.
	 * @param array $b Second notification to compare.
	 *
	 * @return int
	 * @since  2.4
	 * @access public
	 *
	 */
	public function sort_notifications( $a = [], $b = [] ) {

		return strcasecmp( $a['name'], $b['name'] );

	}

	/**
	 * Displays the list table.
	 *
	 * @return void
	 * @uses \WP_List_Table::get_table_classes()
	 * @uses \WP_List_Table::print_column_headers()
	 * @uses \WP_List_Table::display_rows_or_placeholder()
	 *
	 * @since  Unknown
	 * @access public
	 *
	 */
	public function display() {
		$singular = rgar( $this->_args, 'singular' );

		$this->display_tablenav( 'top' );
		?>

		<table class="wp-list-table <?php echo esc_attr( implode( ' ', $this->get_table_classes() ) ); ?>" cellspacing="0">
			<thead>
			<tr>
				<?php $this->print_column_headers(); ?>
			</tr>
			</thead>

			<tfoot>
			<tr>
				<?php $this->print_column_headers( false ); ?>
			</tr>
			</tfoot>

			<tbody id="the-list"<?php if ( $singular ) {
				echo " class='list:" . esc_attr( $singular ) . "'";
			} ?>>

			<?php $this->display_rows_or_placeholder(); ?>

			</tbody>
		</table>

		<?php
	}

	/**
	 * Builds the single row content for the list table
	 *
	 * @param object $item The current view.
	 *
	 * @return void
	 * @since  Unknown
	 * @access public
	 *
	 * @uses WP_List_Table::single_row_columns()
	 *
	 */
	public function single_row( $item ) {
		static $row_class = '';
		$row_class = ( $row_class == '' ? ' class="alternate"' : '' );

		echo '<tr id="notification-' . esc_attr( $item['id'] ) . '" ' . esc_attr( $row_class ) . '>';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Gets the column headers.
	 *
	 * @return array The column headers.
	 * @uses    WP_List_Table::$_column_headers
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by Filter: manage_{$this->screen->id}_columns
	 */
	public function get_columns() {
		return $this->_column_headers[0];
	}

	/**
	 * Defines the default values in a column.
	 *
	 * @param object|array $item The content to display.
	 * @param string $column_name The column to apply to.
	 *
	 * @return void
	 * @since  Unknown
	 * @access public
	 *
	 */
	public function column_default( $item, $column_name ) {
		echo esc_html( rgar( $item, $column_name ) );
	}

	/**
	 * Defines a checkbox column.
	 *
	 * @param array $item The column data.
	 *
	 * @return void
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFCommon::get_base_url()
	 *
	 */
	public function column_cb( $item ) {
		if ( rgar( $item, 'isDefault' ) ) {
			return;
		}

		$active = rgar( $item, 'isActive' ) !== false;

		if ( $active ) {
			$class = 'gform-status--active';
			$text  = esc_html__( 'Active', 'gravityforms' );
		} else {
			$class = 'gform-status--inactive';
			$text  = esc_html__( 'Inactive', 'gravityforms' );
		}
		?>

		<button
			type="button"
			class="gform-status-indicator gform-status-indicator--size-sm gform-status-indicator--theme-cosmos <?php echo esc_attr( $class ); ?>"
			onclick="ToggleActive( this, '<?php echo esc_js( $item['id'] ); ?>' );"
			onkeypress="ToggleActive( this, '<?php echo esc_js( $item['id'] ); ?>' );"
		>
			<span class="gform-status-indicator-status gform-typography--weight-medium gform-typography--size-text-xs">
				<?php echo esc_html( $text ); ?>
			</span>
		</button>

		<?php
	}

	/**
	 * Sets the column name in the list table.
	 *
	 * @param array $item The column data.
	 *
	 * @return void
	 * @since  Unknown
	 * @access public
	 *
	 */
	public function column_name( $item ) {
		$edit_url = add_query_arg( [ 'sid' => $item['id'] ] );
		/**
		 * Filters the row action links.
		 *
		 * @param array $actions The action links.
		 *
		 * @since Unknown
		 *
		 */
		$actions = apply_filters(
			'gform_notification_actions', [
				'edit'      => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'gravityforms' ) . '</a>',
				'duplicate' => '<a href="javascript:void(0);" onclick="javascript: DuplicateNotification(\'' . esc_js( $item['id'] ) . '\');" onkeypress="javascript: DuplicateNotification(\'' . esc_js( $item['id'] ) . '\');" style="cursor:pointer;">' . esc_html__( 'Duplicate', 'gravityforms' ) . '</a>',
				'delete'    => '<a href="javascript:void(0);" class="submitdelete" onclick="javascript: if(confirm(\'' . esc_js( esc_html__( 'WARNING: You are about to delete this notification.', 'gravityforms' ) ) . esc_js( esc_html__( "'Cancel' to stop, 'OK' to delete.", 'gravityforms' ) ) . '\')){ DeleteNotification(\'' . esc_js( $item['id'] ) . '\'); }" onkeypress="javascript: if(confirm(\'' . esc_js( esc_html__( 'WARNING: You are about to delete this notification.', 'gravityforms' ) ) . esc_js( esc_html__( "'Cancel' to stop, 'OK' to delete.", 'gravityforms' ) ) . '\')){ DeleteNotification(\'' . esc_js( $item['id'] ) . '\'); }" style="cursor:pointer;">' . esc_html__( 'Delete', 'gravityforms' ) . '</a>'
			]
		);

		if ( isset( $item['isDefault'] ) && $item['isDefault'] ) {
			unset( $actions['delete'] );
		}

		$aria_label = sprintf(
		/* translators: %s: Notification name */
			__( '%s (Edit)', 'gravityforms' ),
			$item['name']
		);

		?>

		<a href="<?php echo esc_url( $edit_url ); ?>" aria-label="<?php echo esc_attr( $aria_label ); ?>"><strong><?php echo esc_html( rgar( $item, 'name' ) ); ?></strong></a>
		<div class="row-actions">

			<?php
			if ( is_array( $actions ) && ! empty( $actions ) ) {
				$keys     = array_keys( $actions );
				$last_key = array_pop( $keys );
				foreach ( $actions as $key => $html ) {
					$divider = $key == $last_key ? '' : ' | ';
					?>
					<span class="<?php echo esc_attr( $key ); ?>">
						<?php echo $html . esc_html( $divider ); ?>
					</span>
					<?php
				}
			}
			?>

		</div>

		<?php
	}

	/**
	 * Displays the content of the Event column.
	 *
	 * @param array $notification The Notification Object.
	 *
	 * @return void
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFNotificationTable::$notification_events()
	 *
	 */
	public function column_event( $notification ) {
		echo esc_html( rgar( $this->notification_events, rgar( $notification, 'event' ) ) );
	}

	/**
	 * Content to display if the form does not have any notifications.
	 *
	 * @return void
	 * @since  Unknown
	 * @access public
	 *
	 */
	public function no_items() {
		$url = add_query_arg( [ 'sid' => 0 ] );
		printf( esc_html__( "This form doesn't have any notifications. Let's go %screate one%s.", 'gravityforms' ), "<a href='" . esc_url( $url ) . "'>", '</a>' );
	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination
	 *
	 * @param string $which
	 *
	 * @since 2.5
	 *
	 */
	protected function extra_tablenav( $which ) {

		if ( $which !== 'top' ) {
			return;
		}

		printf(
			'<div class="alignright"><a href="%s" class="button">%s</a></div>',
			esc_url( add_query_arg( [ 'sid' => 0 ] ) ),
			esc_html__( 'Add New', 'gravityforms' )
		);


	}

}



