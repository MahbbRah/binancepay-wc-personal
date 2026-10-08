<?php
/**
 * Admin transactions view for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$table_name = BPP_Schema::get_table_name();

// Handle search and filtering.
$status_filter = isset( $_GET['status_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['status_filter'] ) ) : '';
$search_query  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged         = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$per_page      = 20;
$offset        = ( $paged - 1 ) * $per_page;

$where_clauses = array( '1=1' );
$params        = array();

if ( ! empty( $status_filter ) ) {
	$where_clauses[] = 'status = %s';
	$params[]        = $status_filter;
}

if ( ! empty( $search_query ) ) {
	$where_clauses[] = '(binance_transaction_id LIKE %s OR binance_order_id LIKE %s OR note LIKE %s OR woocommerce_order_id = %d)';
	$like            = '%' . $wpdb->esc_like( $search_query ) . '%';
	$params[]        = $like;
	$params[]        = $like;
	$params[]        = $like;
	$params[]        = absint( $search_query );
}

$where_sql = implode( ' AND ', $where_clauses );

// Total count.
$count_query = "SELECT COUNT(id) FROM {$table_name} WHERE {$where_sql}";
if ( ! empty( $params ) ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$total_items = (int) $wpdb->get_var( $wpdb->prepare( $count_query, ...$params ) );
} else {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$total_items = (int) $wpdb->get_var( $count_query );
}

$total_pages = ceil( $total_items / $per_page );

// Query rows.
$query = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
$query_params = array_merge( $params, array( $per_page, $offset ) );
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$rows = $wpdb->get_results( $wpdb->prepare( $query, ...$query_params ), ARRAY_A );
?>

<div class="wrap bpp-admin-wrap">
	<h1 class="wp-heading-inline">
		⚡ <?php esc_html_e( 'Binance Pay Transactions', 'binance-personal-pay' ); ?>
	</h1>

	<!-- Scan Now Action Button -->
	<button type="button" class="page-title-action button-primary" id="bpp-btn-scan-now">
		🔄 <?php esc_html_e( 'Scan Now', 'binance-personal-pay' ); ?>
	</button>
	<span class="spinner" id="bpp-scan-spinner" style="float:none; margin-left: 8px;"></span>

	<hr class="wp-header-end">

	<!-- Notice Box for Scan Results -->
	<div id="bpp-scan-notice" class="notice notice-info is-dismissible" style="display:none; margin-top:15px;">
		<p id="bpp-scan-notice-text"></p>
	</div>

	<!-- Filter / Search Form -->
	<form method="get" class="bpp-filter-bar" style="margin: 15px 0;">
		<input type="hidden" name="page" value="binance-payments">

		<select name="status_filter">
			<option value=""><?php esc_html_e( 'All Statuses', 'binance-personal-pay' ); ?></option>
			<option value="processed" <?php selected( $status_filter, 'processed' ); ?>><?php esc_html_e( 'Processed / Paid', 'binance-personal-pay' ); ?></option>
			<option value="matched" <?php selected( $status_filter, 'matched' ); ?>><?php esc_html_e( 'Matched', 'binance-personal-pay' ); ?></option>
			<option value="discovered" <?php selected( $status_filter, 'discovered' ); ?>><?php esc_html_e( 'Discovered', 'binance-personal-pay' ); ?></option>
			<option value="unmatched" <?php selected( $status_filter, 'unmatched' ); ?>><?php esc_html_e( 'Unmatched', 'binance-personal-pay' ); ?></option>
			<option value="amount_mismatch" <?php selected( $status_filter, 'amount_mismatch' ); ?>><?php esc_html_e( 'Amount Mismatch', 'binance-personal-pay' ); ?></option>
			<option value="note_mismatch" <?php selected( $status_filter, 'note_mismatch' ); ?>><?php esc_html_e( 'Note Mismatch', 'binance-personal-pay' ); ?></option>
			<option value="duplicate" <?php selected( $status_filter, 'duplicate' ); ?>><?php esc_html_e( 'Duplicate', 'binance-personal-pay' ); ?></option>
			<option value="ignored" <?php selected( $status_filter, 'ignored' ); ?>><?php esc_html_e( 'Ignored', 'binance-personal-pay' ); ?></option>
		</select>

		<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search Order, Note, or Tx ID...', 'binance-personal-pay' ); ?>" style="width: 260px;">

		<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'binance-personal-pay' ); ?>">

		<?php if ( ! empty( $status_filter ) || ! empty( $search_query ) ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=binance-payments' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'binance-personal-pay' ); ?></a>
		<?php endif; ?>

		<span class="displaying-num" style="float:right; line-height:30px;">
			<?php
			printf(
				/* translators: %s: Total transactions count */
				esc_html__( '%s transactions found', 'binance-personal-pay' ),
				number_format_i18n( $total_items )
			);
			?>
		</span>
	</form>

	<!-- Transactions Table -->
	<table class="wp-list-table widefat fixed striped table-view-list bpp-table">
		<thead>
			<tr>
				<th style="width: 80px;"><?php esc_html_e( 'WC Order', 'binance-personal-pay' ); ?></th>
				<th style="width: 120px;"><?php esc_html_e( 'Amount', 'binance-personal-pay' ); ?></th>
				<th><?php esc_html_e( 'Payment Note', 'binance-personal-pay' ); ?></th>
				<th><?php esc_html_e( 'Binance Order ID', 'binance-personal-pay' ); ?></th>
				<th><?php esc_html_e( 'Binance Tx ID', 'binance-personal-pay' ); ?></th>
				<th style="width: 140px;"><?php esc_html_e( 'Transaction Time', 'binance-personal-pay' ); ?></th>
				<th style="width: 110px;"><?php esc_html_e( 'Status', 'binance-personal-pay' ); ?></th>
				<th style="width: 130px;"><?php esc_html_e( 'Matched At', 'binance-personal-pay' ); ?></th>
				<th style="width: 70px;"><?php esc_html_e( 'Details', 'binance-personal-pay' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr>
					<td colspan="9" style="text-align:center; padding: 20px;">
						<?php esc_html_e( 'No Binance Pay transactions recorded yet.', 'binance-personal-pay' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$order_id = absint( $row['woocommerce_order_id'] ?? 0 );
					$order_edit_url = '';
					if ( $order_id > 0 ) {
						$order = wc_get_order( $order_id );
						if ( $order ) {
							$order_edit_url = $order->get_edit_order_url();
						}
					}
					$tx_time = (int) ( $row['transaction_time'] / 1000 );
					$status  = $row['status'];
					?>
					<tr>
						<td>
							<?php if ( $order_edit_url ) : ?>
								<a href="<?php echo esc_url( $order_edit_url ); ?>" style="font-weight:600;">#<?php echo esc_html( (string) $order_id ); ?></a>
							<?php elseif ( $order_id > 0 ) : ?>
								#<?php echo esc_html( (string) $order_id ); ?>
							<?php else : ?>
								<span style="color:#94a3b8;">&mdash;</span>
							<?php endif; ?>
						</td>
						<td>
							<strong><?php echo esc_html( $row['amount'] ); ?></strong>
							<span style="color:#64748b; font-size:12px;"><?php echo esc_html( $row['currency'] ); ?></span>
						</td>
						<td>
							<?php if ( ! empty( $row['note'] ) ) : ?>
								<code><?php echo esc_html( $row['note'] ); ?></code>
							<?php else : ?>
								<span style="color:#94a3b8; font-style:italic;"><?php esc_html_e( '(None)', 'binance-personal-pay' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<code style="font-size:11px;"><?php echo esc_html( $row['binance_order_id'] ); ?></code>
						</td>
						<td>
							<code style="font-size:11px;"><?php echo esc_html( $row['binance_transaction_id'] ); ?></code>
						</td>
						<td>
							<?php echo esc_html( $tx_time > 0 ? wp_date( 'Y-m-d H:i:s', $tx_time ) : '&mdash;' ); ?>
						</td>
						<td>
							<span class="bpp-badge bpp-badge-<?php echo esc_attr( $status ); ?>">
								<?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?>
							</span>
						</td>
						<td>
							<?php echo esc_html( $row['matched_at'] ?: '&mdash;' ); ?>
						</td>
						<td>
							<button type="button" class="button button-small bpp-view-details" data-json="<?php echo esc_attr( $row['raw_response'] ?: '{}' ); ?>">
								<?php esc_html_e( 'View', 'binance-personal-pay' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav" style="margin-top: 15px;">
			<div class="tablenav-pages">
				<?php
				echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
						'total'     => $total_pages,
						'current'   => $paged,
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Raw Detail Modal -->
	<div id="bpp-modal" class="bpp-modal-backdrop" style="display:none;">
		<div class="bpp-modal-content">
			<div class="bpp-modal-header">
				<h3><?php esc_html_e( 'Binance Pay Transaction Details', 'binance-personal-pay' ); ?></h3>
				<span class="bpp-modal-close">&times;</span>
			</div>
			<div class="bpp-modal-body">
				<pre id="bpp-modal-json" style="background:#0f172a; color:#f8fafc; padding:15px; border-radius:6px; overflow:auto; max-height:400px; font-size:12px;"></pre>
			</div>
		</div>
	</div>

</div>
