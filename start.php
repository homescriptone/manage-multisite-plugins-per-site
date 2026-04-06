<?php
/*
Plugin Name: Network Plugin Manager (per-site)
Description: Admin UI to selectively disable network-enabled plugins on individual subsites.
Version: 1.0
Author: UltiWP ( Previously Homescriptone Solutions Ltd )
Author URI: https://ultiwp.com
Author Email: contact@homescriptone.com
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add a Network Admin submenu under "Plugins"
 */
add_action( 'network_admin_menu', function() {
    add_submenu_page(
        'plugins.php',
        'Per-Site Plugin Overrides',
        'Per-Site Plugin Overrides',
        'manage_network_options',
        'per-site-plugin-overrides',
        'nppm_render_admin_page'
    );
} );

/**
 * Handle form submission and save overrides in site option 'nppm_disabled_plugins'
 * Structure: array( <blog_id> => array( <plugin_file> => 1, ... ), ... )
 */
add_action( 'network_admin_edit_nppm_save', function() {
    if ( ! current_user_can( 'manage_network_options' ) ) {
        wp_die( __( 'Unauthorized', 'nppm' ) );
    }

    check_admin_referer( 'nppm-save' );

    $data = array();
    $raw = isset( $_POST['nppm'] ) && is_array( $_POST['nppm'] ) ? $_POST['nppm'] : array();

    foreach ( $raw as $blog_id => $plugins ) {
        $bid = intval( $blog_id );
        if ( $bid <= 0 ) {
            continue;
        }
        $data[ $bid ] = array();
        if ( is_array( $plugins ) ) {
            foreach ( $plugins as $p => $v ) {
                $p = sanitize_text_field( wp_unslash( $p ) );
                if ( $p !== '' ) {
                    $data[ $bid ][ $p ] = 1;
                }
            }
        }
        if ( empty( $data[ $bid ] ) ) {
            unset( $data[ $bid ] );
        }
    }

    update_site_option( 'nppm_disabled_plugins', $data );

    wp_redirect( add_query_arg( array( 'page' => 'per-site-plugin-overrides', 'updated' => '1' ), network_admin_url( 'plugins.php' ) ) );
    exit;
} );

/**
 * Render admin page
 */
function nppm_render_admin_page() {
    if ( ! current_user_can( 'manage_network_options' ) ) {
        wp_die( __( 'Unauthorized', 'nppm' ) );
    }

    $sites = get_sites( array( 'number' => 1000 ) ); // reasonable default; adjust if you have many sites
    $network_plugins = get_site_option( 'active_sitewide_plugins', array() );
    $disabled = get_site_option( 'nppm_disabled_plugins', array() );

    ?>
    <div class="wrap">
        <h1>Per-Site Plugin Overrides</h1>
        <?php if ( isset( $_GET['updated'] ) ): ?>
            <div id="message" class="updated notice is-dismissible"><p>Saved.</p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'nppm-save' ); ?>
            <input type="hidden" name="action" value="nppm_save" />

            <table class="widefat">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Disable Network Plugins</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $network_plugins ) ) : ?>
                    <tr><td colspan="2">No network-activated plugins found.</td></tr>
                <?php else : ?>
                    <?php foreach ( $sites as $site ) : 
                        $blog_id = (int) $site->blog_id;
                        $siteinfo = get_blog_details( $blog_id );
                        $site_label = $siteinfo ? $siteinfo->blogname . ' (' . $siteinfo->domain . $siteinfo->path . ')' : 'Site ' . $blog_id;
                    ?>
                        <tr>
                            <td style="vertical-align:top; width:30%">
                                <strong><?php echo esc_html( $site_label ); ?></strong><br>
                                ID: <?php echo $blog_id; ?>
                            </td>
                            <td>
                                <?php foreach ( $network_plugins as $plugin_file => $val ) :
                                    $plugin_data = get_plugin_data_if_exists( $plugin_file );
                                    $checked = isset( $disabled[ $blog_id ] ) && isset( $disabled[ $blog_id ][ $plugin_file ] );
                                ?>
                                    <label style="display:block; margin-bottom:6px;">
                                        <input type="checkbox" name="nppm[<?php echo $blog_id; ?>][<?php echo esc_attr( $plugin_file ); ?>]" value="1" <?php checked( $checked ); ?> />
                                        <?php echo esc_html( $plugin_data ); ?> <code style="color:#666"><?php echo esc_html( $plugin_file ); ?></code>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <p class="submit"><button type="submit" class="button button-primary">Save</button></p>
        </form>
    </div>
    <?php
}

/**
 * Helper: returns a short plugin label (Name - Version or file if not available)
 */
function get_plugin_data_if_exists( $plugin_file ) {
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $all = get_plugins();
    if ( isset( $all[ $plugin_file ] ) ) {
        $p = $all[ $plugin_file ];
        return ( ! empty( $p['Name'] ) ? $p['Name'] : $plugin_file ) . ( ! empty( $p['Version'] ) ? ' v' . $p['Version'] : '' );
    }
    return $plugin_file;
}

/**
 * Filter active_sitewide_plugins to remove disabled plugins per-site
 */
add_filter( 'site_option_active_sitewide_plugins', function( $value ) {
    if ( ! is_object( $GLOBALS['current_blog'] ) ) {
        return $value;
    }

    $blog_id = isset( $GLOBALS['current_blog']->blog_id ) ? intval( $GLOBALS['current_blog']->blog_id ) : 0;
    if ( $blog_id <= 0 ) {
        return $value;
    }

    $disabled = get_site_option( 'nppm_disabled_plugins', array() );
    if ( empty( $disabled ) || ! isset( $disabled[ $blog_id ] ) ) {
        return $value;
    }

    foreach ( $disabled[ $blog_id ] as $plugin_file => $_ ) {
        if ( isset( $value[ $plugin_file ] ) ) {
            unset( $value[ $plugin_file ] );
        }
    }

    return $value;
} );
