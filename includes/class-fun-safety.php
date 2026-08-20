<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The decision gate before any force-install.
 *
 * "Protect the Shire" holds updates back for 6-24h so WordPress.org can scan
 * them (Gandalf) — the kind of window that would have caught compromised
 * releases like the Smart Slider 3 Pro incident. Forcing the install
 * immediately means giving up exactly that window for this one plugin.
 *
 * is_force_update_allowed() is where you decide WHEN that risk is worth
 * taking. There's no universally correct answer here — it depends on how
 * much you trust the plugin author, how critical the site is, and why you're
 * forcing the update in the first place (e.g. an urgent security fix vs. a
 * routine feature update).
 */
class FUN_Safety {

	/**
	 * @param string $type 'plugin' or 'theme'.
	 * @param string $slug WordPress.org slug.
	 * @param array  $diff Result of FUN_Checker::diff_plugin()/diff_theme()
	 *                      (has 'installed', 'remote', 'last_updated').
	 *
	 * @return true|WP_Error true if allowed, WP_Error with the reason if not.
	 */
	public static function is_force_update_allowed( $type, $slug, array $diff ) {
		// TODO(user): write your own safety policy here.
		//
		// Some starting points (pick one or combine them):
		//  - explicit whitelist: only slugs from get_option('fun_allowed_slugs')
		//    can be forced, everything else gets rejected;
		//  - partial minimum age: require $diff['last_updated'] to be older
		//    than X hours even when forcing (keep SOME of the safety window
		//    instead of removing it entirely);
		//  - audit log: write who forced what and when to a file/option,
		//    regardless of the final decision;
		//  - no restrictions: return true unconditionally, if you've decided
		//    the responsibility is entirely yours on every click.
		//
		// Default (until you fill this in) allows everything, so the plugin
		// works out of the box — but that means zero safety brake.
		return true;
	}
}
