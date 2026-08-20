<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This plugin isn't on WordPress.org (that's rather the point), so WordPress
 * has no way to check it for updates on its own. This hooks into the same
 * transient/filters core uses for WordPress.org plugins, but backed by
 * GitHub Releases instead — so it shows up as a normal update on the
 * Plugins screen and installs through the normal upgrade flow.
 */
class FUN_Self_Updater {

	const GITHUB_REPO = 'dudaster/force-update-now';
	const SLUG         = 'force-update-now';
	const CACHE_KEY    = 'fun_self_update_check';
	const CACHE_TTL    = 12 * HOUR_IN_SECONDS;

	private static $plugin_file;

	public static function init() {
		self::$plugin_file = plugin_basename( FUN_PLUGIN_FILE );

		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_dir' ), 10, 4 );
	}

	/**
	 * @return array { version, zip_url, html_url, body } — empty array if no
	 *                release exists yet or the GitHub API call failed.
	 */
	private static function get_release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest',
			array(
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'force-update-now',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Cache the miss briefly too, so a 404 (no releases yet) doesn't
			// hammer the GitHub API on every admin page load.
			set_site_transient( self::CACHE_KEY, array(), 5 * MINUTE_IN_SECONDS );
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		$release = array(
			'version'  => ltrim( $data['tag_name'] ?? '', 'v' ),
			'zip_url'  => self::pick_zip_url( $data ),
			'html_url' => $data['html_url'] ?? '',
			'body'     => $data['body'] ?? '',
		);

		set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );

		return $release;
	}

	/**
	 * Prefers an uploaded release asset named exactly "force-update-now.zip"
	 * (its root folder already matches the plugin slug). Falls back to
	 * GitHub's auto-generated source zip, whose root folder is
	 * "force-update-now-<sha>" — fix_source_dir() renames that on install.
	 */
	private static function pick_zip_url( $data ) {
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( self::SLUG . '.zip' === $asset['name'] ) {
				return $asset['browser_download_url'];
			}
		}
		return $data['zipball_url'] ?? '';
	}

	public static function inject_update( $transient ) {
		if ( empty( $transient->checked ) || empty( $transient->checked[ self::$plugin_file ] ) ) {
			return $transient;
		}

		$release = self::get_release();
		if ( empty( $release['version'] ) || empty( $release['zip_url'] ) ) {
			return $transient;
		}

		$installed = $transient->checked[ self::$plugin_file ];

		if ( version_compare( $release['version'], $installed, '>' ) ) {
			$transient->response[ self::$plugin_file ] = (object) array(
				'slug'        => self::SLUG,
				'plugin'      => self::$plugin_file,
				'new_version' => $release['version'],
				'url'         => $release['html_url'],
				'package'     => $release['zip_url'],
			);
		} else {
			unset( $transient->response[ self::$plugin_file ] );
		}

		return $transient;
	}

	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = self::get_release();

		return (object) array(
			'name'          => 'Force Update Now',
			'slug'          => self::SLUG,
			'version'       => $release['version'] ?? '',
			'author'        => 'dudaster',
			'homepage'      => 'https://github.com/' . self::GITHUB_REPO,
			'sections'      => array(
				'description' => wpautop( $release['body'] ?? 'See the changelog on GitHub.' ),
			),
			'download_link' => $release['zip_url'] ?? '',
		);
	}

	/**
	 * GitHub's zipball extracts to "<repo>-<sha>" — rename it to the plugin's
	 * own slug so the upgrader overwrites the existing install instead of
	 * creating a differently-named sibling plugin.
	 */
	public static function fix_source_dir( $source, $remote_source, $upgrader, $args = array() ) {
		global $wp_filesystem;

		if ( empty( $args['plugin'] ) || self::$plugin_file !== $args['plugin'] ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . self::SLUG . '/';

		if ( trailingslashit( $source ) === $desired ) {
			return $source;
		}

		if ( $wp_filesystem->move( $source, $desired, true ) ) {
			return $desired;
		}

		return $source;
	}
}
