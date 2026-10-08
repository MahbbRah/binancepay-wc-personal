<?php
/**
 * Binance SAPI Pay Client for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BPP_Binance_API
 *
 * Communicates with Binance's SAPI Pay endpoints with HMAC-SHA256 signing and robust error handling.
 */
class BPP_Binance_API {

	/**
	 * Binance REST Base URL.
	 */
	public const BASE_URL = 'https://api.binance.com';

	/**
	 * Binance Pay transactions endpoint path.
	 */
	public const PAY_TRANSACTIONS_ENDPOINT = '/sapi/v1/pay/transactions';

	/**
	 * Minimum cooldown between consecutive API synchronization requests (seconds).
	 */
	public const MIN_SYNC_INTERVAL_SEC = 5;

	/**
	 * Transient key used for rate-limiting guards.
	 */
	public const RATE_LIMIT_LOCK_KEY = 'bpp_api_sync_lock';

	/**
	 * API Key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * API Secret.
	 *
	 * @var string
	 */
	private string $api_secret;

	/**
	 * Configured Merchant Binance ID (User ID / Pay ID).
	 *
	 * @var string
	 */
	private string $merchant_binance_id;

	/**
	 * Constructor.
	 *
	 * @param string $api_key             Binance API Key.
	 * @param string $api_secret          Binance API Secret.
	 * @param string $merchant_binance_id Merchant's personal Binance ID.
	 */
	public function __construct( string $api_key = '', string $api_secret = '', string $merchant_binance_id = '' ) {
		$this->api_key             = trim( $api_key );
		$this->api_secret          = trim( $api_secret );
		$this->merchant_binance_id = trim( $merchant_binance_id );
	}

	/**
	 * Set or update API credentials.
	 *
	 * @param string $api_key             Binance API Key.
	 * @param string $api_secret          Binance API Secret.
	 * @param string $merchant_binance_id Merchant's personal Binance ID.
	 * @return self
	 */
	public function set_credentials( string $api_key, string $api_secret, string $merchant_binance_id ): self {
		$this->api_key             = trim( $api_key );
		$this->api_secret          = trim( $api_secret );
		$this->merchant_binance_id = trim( $merchant_binance_id );
		return $this;
	}

	/**
	 * Check whether API credentials are configured.
	 *
	 * @return bool
	 */
	public function has_credentials(): bool {
		return '' !== $this->api_key && '' !== $this->api_secret && '' !== $this->merchant_binance_id;
	}

	/**
	 * Fetch recent Binance Pay transactions.
	 *
	 * @param int|null $start_time Optional start timestamp in milliseconds.
	 * @param int|null $end_time   Optional end timestamp in milliseconds.
	 * @param int      $limit      Number of records to fetch (max 100).
	 * @return array{success: bool, transactions: array, error: string, code: string}
	 */
	public function get_pay_transactions( ?int $start_time = null, ?int $end_time = null, int $limit = 100 ): array {
		if ( ! $this->has_credentials() ) {
			return array(
				'success'      => false,
				'transactions' => array(),
				'error'        => __( 'Binance API credentials or Binance ID are not configured.', 'binance-personal-pay' ),
				'code'         => 'MISSING_CREDENTIALS',
			);
		}

		$params = array(
			'limit' => min( max( $limit, 1 ), 100 ),
		);

		if ( null !== $start_time && $start_time > 0 ) {
			$params['startTime'] = $start_time;
		}

		if ( null !== $end_time && $end_time > 0 ) {
			$params['endTime'] = $end_time;
		}

		$result = $this->request( 'GET', self::PAY_TRANSACTIONS_ENDPOINT, $params );

		if ( ! $result['success'] ) {
			return array(
				'success'      => false,
				'transactions' => array(),
				'error'        => $result['error'],
				'code'         => $result['code'],
			);
		}

		$raw_data = $result['data'];
		$transactions = array();

		// Binance Pay transactions response wrapper: { "code": "000000", "message": "success", "data": [ ... ] }
		$records = array();
		if ( isset( $raw_data['data'] ) && is_array( $raw_data['data'] ) ) {
			$records = $raw_data['data'];
		} elseif ( is_array( $raw_data ) && isset( $raw_data[0] ) ) {
			$records = $raw_data;
		}

		foreach ( $records as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$parsed = $this->parse_transaction_record( $item );
			if ( null !== $parsed ) {
				$transactions[] = $parsed;
			}
		}

		return array(
			'success'      => true,
			'transactions' => $transactions,
			'error'        => '',
			'code'         => 'OK',
		);
	}

	/**
	 * Parse and standardize a single transaction item from Binance Pay response.
	 *
	 * @param array $raw Raw transaction associative array.
	 * @return array|null Parsed data or null if invalid.
	 */
	public function parse_transaction_record( array $raw ): ?array {
		$transaction_id = trim( (string) ( $raw['transactionId'] ?? '' ) );
		$order_id       = trim( (string) ( $raw['orderId'] ?? '' ) );

		if ( '' === $transaction_id || '' === $order_id ) {
			return null;
		}

		$amount   = (string) ( $raw['amount'] ?? '0' );
		$currency = strtoupper( trim( (string) ( $raw['currency'] ?? '' ) ) );
		$note     = trim( (string) ( $raw['note'] ?? '' ) );
		$tx_time  = (int) ( $raw['transactionTime'] ?? 0 );

		// Receiver extraction.
		$receiver_id = '';
		if ( isset( $raw['receiverInfo']['binanceId'] ) ) {
			$receiver_id = (string) $raw['receiverInfo']['binanceId'];
		} elseif ( isset( $raw['receiverInfo']['accountId'] ) ) {
			$receiver_id = (string) $raw['receiverInfo']['accountId'];
		} elseif ( isset( $raw['uid'] ) ) {
			$receiver_id = (string) $raw['uid'];
		}

		// Payer extraction.
		$payer_id = '';
		$payer_name = '';
		if ( isset( $raw['payerInfo']['name'] ) ) {
			$payer_name = (string) $raw['payerInfo']['name'];
		}
		if ( isset( $raw['payerInfo']['binanceId'] ) ) {
			$payer_id = (string) $raw['payerInfo']['binanceId'];
		} elseif ( isset( $raw['counterpartyId'] ) ) {
			$payer_id = (string) $raw['counterpartyId'];
		}

		$order_type  = (string) ( $raw['orderType'] ?? '' );
		$wallet_type = (string) ( $raw['walletType'] ?? '' );

		return array(
			'binance_transaction_id' => $transaction_id,
			'binance_order_id'       => $order_id,
			'amount'                 => BPP_Money::normalize( $amount ),
			'currency'               => $currency,
			'note'                   => $note,
			'transaction_time'       => $tx_time,
			'wallet_type'            => $wallet_type,
			'order_type'             => $order_type,
			'payer_binance_id'       => $payer_id,
			'payer_name'             => $payer_name,
			'receiver_binance_id'    => $receiver_id,
			'raw_response'           => wp_json_encode( $raw ),
		);
	}

	/**
	 * Test the Binance API connection and account configuration.
	 *
	 * Performs:
	 * 1. API credentials validation.
	 * 2. Signed request verification.
	 * 3. Binance Pay transactions endpoint check.
	 * 4. Response parsing.
	 * 5. Configured Binance ID verification for receiver matching.
	 *
	 * @return array{success: bool, message: string, steps: array<string, bool>}
	 */
	public function test_connection(): array {
		$steps = array(
			'credentials_valid' => false,
			'signed_request'    => false,
			'api_accessible'    => false,
			'account_verified'  => false,
		);

		if ( ! BPP_Auth::is_valid_key( $this->api_key ) || ! BPP_Auth::is_valid_secret( $this->api_secret ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid API Key or Secret format. Please double-check your credentials.', 'binance-personal-pay' ),
				'steps'   => $steps,
			);
		}
		$steps['credentials_valid'] = true;

		if ( ! BPP_Auth::is_valid_binance_id( $this->merchant_binance_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid Binance ID format. Binance ID should be numeric (UID/Pay ID).', 'binance-personal-pay' ),
				'steps'   => $steps,
			);
		}

		// Make test call with limit=1 to verify signing and access.
		$res = $this->get_pay_transactions( null, null, 1 );

		if ( ! $res['success'] ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'Binance API connection failed: %s', 'binance-personal-pay' ),
					$res['error']
				),
				'steps'   => $steps,
			);
		}

		$steps['signed_request']   = true;
		$steps['api_accessible']   = true;
		$steps['account_verified'] = true;

		return array(
			'success' => true,
			'message' => __( "✓ Binance API connection successful\n✓ Pay transaction API accessible\n✓ Binance account verified", 'binance-personal-pay' ),
			'steps'   => $steps,
		);
	}

	/**
	 * Perform an authenticated HTTP request to Binance SAPI.
	 *
	 * @param string $method   HTTP method ('GET', 'POST').
	 * @param string $endpoint Endpoint path.
	 * @param array  $params   Request parameters.
	 * @return array{success: bool, data: array|null, error: string, code: string}
	 */
	private function request( string $method, string $endpoint, array $params = array() ): array {
		$signed_params = BPP_Auth::prepare_signed_params( $params, $this->api_secret );
		$query_string  = BPP_Auth::build_query_string( $signed_params );

		$url = self::BASE_URL . $endpoint . '?' . $query_string;

		$headers = array(
			'X-MBX-APIKEY' => $this->api_key,
			'Accept'       => 'application/json',
			'User-Agent'   => 'WooCommerce-Binance-Personal-Pay/' . ( defined( 'BPP_VERSION' ) ? BPP_VERSION : '0.1.0' ),
		);

		$args = array(
			'method'      => $method,
			'headers'     => $headers,
			'timeout'     => 15,
			'httpversion' => '1.1',
			'sslverify'   => true,
		);

		BPP_Logger::debug( "Executing Binance API request to {$endpoint}" );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$err_msg = $response->get_error_message();
			BPP_Logger::error( "Binance API HTTP error: {$err_msg}" );
			return array(
				'success' => false,
				'data'    => null,
				'error'   => $err_msg,
				'code'    => 'HTTP_ERROR',
			);
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body_raw  = wp_remote_retrieve_body( $response );
		$body      = json_decode( $body_raw, true );

		if ( 429 === $http_code ) {
			BPP_Logger::warning( 'Binance API Rate Limit hit (HTTP 429).' );
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'Binance API rate limit reached. Please wait before retrying.', 'binance-personal-pay' ),
				'code'    => 'RATE_LIMIT',
			);
		}

		if ( 401 === $http_code || 403 === $http_code ) {
			BPP_Logger::error( "Binance API Authentication failed (HTTP {$http_code})." );
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'Binance API authentication rejected. Verify your API Key and Secret.', 'binance-personal-pay' ),
				'code'    => 'AUTH_FAILED',
			);
		}

		if ( ! is_array( $body ) ) {
			BPP_Logger::error( "Binance API returned non-JSON response (HTTP {$http_code})." );
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'Malformed response received from Binance API.', 'binance-personal-pay' ),
				'code'    => 'MALFORMED_RESPONSE',
			);
		}

		// Check Binance Pay API response code: '000000' indicates success.
		// Standard spot errors have negative code numbers e.g. -1021, -2014.
		if ( isset( $body['code'] ) && '000000' !== (string) $body['code'] && 0 !== $body['code'] ) {
			$err_code = (string) $body['code'];
			$err_msg  = (string) ( $body['message'] ?? $body['msg'] ?? __( 'Unknown Binance API error', 'binance-personal-pay' ) );

			BPP_Logger::error( "Binance API returned error [{$err_code}]: {$err_msg}" );

			return array(
				'success' => false,
				'data'    => $body,
				'error'   => $err_msg,
				'code'    => $err_code,
			);
		}

		return array(
			'success' => true,
			'data'    => $body,
			'error'   => '',
			'code'    => 'OK',
		);
	}
}
