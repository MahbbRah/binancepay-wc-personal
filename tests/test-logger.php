<?php
/**
 * Tests for BPP_Logger sensitive data redaction.
 *
 * @package BinancePersonalPay
 */

function test_logger_redaction(): void {
	$secret_text = 'Calling API with signature=abcdef1234567890abcdef and X-MBX-APIKEY: mysecretkey';
	$sanitized = BPP_Logger::redact_sensitive_data( $secret_text );

	assert( ! str_contains( $sanitized, 'abcdef1234567890abcdef' ), 'Signature was redacted from string' );
	assert( ! str_contains( $sanitized, 'mysecretkey' ), 'API key header was redacted from string' );
	assert( str_contains( $sanitized, '***REDACTED***' ), 'Contains REDACTED replacement' );

	$context = array(
		'order_id'   => 12345,
		'api_secret' => 'super_secret_value_123',
		'nested'     => array(
			'token' => 'nested_token_secret',
			'note'  => 'plan-12345',
		),
	);

	$sanitized_context = BPP_Logger::redact_context( $context );

	assert( $sanitized_context['api_secret'] === '***REDACTED***', 'Context api_secret was redacted' );
	assert( $sanitized_context['nested']['token'] === '***REDACTED***', 'Nested token was redacted' );
	assert( $sanitized_context['nested']['note'] === 'plan-12345', 'Safe context data preserved' );
}
