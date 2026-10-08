<?php
/**
 * Tests for BPP_Order_Handler.
 *
 * @package BinancePersonalPay
 */

function test_order_handler_reference(): void {
	$order = new Mock_WC_Order( 12345 );

	assert( BPP_Order_Handler::get_payment_reference( 12345 ) === 'plan-12345', 'Reference for int order ID' );
	assert( BPP_Order_Handler::get_payment_reference( $order ) === 'plan-12345', 'Reference for Mock_WC_Order' );

	assert( BPP_Order_Handler::get_order_id_from_reference( 'plan-12345' ) === 12345, 'Parse lowercase reference' );
	assert( BPP_Order_Handler::get_order_id_from_reference( 'PLAN-9988' ) === 9988, 'Parse uppercase reference' );
	assert( BPP_Order_Handler::get_order_id_from_reference( 'invalid-reference' ) === null, 'Parse invalid reference' );
	assert( BPP_Order_Handler::get_order_id_from_reference( 'plan-abc' ) === null, 'Parse non-numeric reference' );
}

function test_order_handler_initialize_and_expiry(): void {
	$order = new Mock_WC_Order( 7788 );
	BPP_Order_Handler::initialize_order( $order );

	$ref = $order->get_meta( BPP_Order_Handler::META_PAYMENT_REFERENCE );
	assert( $ref === 'plan-7788', "Payment reference initialized: {$ref}" );

	$expires_at = BPP_Order_Handler::get_payment_expires_at( $order );
	assert( $expires_at > time(), 'Expires at is set in future' );
	assert( BPP_Order_Handler::is_payment_window_active( $order ) === true, 'Payment window is active' );
}

function test_order_handler_access_verification(): void {
	$order = new Mock_WC_Order( 555 );
	$valid_key = $order->get_order_key();

	assert( BPP_Order_Handler::verify_order_access( $order, $valid_key ) === true, 'Valid order key grants access' );
	assert( BPP_Order_Handler::verify_order_access( $order, 'wrong_key' ) === false, 'Invalid order key is rejected' );
	assert( BPP_Order_Handler::verify_order_access( $order, '' ) === false, 'Empty order key is rejected' );
}
