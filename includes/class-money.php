<?php
/**
 * Decimal and monetary utility for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BPP_Money
 *
 * Provides string/decimal-based monetary precision comparison without float inaccuracy.
 */
class BPP_Money {

	/**
	 * Default decimal scales for currencies.
	 *
	 * @var array<string, int>
	 */
	private static array $currency_scales = array(
		'USDT' => 8,
		'BUSD' => 8,
		'USDC' => 8,
		'BTC'  => 8,
		'ETH'  => 8,
		'BNB'  => 8,
		'USD'  => 2,
		'EUR'  => 2,
	);

	/**
	 * Get decimal scale for a given currency.
	 *
	 * @param string $currency Currency code.
	 * @return int
	 */
	public static function get_scale( string $currency = 'USDT' ): int {
		$currency = strtoupper( trim( $currency ) );
		return self::$currency_scales[ $currency ] ?? 8;
	}

	/**
	 * Normalize a numeric string representation to a clean canonical decimal.
	 * Trims whitespace, removes comma separators, handles scientific notation if needed,
	 * and strips unnecessary trailing zeroes after the decimal point.
	 *
	 * @param string|int|float $amount Amount to normalize.
	 * @param int|null         $scale  Optional fixed decimal scale.
	 * @return string
	 */
	public static function normalize( string|int|float $amount, ?int $scale = null ): string {
		$val = trim( (string) $amount );

		// Remove thousands separators.
		$val = str_replace( array( ',', ' ' ), '', $val );

		if ( '' === $val || ! is_numeric( $val ) ) {
			return '0';
		}

		// Handle scientific notation (e.g., 1e-4) if present.
		if ( false !== stripos( $val, 'e' ) ) {
			$val = sprintf( '%.8f', (float) $val );
		}

		$parts = explode( '.', $val );
		$integer_part = ltrim( $parts[0], '0' );
		if ( '' === $integer_part || '-' === $integer_part ) {
			$integer_part = ( str_starts_with( $parts[0], '-' ) ? '-0' : '0' );
		}

		$fraction_part = $parts[1] ?? '';

		if ( null !== $scale ) {
			if ( $scale <= 0 ) {
				return $integer_part;
			}
			$fraction_part = str_pad( substr( $fraction_part, 0, $scale ), $scale, '0' );
			return $integer_part . '.' . $fraction_part;
		}

		// Trim trailing zeroes for canonical representation.
		$fraction_part = rtrim( $fraction_part, '0' );
		if ( '' === $fraction_part ) {
			return $integer_part;
		}

		return $integer_part . '.' . $fraction_part;
	}

	/**
	 * Compare two monetary amounts using arbitrary-precision decimal logic.
	 *
	 * Returns:
	 *   -1 if $a < $b
	 *    0 if $a == $b
	 *    1 if $a > $b
	 *
	 * @param string|int|float $a First amount.
	 * @param string|int|float $b Second amount.
	 * @param int|null         $scale Scale precision to compare. Defaults to 8.
	 * @return int
	 */
	public static function compare( string|int|float $a, string|int|float $b, ?int $scale = 8 ): int {
		$scale = $scale ?? 8;
		$norm_a = self::normalize( $a, $scale );
		$norm_b = self::normalize( $b, $scale );

		if ( function_exists( 'bccomp' ) ) {
			return bccomp( $norm_a, $norm_b, $scale );
		}

		// Fallback comparison without bcmath.
		return self::fallback_compare( $norm_a, $norm_b );
	}

	/**
	 * Check if two amounts are equal within currency precision.
	 *
	 * @param string|int|float $a First amount.
	 * @param string|int|float $b Second amount.
	 * @param string           $currency Currency code (used for scale resolution).
	 * @return bool
	 */
	public static function is_equal( string|int|float $a, string|int|float $b, string $currency = 'USDT' ): bool {
		$scale = self::get_scale( $currency );
		return 0 === self::compare( $a, $b, $scale );
	}

	/**
	 * Format a monetary amount for display.
	 *
	 * @param string|int|float $amount Amount to format.
	 * @param string           $currency Currency symbol or code.
	 * @param int              $decimals Number of decimals (default 2 for fiat/stablecoins, 8 for crypto).
	 * @return string
	 */
	public static function format( string|int|float $amount, string $currency = 'USDT', int $decimals = 2 ): string {
		$norm = self::normalize( $amount, $decimals );
		return number_format( (float) $norm, $decimals, '.', '' ) . ' ' . strtoupper( $currency );
	}

	/**
	 * Pure PHP decimal comparison fallback if bcmath is not compiled.
	 *
	 * @param string $a Normalized decimal string.
	 * @param string $b Normalized decimal string.
	 * @return int
	 */
	private static function fallback_compare( string $a, string $b ): int {
		if ( $a === $b ) {
			return 0;
		}

		$a_is_neg = str_starts_with( $a, '-' );
		$b_is_neg = str_starts_with( $b, '-' );

		if ( $a_is_neg && ! $b_is_neg ) {
			return -1;
		}
		if ( ! $a_is_neg && $b_is_neg ) {
			return 1;
		}

		$a_clean = ltrim( $a, '-' );
		$b_clean = ltrim( $b, '-' );

		$parts_a = explode( '.', $a_clean );
		$parts_b = explode( '.', $b_clean );

		$int_a = $parts_a[0];
		$int_b = $parts_b[0];

		$len_a = strlen( $int_a );
		$len_b = strlen( $int_b );

		if ( $len_a > $len_b ) {
			$res = 1;
		} elseif ( $len_a < $len_b ) {
			$res = -1;
		} else {
			$int_cmp = strcmp( $int_a, $int_b );
			if ( 0 !== $int_cmp ) {
				$res = ( $int_cmp > 0 ) ? 1 : -1;
			} else {
				$dec_a = $parts_a[1] ?? '';
				$dec_b = $parts_b[1] ?? '';
				$max_len = max( strlen( $dec_a ), strlen( $dec_b ) );
				$dec_a = str_pad( $dec_a, $max_len, '0' );
				$dec_b = str_pad( $dec_b, $max_len, '0' );
				$dec_cmp = strcmp( $dec_a, $dec_b );
				$res = ( 0 === $dec_cmp ) ? 0 : ( ( $dec_cmp > 0 ) ? 1 : -1 );
			}
		}

		return $a_is_neg ? -$res : $res;
	}
}
