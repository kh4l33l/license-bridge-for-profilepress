<?php

namespace LicenseBridgeForProfilePress;

defined( 'ABSPATH' ) || exit;

class Admin_Settings {

	private const OPTION_GROUP = 'lbfp_settings';
	private const OPTION_NAME  = 'lbfp_plan_map';

	private const RELEASE_GROUP  = 'lbfp_release_settings';
	private const RELEASE_OPTION = Update_Server::OPTION_NAME;

	private const PRODUCT_GROUP  = 'lbfp_product_settings';
	private const PRODUCT_OPTION = Product::OPTION;

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_init', [ $this, 'register_setting' ] );
	}

	public function menu(): void {
		add_submenu_page(
			'ppress-dashboard',
			__( 'SLM Integration', 'license-bridge-for-profilepress' ),
			__( 'SLM Integration', 'license-bridge-for-profilepress' ),
			'manage_options',
			'license-bridge-for-profilepress',
			[ $this, 'render' ]
		);
	}

	public function register_setting(): void {
		register_setting( self::OPTION_GROUP, self::OPTION_NAME, [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize' ],
			'default'           => [],
		] );

		register_setting( self::RELEASE_GROUP, self::RELEASE_OPTION, [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_release' ],
			'default'           => [],
		] );

		register_setting( self::PRODUCT_GROUP, self::PRODUCT_OPTION, [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_product' ],
			'default'           => [],
		] );
	}

	public function sanitize_product( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		return [
			'item_reference' => sanitize_text_field( (string) ( $input['item_reference'] ?? '' ) ),
			'name'           => sanitize_text_field( (string) ( $input['name'] ?? '' ) ),
			'slug'           => sanitize_title( (string) ( $input['slug'] ?? '' ) ),
			'homepage'       => esc_url_raw( (string) ( $input['homepage'] ?? '' ) ),
			'author'         => wp_kses_post( (string) ( $input['author'] ?? '' ) ),
		];
	}

	public function sanitize_release( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		$zip_path = isset( $input['zip_path'] ) ? trim( (string) $input['zip_path'] ) : '';
		$is_url   = (bool) preg_match( '#^https?://#i', $zip_path );
		if ( '' !== $zip_path && ! $is_url && ! is_readable( $zip_path ) ) {
			add_settings_error(
				self::RELEASE_OPTION,
				'zip_unreadable',
				__( 'The release ZIP path is not readable by the server — updates will 404 until this is fixed. Enter an absolute filesystem path, or a full https:// URL.', 'license-bridge-for-profilepress' )
			);
		}

		return [
			'version'      => sanitize_text_field( (string) ( $input['version'] ?? '' ) ),
			'requires'     => sanitize_text_field( (string) ( $input['requires'] ?? '' ) ),
			'tested'       => sanitize_text_field( (string) ( $input['tested'] ?? '' ) ),
			'requires_php' => sanitize_text_field( (string) ( $input['requires_php'] ?? '' ) ),
			'last_updated' => sanitize_text_field( (string) ( $input['last_updated'] ?? '' ) ),
			'description'  => wp_kses_post( (string) ( $input['description'] ?? '' ) ),
			'changelog'    => wp_kses_post( (string) ( $input['changelog'] ?? '' ) ),
			'zip_path'     => $zip_path,
		];
	}

	public function sanitize( $input ): array {
		if ( ! is_array( $input ) ) return [];
		$out = [];
		foreach ( $input as $plan_id => $max ) {
			$plan_id = (int) $plan_id;
			$max     = (int) $max;
			if ( $plan_id > 0 && $max >= 0 ) {
				$out[ $plan_id ] = $max;
			}
		}
		return $out;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$plans = $this->get_plans();
		$map   = (array) get_option( self::OPTION_NAME, [] );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'License Bridge for ProfilePress', 'license-bridge-for-profilepress' ); ?></h1>
			<p>
				<?php esc_html_e( 'Map each membership plan to the number of sites a customer\'s license should allow. Set to 0 (or leave blank) to skip license provisioning for a plan.', 'license-bridge-for-profilepress' ); ?>
			</p>
			<p style="color:#666;">
				<?php
				printf(
					/* translators: %s: SLM URL */
					esc_html__( 'SLM target: %s. Set LBFP_SLM_SECRET in wp-config.php to enable license provisioning.', 'license-bridge-for-profilepress' ),
					'<code>' . esc_html( LBFP_SLM_URL ) . '</code>'
				);
				?>
				<?php if ( '' === LBFP_SLM_SECRET ) : ?>
					<br><strong style="color:#b71c1c;">
						<?php esc_html_e( 'LBFP_SLM_SECRET is not set — license calls will fail.', 'license-bridge-for-profilepress' ); ?>
					</strong>
				<?php endif; ?>
			</p>

			<h2><?php esc_html_e( 'Licensed product', 'license-bridge-for-profilepress' ); ?></h2>
			<p>
				<?php esc_html_e( 'The product this site licenses. The item reference must match the product configured in Software License Manager — it scopes every license call. The remaining fields label the updates served to customer sites.', 'license-bridge-for-profilepress' ); ?>
			</p>
			<?php
			$product = wp_parse_args( (array) get_option( self::PRODUCT_OPTION, [] ), [
				'item_reference' => '',
				'name'           => '',
				'slug'           => '',
				'homepage'       => '',
				'author'         => '',
			] );
			?>
			<form method="post" action="options.php">
				<?php settings_fields( self::PRODUCT_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="lbfp_item_reference"><?php esc_html_e( 'SLM item reference', 'license-bridge-for-profilepress' ); ?></label></th>
						<td>
							<input type="text" id="lbfp_item_reference" class="regular-text" name="<?php echo esc_attr( self::PRODUCT_OPTION ); ?>[item_reference]" value="<?php echo esc_attr( $product['item_reference'] ); ?>" placeholder="My Pro Plugin" />
							<p class="description"><?php esc_html_e( 'Must exactly match the item reference set on the product in Software License Manager.', 'license-bridge-for-profilepress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lbfp_name"><?php esc_html_e( 'Update display name', 'license-bridge-for-profilepress' ); ?></label></th>
						<td>
							<input type="text" id="lbfp_name" class="regular-text" name="<?php echo esc_attr( self::PRODUCT_OPTION ); ?>[name]" value="<?php echo esc_attr( $product['name'] ); ?>" placeholder="My Pro Plugin" />
							<p class="description"><?php esc_html_e( 'Shown in the customer-side update notice. Defaults to the item reference if blank.', 'license-bridge-for-profilepress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lbfp_slug"><?php esc_html_e( 'Plugin slug', 'license-bridge-for-profilepress' ); ?></label></th>
						<td>
							<input type="text" id="lbfp_slug" class="regular-text" name="<?php echo esc_attr( self::PRODUCT_OPTION ); ?>[slug]" value="<?php echo esc_attr( $product['slug'] ); ?>" placeholder="my-pro-plugin" />
							<p class="description"><?php esc_html_e( 'The customer plugin folder slug; also used for the download filename. Derived from the name if blank.', 'license-bridge-for-profilepress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lbfp_homepage"><?php esc_html_e( 'Homepage URL', 'license-bridge-for-profilepress' ); ?></label></th>
						<td><input type="url" id="lbfp_homepage" class="regular-text" name="<?php echo esc_attr( self::PRODUCT_OPTION ); ?>[homepage]" value="<?php echo esc_attr( $product['homepage'] ); ?>" placeholder="https://example.com" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lbfp_author"><?php esc_html_e( 'Author', 'license-bridge-for-profilepress' ); ?></label></th>
						<td>
							<input type="text" id="lbfp_author" class="regular-text" name="<?php echo esc_attr( self::PRODUCT_OPTION ); ?>[author]" value="<?php echo esc_attr( $product['author'] ); ?>" placeholder="Your Company" />
							<p class="description"><?php esc_html_e( 'Shown in the update "View details" popup. Plain text or a link is allowed.', 'license-bridge-for-profilepress' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save product', 'license-bridge-for-profilepress' ) ); ?>
			</form>

			<hr style="margin:2.5em 0;">

			<h2><?php esc_html_e( 'Plan → license mapping', 'license-bridge-for-profilepress' ); ?></h2>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<table class="form-table" role="presentation">
					<thead>
						<tr><th><?php esc_html_e( 'Plan', 'license-bridge-for-profilepress' ); ?></th>
							<th><?php esc_html_e( 'Plan ID', 'license-bridge-for-profilepress' ); ?></th>
							<th><?php esc_html_e( 'Max sites', 'license-bridge-for-profilepress' ); ?></th></tr>
					</thead>
					<tbody>
						<?php if ( empty( $plans ) ) : ?>
							<tr><td colspan="3"><?php esc_html_e( 'No ProfilePress plans found yet. Create plans first under ProfilePress → Membership → Plans.', 'license-bridge-for-profilepress' ); ?></td></tr>
						<?php else : foreach ( $plans as $plan ) :
							$pid = (int) $plan['id'];
							$val = (int) ( $map[ $pid ] ?? 0 );
						?>
							<tr>
								<td><?php echo esc_html( $plan['name'] ); ?></td>
								<td><code><?php echo esc_html( $pid ); ?></code></td>
								<td>
									<input
										type="number"
										min="0"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $pid ); ?>]"
										value="<?php echo esc_attr( $val ); ?>"
										class="small-text"
									/>
								</td>
							</tr>
						<?php endforeach; endif; ?>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr style="margin:2.5em 0;">

			<h2><?php esc_html_e( 'Plugin Release', 'license-bridge-for-profilepress' ); ?></h2>
			<p>
				<?php esc_html_e( 'Publish the current plugin build to licensed sites. After each release, bump the version and point to the new ZIP. Leave the version blank to pause update notifications.', 'license-bridge-for-profilepress' ); ?>
			</p>
			<?php
			$release = wp_parse_args( (array) get_option( self::RELEASE_OPTION, [] ), [
				'version'      => '',
				'requires'     => '',
				'tested'       => '',
				'requires_php' => '',
				'last_updated' => '',
				'description'  => '',
				'changelog'    => '',
				'zip_path'     => '',
			] );
			?>
			<form method="post" action="options.php">
				<?php settings_fields( self::RELEASE_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="lbfp_version"><?php esc_html_e( 'Version', 'license-bridge-for-profilepress' ); ?></label></th>
						<td><input type="text" id="lbfp_version" class="regular-text" name="<?php echo esc_attr( self::RELEASE_OPTION ); ?>[version]" value="<?php echo esc_attr( $release['version'] ); ?>" placeholder="2.0.11" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lbfp_zip"><?php esc_html_e( 'Release ZIP path', 'license-bridge-for-profilepress' ); ?></label></th>
						<td>
							<input type="text" id="lbfp_zip" class="large-text code" name="<?php echo esc_attr( self::RELEASE_OPTION ); ?>[zip_path]" value="<?php echo esc_attr( $release['zip_path'] ); ?>" placeholder="/home/user/releases/plugin.zip" />
							<p class="description"><?php esc_html_e( 'Either an absolute filesystem path (recommended — kept outside the web root and streamed only after the license check passes), or a full https:// URL to a publicly hosted ZIP (simpler, but the file is then downloadable by anyone with the link).', 'license-bridge-for-profilepress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lbfp_requires"><?php esc_html_e( 'Requires WP', 'license-bridge-for-profilepress' ); ?></label></th>
						<td><input type="text" id="lbfp_requires" class="small-text" name="<?php echo esc_attr( self::RELEASE_OPTION ); ?>[requires]" value="<?php echo esc_attr( $release['requires'] ); ?>" placeholder="6.0" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lbfp_tested"><?php esc_html_e( 'Tested up to', 'license-bridge-for-profilepress' ); ?></label></th>
						<td><input type="text" id="lbfp_tested" class="small-text" name="<?php echo esc_attr( self::RELEASE_OPTION ); ?>[tested]" value="<?php echo esc_attr( $release['tested'] ); ?>" placeholder="6.7" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lbfp_php"><?php esc_html_e( 'Requires PHP', 'license-bridge-for-profilepress' ); ?></label></th>
						<td><input type="text" id="lbfp_php" class="small-text" name="<?php echo esc_attr( self::RELEASE_OPTION ); ?>[requires_php]" value="<?php echo esc_attr( $release['requires_php'] ); ?>" placeholder="8.0" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lbfp_updated"><?php esc_html_e( 'Last updated', 'license-bridge-for-profilepress' ); ?></label></th>
						<td><input type="text" id="lbfp_updated" class="regular-text" name="<?php echo esc_attr( self::RELEASE_OPTION ); ?>[last_updated]" value="<?php echo esc_attr( $release['last_updated'] ); ?>" placeholder="2026-06-10" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lbfp_changelog"><?php esc_html_e( 'Changelog', 'license-bridge-for-profilepress' ); ?></label></th>
						<td><textarea id="lbfp_changelog" class="large-text code" rows="6" name="<?php echo esc_attr( self::RELEASE_OPTION ); ?>[changelog]"><?php echo esc_textarea( $release['changelog'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown in the "View details" popup. Basic HTML allowed.', 'license-bridge-for-profilepress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lbfp_desc"><?php esc_html_e( 'Description', 'license-bridge-for-profilepress' ); ?></label></th>
						<td><textarea id="lbfp_desc" class="large-text code" rows="3" name="<?php echo esc_attr( self::RELEASE_OPTION ); ?>[description]"><?php echo esc_textarea( $release['description'] ); ?></textarea></td>
					</tr>
				</table>
				<?php submit_button( __( 'Publish release', 'license-bridge-for-profilepress' ) ); ?>
			</form>
		</div>
		<?php
	}

	private function get_plans(): array {
		if ( ! class_exists( '\\ProfilePress\\Core\\DBTables' ) ) {
			return [];
		}
		global $wpdb;
		$table = \ProfilePress\Core\DBTables::subscription_plans_db_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results( "SELECT id, name FROM {$table} WHERE status = 'true' ORDER BY name ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}
}
