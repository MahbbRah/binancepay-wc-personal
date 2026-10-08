=== Binance Personal Pay for WooCommerce ===
Contributors: opensource
Tags: woocommerce, binance, binance pay, crypto, payment gateway, usdt
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Binance Pay payments directly into your personal Binance account with automated transaction verification and zero gateway fees.

== Description ==

**Binance Personal Pay for WooCommerce** allows store owners to accept Binance Pay crypto payments directly into their **personal Binance accounts** without requiring a Binance Pay Merchant account or third-party payment SaaS.

*Notice: This plugin is an independent open-source project and is not affiliated with or endorsed by Binance.*

### Why Binance Personal Pay?
* **No Merchant Account Required:** Use your standard personal Binance account (UID / Pay ID).
* **Zero Middleman Fees:** Keep 100% of your earnings; payments transfer directly peer-to-peer into your Binance account.
* **Non-Custodial on Server:** Your customer funds never pass through our servers or any intermediary.
* **Automated Verification:** Seamlessly reads your Binance Pay transaction history to detect matching payments.
* **HPOS Compatible:** Fully compatible with WooCommerce High-Performance Order Storage.

### Core Payment Flow
1. Customer selects Binance Pay at WooCommerce checkout.
2. Order is created with status **Pending Payment**.
3. Customer arrives at the dedicated Binance payment page displaying the exact amount, your Binance ID, and a unique payment note (`plan-{order_id}`).
4. Customer sends the payment from their Binance app or web.
5. The plugin detects the incoming transaction, verifies amount, currency, receiver, and note, and marks the order as paid via standard WooCommerce `payment_complete()`.

### Triple Hybrid Verification
1. **Automatic Background Verification:** Uses WooCommerce Action Scheduler to poll recent Binance transactions.
2. **Manual "I've Completed Payment" Button:** Immediate server-side check without waiting for the next cron cycle.
3. **Binance Order ID Fallback:** If the customer forgot to include the payment note, they can enter their Binance Pay Order ID to verify their transaction.

== Installation ==

1. Upload the `binance-personal-pay` folder to `/wp-content/plugins/` or install via the WordPress Plugins menu.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **WooCommerce > Settings > Payments > Binance Pay**.
4. Enter your personal Binance **API Key**, **API Secret**, and **Binance ID**. Ensure your API Key only has **Read-Only** permissions.
5. Click **Test Binance Connection** to verify your setup.
6. Enable the gateway and save changes.

== Frequently Asked Questions ==

= Do I need a Binance Merchant account? =
No! This plugin is specifically designed to work with personal Binance accounts.

= Is my Binance API Secret safe? =
Yes. Your API Secret is stored only in your WordPress database and is never exposed to browser scripts, public REST/AJAX endpoints, error messages, or logs.

= What permissions does the API Key require? =
Enable ONLY Read-Only permissions. You should explicitly disable Trading, Withdrawals, Futures, and Margin permissions.

= What currency is supported? =
Version 0.1.0 focuses on USDT. Future updates will add multi-currency selection.

== Screenshots ==

1. Customer dedicated payment screen with countdown timer and one-click copy buttons.
2. WooCommerce Admin Binance Payments transaction overview and live "Scan Now" tool.
3. Gateway settings and "Test Binance Connection" tool.

== Changelog ==

= 0.1.0 =
* Initial public release with hybrid verification architecture and HPOS compatibility.
