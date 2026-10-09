# Personal Payment Gateway for Binance on WooCommerce

Accept Binance Pay directly into your **personal Binance account**. No corporate registration. No merchant approval. No middleman fees. Fully automated order completion.

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![WooCommerce 7.0+](https://img.shields.io/badge/WooCommerce-7.0%2B-96588A.svg?logo=woocommerce&logoColor=white)](https://woocommerce.com/)
[![HPOS Compatible](https://img.shields.io/badge/HPOS-Compatible-success.svg)](https://woocommerce.com/)
[![Blocks Ready](https://img.shields.io/badge/WooCommerce%20Blocks-Supported-blueviolet.svg)](https://woocommerce.com/)
[![Tests Passing](https://img.shields.io/badge/Tests-28%2F28%20Passed-brightgreen.svg)](tests/)

---

## Why this exists

If you run a WooCommerce store and want to accept crypto via Binance Pay, you usually hit one of three walls:

1. **The Official Merchant Wall**: Binance Pay Merchant accounts require a registered company, certificate of incorporation, business bank accounts, and weeks of compliance paperwork. If you are a freelancer, solo dev, or based in an unsupported country, you cannot get one.
2. **The "Send a Screenshot" Trap**: You put your Binance ID on the checkout page, tell buyers to pay and email a screenshot. Then you spend your day matching transaction IDs on your phone, handling fake or reused screenshots, and making customers wait hours for their downloads or orders.
3. **Third-Party Gateways (CoinGate, NOWPayments, etc.)**: They take 1% to 3%+ per transaction, hold your funds until payout thresholds are met, and can freeze your balance whenever their compliance bots flag an account.

### The Fix

This plugin turns your regular personal Binance account into an automated payment gateway.

Customers pay straight to your Binance ID using a short reference note (`plan-{order_id}`). The plugin queries your Binance Pay transaction history via Binance's official read-only SAPI (`GET /sapi/v1/pay/transactions`), matches the incoming payment, and marks the WooCommerce order complete.

- **0% fees**: No middleman takes a cut. Money lands directly in your personal Binance funding wallet.
- **No merchant account needed**: Uses your regular personal Binance User ID (UID / Pay ID).
- **Automated**: Orders complete within seconds without manual verification.
- **Non-custodial on your server**: Funds never touch any intermediary server.

---

## Comparison

| | Official Binance Merchant | 3rd-Party Gateways | Manual Screenshot | **This Plugin** |
| :--- | :---: | :---: | :---: | :---: |
| **Requires Registered Company?** | Yes | Often | No | **No (Personal account)** |
| **Transaction Cut** | 0.5% – 1% | 1% – 3%+ | 0% | **0% (Free)** |
| **Automated Order Fulfillment?** | Yes | Yes | No (Manual checks) | **Yes** |
| **Custodial Risk?** | Low | High (Gateway holds balance) | None | **None (Direct to your wallet)** |
| **Fake Screenshot Vulnerability?** | None | None | Very high | **None (Binance API verifies)** |
| **Setup Time** | Weeks | Days | 5 mins | **5 mins** |

---

## How verification works

```mermaid
flowchart TD
    A[Customer on Checkout] -->|Selects Binance Pay| B[Pending Order Created]
    B --> C[Payment Screen: Shows Amount, Binance ID & Note: plan-{order_id}]
    C -->|Customer sends payment in Binance App| D[Payment hits your Binance account]
    D --> E{Verification Layer}
    E -->|Background Scanner every 60s| F[Query Binance SAPI]
    E -->|Customer clicks 'I've Completed Payment'| F
    E -->|Customer enters Binance Order ID fallback| F
    F --> G{Checks: Amount, Currency, Receiver UID, Note, Window, Uniqueness}
    G -->|Valid| H[Order Completed via payment_complete]
```

The plugin uses three verification methods so payments are never lost:

### 1. Background Scanner (Primary)
WooCommerce Action Scheduler runs every ~60 seconds in the background. It fetches recent transactions from Binance, checks pending orders, and marks matching orders paid. The buyer doesn't even have to keep their browser tab open.

### 2. "I've Completed Payment" Button
On the payment screen, the buyer can click "I've Completed Payment". This triggers an immediate server-side check with Binance so they don't have to wait for the next 60-second cron cycle.

### 3. Binance Order ID Fallback
If a customer forgot to enter the payment note (`plan-{order_id}`) when sending money from the Binance app:
- They grab the 18-digit **Order ID** from their Binance app receipt (e.g., `454903295241510912`).
- They paste it into the fallback field on the payment page.
- The server searches your Binance transaction history for that ID, verifies the incoming amount, currency, and recipient, and completes the order.

> *The Binance Order ID is strictly a lookup key. The server queries Binance directly and never marks an order paid without API confirmation.*

---

## Security

- **Read-Only API Keys**: You only need to give the key `Enable Reading` permission. Do **not** enable Spot, Margin, Futures, or Withdrawals. Even if someone gained access to your WordPress database, they cannot withdraw funds or execute trades.
- **Server IP Whitelist**: Binance allows you to lock API keys to your web server's static IP address. Highly recommended.
- **No Secret Leakage**: Your API Secret is stored encrypted in the WordPress database and is stripped from all JavaScript files, DOM outputs, AJAX responses, and WooCommerce debug logs.
- **Double-Spend Protection**: Every transaction processed is indexed in a local table (`wp_bpp_transactions`). A transaction can never be credited more than once.

---

## 5-Minute Setup

### 1. Install Plugin
Upload the `personal-payment-gateway-for-binance` folder to `/wp-content/plugins/` (or install via the zip in Releases) and activate it in WordPress Plugins.

### 2. Create a Read-Only API Key in Binance
1. Log in to [Binance.com](https://www.binance.com/).
2. Go to **Profile Icon > API Management**.
3. Click **Create API** (choose *System generated*).
4. Name it (e.g., `WooCommerce Store`).
5. Complete 2FA.
6. Under **API Restrictions**:
   - ✅ Keep **Enable Reading** checked.
   - ❌ Leave everything else unchecked (No trading, no withdrawals, no futures).
   - *(Recommended)* Select **Restrict access to trusted IPs only** and add your server's IP.
7. Save the **API Key** and **API Secret**.

### 3. Get your Binance Pay ID
Open the Binance mobile app, tap the top-left profile icon, and copy your numeric **UID** (or Pay ID).

### 4. Configure WooCommerce
1. Go to **WooCommerce > Settings > Payments > Binance Pay**.
2. Paste your **API Key**, **API Secret**, and **Merchant Binance ID**.
3. Click **Test Binance Connection**. You should see green checkmarks confirming API access and signature validity.
4. Enable the gateway and click **Save changes**.

---

## WooCommerce Blocks & Currency

- **WooCommerce Blocks Native**: Fully supports modern block checkouts (`<!-- wp:woocommerce/checkout -->`) and classic checkout shortcodes.
- **HPOS Compatible**: Declares full compatibility with High-Performance Order Storage (`custom_order_tables`).
- **USD Currency Enforcement**: In v0.1.0, orders are settled 1:1 against **USDT**. If your store uses a multi-currency switcher (like WOOCS), the Binance Pay option automatically displays when the customer checks out in USD and cleanly hides when they switch to local fiat (e.g., BDT), preventing currency calculation errors.

---

## Local Database Table

The plugin stores an audit trail in `wp_bpp_transactions` with indexes on transaction IDs and order IDs:

```sql
CREATE TABLE wp_bpp_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    binance_transaction_id VARCHAR(64) NOT NULL UNIQUE,
    binance_order_id VARCHAR(64) NULL,
    woocommerce_order_id BIGINT UNSIGNED NULL,
    amount VARCHAR(32) NOT NULL,
    currency VARCHAR(16) NOT NULL,
    note VARCHAR(255) NULL,
    transaction_time BIGINT UNSIGNED NOT NULL,
    wallet_type VARCHAR(32) NULL,
    order_type VARCHAR(32) NULL,
    payer_name VARCHAR(255) NULL,
    payer_binance_id VARCHAR(64) NULL,
    receiver_binance_id VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'discovered',
    matched_at DATETIME NULL,
    ...
);
```

---

## Running Tests

The test suite runs standalone without needing a WordPress or database server:

```bash
php tests/run-tests.php
```

Covers:
- Arbitrary-precision decimal math (no floating point errors)
- HMAC-SHA256 signature generation and timestamp handling
- Secret masking and log redaction
- Transaction payload parsing
- Note matching, amount tolerance, currency matching
- Action Scheduler reconciliation and duplicate-payment rejection
- Prefixed settings saving (`woocommerce_binance_pay_*`)

---

## Building Release ZIP

```bash
chmod +x bin/build-zip.sh
./bin/build-zip.sh
```

Generates a clean distribution zip in `dist/binance-personal-pay.zip`.

---

## FAQ

**Can this plugin touch my funds?**  
No. Your API key should be set to Read-Only. The plugin has no ability to move, trade, or withdraw funds.

**What currency do customers send?**  
USDT (Tether) into your personal Binance account.

**What if the customer sends the wrong amount?**  
The scanner will flag the transaction as `amount_mismatch` in your Binance Payments admin tab and will not mark the order complete.

**What if Binance API is slow or temporarily down?**  
Action Scheduler retries every minute. If an API call fails, pending orders stay pending until the connection restores.

---

## Disclaimer

This plugin is an independent open-source project and is **not affiliated with, endorsed by, or sponsored by Binance**. 

"Binance" and "Binance Pay" are trademarks of their respective owners. Users are responsible for complying with local tax laws and Binance's Terms of Use.

---

## License

GNU General Public License v2.0 or later ([GPL-2.0-or-later](LICENSE)).
