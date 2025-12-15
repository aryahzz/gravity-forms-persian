<?php

abstract class GFPersian_SMS_Gateway {

	/**
	 * Return class name to show as label
	 *
	 * @return string
	 */
	abstract public static function name(): string;

	/**
	 * Process given action
	 *
	 * @param array $options
	 * @param string $action
	 * @param string $from
	 * @param string $to
	 * @param string $message
	 *
	 * @return string
	 */
	abstract public static function process( array $options, string $action, string $from, string $to, string $message ): string;


	/**
	 * Create standard phone numbers with 0 prefix as default
	 *
	 * @param string $numbers String of numbers separated with ,
	 * @param string $prefix Default prefix
	 *
	 * @return array List of phone numbers starting with $prefix
	 */
	public static function normalize_numbers( string $numbers, string $prefix = '0' ): array {
		$result = [];

		if ( empty( $numbers ) ) {
			return $result;
		}

		if ( str_contains( $numbers, ',' ) ) {
			$numbers = explode( ',', $numbers );
		} else {
			$numbers = [ $numbers ];
		}

		foreach ( $numbers as $number ) {

			$number   = trim( $number );
			$number   = preg_replace( '/^(%2B98|%2b98|\+98|0098|98|098)/', '', $number );
			$result[] = $prefix . ltrim( $number, '0' );

		}

		return $result;
	}


	/**
	 * Get current SMS gateway
	 *
	 * @return string
	 */
	public static function get_current_gateway(): string {
		$current_gateway = GFPersian_Core::_option( 'sms_gateway', 'none' );

		if ( empty( $current_gateway ) ) {
			return '';
		}

		return $current_gateway;
	}

	public static function get_sender_number(): string {
		$sender_number = GFPersian_Core::_option( 'sms_from_numbers', '' );

		if ( empty( $sender_number ) ) {
			return '';
		}

		return $sender_number;
	}

	protected function get_username(): string {
		$username = GFPersian_Core::_option( 'sms_username', '' );

		if ( empty( $username ) ) {
			return '';
		}

		return $username;
	}

	protected function get_password(): string {
		$password = GFPersian_Core::_option( 'sms_password', '' );

		if ( empty( $password ) ) {
			return '';
		}

		return $password;
	}


	public static function action( array $settings, string $action, string $from, string $to, string $message ) {

		$current_gateway = $settings['ws'];

		if ( empty( $current_gateway ) ) {
			return 'درگاه پیامکی یافت نشد.';
		}


		$message = str_replace( [ "<br>", "<br/>", "<br />", '&nbsp;' ], [
			"\n",
			"\n",
			"\n",
			''
		], $message );
		$message = strip_tags( $message );


		if ( class_exists( $current_gateway ) ) {

			/**
			 * @var self $current_gateway
			 */
			return $current_gateway::process( $settings, $action, $from, $to, $message );
		}

		return 'درگاه پیامکی یافت نشد.';

	}


}