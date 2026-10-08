<?php
/**
 * Authentication and signing utility for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BPP_Auth
 *
 * Handles HMAC-SHA256 request signing and credential validation.
 */
class BPP_Auth {

	/**
	 * Default recvWindow in milliseconds.
	 */
	public const DEFAULT_RECV_WINDOW = 10000;

	/**
	 * Sign query parameters with HMAC-SHA256 using the Binance API secret.
	 *
	 * @param array  $params     Request parameters.
	 * @param string $api_secret Binance API secret.
	 * @return string HMAC-SHA256 signature.
	 */
	public static function sign( array $params, string $api_secret ): string {
		$query_string = self::build_query_string( $params );
		return hash_hmac( 'sha256', $query_string, trim( $api_secret ) );
	}

	/**
	 * Build HTTP query string for Binance signing without URL encoding issues.
	 *
	 * @param array $params Query parameters.
	 * @return string
	 */
	public static function build_query_string( array $params ): string {
		return http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Prepare and sign request parameters including timestamp and recvWindow.
	 *
	 * @param array  $params      Base parameters.
	 * @param string $api_secret  Binance API Secret.
	 * @param int    $recv_window Receive window in milliseconds.
	 * @return array Parameters with timestamp, recvWindow, and signature.
	 */
	public static function prepare_signed_params( array $params, string $api_secret, int $recv_window = self::DEFAULT_RECV_WINDOW ): array {
		if ( ! isset( $params['timestamp'] ) ) {
			$params['timestamp'] = self::get_current_timestamp_ms();
		}

		if ( ! isset( $params['recvWindow'] ) ) {
			$params['recvWindow'] = $recv_window;
		}

		// Calculate signature on current parameter set.
		$params['signature'] = self::sign( $params, $api_secret );

		return $params;
	}

	/**
	 * Get current Unix timestamp in milliseconds.
	 *
	 * @return int
	 */
	public static function get_current_timestamp_ms(): int {
		return (int) round( microtime( true ) * 1000 );
	}

	/**
	 * Mask a secret string for safe display in admin screens.
	 *
	 * @param string $secret Plain secret string.
	 * @return string Masked string.
	 */
	public static function mask_secret( string $secret ): string {
		$secret = trim( $secret );
		$len    = strlen( $secret );

		if ( 0 === $len ) {
			return '';
		}

		if ( $len <= 8 ) {
			return str_repeat( '•', $len );
		}

		return substr( $secret, 0, 4 ) . str_repeat( '•', 8 ) . substr( $secret, -4 );
	}

	/**
	 * Validate format of Binance API key.
	 *
	 * @param string $api_key API key to validate.
	 * @return bool
	 */
	public static function is_valid_key( string $api_key ): bool {
		$key = trim( $api_key );
		return strlen( $key ) >= 16 && preg_match( '/^[a-zA-Z0-9_-]+$/', $key ) === 1;
	}

	/**
	 * Validate format of Binance API secret.
	 *
	 * @param string $api_secret API secret to validate.
	 * @return bool
	 */
	public static function is_valid_secret( string $api_secret ): bool {
		$secret = trim( $api_secret );
		return strlen( $secret ) >= 16 && preg_match( '/^[a-zA-Z0-9_-]+$/', $secret ) === 1;
	}

	/**
	 * Validate format of Binance User ID / Pay ID (typically 6-12 digits).
	 *
	 * @param string|int $binance_id Binance UID.
	 * @return bool
	 */
	public static function is_valid_binance_id( string|int $binance_id ): bool {
		$id = trim( (string) $binance_id );
		return strlen( $id ) >= 4 && ctype_digit( $id );
	}
}
