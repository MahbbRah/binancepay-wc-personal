<?php
/**
 * Test bootstrap for Binance Personal Pay standalone test suite.
 *
 * @package BinancePersonalPay
 */

// Define mock WordPress and WooCommerce constants.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'BPP_VERSION' ) ) {
	define( 'BPP_VERSION', '0.1.0' );
}
if ( ! defined( 'BPP_PLUGIN_FILE' ) ) {
	define( 'BPP_PLUGIN_FILE', dirname( __DIR__ ) . '/binance-personal-pay.php' );
}
if ( ! defined( 'BPP_PLUGIN_DIR' ) ) {
	define( 'BPP_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'BPP_PLUGIN_URL' ) ) {
	define( 'BPP_PLUGIN_URL', 'https://example.com/wp-content/plugins/binance-personal-pay/' );
}
if ( ! defined( 'BPP_PLUGIN_BASENAME' ) ) {
	define( 'BPP_PLUGIN_BASENAME', 'binance-personal-pay/binance-personal-pay.php' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

// Global storage for mock options.
global $mock_options, $wpdb;
$mock_options = array();

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return false;
	}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id(): int {
		return 0;
	}
}

// Mock WordPress functions.
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( string $text, string $domain = 'default' ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( mixed $val ): int {
		return abs( (int) $val );
	}
}
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( mixed $args, array $defaults = array() ): array {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		}
		if ( ! is_array( $args ) ) {
			return $defaults;
		}
		return array_merge( $defaults, $args );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, mixed $default = false ): mixed {
		global $mock_options;
		return $mock_options[ $option ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, mixed $value ): bool {
		global $mock_options;
		$mock_options[ $option ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option ): bool {
		global $mock_options;
		unset( $mock_options[ $option ] );
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $transient ): mixed {
		return false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $transient, mixed $value, int $expiration = 0 ): bool {
		return true;
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( string $type, int $gmt = 0 ): string|int {
		if ( 'mysql' === $type ) {
			return gmdate( 'Y-m-d H:i:s' );
		}
		if ( 'timestamp' === $type ) {
			return time();
		}
		return time();
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $data, int $options = 0, int $depth = 512 ): string {
		return json_encode( $data, $options, $depth );
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $thing ): bool {
		return false;
	}
}
if ( ! function_exists( 'wc_get_logger' ) ) {
	function wc_get_logger() {
		return new class() {
			public array $logs = array();
			public function log( string $level, string $message, array $context = array() ): void {
				$this->logs[] = array(
					'level'   => $level,
					'message' => $message,
					'context' => $context,
				);
			}
		};
	}
}

// Mock WordPress wpdb class.
class Mock_WPDB {
	public string $prefix = 'wp_';
	public array $tables = array();
	public array $last_inserted = array();
	public array $rows = array();

	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	public function prepare( string $query, ...$args ): string {
		foreach ( $args as $arg ) {
			$val = is_numeric( $arg ) ? $arg : "'" . addslashes( (string) $arg ) . "'";
			$query = preg_replace( '/%[sdf]/', (string) $val, $query, 1 );
		}
		return $query;
	}

	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	public function get_var( string $query ) {
		return null;
	}

	public function get_row( string $query, string $output = 'ARRAY_A' ) {
		foreach ( $this->rows as $row ) {
			if ( str_contains( $query, $row['binance_transaction_id'] ?? '---' ) ) {
				return $row;
			}
			if ( isset( $row['note'] ) && str_contains( $query, "'" . addslashes( $row['note'] ) . "'" ) ) {
				return $row;
			}
			if ( isset( $row['binance_order_id'] ) && str_contains( $query, "'" . addslashes( $row['binance_order_id'] ) . "'" ) ) {
				return $row;
			}
		}
		return null;
	}

	public function insert( string $table, array $data, array $format = array() ): int {
		$this->last_inserted = $data;
		$this->rows[] = $data;
		return 1;
	}

	public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ): int {
		foreach ( $this->rows as &$row ) {
			$matches = true;
			foreach ( $where as $wk => $wv ) {
				if ( ( $row[ $wk ] ?? null ) !== $wv ) {
					$matches = false;
					break;
				}
			}
			if ( $matches ) {
				$row = array_merge( $row, $data );
			}
		}
		return 1;
	}

	public function query( string $query ): int {
		return 1;
	}
}

$wpdb = new Mock_WPDB();

// Mock WooCommerce Order class.
class WC_Order {
	private int $id;
	private string $total;
	private string $currency;
	private string $status;
	private array $meta = array();
	private array $notes = array();
	private int $created_timestamp;

	public function __construct( int $id = 12345, string $total = '25.00', string $currency = 'USD', string $status = 'pending' ) {
		$this->id                = $id;
		$this->total             = $total;
		$this->currency          = $currency;
		$this->status            = $status;
		$this->created_timestamp = time() - 60; // 1 minute ago
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_total(): string {
		return $this->total;
	}

	public function get_currency(): string {
		return $this->currency;
	}

	public function get_status(): string {
		return $this->status;
	}

	public function is_paid(): bool {
		return in_array( $this->status, array( 'processing', 'completed' ), true );
	}

	public function update_status( string $status, string $note = '' ): void {
		$this->status = $status;
		if ( ! empty( $note ) ) {
			$this->notes[] = $note;
		}
	}

	public function payment_complete( string $transaction_id = '' ): bool {
		$this->status = 'processing';
		return true;
	}

	public function update_meta_data( string $key, mixed $value ): void {
		$this->meta[ $key ] = $value;
	}

	public function get_meta( string $key, bool $single = true ): mixed {
		return $this->meta[ $key ] ?? '';
	}

	public function save(): void {}

	public function add_order_note( string $note ): void {
		$this->notes[] = $note;
	}

	public function get_notes(): array {
		return $this->notes;
	}

	public function get_order_key(): string {
		return 'wc_order_' . md5( (string) $this->id );
	}

	public function get_customer_id(): int {
		return 1;
	}

	public function get_date_created() {
		return new class( $this->created_timestamp ) {
			private int $ts;
			public function __construct( int $ts ) {
				$this->ts = $ts;
			}
			public function getTimestamp(): int {
				return $this->ts;
			}
		};
	}

	public function get_checkout_order_received_url(): string {
		return 'https://example.com/checkout/order-received/' . $this->id . '/?key=' . $this->get_order_key();
	}
}
class_alias( 'WC_Order', 'Mock_WC_Order' );

// Load plugin classes.
require_once dirname( __DIR__ ) . '/database/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/class-money.php';
require_once dirname( __DIR__ ) . '/includes/class-logger.php';
require_once dirname( __DIR__ ) . '/includes/class-auth.php';
require_once dirname( __DIR__ ) . '/includes/class-binance-api.php';
require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-order-handler.php';
require_once dirname( __DIR__ ) . '/includes/class-payment-matcher.php';
require_once dirname( __DIR__ ) . '/includes/class-payment-status.php';
