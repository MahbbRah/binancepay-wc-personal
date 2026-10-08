<?php
/**
 * Unit tests for BPP_Settings.
 *
 * @package BinancePersonalPay
 */

function test_settings_save_with_prefixed_post_keys(): void {
	// Reset option in test harness.
	update_option( BPP_Settings::OPTION_NAME, array() );

	$post_data = array(
		'woocommerce_binance_pay_enabled'             => '1',
		'woocommerce_binance_pay_title'               => 'Binance Pay Direct',
		'woocommerce_binance_pay_description'         => 'Pay with Crypto',
		'woocommerce_binance_pay_api_key'             => 'test_api_key_abc',
		'woocommerce_binance_pay_api_secret'          => 'secret_1234567890',
		'woocommerce_binance_pay_merchant_binance_id' => '411204837',
		'woocommerce_binance_pay_currency'            => 'usdt',
		'woocommerce_binance_pay_payment_timeout'     => '15',
		'woocommerce_binance_pay_enable_auto_scan'    => '1',
		'woocommerce_binance_pay_enable_manual_verify'=> '1',
		'woocommerce_binance_pay_scanner_interval'    => '45',
		'woocommerce_binance_pay_lookback_minutes'    => '20',
	);

	$saved = BPP_Settings::save_settings( $post_data );

	assert( 'yes' === $saved['enabled'], 'Enabled should be yes' );
	assert( 'Binance Pay Direct' === $saved['title'], 'Title should match' );
	assert( 'Pay with Crypto' === $saved['description'], 'Description should match' );
	assert( 'test_api_key_abc' === $saved['api_key'], 'API key should match' );
	assert( 'secret_1234567890' === $saved['api_secret'], 'API secret should match' );
	assert( '411204837' === $saved['merchant_binance_id'], 'Binance ID should match' );
	assert( 'USDT' === $saved['currency'], 'Currency should be uppercase USDT' );
	assert( 15 === $saved['payment_timeout'], 'Timeout should be 15' );
	assert( 'yes' === $saved['enable_auto_scan'], 'Auto scan should be yes' );
	assert( 'yes' === $saved['enable_manual_verify'], 'Manual verify should be yes' );
	assert( 45 === $saved['scanner_interval'], 'Interval should be 45' );
	assert( 20 === $saved['lookback_minutes'], 'Lookback should be 20' );
}

function test_settings_preserve_secret_when_masked_or_empty(): void {
	// First save valid secret.
	$initial = array(
		'woocommerce_binance_pay_api_key'             => 'initial_key',
		'woocommerce_binance_pay_api_secret'          => 'super_secret_token_xyz',
		'woocommerce_binance_pay_merchant_binance_id' => '111222333',
	);
	BPP_Settings::save_settings( $initial );

	// Save again with masked secret.
	$second_post = array(
		'woocommerce_binance_pay_title'               => 'Binance Pay',
		'woocommerce_binance_pay_api_key'             => 'initial_key',
		'woocommerce_binance_pay_api_secret'          => '••••••••••••••••token_xyz',
		'woocommerce_binance_pay_merchant_binance_id' => '111222333',
	);
	$saved_second = BPP_Settings::save_settings( $second_post );

	assert( 'super_secret_token_xyz' === $saved_second['api_secret'], 'Secret should remain preserved when masked value is submitted' );

	// Save again with empty secret.
	$third_post = array(
		'woocommerce_binance_pay_title'               => 'Binance Pay',
		'woocommerce_binance_pay_api_key'             => 'initial_key',
		'woocommerce_binance_pay_api_secret'          => '',
		'woocommerce_binance_pay_merchant_binance_id' => '111222333',
	);
	$saved_third = BPP_Settings::save_settings( $third_post );

	assert( 'super_secret_token_xyz' === $saved_third['api_secret'], 'Secret should remain preserved when empty string is submitted' );
}

function test_settings_checkbox_omitted_on_form_submission(): void {
	// If form is submitted with title and api_key but enabled is omitted (unchecked), enabled becomes 'no'.
	$post = array(
		'woocommerce_binance_pay_title'   => 'Binance Pay',
		'woocommerce_binance_pay_api_key' => 'key_123',
		// enabled omitted
	);
	$saved = BPP_Settings::save_settings( $post );

	assert( 'no' === $saved['enabled'], 'Omitted checkbox on form submission should save as "no"' );
}
