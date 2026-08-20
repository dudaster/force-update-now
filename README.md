# Force Update Now

A WordPress plugin that bypasses the "Protect the Shire" cooldown
WordPress.org introduced on June 5, 2026: new plugin/theme releases are held
back for up to 24 hours (later reduced to 6 hours) before the update
notification and auto-update pipeline reach your site, giving WordPress.org's
automated scanner (Gandalf) time to review the release.

That cooldown only affects the `update_plugins` / `update_themes` transient
and the notifications built on top of it — the `plugin_information` /
`theme_information` API endpoints (the ones behind "View details") reflect a
newly published version immediately. This plugin uses those endpoints to
check the real current version and, on request, installs it right away.

## What it does

- Adds **Tools → Force Update Now** in wp-admin.
- Lists installed plugins and themes with a **Check now** button that queries
  WordPress.org directly, independent of the cached update transient.
- When a newer version is found, a **Force update** button installs it
  immediately by downloading the package straight from WordPress.org and
  overwriting the current install (`Plugin_Upgrader`/`Theme_Upgrader` with
  `overwrite_package`), without waiting for the cooldown to lift.

## What it does not do

- It does not touch WordPress core updates — Protect the Shire targets the
  plugin/theme directory, not core.
- It does not work for plugins/themes that aren't hosted on WordPress.org
  (no matching slug in the directory means no `plugin_information` result).
- It ships with **no safety policy** beyond the `update_plugins` capability
  check — see below.

## The tradeoff you're opting into

The cooldown exists because WordPress.org's own review window has already
caught real supply-chain incidents (backdoored releases across dozens of
plugins, a compromised Smart Slider 3 Pro update). Forcing an install skips
that review for the plugin you're forcing.

`includes/class-fun-safety.php` is the single decision point where this is
enforced (`FUN_Safety::is_force_update_allowed()`). It ships as an open gate
(always returns `true`) so the plugin works out of the box, but it's meant to
be filled in with your own policy — a slug whitelist, a partial minimum-age
check against the version's `last_updated` timestamp, an audit log, or
whatever fits how much risk you're willing to carry per site.

## Requirements

- WordPress with filesystem access that doesn't require FTP credentials for
  plugin installs (true for most local/Docker setups and many hosts).
- A user capable of `update_plugins`.

## Install

Drop the `force-update-now` folder into `wp-content/plugins/` and activate
it from the Plugins screen.
