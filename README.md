# ⚡ Binance Personal Pay for WooCommerce

<p align="center">
  <img src="https://raw.githubusercontent.com/open-source/woocommerce-binance-personal-pay/main/frontend/images/binance-pay.svg" alt="Binance Pay Logo" width="220" />
</p>

<p align="center">
  <strong>Accept Binance Pay directly into your personal Binance account — Zero merchant fees, no corporate registration required, 100% automated peer-to-peer verification.</strong>
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg" alt="License: GPL v2+"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg?logo=php&logoColor=white" alt="PHP 8.1+"></a>
  <a href="https://woocommerce.com/"><img src="https://img.shields.io/badge/WooCommerce-7.0%2B-96588A.svg?logo=woocommerce&logoColor=white" alt="WooCommerce 7.0+"></a>
  <a href="https://woocommerce.com/"><img src="https://img.shields.io/badge/HPOS-Compatible-success.svg" alt="HPOS Compatible"></a>
  <a href="https://woocommerce.com/"><img src="https://img.shields.io/badge/WooCommerce%20Blocks-Native-blueviolet.svg" alt="WooCommerce Blocks Native"></a>
  <a href="tests/"><img src="https://img.shields.io/badge/Unit%20Tests-28%2F28%20Passed-brightgreen.svg" alt="28 Tests Passed"></a>
</p>

---

## 🪝 The Problem: Why is accepting crypto on WooCommerce so frustrating?

If you are an independent creator, freelancer, or solo entrepreneur running a WooCommerce store, you've likely hit one of these three brick walls:

### 1. 🧱 The "Binance Merchant Account" Wall
To accept Binance Pay officially via the Binance Merchant program, Binance requires:
- A registered corporate entity (certificate of incorporation).
- Business licenses, articles of association, and corporate tax documents.
- A commercial bank account registered under the business name.
- Long, rigorous compliance reviews that take weeks — and are **completely unavailable in dozens of countries**.

If you're a solopreneur, digital seller, or small business without a corporation, **you're locked out**.

### 2. 📸 The "Send Me a Screenshot" Nightmare
To bypass merchant accounts, thousands of store owners resort to manual peer-to-peer payments:
> *"Send 25 USDT to Binance ID `123456789`, take a screenshot, and email it to us."*

The result?
- ❌ **Wasted Time:** Store owners spend hours every day checking their phones and matching transactions.
- ❌ **Delayed Fulfillment:** Customers wait hours or days for their digital downloads, license keys, or physical orders.
- ❌ **Fraud & Scams:** Bad actors reuse old transaction screenshots or fake image receipts.
- ❌ **High Abandonment:** Clunky checkout flows kill sales conversion rates.

### 3. 💸 The "Third-Party Payment Gateway" Tax
Third-party crypto gateway plugins (CoinGate, NOWPayments, etc.) seem like an easy fix, but they come with heavy compromises:
- ❌ **High Fees:** They take **1% to 3%+** out of every single sale you make.
- ❌ **Custodial Risk:** They hold your funds in their wallets until payout thresholds are met.
- ❌ **Account Freezes:** Your hard-earned funds can be frozen without notice due to arbitrary third-party risk algorithms.

---

## 💡 The Solution: Binance Personal Pay for WooCommerce

**Binance Personal Pay** bridges the gap. It turns your **standard personal Binance account** into a fully automated, non-custodial WooCommerce payment gateway with **zero middleman fees**.

```text
Customer Pays (Binance App) ➔ Directly to Your Personal Binance ID ➔ Plugin Validates via SAPI ➔ Order Instantly Completed!
```

- ✅ **No Merchant Account Required:** Works directly with your standard personal Binance User ID (UID / Pay ID).
- ✅ **0% Middleman Fees:** 100% free and open-source. Funds go straight into your personal Binance account peer-to-peer.
- ✅ **Fully Automated Verification:** Reads your personal Binance Pay transaction history via Binance's official SAPI read-only endpoint (`GET /sapi/v1/pay/transactions`) and marks WooCommerce orders paid automatically.
- ✅ **Triple Hybrid Verification Engine:** Automated background scheduler + customer instant verification button + Binance Order ID fallback.
- ✅ **WooCommerce Blocks & HPOS Native:** Seamlessly compatible with modern block-based checkout (`cart_checkout_blocks`) and High-Performance Order Storage (`custom_order_tables`).
- ✅ **Enterprise Security:** Requires **Read-Only** Binance API permissions. Never requires trading, withdrawal, or futures access.

---

## 📊 Comparison: Why Binance Personal Pay Wins

| Feature | Official Binance Merchant | 3rd-Party Crypto Gateways | Manual Screenshot Method | **Binance Personal Pay (This Plugin)** |
| :--- | :---: | :---: | :---: | :---: |
| **Requires Registered Corporation?** | ⚠️ Yes (Strict) | ⚠️ Often Yes | ❌ No | **❌ No (Personal Account)** |
| **Available to Freelancers & Solo Creators?** | ❌ No | ⚠️ Varies | ✅ Yes | **✅ Yes (Worldwide)** |
| **Gateway Transaction Fee** | 0.5% – 1% | 1.0% – 3.0%+ | 0% | **0% (Completely Free)** |
| **Automated Order Processing?** | ✅ Yes | ✅ Yes | ❌ Manual (Hours) | **✅ Yes (Automated)** |
| **Custodial Risk?** | Low | ⚠️ High (Funds held) | None | **None (Direct to your Binance wallet)** |
| **Risk of Fake/Reused Screenshots?** | No | No | ⚠️ High Risk | **No (Binance SAPI is source of truth)** |
| **Setup Time** | Weeks (Review) | Days | Immediate | **5 Minutes** |

---

## 🔄 How It Works: The Hybrid Verification Flow

```mermaid
flowchart TD
    A[Customer Checkout] -->|Selects Binance Pay| B[Pending WooCommerce Order]
    B --> C[Dedicated Binance Pay Payment Screen]
    C -->|Shows exact USDT amount, Binance ID & Note: plan-{order_id}| D[Customer sends funds via Binance App]
    D --> E[Transaction appears in your personal Binance account]
    E --> F{Reconciliation Engine}
    F -->|1. Auto Background Scheduler| G[Validate Transaction against SAPI]
    F -->|2. Customer clicks 'I've Completed Payment'| G
    F -->|3. Customer submits Binance Order ID Fallback| G
    G --> H{Rigorous Validation}
    H -->|Incoming? Correct Receiver? Exact Amount? Correct Currency? Valid Window? Unused?| I[WooCommerce payment_complete]
    I --> J[Order Status: Processing / Completed]
```

### 1. Mechanism 1 — Automatic Background Verification (Primary)
The plugin schedules a background job using WooCommerce **Action Scheduler** (`bpp_scan_transactions`) running approximately once every 60 seconds:
- Queries Binance Pay recent transactions (`GET /sapi/v1/pay/transactions`).
- Ingests incoming transactions into local audit table `wp_bpp_transactions`.
- Finds pending orders and matches transactions where:
  - `note` = `plan-{order_id}`
  - `amount` equals order total (using exact arbitrary-precision math)
  - `currency` = `USDT`
  - `receiver_binance_id` matches your configured Binance ID
  - `amount > 0` (incoming payment)
  - Within valid payment time window
  - Transaction ID has never been used for any prior order
- Calls `$order->payment_complete($transaction_id)`. The customer can close their tab immediately after paying; their order will complete automatically!

### 2. Mechanism 2 — Instant "I've Completed Payment" Button
The customer payment screen features an **"I've Completed Payment"** button:
- Clicking triggers an immediate server-side verification request.
- The server checks Binance Pay transactions right away without waiting for the next cron cycle.
- If verified, the order completes instantly and redirects the customer to your store's Thank You / Order Received page.
- If not detected yet, friendly feedback informs the customer that background scanning is continuing.

### 3. Mechanism 3 — Binance Order ID Fallback
What if a customer forgot to enter the payment note `plan-{order_id}` when sending funds from the Binance app?
- The customer simply pastes their 18-digit **Binance Pay Order ID** (e.g. `454903295241510912`) from their Binance app payment receipt into the fallback input.
- The server queries your Binance transaction history for that specific `orderId`.
- The server performs the exact same rigorous validation (incoming direction, correct recipient, exact amount, currency, order timeframe, uniqueness).
- If valid, the order is completed immediately. The Binance Order ID acts purely as a lookup key—never trusted without server-side API verification!

---

## 🛡️ Enterprise Security Model

Your security is the top priority:

1. **Read-Only API Permissions Only:** The plugin strictly calls `GET /sapi/v1/pay/transactions`. It **never** requests or requires Spot Trading, Margin, Futures, or Withdrawal permissions.
2. **Server-Side Request Signing:** Requests are signed using HMAC-SHA256 (`timestamp`, `recvWindow`, query string) entirely on your WordPress server.
3. **Zero Secret Leakage:** The Binance API Secret is stored safely in WordPress options and is **strictly redacted** from:
   - Frontend HTML and DOM
   - JavaScript files and client-side objects
   - AJAX & REST API responses
   - WooCommerce debug logs and status reports
   - Customer and admin emails
4. **IP Access Restriction Support:** You can bind your Binance API Key directly to your WordPress web server's static IP inside Binance API Management for maximum protection.
5. **Strict Anti-IDOR Protection:** Payment status polling and fallback verification require valid WooCommerce order keys and customer access verification.
6. **Double-Spend & Replay Protection:** Transaction IDs are indexed and permanently recorded in `wp_bpp_transactions`. A transaction can never be credited more than once.

---

## 🚀 Installation & Setup Guide

### Requirements
- **WordPress:** 6.0 or higher
- **WooCommerce:** 7.0 or higher
- **PHP:** 8.1 or higher (with `bcmath`, `curl`, and `openssl` extensions enabled)

---

### Step 1: Install the Plugin
1. Download `binance-personal-pay.zip` from the [Releases](https://github.com/open-source/woocommerce-binance-personal-pay/releases) page (or clone the repository):
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/open-source/woocommerce-binance-personal-pay.git binance-personal-pay
   ```
2. Activate the plugin via **WordPress Admin > Plugins**.

---

### Step 2: Create a Read-Only API Key on Binance
1. Log into your personal account on [Binance.com](https://www.binance.com/).
2. Click your Profile icon in the top right > **API Management**.
3. Click **Create API** and select **System generated**.
4. Label it (e.g. `My WooCommerce Store`).
5. Complete your 2FA verification.
6. **Configure API Restrictions (CRITICAL):**
   - ✅ Check **Enable Reading** (Read-Only).
   - ❌ **UNCHECK** `Enable Spot & Margin Trading`.
   - ❌ **UNCHECK** `Enable Withdrawals`.
   - ❌ **UNCHECK** `Enable Futures`.
   - ❌ **UNCHECK** all other permissions.
   - *(Highly Recommended)* If your server has a static IP address, choose **Restrict access to trusted IPs only** and enter your server IP.
7. Copy your **API Key** and **API Secret** (Binance only displays the Secret once!).

---

### Step 3: Find Your Binance Pay ID / UID
1. Open the Binance Mobile App.
2. Tap your profile avatar in the top-left corner (or go to **Pay**).
3. Copy your numeric **User ID (UID)** or **Binance Pay ID** (e.g. `411204837`).

---

### Step 4: Configure WooCommerce
1. In your WordPress admin, go to:
   **WooCommerce > Settings > Payments > Binance Pay**
2. Fill in your credentials:
   - **Binance API Key**: Your personal API key.
   - **Binance API Secret**: Your personal API secret.
   - **Merchant Binance ID**: Your personal Binance UID / Pay ID where customers send funds.
3. Click **[Test Binance Connection]**:
   ```text
   ✓ Binance API connection successful
   ✓ Pay transaction API accessible
   ✓ Binance account verified
   ```
4. Check **Enable Binance Personal Pay**.
5. Set your preferred payment timeout (default: 10 minutes).
6. Click **Save changes**.

---

## 🛒 WooCommerce Blocks & Currency Support

### WooCommerce Blocks Native
The plugin includes first-class integration with modern WooCommerce Block Checkout (`<!-- wp:woocommerce/checkout -->`):
- Registered with the WooCommerce Blocks payment method registry (`AbstractPaymentMethodType`).
- Enqueues interactive React components with official Binance Pay branding and instant verification badge.
- Declares early HPOS (`custom_order_tables`) and Blocks (`cart_checkout_blocks`) compatibility before WooCommerce initialization.

### USD Currency Enforcement
- **Standard Crypto Peg:** In v0.1.0, payments are matched 1:1 against **USDT** (Tether), the global standard crypto dollar.
- **Storefront Currency Harmony:** If your store utilizes a currency switcher (such as `woocommerce-currency-switcher` / WOOCS) to support multiple local currencies:
  - When customers select **USD**, Binance Pay automatically appears on the checkout page.
  - When customers select other local currencies (e.g. BDT), local gateways appear while Binance Pay is cleanly hidden.

---

## 🗄️ Local Transaction Database Schema

The plugin maintains an internal ledger (`wp_bpp_transactions`) to ensure full auditability:

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Primary auto-incrementing record ID |
| `binance_transaction_id` | `VARCHAR(64)` | Unique Binance Pay Transaction ID (`P_...`) |
| `binance_order_id` | `VARCHAR(64)` | Binance Pay Order ID (`454903...`) |
| `woocommerce_order_id` | `BIGINT UNSIGNED` | Linked WooCommerce Order ID |
| `amount` | `VARCHAR(32)` | Normalized transaction amount |
| `currency` | `VARCHAR(16)` | Transaction currency (e.g. `USDT`) |
| `note` | `VARCHAR(255)` | Payment note from customer (`plan-{order_id}`) |
| `transaction_time` | `BIGINT UNSIGNED` | Unix timestamp in milliseconds |
| `wallet_type` | `VARCHAR(32)` | Wallet type (e.g. `1` for Funding wallet) |
| `order_type` | `VARCHAR(32)` | Order type (e.g. `C2C`) |
| `payer_name` | `VARCHAR(255)` | Customer Binance display name |
| `payer_binance_id` | `VARCHAR(64)` | Customer Binance ID / Counterparty ID |
| `receiver_binance_id` | `VARCHAR(64)` | Merchant Binance ID |
| `status` | `VARCHAR(32)` | `discovered`, `matched`, `processed`, `unmatched`, `duplicate` |
| `matched_at` | `DATETIME` | Timestamp when order was matched |

---

## 🧪 Automated Unit Test Suite

The repository includes a comprehensive, standalone PHP unit test suite that runs with zero external dependencies:

```bash
php tests/run-tests.php
```

```text
========================================================
 Binance Personal Pay for WooCommerce - Test Suite
========================================================

 [PASS] test_settings_save_with_prefixed_post_keys
 [PASS] test_settings_preserve_secret_when_masked_or_empty
 [PASS] test_settings_checkbox_omitted_on_form_submission
 [PASS] test_money_normalization
 [PASS] test_money_equality
 [PASS] test_money_compare
 [PASS] test_money_format
 [PASS] test_auth_hmac_signing
 [PASS] test_auth_prepare_signed_params
 [PASS] test_auth_secret_masking
 [PASS] test_auth_validation
 [PASS] test_binance_api_transaction_parsing
 [PASS] test_binance_api_invalid_record_handling
 [PASS] test_order_handler_reference
 [PASS] test_order_handler_initialize_and_expiry
 [PASS] test_order_handler_access_verification
 [PASS] test_matcher_valid_note_payment
 [PASS] test_matcher_wrong_note
 [PASS] test_matcher_wrong_amount
 [PASS] test_matcher_wrong_currency
 [PASS] test_matcher_wrong_receiver
 [PASS] test_matcher_fallback_binance_order_id
 [PASS] test_matcher_idempotency_duplicate
 [PASS] test_matcher_transaction_prior_to_order
 [PASS] test_matcher_zero_or_negative_amount
 [PASS] test_matcher_fallback_amount_mismatch
 [PASS] test_matcher_non_usd_order_rejected
 [PASS] test_logger_redaction

--------------------------------------------------------
 Summary: 28 Passed, 0 Failed (Total: 28)
========================================================
```

---

## 📦 Building a Release ZIP

To build a clean production archive ready for WordPress upload:

```bash
chmod +x bin/build-zip.sh
./bin/build-zip.sh
```

The installable package will be created at `dist/binance-personal-pay.zip`.

---

## ❓ Frequently Asked Questions (FAQ)

<details>
<summary><strong>Do I need a business license or company to use this?</strong></summary>
<p>No! This plugin is created specifically for personal Binance accounts. All you need is a verified personal Binance account and a personal API key.</p>
</details>

<details>
<summary><strong>Can this plugin withdraw or trade funds from my Binance account?</strong></summary>
<p>Never. The plugin only requires read-only permissions to query your payment history. You should explicitly disable trading and withdrawal permissions when creating your API key on Binance.</p>
</details>

<details>
<summary><strong>Are there any transaction fees?</strong></summary>
<p>No. The plugin is 100% free and open-source with zero gateway fees. Binance Pay transfers between personal users typically have zero transfer fees.</p>
</details>

<details>
<summary><strong>What happens if the customer sends money without the payment note?</strong></summary>
<p>The payment page includes a fallback tool where the customer can input their Binance Order ID. The server validates that transaction against your Binance history and marks the order paid if all parameters match.</p>
</details>

<details>
<summary><strong>Does this work with WooCommerce Cart & Checkout Blocks?</strong></summary>
<p>Yes! Binance Personal Pay provides full native integration for both classic checkout shortcodes and the modern WooCommerce Block Checkout.</p>
</details>

---

## ⚠️ Disclaimer

> This plugin is an independent open-source project and is **not affiliated with, authorized, maintained, or endorsed by Binance** or any of its affiliates.
> 
> "Binance" and "Binance Pay" are trademarks of their respective owners. This plugin interacts with publicly available personal account API endpoints provided by Binance. Store owners are responsible for ensuring compliance with local laws, regulations, and Binance Terms of Use.

---

## 📄 License

This project is open-source software licensed under the **GNU General Public License v2.0 or later** ([GPL-2.0-or-later](LICENSE)).
