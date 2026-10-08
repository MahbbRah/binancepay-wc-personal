<?php
/**
 * Tests for BPP_Binance_API parsing.
 *
 * @package BinancePersonalPay
 */

function test_binance_api_transaction_parsing(): void {
	$api = new BPP_Binance_API( 'key1234567890123456', 'secret1234567890123456', '411204837' );

	$raw_sample = array(
		'uid'             => 411204837,
		'counterpartyId'  => 1115744070,
		'orderId'         => '454903295241510912',
		'note'            => 'plan-12345',
		'orderType'       => 'C2C',
		'transactionId'   => 'P_A24F9ZJM5B57111A',
		'transactionTime' => 1789667654792,
		'amount'          => '538',
		'currency'        => 'USDT',
		'walletType'      => 1,
		'walletTypes'     => array( '1' ),
		'fundsDetail'     => array(
			array(
				'currency' => 'USDT',
				'amount'   => '538',
			),
		),
		'payerInfo'       => array(
			'name'       => 'Shoebislam',
			'type'       => 'USER',
			'unmaskData' => false,
		),
		'receiverInfo'    => array(
			'binanceId'  => 411204837,
			'unmaskData' => false,
		),
		'totalPaymentFee' => '0',
	);

	$parsed = $api->parse_transaction_record( $raw_sample );

	assert( null !== $parsed, 'Transaction successfully parsed' );
	assert( $parsed['binance_transaction_id'] === 'P_A24F9ZJM5B57111A', 'Transaction ID match' );
	assert( $parsed['binance_order_id'] === '454903295241510912', 'Binance Order ID match' );
	assert( $parsed['amount'] === '538', 'Amount normalized match' );
	assert( $parsed['currency'] === 'USDT', 'Currency match' );
	assert( $parsed['note'] === 'plan-12345', 'Note match' );
	assert( $parsed['transaction_time'] === 1789667654792, 'Timestamp match' );
	assert( $parsed['payer_name'] === 'Shoebislam', 'Payer name match' );
	assert( $parsed['receiver_binance_id'] === '411204837', 'Receiver Binance ID match' );
	assert( ! empty( $parsed['raw_response'] ), 'Raw response JSON preserved' );
}

function test_binance_api_invalid_record_handling(): void {
	$api = new BPP_Binance_API();

	$invalid = array(
		'note' => 'missing IDs',
	);

	assert( $api->parse_transaction_record( $invalid ) === null, 'Invalid transaction record returns null' );
}
