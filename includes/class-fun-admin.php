<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FUN_Admin {

	const NONCE_ACTION = 'fun_force_update';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_fun_check', array( __CLASS__, 'ajax_check' ) );
		add_action( 'wp_ajax_fun_force_update', array( __CLASS__, 'ajax_force_update' ) );
	}

	public static function register_menu() {
		add_management_page(
			'Force Update Now',
			'Force Update Now',
			'update_plugins',
			'force-update-now',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( 'tools_page_force-update-now' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'fun-admin',
			plugins_url( 'assets/admin.js', FUN_PLUGIN_FILE ),
			array( 'jquery' ),
			'0.1.0',
			true
		);

		wp_localize_script(
			'fun-admin',
			'FUN',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage updates.' ) );
		}

		$plugins = get_plugins();
		$themes  = wp_get_themes();
		?>
		<div class="wrap">
			<h1>Force Update Now</h1>
			<p>
				Checks the actual version published on WordPress.org (bypassing the
				"Protect the Shire" 24h/6h cooldown on update notifications) and lets
				you install it immediately.
			</p>

			<h2>Plugins</h2>
			<table class="widefat striped" id="fun-plugins-table">
				<thead>
					<tr>
						<th>Plugin</th>
						<th>Installed</th>
						<th>Latest on WordPress.org</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $plugins as $plugin_file => $data ) : ?>
						<?php $slug = self::guess_plugin_slug( $plugin_file ); ?>
						<tr data-type="plugin" data-file="<?php echo esc_attr( $plugin_file ); ?>" data-slug="<?php echo esc_attr( $slug ); ?>">
							<td><?php echo esc_html( $data['Name'] ); ?></td>
							<td class="fun-installed"><?php echo esc_html( $data['Version'] ); ?></td>
							<td class="fun-remote">—</td>
							<td class="fun-actions">
								<button class="button fun-check">Check now</button>
								<button class="button button-primary fun-force" disabled>Force update</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Themes</h2>
			<table class="widefat striped" id="fun-themes-table">
				<thead>
					<tr>
						<th>Theme</th>
						<th>Installed</th>
						<th>Latest on WordPress.org</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $themes as $stylesheet => $theme ) : ?>
						<tr data-type="theme" data-file="<?php echo esc_attr( $stylesheet ); ?>" data-slug="<?php echo esc_attr( $stylesheet ); ?>">
							<td><?php echo esc_html( $theme->get( 'Name' ) ); ?></td>
							<td class="fun-installed"><?php echo esc_html( $theme->get( 'Version' ) ); ?></td>
							<td class="fun-remote">—</td>
							<td class="fun-actions">
								<button class="button fun-check">Check now</button>
								<button class="button button-primary fun-force" disabled>Force update</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Plugins not hosted on WordPress.org (or with a slug that doesn't match
	 * their directory name) won't resolve correctly here — this plugin only
	 * targets the WordPress.org directory, per Protect the Shire's scope.
	 */
	private static function guess_plugin_slug( $plugin_file ) {
		if ( strpos( $plugin_file, '/' ) !== false ) {
			return dirname( $plugin_file );
		}
		return basename( $plugin_file, '.php' );
	}

	public static function ajax_check() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}

		$type = sanitize_key( $_POST['type'] ?? '' );
		$file = sanitize_text_field( wp_unslash( $_POST['file'] ?? '' ) );
		$slug = sanitize_key( $_POST['slug'] ?? '' );

		$diff = 'theme' === $type
			? FUN_Checker::diff_theme( $slug )
			: FUN_Checker::diff_plugin( $file, $slug );

		if ( is_wp_error( $diff ) ) {
			wp_send_json_error( $diff->get_error_message() );
		}

		wp_send_json_success( $diff );
	}

	public static function ajax_force_update() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}

		$type = sanitize_key( $_POST['type'] ?? '' );
		$file = sanitize_text_field( wp_unslash( $_POST['file'] ?? '' ) );
		$slug = sanitize_key( $_POST['slug'] ?? '' );

		$diff = 'theme' === $type
			? FUN_Checker::diff_theme( $slug )
			: FUN_Checker::diff_plugin( $file, $slug );

		if ( is_wp_error( $diff ) ) {
			wp_send_json_error( $diff->get_error_message() );
		}

		$allowed = FUN_Safety::is_force_update_allowed( $type, $slug, $diff );
		if ( is_wp_error( $allowed ) ) {
			wp_send_json_error( $allowed->get_error_message() );
		}

		$result = 'theme' === $type
			? FUN_Installer::force_install_theme( $slug, $diff['download_link'] )
			: FUN_Installer::force_install_plugin( $file, $diff['download_link'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( array( 'version' => $diff['remote'] ) );
	}
}
