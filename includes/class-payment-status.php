<?php
/**
 * Payment status and timeout handling for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BPP_Payment_Status
 *
 * Evaluates current order status, handles polling checks, and implements timeout policy.
 */
class BPP_Payment_Status {

	/**
	 * Get current payment status for an order.
	 *
	 * @param WC_Order $order WooCommerce order instance.
	 * @return array{status: string, is_paid: bool, time_left: int, expires_at: int, redirect_url: string}
	 */
	public static function get_status_info( WC_Order $order ): array {
		$is_paid      = $order->is_paid() || in_array( $order->get_status(), array( 'processing', 'completed' ), true );
		$expires_at   = BPP_Order_Handler::get_payment_expires_at( $order );
		$now          = time();
		$time_left    = max( 0, $expires_at - $now );
		$redirect_url = $is_paid ? $order->get_checkout_order_received_url() : '';

		$status_string = 'pending';
		if ( $is_paid ) {
			$status_string = 'paid';
		} elseif ( 0 === $time_left ) {
			$status_string = 'expired';
		}

		return array(
			'status'       => $status_string,
			'is_paid'      => $is_paid,
			'time_left'    => $time_left,
			'expires_at'   => $expires_at,
			'redirect_url' => $redirect_url,
		);
	}

	/**
	 * Handle order expiration when countdown timer hits zero.
	 *
	 * Performs:
	 * 1. Final verification attempt via scanner.
	 * 2. If paid, completes order.
	 * 3. If unpaid, applies configured timeout action (keep_pending, mark_failed, cancel_order).
	 *
	 * @param WC_Order $order WooCommerce order instance.
	 * @return array
	 */
	public static function handle_timeout( WC_Order $order ): array {
		$order_id = $order->get_id();

		// Check if already paid.
		if ( $order->is_paid() ) {
			return array(
				'status'       => 'paid',
				'redirect_url' => $order->get_checkout_order_received_url(),
				'message'      => __( 'Payment verified successfully.', 'binance-personal-pay' ),
			);
		}

		// 1. Perform final verification check.
		$match = BPP_Payment_Matcher::match_order_by_note( $order );
		if ( $match['matched'] ) {
			return array(
				'status'       => 'paid',
				'redirect_url' => $order->get_checkout_order_received_url(),
				'message'      => __( 'Payment verified during final verification.', 'binance-personal-pay' ),
			);
		}

		// 2. Apply configured timeout action.
		$action = BPP_Settings::get_timeout_action();

		switch ( $action ) {
			case 'mark_failed':
				$order->update_status( 'failed', __( 'Binance Pay payment window expired without detected payment.', 'binance-personal-pay' ) );
				BPP_Logger::info( "Order #{$order_id} marked as failed due to timeout policy." );
				return array(
					'status'       => 'failed',
					'redirect_url' => '',
					'message'      => __( 'Payment window expired. The order has been marked as failed.', 'binance-personal-pay' ),
				);

			case 'cancel_order':
				$order->update_status( 'cancelled', __( 'Binance Pay payment window expired. Order cancelled.', 'binance-personal-pay' ) );
				BPP_Logger::info( "Order #{$order_id} cancelled due to timeout policy." );
				return array(
					'status'       => 'cancelled',
					'redirect_url' => '',
					'message'      => __( 'Payment window expired. The order has been cancelled.', 'binance-personal-pay' ),
				);

			case 'keep_pending':
			default:
				// Default behavior: Keep order pending to protect customer from delayed network/API confirmations.
				BPP_Logger::info( "Order #{$order_id} timeout reached; keeping order pending per configuration." );
				return array(
					'status'       => 'expired',
					'redirect_url' => '',
					'message'      => __( 'Payment window ended. We will continue checking in the background. If you already sent the payment, it will be processed shortly.', 'binance-personal-pay' ),
				);
		}
	}
}
