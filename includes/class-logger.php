<?php
/**
 * Logger utility for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BPP_Logger
 *
 * Integrates with WooCommerce logging while guaranteeing strict redaction of sensitive credentials.
 */
class BPP_Logger {

	/**
	 * Log source identifier.
	 */
	public const LOG_SOURCE = 'binance-personal-pay';

	/**
	 * Log an informational message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Context data.
	 * @return void
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Context data.
	 * @return void
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Context data.
	 * @return void
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Context data.
	 * @return void
	 */
	public static function debug( string $message, array $context = array() ): void {
		self::log( 'debug', $message, $context );
	}

	/**
	 * Write a message to the WooCommerce log after redacting any sensitive data.
	 *
	 * @param string $level   Log level ('info', 'warning', 'error', 'debug').
	 * @param string $message Message to log.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public static function log( string $level, string $message, array $context = array() ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$logger = wc_get_logger();
		if ( ! $logger ) {
			return;
		}

		$sanitized_message = self::redact_sensitive_data( $message );
		$sanitized_context = self::redact_context( $context );
		$sanitized_context['source'] = self::LOG_SOURCE;

		$logger->log( $level, $sanitized_message, $sanitized_context );
	}

	/**
	 * Recursively redact sensitive keys from context array.
	 *
	 * @param array $context Context array.
	 * @return array
	 */
	public static function redact_context( array $context ): array {
		$sensitive_keys = array(
			'api_secret',
			'secret',
			'binance_api_secret',
			'signature',
			'X-MBX-APIKEY',
			'x-mbx-apikey',
			'api_key',
			'token',
			'password',
			'authorization',
		);

		$cleaned = array();
		foreach ( $context as $key => $value ) {
			$lower_key = strtolower( (string) $key );
			if ( in_array( $lower_key, $sensitive_keys, true ) ) {
				$cleaned[ $key ] = '***REDACTED***';
				continue;
			}

			if ( is_array( $value ) ) {
				$cleaned[ $key ] = self::redact_context( $value );
			} elseif ( is_string( $value ) ) {
				$cleaned[ $key ] = self::redact_sensitive_data( $value );
			} else {
				$cleaned[ $key ] = $value;
			}
		}

		return $cleaned;
	}

	/**
	 * Redact credentials, query signatures, and API keys from string messages.
	 *
	 * @param string $text Text to redact.
	 * @return string
	 */
	public static function redact_sensitive_data( string $text ): string {
		// Redact signature parameter: signature=...
		$text = preg_replace( '/(signature=)[a-zA-Z0-9_-]+/i', '$1***REDACTED***', $text );

		// Redact headers like X-MBX-APIKEY: ...
		$text = preg_replace( '/(X-MBX-APIKEY:\s*)[a-zA-Z0-9_-]+/i', '$1***REDACTED***', $text );

		// Redact secret query params or json values.
		$text = preg_replace( '/("api_secret"\s*:\s*")[^"]+(")/i', '$1***REDACTED***$2', $text );
		$text = preg_replace( '/("secret"\s*:\s*")[^"]+(")/i', '$1***REDACTED***$2', $text );

		return $text;
	}
}
