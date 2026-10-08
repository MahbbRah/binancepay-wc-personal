<?php
/**
 * Tests for BPP_Payment_Matcher.
 *
 * @package BinancePersonalPay
 */

function setup_test_settings(): void {
	BPP_Settings::save_settings(
		array(
			'merchant_binance_id' => '411204837',
			'currency'            => 'USDT',
			'payment_timeout'     => 10,
		)
	);
}

function test_matcher_valid_note_payment(): void {
	setup_test_settings();
	$order = new Mock_WC_Order( 12345, '25.00', 'USD' );

	$tx = array(
		'binance_transaction_id' => 'TX_SUCCESS_001',
		'binance_order_id'       => 'BO_001',
		'amount'                 => '25.0',
		'currency'               => 'USDT',
		'note'                   => 'plan-12345',
		'receiver_binance_id'    => '411204837',
		'transaction_time'       => time() * 1000,
		'payer_name'             => 'Alice',
		'payer_binance_id'       => '111',
	);

	$result = BPP_Payment_Matcher::verify_and_match( $tx, $order, false );

	assert( $result['matched'] === true, 'Valid payment matches successfully' );
	assert( $order->is_paid() === true, 'Order status changed to paid/processing' );
	assert( $order->get_meta( BPP_Order_Handler::META_BINANCE_TX_ID ) === 'TX_SUCCESS_001', 'Transaction ID saved to order' );
}

function test_matcher_wrong_note(): void {
	setup_test_settings();
	$order = new Mock_WC_Order( 12345, '25.00', 'USD' );

	$tx = array(
		'binance_transaction_id' => 'TX_WRONG_NOTE',
		'binance_order_id'       => 'BO_002',
		'amount'                 => '25.00',
		'currency'               => 'USDT',
		'note'                   => 'plan-99999', // Wrong order ID in note
		'receiver_binance_id'    => '411204837',
		'transaction_time'       => time() * 1000,
	);

	$result = BPP_Payment_Matcher::verify_and_match( $tx, $order, false );

	assert( $result['matched'] === false, 'Wrong note must not match' );
	assert( $order->is_paid() === false, 'Order remains unpaid' );
}

function test_matcher_wrong_amount(): void {
	setup_test_settings();
	$order = new Mock_WC_Order( 12345, '25.00', 'USD' );

	$tx = array(
		'binance_transaction_id' => 'TX_WRONG_AMOUNT',
		'binance_order_id'       => 'BO_003',
		'amount'                 => '20.00', // Sent 20 instead of 25
		'currency'               => 'USDT',
		'note'                   => 'plan-12345',
		'receiver_binance_id'    => '411204837',
		'transaction_time'       => time() * 1000,
	);

	$result = BPP_Payment_Matcher::verify_and_match( $tx, $order, false );

	assert( $result['matched'] === false, 'Wrong amount must not match' );
	assert( $order->is_paid() === false, 'Order remains unpaid' );
}

function test_matcher_wrong_currency(): void {
	setup_test_settings();
	$order = new Mock_WC_Order( 12345, '25.00', 'USD' );

	$tx = array(
		'binance_transaction_id' => 'TX_WRONG_CURRENCY',
		'binance_order_id'       => 'BO_004',
		'amount'                 => '25.00',
		'currency'               => 'BTC', // Sent BTC instead of USDT
		'note'                   => 'plan-12345',
		'receiver_binance_id'    => '411204837',
		'transaction_time'       => time() * 1000,
	);

	$result = BPP_Payment_Matcher::verify_and_match( $tx, $order, false );

	assert( $result['matched'] === false, 'Wrong currency must not match' );
	assert( $order->is_paid() === false, 'Order remains unpaid' );
}

function test_matcher_wrong_receiver(): void {
	setup_test_settings();
	$order = new Mock_WC_Order( 12345, '25.00', 'USD' );

	$tx = array(
		'binance_transaction_id' => 'TX_WRONG_RECEIVER',
		'binance_order_id'       => 'BO_005',
		'amount'                 => '25.00',
		'currency'               => 'USDT',
		'note'                   => 'plan-12345',
		'receiver_binance_id'    => '999999999', // Different Binance ID
		'transaction_time'       => time() * 1000,
	);

	$result = BPP_Payment_Matcher::verify_and_match( $tx, $order, false );

	assert( $result['matched'] === false, 'Wrong receiver must not match' );
	assert( $order->is_paid() === false, 'Order remains unpaid' );
}

function test_matcher_fallback_binance_order_id(): void {
	setup_test_settings();
	$order = new Mock_WC_Order( 12345, '25.00', 'USD' );

	// Customer forgot note, note is empty.
	$tx = array(
		'binance_transaction_id' => 'TX_FALLBACK_001',
		'binance_order_id'       => '454903295241510912',
		'amount'                 => '25.00',
		'currency'               => 'USDT',
		'note'                   => '', // Empty note!
		'receiver_binance_id'    => '411204837',
		'transaction_time'       => time() * 1000,
	);

	// In fallback mode ($is_fallback_mode = true).
	$result = BPP_Payment_Matcher::verify_and_match( $tx, $order, true );

	assert( $result['matched'] === true, 'Binance Order ID fallback succeeds when amount, currency & receiver match' );
	assert( $order->is_paid() === true, 'Order is paid via fallback' );
	assert( $order->get_meta( BPP_Order_Handler::META_BINANCE_ORDER_ID ) === '454903295241510912', 'Binance Order ID stored' );
}

function test_matcher_idempotency_duplicate(): void {
	global $wpdb;
	setup_test_settings();
	$order1 = new Mock_WC_Order( 1001, '25.00', 'USD' );
	$order2 = new Mock_WC_Order( 1002, '25.00', 'USD' );

	$tx = array(
		'binance_transaction_id' => 'TX_CONSUMED_ONCE',
		'binance_order_id'       => 'BO_CONSUMED',
		'amount'                 => '25.00',
		'currency'               => 'USDT',
		'note'                   => 'plan-1001',
		'receiver_binance_id'    => '411204837',
		'transaction_time'       => time() * 1000,
	);

	// First order consumes transaction.
	$res1 = BPP_Payment_Matcher::verify_and_match( $tx, $order1, false );
	assert( $res1['matched'] === true, 'First order consumes transaction' );

	// Mock DB row recording it as PROCESSED for Order 1001.
	$wpdb->rows[] = array(
		'binance_transaction_id' => 'TX_CONSUMED_ONCE',
		'woocommerce_order_id'   => 1001,
		'status'                 => BPP_Payment_Matcher::STATUS_PROCESSED,
	);

	// Second order tries to claim the exact same transaction ID.
	$tx_reuse = array(
		'binance_transaction_id' => 'TX_CONSUMED_ONCE',
		'binance_order_id'       => 'BO_CONSUMED',
		'amount'                 => '25.00',
		'currency'               => 'USDT',
		'note'                   => 'plan-1002',
		'receiver_binance_id'    => '411204837',
		'transaction_time'       => time() * 1000,
	);

	$res2 = BPP_Payment_Matcher::verify_and_match( $tx_reuse, $order2, false );
	assert( $res2['matched'] === false, 'Duplicate transaction rejected for second order' );
	assert( $order2->is_paid() === false, 'Second order remains unpaid' );
}

function test_matcher_transaction_prior_to_order(): void {
	setup_test_settings();
	$order = new Mock_WC_Order( 2001, '25.00', 'USD' );

	$tx = array(
		'binance_transaction_id' => 'TX_OLD_001',
		'binance_order_id'       => 'BO_OLD',
		'amount'                 => '25.00',
		'currency'               => 'USDT',
		'note'                   => 'plan-2001',
		'receiver_binance_id'    => '411204837',
		// Transaction time was 2 hours ago, way before order creation time.
		'transaction_time'       => ( time() - 7200 ) * 1000,
	);

	$res = BPP_Payment_Matcher::verify_and_match( $tx, $order, false );
	assert( $res['matched'] === false, 'Pre-dated transaction rejected' );
	assert( str_contains( $res['reason'], 'before' ), 'Reason mentions occurred before order' );
}

function test_matcher_zero_or_negative_amount(): void {
	setup_test_settings();
	$order = new Mock_WC_Order( 3001, '25.00', 'USD' );

	$tx_zero = array(
		'binance_transaction_id' => 'TX_ZERO_001',
		'binance_order_id'       => 'BO_ZERO',
		'amount'                 => '0',
		'currency'               => 'USDT',
		'note'                   => 'plan-3001',
		'receiver_binance_id'    => '411204837',
		'transaction_time'       => time() * 1000,
	);

	$res = BPP_Payment_Matcher::verify_and_match( $tx_zero, $order, false );
	assert( $res['matched'] === false, 'Zero amount rejected' );
}

function test_matcher_fallback_amount_mismatch(): void {
	setup_test_settings();
	$order = new Mock_WC_Order( 4001, '50.00', 'USD' );

	$tx = array(
		'binance_transaction_id' => 'TX_FB_MISMATCH',
		'binance_order_id'       => 'BO_FB_MISMATCH',
		'amount'                 => '49.99', // 49.99 != 50.00
		'currency'               => 'USDT',
		'note'                   => '',
		'receiver_binance_id'    => '411204837',
		'transaction_time'       => time() * 1000,
	);

	$res = BPP_Payment_Matcher::verify_and_match( $tx, $order, true );
	assert( $res['matched'] === false, 'Fallback with amount mismatch rejected' );
}

function test_matcher_non_usd_order_rejected(): void {
	setup_test_settings();
	// Order placed in EUR or BDT must be rejected!
	$order_eur = new Mock_WC_Order( 5001, '25.00', 'EUR' );

	$tx = array(
		'binance_transaction_id' => 'TX_EUR_ORDER',
		'binance_order_id'       => 'BO_EUR',
		'amount'                 => '25.00',
		'currency'               => 'USDT',
		'note'                   => 'plan-5001',
		'receiver_binance_id'    => '411204837',
		'transaction_time'       => time() * 1000,
	);

	$res = BPP_Payment_Matcher::verify_and_match( $tx, $order_eur, false );
	assert( $res['matched'] === false, 'Non-USD order must be rejected' );
	assert( str_contains( $res['reason'], 'USD' ), 'Reason states order currency must be USD' );
}


