<?php
/**
 * Customer payment page for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BPP_Payment_Page
 *
 * Renders the dedicated Binance Pay instructions, live countdown timer,
 * copy buttons, manual verification button, and Binance Order ID fallback.
 */
class BPP_Payment_Page {

	/**
	 * Hook initialization.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Hook into WooCommerce order receipt page.
		add_action( 'woocommerce_receipt_binance_pay', array( __CLASS__, 'render_receipt_page' ) );
		// Intercept standalone payment page requests if needed.
		add_action( 'template_redirect', array( __CLASS__, 'handle_standalone_payment_page' ) );
	}

	/**
	 * Get payment page URL for an order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string
	 */
	public static function get_payment_url( WC_Order $order ): string {
		return $order->get_checkout_payment_url( true );
	}

	/**
	 * Enqueue payment page assets (CSS & JS).
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return void
	 */
	public static function enqueue_assets( WC_Order $order ): void {
		$plugin_url = defined( 'BPP_PLUGIN_URL' ) ? BPP_PLUGIN_URL : plugins_url( '/', dirname( __FILE__ ) );
		$version    = defined( 'BPP_VERSION' ) ? BPP_VERSION : '0.1.0';

		wp_enqueue_style(
			'bpp-payment-page',
			$plugin_url . 'frontend/css/payment-page.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'bpp-payment-page',
			$plugin_url . 'frontend/js/payment-page.js',
			array( 'jquery' ),
			$version,
			true
		);

		$status_info = BPP_Payment_Status::get_status_info( $order );

		wp_localize_script(
			'bpp-payment-page',
			'bppData',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'bpp_payment_nonce' ),
				'orderId'            => $order->get_id(),
				'orderKey'           => $order->get_order_key(),
				'expiresAt'          => $status_info['expires_at'],
				'timeLeft'           => $status_info['time_left'],
				'pollInterval'       => 5000,
				'redirectUrl'        => $order->get_checkout_order_received_url(),
				'isPaid'             => $status_info['is_paid'],
				'enableManualVerify' => BPP_Settings::is_manual_verify_enabled(),
				'strings'            => array(
					'copied'           => __( 'Copied to clipboard!', 'binance-personal-pay' ),
					'copy'             => __( 'Copy', 'binance-personal-pay' ),
					'checking'         => __( 'Checking payment status...', 'binance-personal-pay' ),
					'verifying'        => __( 'Verifying with Binance...', 'binance-personal-pay' ),
					'notDetected'      => __( "Payment not detected yet. We'll continue checking automatically.", 'binance-personal-pay' ),
					'paymentSuccess'   => __( '✓ Payment verified! Redirecting to confirmation...', 'binance-personal-pay' ),
					'timeout'          => __( 'Payment window expired. Checking final status...', 'binance-personal-pay' ),
					'fallbackEmpty'    => __( 'Please enter your Binance Order ID.', 'binance-personal-pay' ),
					'fallbackFailed'   => __( 'Payment verification failed. Please check your Binance Order ID and try again.', 'binance-personal-pay' ),
					'unexpectedError'  => __( 'Network error. Please try again or wait for automatic verification.', 'binance-personal-pay' ),
				),
			)
		);
	}

	/**
	 * Render payment page on WooCommerce order receipt hook.
	 *
	 * @param int $order_id WooCommerce Order ID.
	 * @return void
	 */
	public static function render_receipt_page( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			echo '<div class="woocommerce-error">' . esc_html__( 'Invalid order.', 'binance-personal-pay' ) . '</div>';
			return;
		}

		$order_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : $order->get_order_key();
		if ( ! BPP_Order_Handler::verify_order_access( $order, $order_key ) ) {
			echo '<div class="woocommerce-error">' . esc_html__( 'Unauthorized access to this order.', 'binance-personal-pay' ) . '</div>';
			return;
		}

		if ( 'USD' !== strtoupper( $order->get_currency() ) ) {
			echo '<div class="woocommerce-error">' . esc_html__( 'Binance Pay is only available for orders placed in USD currency.', 'binance-personal-pay' ) . '</div>';
			return;
		}

		self::enqueue_assets( $order );
		self::render_payment_ui( $order );
	}

	/**
	 * Handle standalone payment page template redirect if accessed via custom query.
	 *
	 * @return void
	 */
	public static function handle_standalone_payment_page(): void {
		if ( ! isset( $_GET['bpp_pay'] ) || ! isset( $_GET['order_id'] ) ) {
			return;
		}

		$order_id  = absint( $_GET['order_id'] );
		$order_key = isset( $_GET['order_key'] ) ? sanitize_text_field( wp_unslash( $_GET['order_key'] ) ) : '';
		$order     = wc_get_order( $order_id );

		if ( ! $order || ! BPP_Order_Handler::verify_order_access( $order, $order_key ) ) {
			wp_die( esc_html__( 'Unauthorized order access.', 'binance-personal-pay' ), 403 );
		}

		if ( 'USD' !== strtoupper( $order->get_currency() ) ) {
			wp_die( esc_html__( 'Binance Pay is only available for orders placed in USD currency.', 'binance-personal-pay' ), 400 );
		}

		// Render within standard WordPress header/footer.
		get_header();
		echo '<div class="woocommerce bpp-payment-wrapper-container">';
		self::enqueue_assets( $order );
		self::render_payment_ui( $order );
		echo '</div>';
		get_footer();
		exit;
	}

	/**
	 * Output the HTML markup for the payment instructions card.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return void
	 */
	public static function render_payment_ui( WC_Order $order ): void {
		$order_id       = $order->get_id();
		$order_total    = $order->get_total();
		$currency       = BPP_Settings::get_currency();
		$merchant_id    = BPP_Settings::get_merchant_binance_id();
		$reference_note = BPP_Order_Handler::get_payment_reference( $order_id );
		$is_paid        = $order->is_paid() || in_array( $order->get_status(), array( 'processing', 'completed' ), true );
		$items          = $order->get_items();

		$item_names = array();
		foreach ( $items as $item ) {
			$item_names[] = $item->get_name() . ' × ' . $item->get_quantity();
		}
		$items_summary = ! empty( $item_names ) ? implode( ', ', $item_names ) : __( 'Order Items', 'binance-personal-pay' );
		?>
		<div class="bpp-payment-container" id="bpp-payment-container">
			<div class="bpp-payment-card">
				
				<!-- Header -->
				<div class="bpp-header">
					<div class="bpp-logo-badge">
						<span class="bpp-badge-icon">⚡</span>
						<span class="bpp-badge-text"><?php esc_html_e( 'Binance Pay', 'binance-personal-pay' ); ?></span>
					</div>
					<h2 class="bpp-title"><?php esc_html_e( 'Complete Binance Payment', 'binance-personal-pay' ); ?></h2>
					<p class="bpp-subtitle">
						<?php
						printf(
							/* translators: %s: Order ID */
							esc_html__( 'Order #%s', 'binance-personal-pay' ),
							esc_html( (string) $order_id )
						);
						?>
						&bull;
						<span class="bpp-items-summary"><?php echo esc_html( $items_summary ); ?></span>
					</p>
				</div>

				<!-- Amount Box -->
				<div class="bpp-amount-box">
					<span class="bpp-amount-label"><?php esc_html_e( 'Total Amount to Send', 'binance-personal-pay' ); ?></span>
					<div class="bpp-amount-val">
						<span class="bpp-amount-number"><?php echo esc_html( BPP_Money::normalize( $order_total, 2 ) ); ?></span>
						<span class="bpp-currency-badge"><?php echo esc_html( $currency ); ?></span>
					</div>
					<small class="bpp-amount-note"><?php esc_html_e( 'Send the exact amount to guarantee automatic verification.', 'binance-personal-pay' ); ?></small>
				</div>

				<!-- Recipient & Note Details -->
				<div class="bpp-details-grid">
					
					<!-- Send to Binance ID -->
					<div class="bpp-field-card">
						<div class="bpp-field-header">
							<span class="bpp-field-label"><?php esc_html_e( 'Send to Binance ID (UID / Pay ID):', 'binance-personal-pay' ); ?></span>
						</div>
						<div class="bpp-field-row">
							<input type="text" class="bpp-copy-input" id="bpp-binance-id-val" readonly value="<?php echo esc_attr( $merchant_id ); ?>">
							<button type="button" class="bpp-btn-copy" data-target="bpp-binance-id-val" title="<?php esc_attr_e( 'Copy Binance ID', 'binance-personal-pay' ); ?>">
								<span class="bpp-copy-icon">📋</span>
								<span class="bpp-copy-text"><?php esc_html_e( 'Copy ID', 'binance-personal-pay' ); ?></span>
							</button>
						</div>
					</div>

					<!-- Payment Note -->
					<div class="bpp-field-card bpp-field-highlight">
						<div class="bpp-field-header">
							<span class="bpp-field-label"><?php esc_html_e( 'Payment Note (Required for Auto-Detection):', 'binance-personal-pay' ); ?></span>
						</div>
						<div class="bpp-field-row">
							<input type="text" class="bpp-copy-input bpp-note-input" id="bpp-note-val" readonly value="<?php echo esc_attr( $reference_note ); ?>">
							<button type="button" class="bpp-btn-copy" data-target="bpp-note-val" title="<?php esc_attr_e( 'Copy Payment Note', 'binance-personal-pay' ); ?>">
								<span class="bpp-copy-icon">📋</span>
								<span class="bpp-copy-text"><?php esc_html_e( 'Copy Note', 'binance-personal-pay' ); ?></span>
							</button>
						</div>
					</div>

				</div>

				<!-- Instructions -->
				<div class="bpp-instructions-box">
					<h4 class="bpp-instructions-title"><?php esc_html_e( 'How to Pay via Binance App / Web:', 'binance-personal-pay' ); ?></h4>
					<ol class="bpp-steps-list">
						<li><?php esc_html_e( 'Open your Binance App or website and navigate to Binance Pay.', 'binance-personal-pay' ); ?></li>
						<li>
							<?php
							printf(
								/* translators: 1: Amount, 2: Currency */
								esc_html__( 'Choose "Send" and enter exactly %1$s %2$s.', 'binance-personal-pay' ),
								'<strong>' . esc_html( BPP_Money::normalize( $order_total, 2 ) ) . '</strong>',
								'<strong>' . esc_html( $currency ) . '</strong>'
							);
							?>
						</li>
						<li>
							<?php
							printf(
								/* translators: %s: Binance ID */
								esc_html__( 'Recipient Binance ID: %s', 'binance-personal-pay' ),
								'<code>' . esc_html( $merchant_id ) . '</code>'
							);
							?>
						</li>
						<li>
							<?php
							printf(
								/* translators: %s: Reference Note */
								esc_html__( 'Paste %s into the Payment Note / Description field.', 'binance-personal-pay' ),
								'<code>' . esc_html( $reference_note ) . '</code>'
							);
							?>
						</li>
						<li><?php esc_html_e( 'Confirm the transaction on Binance. Your payment will be verified automatically.', 'binance-personal-pay' ); ?></li>
					</ol>
				</div>

				<!-- Timer & Live Status Indicator -->
				<div class="bpp-status-section">
					<div class="bpp-timer-container">
						<span class="bpp-timer-label"><?php esc_html_e( 'Payment window closes in:', 'binance-personal-pay' ); ?></span>
						<span class="bpp-countdown-display" id="bpp-countdown">--:--</span>
					</div>

					<div class="bpp-pulse-indicator" id="bpp-status-indicator">
						<span class="bpp-dot-pulse"></span>
						<span class="bpp-status-msg" id="bpp-status-text">
							<?php echo $is_paid ? esc_html__( '✓ Payment verified!', 'binance-personal-pay' ) : esc_html__( 'Waiting for payment...', 'binance-personal-pay' ); ?>
						</span>
					</div>
				</div>

				<!-- Manual "I've Completed Payment" Button -->
				<?php if ( BPP_Settings::is_manual_verify_enabled() && ! $is_paid ) : ?>
					<div class="bpp-action-area">
						<button type="button" class="button bpp-btn-primary" id="bpp-btn-manual-verify">
							<span class="bpp-btn-icon">✓</span>
							<?php esc_html_e( "I've Completed Payment", 'binance-personal-pay' ); ?>
						</button>
						<p class="bpp-auto-hint">
							<small><?php esc_html_e( 'You do not have to click this button; payment is also detected automatically in the background.', 'binance-personal-pay' ); ?></small>
						</p>
					</div>
				<?php endif; ?>

				<!-- Feedback Alert Container -->
				<div class="bpp-alert" id="bpp-alert-box" style="display:none;"></div>

				<!-- Mechanism 3: Binance Order ID Fallback -->
				<div class="bpp-fallback-section">
					<button type="button" class="bpp-accordion-toggle" id="bpp-toggle-fallback">
						<span><?php esc_html_e( 'Forgot to add the payment note?', 'binance-personal-pay' ); ?></span>
						<span class="bpp-accordion-arrow">▼</span>
					</button>
					<div class="bpp-fallback-content" id="bpp-fallback-content" style="display:none;">
						<p class="bpp-fallback-desc">
							<?php esc_html_e( 'If you sent the payment but forgot to enter the payment note, enter your Binance Pay Order ID from your Binance transaction history below to verify your payment.', 'binance-personal-pay' ); ?>
						</p>
						<div class="bpp-fallback-form">
							<input type="text" class="bpp-fallback-input" id="bpp-fallback-order-id" placeholder="<?php esc_attr_e( 'e.g. 454903295241510912', 'binance-personal-pay' ); ?>" autocomplete="off">
							<button type="button" class="button bpp-btn-secondary" id="bpp-btn-verify-fallback">
								<?php esc_html_e( 'Verify Payment', 'binance-personal-pay' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Open Source & Disclaimer Notice -->
				<div class="bpp-disclaimer">
					<small><?php esc_html_e( 'This payment gateway is an independent open-source project and is not affiliated with or endorsed by Binance.', 'binance-personal-pay' ); ?></small>
				</div>

			</div>
		</div>
		<?php
	}
}
