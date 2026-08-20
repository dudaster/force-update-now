<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installs the zip straight from the download_link returned by
 * plugins_api()/themes_api(), with overwrite_package=true — this never
 * touches the update_plugins/update_themes transient, so it doesn't matter
 * whether that's still inside its cooldown window.
 */
class FUN_Installer {

	public static function force_install_plugin( $plugin_file, $download_link ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$was_active = is_plugin_active( $plugin_file );

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );

		$result = $upgrader->install(
			$download_link,
			array( 'overwrite_package' => true )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			return new WP_Error( 'fun_install_failed', $skin->get_upgrade_messages() ? implode( ' ', $skin->get_upgrade_messages() ) : 'Installation failed.' );
		}

		if ( $was_active && ! is_plugin_active( $plugin_file ) ) {
			activate_plugin( $plugin_file, '', false, true );
		}

		return true;
	}

	public static function force_install_theme( $stylesheet, $download_link ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );

		$result = $upgrader->install(
			$download_link,
			array( 'overwrite_package' => true )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			return new WP_Error( 'fun_install_failed', $skin->get_upgrade_messages() ? implode( ' ', $skin->get_upgrade_messages() ) : 'Installation failed.' );
		}

		return true;
	}
}
