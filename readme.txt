=== Personal Payment Gateway for Binance on WooCommerce ===
Contributors: opensource
Tags: woocommerce, binance, binance pay, crypto, payment gateway, usdt, p2p
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Binance Pay payments directly into your personal Binance account — No merchant account, zero middleman fees, 100% automated peer-to-peer verification.

== Description ==

**Personal Payment Gateway for Binance on WooCommerce** turns your personal Binance account into an automated, zero-fee crypto payment gateway for WooCommerce.

### The Problem
* **The Merchant Wall:** Official Binance Pay Merchant accounts require registered corporations, business licenses, bank accounts, and lengthy compliance reviews that exclude solo entrepreneurs and freelancers.
* **The Manual Screenshot Nightmare:** Store owners asking customers to email screenshots of payments end up wasting hours verifying transactions, suffering fraud from forged receipts, and causing delayed orders.
* **Third-Party Fees:** Gateways like CoinGate and NOWPayments charge 1%–3%+ transaction fees and hold your funds in custodial wallets.

### The Solution: Personal Payment Gateway for Binance
* **Personal Binance Accounts:** Accept payments directly to your personal Binance User ID (UID / Pay ID).
* **0% Middleman Fees:** Funds settle immediately into your personal Binance wallet peer-to-peer.
* **Automated Order Processing:** The plugin queries your Binance Pay transaction history via Binance's official SAPI read-only endpoint (`GET /sapi/v1/pay/transactions`) and marks orders paid automatically.
* **Triple Hybrid Verification:** Automatic background scan (Action Scheduler), instant customer check button, and Binance Order ID fallback.
* **WooCommerce Blocks & HPOS Native:** Compatible with modern Block Checkout (`cart_checkout_blocks`) and High-Performance Order Storage (`custom_order_tables`).
* **Enterprise Security:** Requires strictly Read-Only API permissions. No trading or withdrawal permissions are ever needed.

*Notice: This plugin is an independent open-source project and is not affiliated with or endorsed by Binance.*

== Installation ==

1. Upload the `personal-payment-gateway-for-binance` folder to `/wp-content/plugins/` or install via the WordPress Plugins menu.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **WooCommerce > Settings > Payments > Binance Pay**.
4. Enter your personal Binance **API Key**, **API Secret**, and **Binance ID**. (Ensure your API Key has ONLY Read-Only permissions).
5. Click **Test Binance Connection** to verify your setup.
6. Check **Enable Binance Personal Pay** and save changes.

== Frequently Asked Questions ==

= Do I need a Binance Merchant account? =
No! This plugin is specifically designed to work with personal Binance accounts without corporate registration.

= Is my Binance API Secret safe? =
Yes. Your API Secret is stored only in your WordPress database and is strictly redacted from browser scripts, public REST/AJAX endpoints, error messages, and logs.

= What permissions does the API Key require? =
Enable ONLY Read-Only permissions. You should explicitly disable Spot Trading, Margin, Withdrawals, and Futures.

= Does this support WooCommerce Checkout Blocks? =
Yes! Native support for both classic checkout shortcodes and modern WooCommerce Block Checkout is included out of the box.

== Changelog ==

= 0.1.0 =
* Initial public release with hybrid verification architecture, WooCommerce Blocks integration, HPOS compatibility, and automated Action Scheduler background scanner.
