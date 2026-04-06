# Network Plugin Manager (per-site)

Converts the provided snippet into a network-admin plugin that allows disabling network-activated plugins on a per-site basis.

## Description
Network Plugin Manager (per-site) adds a Network Admin page to select network-enabled plugins to disable on individual subsites. Stores per-site disables in a single site option and filters network-activated plugins at runtime.

## Installation
1. Save the plugin PHP file (e.g., `network-plugin-manager.php`) into `wp-content/plugins/`.
2. Network-activate the plugin from Network Admin → Plugins.
3. In Network Admin, go to Plugins → Per-Site Plugin Overrides to configure disables.

Alternatively, place the file in `wp-content/mu-plugins/` to auto-load it (no activation required).

## Plugin Header
Author: UltiWP ( Previously Homescriptone Solutions Ltd )  
Website: https://ultiwp.com  
Email: contact@homescriptone.com

## Features
- UI in Network Admin to disable network-activated plugins per subsite.
- Stores settings in the site option `nppm_disabled_plugins`.
- Applies disables via the `site_option_active_sitewide_plugins` filter so affected subsites behave as if the plugin is not network-active.
- Minimal, no external libraries.

## Usage
- Network-activate any plugin you may want to optionally disable per-site.
- Open Network Admin → Plugins → Per-Site Plugin Overrides.
- For each site, check the network plugins you want disabled and click Save.
- Changes take effect immediately for front-end and admin pages on the selected subsites.

## Data storage
Settings are saved in the network site option:
- Option name: `nppm_disabled_plugins`
- Structure: array( <blog_id> => array( '<plugin_file>' => 1, ... ), ... )

## Compatibility & Notes
- Tested with WordPress Multisite installations.
- Limits: lists up to 1000 sites by default (adjust the code if you have more).
- Only affects network-activated plugins; site-activated plugins are not managed.
- Test on staging before deploying to production.

## Security
- Only users with the `manage_network_options` capability can access the UI.
- Uses nonces for form submissions.

## Changelog
1.0 — Initial release: network admin UI, per-site disables, settings storage, filter integration.

## License
GPLv2 or later.

## Support / Contact
UltiWP ( Previously Homescriptone Solutions Ltd )  
Website: https://ultiwp.com  
Email: contact@homescriptone.com
