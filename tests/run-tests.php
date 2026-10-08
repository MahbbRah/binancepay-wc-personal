<?php
/**
 * Master test runner for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

// Enable assertions.
ini_set( 'zend.assertions', '1' );
ini_set( 'assert.exception', '1' );

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/test-money.php';
require_once __DIR__ . '/test-auth.php';
require_once __DIR__ . '/test-binance-api.php';
require_once __DIR__ . '/test-order-handler.php';
require_once __DIR__ . '/test-payment-matcher.php';
require_once __DIR__ . '/test-logger.php';

$test_functions = array(
	// Money tests
	'test_money_normalization',
	'test_money_equality',
	'test_money_compare',
	'test_money_format',

	// Auth tests
	'test_auth_hmac_signing',
	'test_auth_prepare_signed_params',
	'test_auth_secret_masking',
	'test_auth_validation',

	// API parsing tests
	'test_binance_api_transaction_parsing',
	'test_binance_api_invalid_record_handling',

	// Order handler tests
	'test_order_handler_reference',
	'test_order_handler_initialize_and_expiry',
	'test_order_handler_access_verification',

	// Payment matcher tests
	'test_matcher_valid_note_payment',
	'test_matcher_wrong_note',
	'test_matcher_wrong_amount',
	'test_matcher_wrong_currency',
	'test_matcher_wrong_receiver',
	'test_matcher_fallback_binance_order_id',
	'test_matcher_idempotency_duplicate',
	'test_matcher_transaction_prior_to_order',
	'test_matcher_zero_or_negative_amount',
	'test_matcher_fallback_amount_mismatch',
	'test_matcher_non_usd_order_rejected',

	// Logger tests
	'test_logger_redaction',
);

echo "========================================================\n";
echo " Binance Personal Pay for WooCommerce - Test Suite\n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

foreach ( $test_functions as $func ) {
	try {
		$func();
		echo " [PASS] {$func}\n";
		$passed++;
	} catch ( Throwable $e ) {
		echo " [FAIL] {$func}: " . $e->getMessage() . "\n";
		echo "        at " . $e->getFile() . ':' . $e->getLine() . "\n";
		$failed++;
	}
}

echo "\n--------------------------------------------------------\n";
echo " Summary: {$passed} Passed, {$failed} Failed (Total: " . count( $test_functions ) . ")\n";
echo "========================================================\n";

exit( $failed > 0 ? 1 : 0 );
