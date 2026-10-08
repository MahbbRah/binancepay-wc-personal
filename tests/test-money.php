<?php
/**
 * Tests for BPP_Money.
 *
 * @package BinancePersonalPay
 */

function test_money_normalization(): void {
	assert( BPP_Money::normalize( '25' ) === '25', 'Normalize 25' );
	assert( BPP_Money::normalize( '25.0' ) === '25', 'Normalize 25.0 trims trailing zero' );
	assert( BPP_Money::normalize( '25.000000' ) === '25', 'Normalize 25.000000 trims zeroes' );
	assert( BPP_Money::normalize( '25.5000' ) === '25.5', 'Normalize 25.5000' );
	assert( BPP_Money::normalize( '  538.00  ' ) === '538', 'Normalize with spaces' );
	assert( BPP_Money::normalize( '1,250.50' ) === '1250.5', 'Normalize with commas' );
}

function test_money_equality(): void {
	// 25, 25.0, 25.00, 25.000000 should represent the same amount.
	assert( BPP_Money::is_equal( '25', '25.0', 'USDT' ) === true, '25 equals 25.0' );
	assert( BPP_Money::is_equal( '25', '25.00', 'USDT' ) === true, '25 equals 25.00' );
	assert( BPP_Money::is_equal( '25', '25.000000', 'USDT' ) === true, '25 equals 25.000000' );
	assert( BPP_Money::is_equal( '538', '538.00', 'USDT' ) === true, '538 equals 538.00' );

	// 24.999999 must NOT match 25!
	assert( BPP_Money::is_equal( '24.999999', '25', 'USDT' ) === false, '24.999999 != 25' );
	assert( BPP_Money::is_equal( '25.01', '25.00', 'USDT' ) === false, '25.01 != 25.00' );
	assert( BPP_Money::is_equal( '0.00000001', '0.00000002', 'BTC' ) === false, 'BTC satoshi difference detected' );
}

function test_money_compare(): void {
	assert( BPP_Money::compare( '10', '20' ) === -1, '10 < 20' );
	assert( BPP_Money::compare( '20', '10' ) === 1, '20 > 10' );
	assert( BPP_Money::compare( '15.5', '15.50' ) === 0, '15.5 == 15.50' );
}

function test_money_format(): void {
	$formatted = BPP_Money::format( '25', 'USDT', 2 );
	assert( $formatted === '25.00 USDT', "Format 25 USDT -> {$formatted}" );

	$formatted_btc = BPP_Money::format( '0.12345678', 'BTC', 8 );
	assert( $formatted_btc === '0.12345678 BTC', "Format BTC -> {$formatted_btc}" );
}
