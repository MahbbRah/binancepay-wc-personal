# Binance Personal Pay for WooCommerce

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-96588A.svg?logo=woocommerce&logoColor=white)](https://woocommerce.com/)
[![HPOS Compatible](https://img.shields.io/badge/HPOS-Compatible-success.svg)](https://woocommerce.com/)

**Binance Personal Pay for WooCommerce** is an open-source payment gateway plugin that allows store owners to accept Binance Pay crypto payments directly into their **personal Binance accounts** using Binance's Pay transaction history API.

> **No Binance Pay Merchant account is required.**  
> Accept payments peer-to-peer into your personal Binance account with **zero intermediary gateway fees** or third-party payment SaaS dependencies.

---

> [!IMPORTANT]
> **Independent Open-Source Project Notice**  
> This plugin is an independent open-source project and is **not affiliated with or endorsed by Binance**.  
> The plugin depends on Binance's current API behavior and availability. Binance may change API permissions, endpoints, transaction-history behavior, or regional/account availability.

---

## Key Features

- **Personal Binance Accounts**: Works with standard personal Binance User IDs (UID / Pay ID).
- **Direct & Non-Custodial**: Funds transfer directly into your Binance account. No third-party middleman holds your funds.
- **Deterministic Payment References**: Uses formatted notes (`plan-{order_id}`) for automatic reconciliation.
- **Triple Hybrid Verification**:
  1. **Automatic Background Scanner**: Periodic checking via WooCommerce Action Scheduler (every ~60 seconds).
  2. **Manual "I've Completed Payment"**: Immediate server-side verification triggered by customer click.
  3. **Binance Order ID Fallback**: Seamless recovery if a customer forgot to enter the payment note.
- **High-Performance Order Storage (HPOS)**: Full support for both HPOS custom order tables and legacy posts storage.
- **Precision Monetary Math**: String/decimal arithmetic utility (`BPP_Money`) preventing floating-point rounding errors.
- **Local Transaction Audit Table**: Stores transaction history in `wp_bpp_transactions` with strict idempotency and duplicate payment protection.
- **Enterprise Security**:
  - Requires **Read-Only** Binance API permissions. Never requires trading or withdrawal rights.
  - Automatic redaction of API secrets and HMAC signatures in all logs, frontend scripts, REST, and AJAX responses.
  - Strict customer order ownership checks preventing Insecure Direct Object Reference (IDOR) attacks.
- **Admin Tools**:
  - Live **Test Connection** button on gateway settings.
  - Dedicated **Binance Payments** transaction list with status badges and raw payload viewer.
  - Manual **Scan Now** button.

---

## Payment Flow

```mermaid
flowchart TD
    A[Customer Checkout] -->|Selects Binance Pay| B[Pending WooCommerce Order]
    B --> C[Binance Pay Payment Screen]
    C -->|Shows exact amount, Binance ID & Note: plan-{order_id}| D[Customer sends payment in Binance App]
    D --> E[Binance Pay Transaction Created]
    E --> F{Reconciliation Engine}
    F -->|Background Action Scheduler| G[Validate Transaction]
    F -->|Customer clicks 'I've Completed Payment'| G
    F -->|Customer submits Binance Order ID fallback| G
    G --> H{Validation Checks}
    H -->|Incoming? Correct Receiver? Exact Amount? Matching Currency? Valid Window? Unused?| I[payment_complete]
    I --> J[Order Processing / Complete]
```

---

## Hybrid Verification Architecture

The plugin implements three complementary verification methods:

### 1. Automatic Background Verification (Primary)
WooCommerce Action Scheduler runs `bpp_scan_transactions` approximately once per minute. The scanner fetches recent transactions (default 15-minute lookback), ingests them into the local database, and reconciles pending orders matching:
- Payment note = `plan-{order_id}`
- Correct amount (decimal equality)
- Correct currency (USDT)
- Receiver equals configured merchant Binance ID
- Incoming payment (amount > 0)
- Within valid order payment window
- Transaction has not already been used

The customer does **not** need to keep their browser open. If they pay and close the tab, the background scanner detects the payment and marks the order complete.

### 2. Manual "I've Completed Payment" Button
The customer payment screen includes an **"I've Completed Payment"** button. Clicking this triggers an immediate server-side synchronization request to Binance rather than waiting for the next scheduler run.
- The server performs the exact same rigorous validation checks.
- If verified, the order completes immediately and the customer is redirected.
- If not yet detected, a friendly message informs the user that automatic checks will continue.

### 3. Binance Order ID Fallback
If a customer forgot to enter the payment note `plan-{order_id}` when sending funds:
1. Customer enters their 18-digit **Binance Pay Order ID** (e.g. `454903295241510912`) from their Binance app payment receipt.
2. The server queries the merchant's Binance transaction history.
3. If found, the server validates:
   - Incoming transaction direction
   - Receiver matches merchant Binance ID
   - Amount matches WooCommerce order
   - Currency matches WooCommerce order
   - Transaction time matches order window
   - Transaction is unique and unused
4. Once all checks pass, `payment_complete()` is invoked.

> [!NOTE]
> The Binance Order ID is solely a **lookup key**, never proof of payment on its own.

---

## Installation & Setup

### Requirements
- WordPress 6.0 or higher
- WooCommerce 7.0 or higher
- PHP 8.1 or higher (with `bcmath`, `curl`, and `openssl` extensions)

### 1. Plugin Installation
1. Clone or download this repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/open-source/woocommerce-binance-personal-pay.git binance-personal-pay
   ```
2. Activate the plugin via **WordPress Admin > Plugins**.

### 2. Binance API Setup Guide
1. Log in to your personal account on [Binance.com](https://www.binance.com/).
2. Navigate to your Profile icon > **API Management**.
3. Click **Create API** and choose **System generated**.
4. Set an API label (e.g. `WooCommerce Store`).
5. Complete 2FA verification.
6. **API Restrictions (Critical)**:
   - Check: `Enable Reading` (Read-only).
   - **UNCHECK**: `Enable Spot & Margin Trading`, `Enable Withdrawals`, `Enable Futures`.
   - *(Recommended)* If your hosting has a static IP address, select **Restrict access to trusted IPs only** and enter your server IP.
7. Find your **Binance ID (Pay ID / UID)**:
   - Open Binance App > Tap your Profile icon or go to **Binance Pay** to copy your numeric UID (e.g. `411204837`).

### 3. WooCommerce Configuration
1. In your WordPress admin, go to **WooCommerce > Settings > Payments > Binance Pay**.
2. Enter your:
   - **Binance API Key**
   - **Binance API Secret**
   - **Merchant Binance ID**
3. Click **[Test Binance Connection]**.
   - You should see:
     ```text
     ✓ Binance API connection successful
     ✓ Pay transaction API accessible
     ✓ Binance account verified
     ```
4. Configure payment timeout (default: 10 minutes) and timeout policy.
5. Check **Enable Binance Personal Pay** and click **Save changes**.

---

## Local Transaction Database Schema

The plugin maintains an optimized audit table (`wp_bpp_transactions`):

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | BIGINT UNSIGNED | Primary Key |
| `binance_transaction_id` | VARCHAR(64) | Unique Binance Pay Transaction ID |
| `binance_order_id` | VARCHAR(64) | Binance Pay Order ID |
| `woocommerce_order_id` | BIGINT UNSIGNED | Linked WooCommerce Order ID |
| `amount` | VARCHAR(32) | Normalized transaction amount |
| `currency` | VARCHAR(16) | Currency code (e.g. USDT) |
| `note` | VARCHAR(255) | Customer payment note |
| `transaction_time` | BIGINT UNSIGNED | Unix timestamp in milliseconds |
| `wallet_type` | VARCHAR(32) | Binance wallet type |
| `order_type` | VARCHAR(32) | Order type (e.g. C2C) |
| `payer_name` | VARCHAR(255) | Payer display name |
| `payer_binance_id` | VARCHAR(64) | Payer Binance ID / Counterparty ID |
| `receiver_binance_id` | VARCHAR(64) | Receiving Binance UID |
| `status` | VARCHAR(32) | `discovered`, `matched`, `processed`, `unmatched`, `amount_mismatch`, `note_mismatch`, `duplicate`, `ignored` |
| `matched_at` | DATETIME | Timestamp when order was matched |

---

## Automated Test Suite

A standalone PHP test suite is included in `tests/` that executes without requiring an active WordPress server:

```bash
php tests/run-tests.php
```

All 24 test cases verify:
- Arbitrary-precision decimal normalization and equality
- HMAC-SHA256 signature generation matching Binance requirements
- RecvWindow, timestamp, and query string formatting
- Masked secret security
- Real Binance transaction JSON payload parsing
- Deterministic reference generation (`plan-{order_id}`)
- Incoming direction detection (`amount > 0` and receiver matching)
- Note mismatches, amount mismatches, and currency mismatches
- Payment time window and clock-skew validation
- Duplicate transaction rejection and idempotency
- Automatic redaction of credentials in logger

---

## Security Architecture

1. **Zero Secret Leakage**: The Binance API Secret is never exposed to JavaScript, frontend HTML, REST responses, AJAX endpoints, customer emails, error messages, or logs.
2. **Read-Only Enforcement**: The plugin requires only read permissions (`GET /sapi/v1/pay/transactions`).
3. **No Third-Party Intermediaries**: Your credentials never leave your WordPress server. Communication is strictly direct between your server and `https://api.binance.com`.
4. **Access Control & Anti-IDOR**: Customer polling and payment endpoints strictly require WooCommerce order keys or authenticated user ownership.

---

## Building a Release Package

To create a clean installable ZIP file:

```bash
chmod +x bin/build-zip.sh
./bin/build-zip.sh
```

The resulting zip will be located at `dist/binance-personal-pay.zip`.

---

## License

This project is licensed under the terms of the GNU General Public License v2.0 or later ([GPL-2.0-or-later](LICENSE)).
