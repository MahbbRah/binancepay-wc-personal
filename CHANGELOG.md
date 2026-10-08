# Changelog

All notable changes to **Binance Personal Pay for WooCommerce** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - Initial Release

### Added
- Direct Binance Pay payment gateway for WooCommerce store owners using **personal Binance accounts**.
- Zero intermediary gateway fees or third-party processor dependencies.
- Personal Binance API integration via authenticated `GET /sapi/v1/pay/transactions` endpoint using HMAC-SHA256 signatures.
- Dedicated customer payment page with live 10-minute countdown timer, clipboard copy actions, and real-time status indicators.
- Deterministic payment note format: `plan-{order_id}`.
- Hybrid verification architecture:
  - **Mechanism 1**: Automatic background scanning via WooCommerce Action Scheduler.
  - **Mechanism 2**: Manual "I've Completed Payment" server-side verification.
  - **Mechanism 3**: Binance Order ID fallback for customers who forgot to attach their payment note.
- Arbitrary-precision decimal money utility (`BPP_Money`) preventing float rounding errors.
- Local high-performance transaction storage table (`wp_bpp_transactions`) with idempotency and duplicate payment protection.
- Transaction validation verifying incoming direction, merchant receiver ID, expected currency, exact amount, and valid payment window.
- WooCommerce High-Performance Order Storage (HPOS) and legacy custom post order compatibility.
- WooCommerce Admin management screen under `WooCommerce -> Binance Payments` with manual **Scan Now** button and detailed transaction inspection.
- "Test Binance Connection" tool verifying credentials, signature creation, and read-only Pay API access.
- Security hardening:
  - Automatic redaction of API secrets and signatures in logs, frontend, REST, and AJAX responses.
  - Customer order authorization checks preventing IDOR vulnerabilities.
  - Rate limiting on manual synchronization triggers.
- Unit and integration test suite covering HMAC signing, currency precision, payment matching edge cases, and logging security.
