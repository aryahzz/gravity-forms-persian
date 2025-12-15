<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Class Name : GFPersian_SMS_{file-postfix}
 */

class GFPersian_SMS_PGFLOG extends GFPersian_SMS_Gateway {

	/*
	* Gateway title
	*/
	public static function name(): string {
		return 'PGF.LOG';
	}

	public static function process( $options, $action, $from, $to, $message ) :string{
		self::logVariables( $options, $action, $from, $to, $message );

		return 'OK';
	}


	public static function logVariables( ...$args ) {
		foreach ( $args as $index => $arg ) {
			self::log( PHP_EOL . "Arg $index: " . print_r( $arg, true ) );
		}

		self::log( '######################################################' );
	}

	public static function log( $message ) {
		if ( is_array( $message ) || is_object( $message ) ) {
			$message = print_r( $message, true );
		}

		error_log( date( 'Y-m-d H:i:s' ) . ' - ' . $message . PHP_EOL, 3, WP_CONTENT_DIR . '/debug.log' );
	}


}
