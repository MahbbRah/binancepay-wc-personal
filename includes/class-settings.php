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
	 * Supports both prefixed (woocommerce_binance_pay_*) and unprefixed keys.
	 *
	 * @param array $input Raw form data.
	 * @return array Sanitized settings.
	 */
	public static function save_settings( array $input ): array {
		$existing = self::get_all();
		$defaults = self::get_defaults();
		$clean    = array();
		$prefix   = 'woocommerce_binance_pay_';

		// Determine if this is a WooCommerce HTML settings form submission.
		$is_form_submission = false;
		foreach ( array_keys( $input ) as $k ) {
			if ( str_starts_with( (string) $k, $prefix ) ) {
				$is_form_submission = true;
				break;
			}
		}

		$has = function( string $key ) use ( $input, $prefix ): bool {
			return array_key_exists( $prefix . $key, $input ) || array_key_exists( $key, $input );
		};

		$get = function( string $key, mixed $default = null ) use ( $input, $prefix ): mixed {
			if ( array_key_exists( $prefix . $key, $input ) ) {
				return $input[ $prefix . $key ];
			}
			if ( array_key_exists( $key, $input ) ) {
				return $input[ $key ];
			}
			return $default;
		};

		// Helper to sanitize checkboxes.
		// In HTML forms, unchecked checkboxes are omitted from POST data.
		$sanitize_checkbox = function( string $key, string $default_val ) use ( $has, $get, $is_form_submission, $existing ): string {
			if ( $has( $key ) ) {
				$val = $get( $key );
				return ( 'yes' === $val || '1' === $val || 1 === $val || 'on' === $val ) ? 'yes' : 'no';
			}
			if ( $is_form_submission ) {
				// Form was submitted but checkbox was omitted -> unchecked.
				return 'no';
			}
			// Programmatic partial update -> keep existing value.
			return $existing[ $key ] ?? $default_val;
		};

		$clean['enabled'] = $sanitize_checkbox( 'enabled', 'no' );

		// Text & Textarea fields.
		if ( $has( 'title' ) ) {
			$clean['title'] = sanitize_text_field( $get( 'title', $defaults['title'] ) );
		} else {
			$clean['title'] = $existing['title'] ?? $defaults['title'];
		}

		if ( $has( 'description' ) ) {
			$clean['description'] = sanitize_textarea_field( $get( 'description', $defaults['description'] ) );
		} else {
			$clean['description'] = $existing['description'] ?? $defaults['description'];
		}

		if ( $has( 'api_key' ) ) {
			$clean['api_key'] = sanitize_text_field( trim( (string) $get( 'api_key', '' ) ) );
		} else {
			$clean['api_key'] = $existing['api_key'] ?? '';
		}

		// API Secret: Only overwrite if a non-empty, non-masked value was provided.
		if ( $has( 'api_secret' ) ) {
			$new_secret = trim( (string) $get( 'api_secret', '' ) );
			if ( '' !== $new_secret && ! str_contains( $new_secret, '•' ) ) {
				$clean['api_secret'] = sanitize_text_field( $new_secret );
			} else {
				$clean['api_secret'] = $existing['api_secret'] ?? '';
			}
		} else {
			$clean['api_secret'] = $existing['api_secret'] ?? '';
		}

		if ( $has( 'merchant_binance_id' ) ) {
			$clean['merchant_binance_id'] = sanitize_text_field( trim( (string) $get( 'merchant_binance_id', '' ) ) );
		} else {
			$clean['merchant_binance_id'] = $existing['merchant_binance_id'] ?? '';
		}

		if ( $has( 'currency' ) ) {
			$clean['currency'] = sanitize_text_field( strtoupper( trim( (string) $get( 'currency', 'USDT' ) ) ) );
		} else {
			$clean['currency'] = $existing['currency'] ?? 'USDT';
		}

		if ( $has( 'payment_timeout' ) ) {
			$clean['payment_timeout'] = max( 1, absint( $get( 'payment_timeout', 10 ) ) );
		} else {
			$clean['payment_timeout'] = (int) ( $existing['payment_timeout'] ?? 10 );
		}

		if ( $has( 'timeout_action' ) ) {
			$timeout_act             = $get( 'timeout_action', 'keep_pending' );
			$clean['timeout_action'] = in_array( $timeout_act, array( 'keep_pending', 'mark_failed', 'cancel_order' ), true ) ? $timeout_act : 'keep_pending';
		} else {
			$clean['timeout_action'] = $existing['timeout_action'] ?? 'keep_pending';
		}

		$clean['enable_auto_scan']     = $sanitize_checkbox( 'enable_auto_scan', 'yes' );
		$clean['enable_manual_verify'] = $sanitize_checkbox( 'enable_manual_verify', 'yes' );

		if ( $has( 'scanner_interval' ) ) {
			$clean['scanner_interval'] = max( 30, absint( $get( 'scanner_interval', 60 ) ) );
		} else {
			$clean['scanner_interval'] = (int) ( $existing['scanner_interval'] ?? 60 );
		}

		if ( $has( 'lookback_minutes' ) ) {
			$clean['lookback_minutes'] = max( 5, absint( $get( 'lookback_minutes', 15 ) ) );
		} else {
			$clean['lookback_minutes'] = (int) ( $existing['lookback_minutes'] ?? 15 );
		}

		$clean['delete_data_on_uninstall'] = $sanitize_checkbox( 'delete_data_on_uninstall', 'no' );
		$clean['debug_log']                = $sanitize_checkbox( 'debug_log', 'yes' );

		update_option( self::OPTION_NAME, $clean );
		self::$settings = $clean;

		return $clean;
	}
}
