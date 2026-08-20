<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queries api.wordpress.org directly (plugin_information / theme_information),
 * NOT the update_plugins/update_themes transient — that's the one held back by
 * the "Protect the Shire" cooldown. The *_information endpoints reflect the
 * published version as soon as the Stable tag/readme changes in SVN.
 */
class FUN_Checker {

	/**
	 * @return object|WP_Error plugins_api() result (has ->version, ->download_link, ->slug).
	 */
	public static function get_remote_plugin_info( $slug ) {
		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		return plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array(
					'sections' => false,
					'tags'     => false,
				),
			)
		);
	}

	/**
	 * @return object|WP_Error themes_api() result (has ->version, ->download_link).
	 */
	public static function get_remote_theme_info( $slug ) {
		if ( ! function_exists( 'themes_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}

		return themes_api(
			'theme_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			)
		);
	}

	/**
	 * Compares the installed version against the version published on
	 * WordPress.org, regardless of what the update transient says (which may
	 * still be sitting inside the cooldown window).
	 *
	 * @return array { installed, remote, has_update, download_link, last_updated } | WP_Error
	 */
	public static function diff_plugin( $plugin_file, $slug ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
		$remote    = self::get_remote_plugin_info( $slug );

		if ( is_wp_error( $remote ) ) {
			return $remote;
		}

		return array(
			'installed'     => $installed['Version'],
			'remote'        => $remote->version,
			'has_update'    => version_compare( $remote->version, $installed['Version'], '>' ),
			'download_link' => $remote->download_link,
			// ->last_updated is stamped by WordPress.org on every SVN commit;
			// useful as a signal for "how fresh" the version you're about to
			// force is.
			'last_updated'        => isset( $remote->last_updated ) ? $remote->last_updated : null,
			'hours_since_release' => self::hours_since( $remote->last_updated ?? null ),
		);
	}

	public static function diff_theme( $stylesheet ) {
		$installed = wp_get_theme( $stylesheet );
		$remote    = self::get_remote_theme_info( $stylesheet );

		if ( is_wp_error( $remote ) ) {
			return $remote;
		}

		return array(
			'installed'           => $installed->get( 'Version' ),
			'remote'              => $remote->version,
			'has_update'          => version_compare( $remote->version, $installed->get( 'Version' ), '>' ),
			'download_link'       => $remote->download_link,
			'last_updated'        => isset( $remote->last_updated ) ? $remote->last_updated : null,
			'hours_since_release' => self::hours_since( $remote->last_updated ?? null ),
		);
	}

	/**
	 * @return float|null Hours elapsed since $last_updated (WordPress.org's
	 *                     "->last_updated" format, e.g. "2026-08-19 3:00pm GMT"),
	 *                     or null if it couldn't be parsed.
	 */
	private static function hours_since( $last_updated ) {
		if ( empty( $last_updated ) ) {
			return null;
		}

		$timestamp = strtotime( $last_updated );
		if ( false === $timestamp ) {
			return null;
		}

		return max( 0, ( time() - $timestamp ) / HOUR_IN_SECONDS );
	}
}
