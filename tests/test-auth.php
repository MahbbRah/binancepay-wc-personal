<?php
/**
 * Tests for BPP_Auth.
 *
 * @package BinancePersonalPay
 */

function test_auth_hmac_signing(): void {
	$params = array(
		'symbol'    => 'LTCBTC',
		'side'      => 'BUY',
		'type'      => 'LIMIT',
		'timeInForce' => 'GTC',
		'quantity'  => '1',
		'price'     => '0.1',
		'recvWindow' => 5000,
		'timestamp' => 1499827319559,
	);
	$secret = 'NhqPtmdSJYdKjVHjA7PZj4Mge3R5YNiP1e3UZjInClVN65XAbvqqM6A7H5fATj0j';

	// Official Binance example HMAC test.
	$signature = BPP_Auth::sign( $params, $secret );
	$expected  = 'c8db56825ae71d6d79447849e617115f4a920fa2acdcab2b053c4b2838bd6b71';

	assert( $signature === $expected, "HMAC-SHA256 signature mismatch. Got: {$signature}, expected: {$expected}" );
}

function test_auth_prepare_signed_params(): void {
	$params = array( 'limit' => 10 );
	$secret = 'testsecret1234567890';

	$signed = BPP_Auth::prepare_signed_params( $params, $secret );

	assert( isset( $signed['timestamp'] ), 'Timestamp was set' );
	assert( isset( $signed['recvWindow'] ), 'RecvWindow was set' );
	assert( isset( $signed['signature'] ), 'Signature was set' );
	assert( strlen( $signed['signature'] ) === 64, 'Signature is 64 hex characters' );
}

function test_auth_secret_masking(): void {
	assert( BPP_Auth::mask_secret( '' ) === '', 'Empty secret masked' );
	assert( BPP_Auth::mask_secret( 'short' ) === '•••••', 'Short secret fully masked' );

	$masked = BPP_Auth::mask_secret( '1234567890abcdefghijklmnop' );
	assert( str_starts_with( $masked, '1234' ), 'Masked starts with first 4 chars' );
	assert( str_ends_with( $masked, 'mnop' ), 'Masked ends with last 4 chars' );
	assert( str_contains( $masked, '••••••••' ), 'Masked contains bullet points' );
}

function test_auth_validation(): void {
	assert( BPP_Auth::is_valid_key( 'vmPUZE6mv9SD5VNHk4HlWFsOr6aKE2zvsw0MuIgwCIPy6utIco14y7Ju91duEh8A' ) === true, 'Valid API key' );
	assert( BPP_Auth::is_valid_key( 'too_short' ) === false, 'Too short API key rejected' );

	assert( BPP_Auth::is_valid_secret( 'NhqPtmdSJYdKjVHjA7PZj4Mge3R5YNiP1e3UZjInClVN65XAbvqqM6A7H5fATj0j' ) === true, 'Valid API secret' );
	assert( BPP_Auth::is_valid_secret( 'invalid secret with spaces!' ) === false, 'Secret with invalid characters rejected' );

	assert( BPP_Auth::is_valid_binance_id( '411204837' ) === true, 'Valid numeric Binance ID' );
	assert( BPP_Auth::is_valid_binance_id( 'not_a_number' ) === false, 'Non-numeric Binance ID rejected' );
	assert( BPP_Auth::is_valid_binance_id( '12' ) === false, 'Too short Binance ID rejected' );
}
