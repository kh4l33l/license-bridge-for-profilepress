<?php

defined( 'ABSPATH' ) || exit;

$flash_messages = [
	'deactivated' => __( 'Domain deactivated. You can now activate the license on a different site.', 'license-bridge-for-profilepress' ),
	'failed'      => __( 'Could not deactivate that domain. Please try again or contact support.', 'license-bridge-for-profilepress' ),
	'invalid'     => __( 'That request expired. Please try again.', 'license-bridge-for-profilepress' ),
	'no_license'  => __( 'No license is associated with your account.', 'license-bridge-for-profilepress' ),
];

?>
<div class="ppmyac-licenses">
	<?php if ( '' !== $flash && isset( $flash_messages[ $flash ] ) ) : ?>
		<div class="ppmyac-licenses-flash flash-<?php echo esc_attr( $flash ); ?>">
			<?php echo esc_html( $flash_messages[ $flash ] ); ?>
		</div>
	<?php endif; ?>

	<div class="ppmyac-licenses-header">
		<h3><?php esc_html_e( 'Your license', 'license-bridge-for-profilepress' ); ?></h3>
		<p class="ppmyac-licenses-help">
			<?php esc_html_e( 'Use this license key to activate the plugin on your WordPress site.', 'license-bridge-for-profilepress' ); ?>
		</p>
	</div>

	<table class="ppmyac-licenses-table">
		<tbody>
			<tr>
				<th><?php esc_html_e( 'License key', 'license-bridge-for-profilepress' ); ?></th>
				<td>
					<code class="ppmyac-licenses-key"><?php echo esc_html( $key ); ?></code>
					<button
						type="button"
						class="ppmyac-licenses-copy"
						data-key="<?php echo esc_attr( $key ); ?>"
						onclick="navigator.clipboard.writeText(this.dataset.key);this.textContent='<?php echo esc_js( __( 'Copied', 'license-bridge-for-profilepress' ) ); ?>'"
					>
						<?php esc_html_e( 'Copy', 'license-bridge-for-profilepress' ); ?>
					</button>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Status', 'license-bridge-for-profilepress' ); ?></th>
				<td><span class="ppmyac-licenses-status status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Expires', 'license-bridge-for-profilepress' ); ?></th>
				<td><?php
					$is_lifetime = empty( $expires ) || '0000-00-00' === $expires;
					echo $is_lifetime ? esc_html__( 'Lifetime', 'license-bridge-for-profilepress' ) : esc_html( $expires );
				?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Site activations', 'license-bridge-for-profilepress' ); ?></th>
				<td>
					<?php echo esc_html( count( $domains ) ); ?>
					<?php if ( $max > 0 ) : ?>
						/ <?php echo esc_html( $max ); ?>
					<?php endif; ?>
					<?php esc_html_e( 'sites', 'license-bridge-for-profilepress' ); ?>
				</td>
			</tr>
		</tbody>
	</table>

	<h4 style="margin-top:24px;"><?php esc_html_e( 'Active sites', 'license-bridge-for-profilepress' ); ?></h4>

	<?php if ( empty( $domains ) ) : ?>
		<p class="ppmyac-licenses-empty"><?php esc_html_e( 'This license is not active on any site yet.', 'license-bridge-for-profilepress' ); ?></p>
	<?php else : ?>
		<ul class="ppmyac-licenses-domains">
			<?php foreach ( $domains as $domain ) : ?>
				<li>
					<span class="domain"><?php echo esc_html( $domain ); ?></span>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<input type="hidden" name="action" value="lbfp_deactivate_domain" />
						<input type="hidden" name="domain" value="<?php echo esc_attr( $domain ); ?>" />
						<?php wp_nonce_field( 'lbfp_deactivate_domain' ); ?>
						<button type="submit" class="ppmyac-licenses-deactivate" onclick="return confirm('<?php echo esc_js( __( 'Deactivate this site? You can re-activate it later.', 'license-bridge-for-profilepress' ) ); ?>')"><?php esc_html_e( 'Deactivate', 'license-bridge-for-profilepress' ); ?></button>
					</form>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>

<style>
.ppmyac-licenses-flash { padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:14px; }
.ppmyac-licenses-flash.flash-deactivated { background:#e8f5e9;color:#1b5e20; }
.ppmyac-licenses-flash.flash-failed,
.ppmyac-licenses-flash.flash-invalid,
.ppmyac-licenses-flash.flash-no_license { background:#ffebee;color:#b71c1c; }
.ppmyac-licenses-table { width:100%;border-collapse:collapse;margin-top:12px; }
.ppmyac-licenses-table th { text-align:left;padding:8px 12px;width:160px;background:#fafafa;font-weight:600; }
.ppmyac-licenses-table td { padding:8px 12px;border-top:1px solid #eee; }
.ppmyac-licenses-key { background:#f5f5f5;padding:4px 8px;border-radius:4px;font-family:Menlo,Consolas,monospace;font-size:13px; }
.ppmyac-licenses-copy { margin-left:8px;padding:4px 10px;font-size:12px;border:1px solid #ccc;background:#fff;border-radius:4px;cursor:pointer; }
.ppmyac-licenses-status { display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600; }
.ppmyac-licenses-status.status-active { background:#e8f5e9;color:#1b5e20; }
.ppmyac-licenses-status.status-expired,
.ppmyac-licenses-status.status-blocked { background:#ffebee;color:#b71c1c; }
.ppmyac-licenses-status.status-pending,
.ppmyac-licenses-status.status-unknown { background:#fff8e1;color:#5d4037; }
.ppmyac-licenses-domains { list-style:none;padding:0;margin:8px 0; }
.ppmyac-licenses-domains li { display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border:1px solid #eee;border-radius:6px;margin-bottom:6px; }
.ppmyac-licenses-domains .domain { font-family:Menlo,Consolas,monospace;font-size:13px; }
.ppmyac-licenses-deactivate { padding:4px 10px;font-size:12px;border:1px solid #ddd;background:#fff;color:#b71c1c;border-radius:4px;cursor:pointer; }
.ppmyac-licenses-deactivate:hover { background:#fff5f5; }
</style>
