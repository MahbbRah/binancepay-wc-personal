<?php
/**
 * Action Scheduler manager for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BPP_Scheduler
 *
 * Manages recurring background transaction scans using WooCommerce Action Scheduler.
 */
class BPP_Scheduler {

	/**
	 * Recurring action hook name.
	 */
	public const ACTION_HOOK = 'bpp_scan_transactions';

	/**
	 * Action Scheduler group name.
	 */
	public const GROUP_NAME = 'binance-personal-pay';

	/**
	 * Initialize Action Scheduler listeners and schedule checks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( self::ACTION_HOOK, array( 'BPP_Transaction_Scanner', 'run_scheduled_scan' ) );
		add_action( 'init', array( __CLASS__, 'check_schedule' ) );
	}

	/**
	 * Verify that recurring job is properly scheduled if auto-scan is enabled.
	 *
	 * @return void
	 */
	public static function check_schedule(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		if ( BPP_Settings::is_auto_scan_enabled() ) {
			if ( ! as_has_scheduled_action( self::ACTION_HOOK, array(), self::GROUP_NAME ) ) {
				self::schedule_scanner();
			}
		} else {
			self::unschedule_scanner();
		}
	}

	/**
	 * Schedule the recurring background transaction scanner.
	 *
	 * @param int|null $interval_sec Optional interval in seconds.
	 * @return void
	 */
	public static function schedule_scanner( ?int $interval_sec = null ): void {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		self::unschedule_scanner();

		$interval = $interval_sec ?? BPP_Settings::get_scanner_interval();
		as_schedule_recurring_action(
			time() + 10,
			$interval,
			self::ACTION_HOOK,
			array(),
			self::GROUP_NAME
		);

		BPP_Logger::info( "Scheduled recurring transaction scan every {$interval} seconds." );
	}

	/**
	 * Unschedule all recurring background scanner actions.
	 *
	 * @return void
	 */
	public static function unschedule_scanner(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::ACTION_HOOK, array(), self::GROUP_NAME );
		BPP_Logger::debug( 'Unscheduled recurring transaction scans.' );
	}
}
