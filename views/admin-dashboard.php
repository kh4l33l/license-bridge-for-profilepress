<?php

defined( 'ABSPATH' ) || exit;

$page_slug   = 'lbfp-license-dashboard';
$base_url    = admin_url( 'admin.php?page=' . $page_slug );
$refresh_url = wp_nonce_url( admin_url( 'admin-post.php?action=lbfp_refresh_dashboard' ), 'lbfp_refresh_dashboard' );

$statuses = [
	''        => __( 'All statuses', 'license-bridge-for-profilepress' ),
	'active'  => __( 'Active', 'license-bridge-for-profilepress' ),
	'expired' => __( 'Expired', 'license-bridge-for-profilepress' ),
	'pending' => __( 'Pending', 'license-bridge-for-profilepress' ),
	'blocked' => __( 'Blocked', 'license-bridge-for-profilepress' ),
	'missing' => __( 'Not in SLM', 'license-bridge-for-profilepress' ),
];

$mask_key = static function ( string $key ): string {
	$len = strlen( $key );
	if ( $len <= 4 ) {
		return $key;
	}
	return str_repeat( '•', min( 12, $len - 4 ) ) . substr( $key, -4 );
};

$cards = [
	[ 'label' => __( 'Licenses', 'license-bridge-for-profilepress' ),       'value' => (int) $stats['licenses'] ],
	[ 'label' => __( 'Active', 'license-bridge-for-profilepress' ),         'value' => (int) $stats['active'],   'tone' => 'ok' ],
	[ 'label' => __( 'Expired', 'license-bridge-for-profilepress' ),        'value' => (int) $stats['expired'],  'tone' => 'bad' ],
	[ 'label' => __( 'Pro installs', 'license-bridge-for-profilepress' ),   'value' => (int) $stats['installs'] ],
	[ 'label' => __( 'Seats used', 'license-bridge-for-profilepress' ),     'value' => sprintf( '%d / %d', (int) $stats['installs'], (int) $stats['seats'] ) ],
	[ 'label' => __( 'At capacity', 'license-bridge-for-profilepress' ),    'value' => (int) $stats['at_capacity'], 'tone' => $stats['at_capacity'] > 0 ? 'warn' : '' ],
];
?>
<div class="wrap lbfp-dashboard">
	<h1 style="display:flex;align-items:center;gap:12px;">
		<?php esc_html_e( 'License Dashboard', 'license-bridge-for-profilepress' ); ?>
		<a href="<?php echo esc_url( $refresh_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Refresh', 'license-bridge-for-profilepress' ); ?></a>
	</h1>
	<p class="description">
		<?php esc_html_e( 'Every license provisioned through the bridge, with live activation status and the customer sites the plugin is installed on. Data is cached for ~2 minutes; use Refresh for an immediate re-read.', 'license-bridge-for-profilepress' ); ?>
	</p>

	<?php if ( ! $available ) : ?>
		<div class="notice notice-error" style="margin-top:16px;">
			<p><?php esc_html_e( 'The Software License Manager tables could not be found. Make sure Software License Manager is active on this site.', 'license-bridge-for-profilepress' ); ?></p>
		</div>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<div class="lbfp-cards">
		<?php foreach ( $cards as $card ) : ?>
			<div class="lbfp-card lbfp-tone-<?php echo esc_attr( $card['tone'] ?? '' ); ?>">
				<div class="lbfp-card-value"><?php echo esc_html( (string) $card['value'] ); ?></div>
				<div class="lbfp-card-label"><?php echo esc_html( $card['label'] ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>

	<form method="get" class="lbfp-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( $page_slug ); ?>" />
		<select name="status">
			<?php foreach ( $statuses as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status_filter, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<input
			type="search"
			name="s"
			value="<?php echo esc_attr( $search ); ?>"
			placeholder="<?php esc_attr_e( 'Search customer, email, domain or key…', 'license-bridge-for-profilepress' ); ?>"
			class="regular-text"
		/>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'license-bridge-for-profilepress' ); ?></button>
		<?php if ( '' !== $status_filter || '' !== $search ) : ?>
			<a href="<?php echo esc_url( $base_url ); ?>" class="button-link"><?php esc_html_e( 'Clear', 'license-bridge-for-profilepress' ); ?></a>
		<?php endif; ?>
		<span class="lbfp-result-count">
			<?php
			printf(
				/* translators: 1: filtered count, 2: total count */
				esc_html__( 'Showing %1$d of %2$d', 'license-bridge-for-profilepress' ),
				count( $filtered ),
				count( $rows )
			);
			?>
		</span>
	</form>

	<table class="wp-list-table widefat fixed striped lbfp-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Customer', 'license-bridge-for-profilepress' ); ?></th>
				<th><?php esc_html_e( 'Plan', 'license-bridge-for-profilepress' ); ?></th>
				<th><?php esc_html_e( 'License key', 'license-bridge-for-profilepress' ); ?></th>
				<th><?php esc_html_e( 'Status', 'license-bridge-for-profilepress' ); ?></th>
				<th><?php esc_html_e( 'Created', 'license-bridge-for-profilepress' ); ?></th>
				<th><?php esc_html_e( 'Expires', 'license-bridge-for-profilepress' ); ?></th>
				<th><?php esc_html_e( 'Installs', 'license-bridge-for-profilepress' ); ?></th>
				<th><?php esc_html_e( 'Sites', 'license-bridge-for-profilepress' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $filtered ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'No licenses match the current filters.', 'license-bridge-for-profilepress' ); ?></td></tr>
			<?php else : foreach ( $filtered as $r ) :
				$status     = (string) $r['status'];
				$at_cap     = $r['max_domains'] > 0 && $r['install_count'] >= $r['max_domains'];
				$user_link  = get_edit_user_link( (int) $r['user_id'] );
			?>
				<tr>
					<td>
						<?php if ( $user_link ) : ?>
							<a href="<?php echo esc_url( $user_link ); ?>"><strong><?php echo esc_html( $r['customer'] ); ?></strong></a>
						<?php else : ?>
							<strong><?php echo esc_html( $r['customer'] ); ?></strong>
						<?php endif; ?>
						<br><span class="lbfp-muted"><?php echo esc_html( $r['email'] ); ?></span>
					</td>
					<td><?php echo esc_html( $r['plan'] ); ?></td>
					<td>
						<code class="lbfp-key" title="<?php echo esc_attr( $r['license_key'] ); ?>"><?php echo esc_html( $mask_key( $r['license_key'] ) ); ?></code>
					</td>
					<td><span class="lbfp-status lbfp-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
					<td><?php echo esc_html( $r['date_created'] ?: '—' ); ?></td>
					<td><?php echo esc_html( $r['date_expiry'] ?: __( 'Lifetime', 'license-bridge-for-profilepress' ) ); ?></td>
					<td>
						<span class="lbfp-installs <?php echo $at_cap ? 'lbfp-at-cap' : ''; ?>">
							<?php
							echo esc_html( (string) $r['install_count'] );
							if ( $r['max_domains'] > 0 ) {
								echo ' / ' . esc_html( (string) $r['max_domains'] );
							}
							?>
						</span>
					</td>
					<td>
						<?php if ( empty( $r['domains'] ) ) : ?>
							<span class="lbfp-muted"><?php esc_html_e( 'No installs yet', 'license-bridge-for-profilepress' ); ?></span>
						<?php else : ?>
							<details>
								<summary><?php echo esc_html( sprintf( _n( '%d site', '%d sites', count( $r['domains'] ), 'license-bridge-for-profilepress' ), count( $r['domains'] ) ) ); ?></summary>
								<ul class="lbfp-domains">
									<?php foreach ( $r['domains'] as $domain ) : ?>
										<li><a href="<?php echo esc_url( esc_url( $domain ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $domain ); ?></a></li>
									<?php endforeach; ?>
								</ul>
							</details>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; endif; ?>
		</tbody>
	</table>
</div>

<style>
.lbfp-dashboard .lbfp-cards { display:flex;flex-wrap:wrap;gap:14px;margin:18px 0 8px; }
.lbfp-dashboard .lbfp-card { flex:1 1 140px;background:#fff;border:1px solid #dcdcde;border-left-width:4px;border-left-color:#2271b1;border-radius:6px;padding:14px 16px; }
.lbfp-dashboard .lbfp-card.lbfp-tone-ok { border-left-color:#1b8a3f; }
.lbfp-dashboard .lbfp-card.lbfp-tone-bad { border-left-color:#b71c1c; }
.lbfp-dashboard .lbfp-card.lbfp-tone-warn { border-left-color:#b8860b; }
.lbfp-dashboard .lbfp-card-value { font-size:26px;font-weight:600;line-height:1.1; }
.lbfp-dashboard .lbfp-card-label { color:#646970;font-size:12px;text-transform:uppercase;letter-spacing:.04em;margin-top:4px; }
.lbfp-dashboard .lbfp-filters { display:flex;align-items:center;gap:8px;margin:14px 0; }
.lbfp-dashboard .lbfp-result-count { color:#646970;margin-left:auto; }
.lbfp-dashboard .lbfp-muted { color:#787c82;font-size:12px; }
.lbfp-dashboard .lbfp-key { background:#f0f0f1;padding:2px 6px;border-radius:4px;font-family:Menlo,Consolas,monospace;font-size:12px; }
.lbfp-dashboard .lbfp-status { display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;text-transform:capitalize; }
.lbfp-dashboard .lbfp-status-active { background:#e8f5e9;color:#1b5e20; }
.lbfp-dashboard .lbfp-status-expired,
.lbfp-dashboard .lbfp-status-blocked { background:#ffebee;color:#b71c1c; }
.lbfp-dashboard .lbfp-status-pending,
.lbfp-dashboard .lbfp-status-unknown { background:#fff8e1;color:#5d4037; }
.lbfp-dashboard .lbfp-status-missing { background:#eee;color:#50575e; }
.lbfp-dashboard .lbfp-installs.lbfp-at-cap { color:#b71c1c;font-weight:700; }
.lbfp-dashboard .lbfp-domains { margin:6px 0 0;padding-left:18px; }
.lbfp-dashboard .lbfp-domains li { font-family:Menlo,Consolas,monospace;font-size:12px;margin:2px 0; }
.lbfp-dashboard details summary { cursor:pointer;color:#2271b1; }
</style>
