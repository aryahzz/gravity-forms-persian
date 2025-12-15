<?php

defined( 'ABSPATH' ) || exit;

class GFPersian_Snippets extends GFPersian_Core {

	public function __construct() {

		if ( $this->option( 'label_visibility', '1' ) == '1' ) {
			add_filter( 'gform_enable_field_label_visibility_settings', '__return_true' );
		}

		if ( $this->option( 'private_post', '1' ) == '1' ) {
			add_action( 'gform_post_status_options', [ $this, 'add_private_post_status' ] );
		}

		if ( $this->option( 'newsletter', '1' ) == '1' ) {
			add_filter( 'gform_notification_events', [ $this, 'add_newsletter_notification_event' ] );
			add_filter( 'gform_before_resend_notifications', [ $this, 'add_notification_filter' ] );
		}

		// Add payment statuses to notification events
		add_filter( 'gform_notification_events', [ $this, 'add_payment_statuses_notification_event' ] );
		add_action( 'gform_post_payment_action', [ $this, 'send_payment_status_notification' ], 10, 2 );
	}

	/**
	 * Append payment events to the form notifications
	 *
	 * @param array $events
	 *
	 * @return array
	 */
	public function add_payment_statuses_notification_event( array $events ): array {
		$events['complete_payment'] = esc_html( 'پرداخت موفق' );
		$events['fail_payment']     = esc_html( 'پرداخت ناموفق' );

		return $events;
	}

	/**
	 * Send notifications based on payment status
	 *
	 * @param array $entry
	 * @param string $event
	 *
	 * @return void
	 */
	public function send_payment_status_notification( array $entry, string $event = 'fail_payment' ): void {
		$allowed_events = [
			'complete_payment',
			'fail_payment',
		];

		if ( ! in_array( $event, $allowed_events, true ) ) {
			return;
		}

		$form = GFAPI::get_form( rgar( $entry, 'form_id' ) );

		// Get all notifications assigned to this event
		$notifications = GFCommon::get_notifications( $event, $form );

		// Retrieve already sent notification IDs for this entry
		$sent_notifications = gform_get_meta( $entry['id'], '_gform_notification_sent' ) ?: [];

		foreach ( $notifications as $nid => $notification ) {

			// Skip if already sent
			if ( in_array( $nid, $sent_notifications, true ) ) {
				continue;
			}

			// Skip if conditional logic is not valid
			if ( ! GFCommon::evaluate_conditional_logic( rgar( $notification, 'conditionalLogic' ), $form, $entry ) ) {
				continue;
			}

			// Send the notification
			GFCommon::send_notification( $notification, $form, $entry );

			// Mark this notification as sent
			$sent_notifications[] = $nid;

			gform_update_meta( $entry['id'], '_gform_notification_sent', $sent_notifications );

		}

	}

	public function add_private_post_status( $post_statuses ) {
		$post_statuses['private'] = 'خصوصی';

		return $post_statuses;
	}

	/*---------------------------------------------------------------------------------------------*/
	/*----------Start of NewsLetter----------------------------------------------------------------*/
	/*---------------------------------------------------------------------------------------------*/
	public function add_newsletter_notification_event( $events ) {
		$events['newsletter'] = 'خبرنامه';

		return $events;
	}

	public function add_notification_filter( $form ) {
		add_filter( 'gform_notification', [ $this, 'evaluate_notification_conditional_logic' ], 10, 3 );

		return $form;
	}

	public function evaluate_notification_conditional_logic( $notification, $form, $entry ) {

		// if it fails conditional logic, suppress it
		if ( $notification['event'] == 'newsletter' && ! GFCommon::evaluate_conditional_logic( rgar( $notification, 'conditionalLogic' ), $form, $entry ) ) {
			add_filter( 'gform_pre_send_email', [ $this, 'abort_next_notification' ] );
		}

		return $notification;
	}

	public function abort_next_notification( $args ) {
		remove_filter( 'gform_pre_send_email', [ $this, 'abort_next_notification' ] );
		$args['abort_email'] = true;

		return $args;
	}
	/*---------------------------------------------------------------------------------------------*/
	/*----------End of NewsLetter------------------------------------------------------------------*/
	/*---------------------------------------------------------------------------------------------*/

}

new GFPersian_Snippets();