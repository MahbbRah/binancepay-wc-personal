<?php
/**
 * Settings manager for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BPP_Settings
 *
 * Handles plugin configuration storage, retrieval, sanitization, and defaults.
 */
class BPP_Settings {

	/**
	 * WooCommerce gateway settings option name.
	 */
	public const OPTION_NAME = 'woocommerce_binance_pay_settings';

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static ?array $settings = null;

	/**
	 * Default setting values.
	 *
	 * @return array
	 */
	public static function get_defaults(): array {
		return array(
			'enabled'                  => 'no',
			'title'                    => __( 'Binance Pay', 'binance-personal-pay' ),
			'description'              => __( 'Pay directly using Binance.', 'binance-personal-pay' ),
			'api_key'                  => '',
			'api_secret'               => '',
			'merchant_binance_id'      => '',
			'currency'                 => 'USDT',
			'payment_timeout'          => 10,
			'timeout_action'           => 'keep_pending',
			'enable_auto_scan'         => 'yes',
			'enable_manual_verify'     => 'yes',
			'scanner_interval'         => 60,
			'lookback_minutes'         => 15,
			'delete_data_on_uninstall' => 'no',
			'debug_log'                => 'yes',
		);
	}

	/**
	 * Retrieve all gateway settings merged with defaults.
	 *
	 * @param bool $refresh Force refresh from database.
	 * @return array
	 */
	public static function get_all( bool $refresh = false ): array {
		if ( null === self::$settings || $refresh ) {
			$saved = get_option( self::OPTION_NAME, array() );
			if ( ! is_array( $saved ) ) {
				$saved = array();
			}
			self::$settings = wp_parse_args( $saved, self::get_defaults() );
		}
		return self::$settings;
	}

	/**
	 * Retrieve a single setting by key.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$settings = self::get_all();
		return $settings[ $key ] ?? ( self::get_defaults()[ $key ] ?? $default );
	}

	/**
	 * Get the Binance API key.
	 *
	 * @return string
	 */
	public static function get_api_key(): string {
		return trim( (string) self::get( 'api_key', '' ) );
	}

	/**
	 * Get the Binance API secret (for server-side signing only).
	 *
	 * @return string
	 */
	public static function get_api_secret(): string {
		return trim( (string) self::get( 'api_secret', '' ) );
	}

	/**
	 * Get masked version of Binance API secret for safe admin display.
	 *
	 * @return string
	 */
	public static function get_masked_api_secret(): string {
		$secret = self::get_api_secret();
		return BPP_Auth::mask_secret( $secret );
	}

	/**
	 * Get the Merchant's personal Binance ID.
	 *
	 * @return string
	 */
	public static function get_merchant_binance_id(): string {
		return trim( (string) self::get( 'merchant_binance_id', '' ) );
	}

	/**
	 * Get default currency (e.g. USDT).
	 *
	 * @return string
	 */
	public static function get_currency(): string {
		return strtoupper( trim( (string) self::get( 'currency', 'USDT' ) ) );
	}

	/**
	 * Get payment timeout in minutes.
	 *
	 * @return int
	 */
	public static function get_payment_timeout_minutes(): int {
		$mins = (int) self::get( 'payment_timeout', 10 );
		return max( 1, min( 1440, $mins ) );
	}

	/**
	 * Get action to take when payment timeout reaches zero.
	 *
	 * @return string 'keep_pending', 'mark_failed', or 'cancel_order'.
	 */
	public static function get_timeout_action(): string {
		$action = (string) self::get( 'timeout_action', 'keep_pending' );
		if ( ! in_array( $action, array( 'keep_pending', 'mark_failed', 'cancel_order' ), true ) ) {
			return 'keep_pending';
		}
		return $action;
	}

	/**
	 * Check if automatic background scan is enabled.
	 *
	 * @return bool
	 */
	public static function is_auto_scan_enabled(): bool {
		return 'yes' === self::get( 'enable_auto_scan', 'yes' );
	}

	/**
	 * Check if manual customer verification button is enabled.
	 *
	 * @return bool
	 */
	public static function is_manual_verify_enabled(): bool {
		return 'yes' === self::get( 'enable_manual_verify', 'yes' );
	}

	/**
	 * Get scanner interval in seconds.
	 *
	 * @return int
	 */
	public static function get_scanner_interval(): int {
		$sec = (int) self::get( 'scanner_interval', 60 );
		return max( 30, min( 3600, $sec ) );
	}

	/**
	 * Get transaction lookback window in minutes.
	 *
	 * @return int
	 */
	public static function get_lookback_minutes(): int {
		$mins = (int) self::get( 'lookback_minutes', 15 );
		return max( 5, min( 1440, $mins ) );
	}

	/**
	 * Check if plugin data should be erased on full uninstall.
	 *
	 * @return bool
	 */
	public static function delete_data_on_uninstall(): bool {
		return 'yes' === self::get( 'delete_data_on_uninstall', 'no' );
	}

	/**
	 * Check if debug logging is enabled.
	 *
	 * @return bool
	 */
	public static function is_debug_log_enabled(): bool {
		return 'yes' === self::get( 'debug_log', 'yes' );
	}

	/**
	 * Sanitize and save settings array.
	 *
	 * @param array $input Raw form data.
	 * @return array Sanitized settings.
	 */
	public static function save_settings( array $input ): array {
		$existing = self::get_all();
		$clean    = array();

		$clean['enabled']                  = isset( $input['enabled'] ) && 'yes' === $input['enabled'] ? 'yes' : 'no';
		$clean['title']                    = sanitize_text_field( $input['title'] ?? __( 'Binance Pay', 'binance-personal-pay' ) );
		$clean['description']              = sanitize_textarea_field( $input['description'] ?? __( 'Pay directly using Binance.', 'binance-personal-pay' ) );
		$clean['api_key']                  = sanitize_text_field( trim( $input['api_key'] ?? '' ) );

		// Only overwrite api_secret if a non-empty, non-masked value was provided.
		$new_secret = trim( (string) ( $input['api_secret'] ?? '' ) );
		if ( '' !== $new_secret && ! str_contains( $new_secret, '•' ) ) {
			$clean['api_secret'] = sanitize_text_field( $new_secret );
		} else {
			$clean['api_secret'] = $existing['api_secret'] ?? '';
		}

		$clean['merchant_binance_id']      = sanitize_text_field( trim( $input['merchant_binance_id'] ?? '' ) );
		$clean['currency']                 = sanitize_text_field( strtoupper( trim( $input['currency'] ?? 'USDT' ) ) );
		$clean['payment_timeout']          = max( 1, absint( $input['payment_timeout'] ?? 10 ) );
		$clean['timeout_action']           = in_array( $input['timeout_action'] ?? '', array( 'keep_pending', 'mark_failed', 'cancel_order' ), true )
			? $input['timeout_action']
			: 'keep_pending';
		$clean['enable_auto_scan']         = isset( $input['enable_auto_scan'] ) && 'yes' === $input['enable_auto_scan'] ? 'yes' : 'no';
		$clean['enable_manual_verify']     = isset( $input['enable_manual_verify'] ) && 'yes' === $input['enable_manual_verify'] ? 'yes' : 'no';
		$clean['scanner_interval']         = max( 30, absint( $input['scanner_interval'] ?? 60 ) );
		$clean['lookback_minutes']         = max( 5, absint( $input['lookback_minutes'] ?? 15 ) );
		$clean['delete_data_on_uninstall'] = isset( $input['delete_data_on_uninstall'] ) && 'yes' === $input['delete_data_on_uninstall'] ? 'yes' : 'no';
		$clean['debug_log']                = isset( $input['debug_log'] ) && 'yes' === $input['debug_log'] ? 'yes' : 'no';

		update_option( self::OPTION_NAME, $clean );
		self::$settings = $clean;

		return $clean;
	}
}
