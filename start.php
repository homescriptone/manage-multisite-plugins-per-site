<?php
/*
Plugin Name: Network Plugin Manager (per-site)
Description: Admin UI to selectively disable network-enabled plugins on individual subsites.
Version: 1.1
Author: UltiWP ( Previously Homescriptone Solutions Ltd )
Author URI: https://ultiwp.com
Author Email: contact@homescriptone.com
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main Plugin Class
 */
class Network_Plugin_Manager {
    
    private $option_name = 'nppm_disabled_plugins';
    private $version = '1.1';
    
    public function __construct() {
        add_action( 'network_admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'network_admin_edit_nppm_save', array( $this, 'handle_save' ) );
        add_filter( 'site_option_active_sitewide_plugins', array( $this, 'filter_plugins' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }
    
    /**
     * Add Network Admin submenu under "Plugins"
     */
    public function add_admin_menu() {
        add_submenu_page(
            'plugins.php',
            'Per-Site Plugin Overrides',
            'Per-Site Overrides',
            'manage_network_options',
            'per-site-plugin-overrides',
            array( $this, 'render_admin_page' )
        );
    }
    
    /**
     * Enqueue CSS and JavaScript
     */
    public function enqueue_assets( $hook ) {
        if ( $hook !== 'plugins_page_per-site-plugin-overrides' ) {
            return;
        }
        
        // Inline CSS
        wp_enqueue_style( 'nppm-admin-style', false );
        wp_add_inline_style( 'nppm-admin-style', '
            .nppm-wrap { max-width: 100%; margin: 20px 0; }
            .nppm-notice { padding: 12px 15px; margin: 15px 0; border-left: 4px solid; }
            .nppm-notice.success { background: #d4edda; border-color: #28a745; color: #155724; }
            .nppm-notice.info { background: #d1ecf1; border-color: #0c5460; color: #0c5460; }
            .nppm-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
            .nppm-controls { display: flex; gap: 10px; align-items: center; margin-bottom: 15px; flex-wrap: wrap; }
            .nppm-search { padding: 8px 12px; width: 300px; border: 1px solid #ddd; border-radius: 4px; }
            .nppm-view-toggle { display: flex; gap: 5px; }
            .nppm-view-btn { padding: 6px 12px; border: 1px solid #ddd; background: #fff; cursor: pointer; }
            .nppm-view-btn.active { background: #2271b1; color: #fff; border-color: #2271b1; }
            .nppm-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .nppm-table th { background: #f7f7f7; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #ddd; position: sticky; top: 0; z-index: 10; }
            .nppm-table td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: top; }
            .nppm-table tbody tr:hover { background: #f9f9f9; }
            .nppm-site-name { font-weight: 600; color: #2271b1; margin-bottom: 4px; }
            .nppm-site-meta { color: #666; font-size: 12px; }
            .nppm-plugin-label { display: block; margin-bottom: 8px; padding: 6px; border-radius: 4px; transition: background 0.2s; }
            .nppm-plugin-label:hover { background: #f0f0f0; }
            .nppm-plugin-name { font-weight: 500; margin-left: 6px; }
            .nppm-plugin-file { color: #666; font-size: 11px; margin-left: 25px; display: block; margin-top: 2px; }
            .nppm-stats { display: flex; gap: 20px; padding: 15px; background: #f7f7f7; border-radius: 4px; margin-bottom: 20px; }
            .nppm-stat-item { flex: 1; }
            .nppm-stat-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
            .nppm-stat-value { font-size: 24px; font-weight: 600; color: #2271b1; margin-top: 4px; }
            .nppm-bulk-actions { display: flex; gap: 10px; margin-bottom: 15px; }
            .nppm-select-all { font-size: 11px; color: #2271b1; cursor: pointer; margin-left: 8px; }
            .nppm-select-all:hover { text-decoration: underline; }
            .nppm-submit-wrapper { position: sticky; bottom: 0; background: #f7f7f7; padding: 15px; margin-top: 20px; border-top: 2px solid #ddd; box-shadow: 0 -2px 5px rgba(0,0,0,0.05); z-index: 9; }
            .nppm-table-container { max-height: 70vh; overflow-y: auto; border: 1px solid #ddd; }
            .nppm-matrix-view .nppm-table th:first-child { position: sticky; left: 0; background: #f7f7f7; z-index: 11; }
            .nppm-matrix-view .nppm-table td:first-child { position: sticky; left: 0; background: #fff; font-weight: 600; }
            .nppm-matrix-view .nppm-table tbody tr:hover td:first-child { background: #f9f9f9; }
            .nppm-checkbox-cell { text-align: center; min-width: 80px; }
            .nppm-empty-state { padding: 40px; text-align: center; color: #666; }
            .nppm-filter-tags { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px; }
            .nppm-filter-tag { padding: 4px 10px; background: #e0e0e0; border-radius: 12px; font-size: 12px; display: flex; align-items: center; gap: 5px; }
            .nppm-filter-tag .remove { cursor: pointer; color: #666; font-weight: bold; }
            .nppm-filter-tag .remove:hover { color: #000; }
        ' );
        
        // Inline JavaScript
        wp_enqueue_script( 'nppm-admin-script', false, array( 'jquery' ), $this->version, true );
        wp_add_inline_script( 'nppm-admin-script', '
            jQuery(document).ready(function($) {
                // View toggle
                $(".nppm-view-btn").on("click", function() {
                    var view = $(this).data("view");
                    $(".nppm-view-btn").removeClass("active");
                    $(this).addClass("active");
                    
                    if (view === "site") {
                        $(".nppm-site-view").show();
                        $(".nppm-matrix-view").hide();
                    } else {
                        $(".nppm-site-view").hide();
                        $(".nppm-matrix-view").show();
                    }
                });
                
                // Search functionality
                $("#nppm-search").on("keyup", function() {
                    var searchTerm = $(this).val().toLowerCase();
                    
                    // Search in site view
                    $(".nppm-site-view tbody tr").each(function() {
                        var text = $(this).text().toLowerCase();
                        $(this).toggle(text.indexOf(searchTerm) > -1);
                    });
                    
                    // Search in matrix view (rows only, keep all columns)
                    $(".nppm-matrix-view tbody tr").each(function() {
                        var rowText = $(this).find("td:first").text().toLowerCase();
                        $(this).toggle(rowText.indexOf(searchTerm) > -1);
                    });
                    
                    updateStats();
                });
                
                // Select all for a site (in site view)
                $(".nppm-select-all-site").on("click", function(e) {
                    e.preventDefault();
                    var row = $(this).closest("tr");
                    var checkboxes = row.find("input[type=checkbox]");
                    var allChecked = checkboxes.filter(":checked").length === checkboxes.length;
                    checkboxes.prop("checked", !allChecked);
                    updateStats();
                });
                
                // Select all for a plugin (in matrix view)
                $(".nppm-select-all-plugin").on("click", function(e) {
                    e.preventDefault();
                    var row = $(this).closest("tr");
                    var checkboxes = row.find("input[type=checkbox]");
                    var allChecked = checkboxes.filter(":checked").length === checkboxes.length;
                    checkboxes.prop("checked", !allChecked);
                    updateStats();
                });
                
                // Select all for a specific site column (in matrix view)
                $(".nppm-select-col").on("click", function(e) {
                    e.preventDefault();
                    var colIndex = $(this).data("col");
                    var checkboxes = $(".nppm-matrix-view tbody tr td:nth-child(" + colIndex + ") input[type=checkbox]");
                    var allChecked = checkboxes.filter(":checked").length === checkboxes.length;
                    checkboxes.prop("checked", !allChecked);
                    updateStats();
                });
                
                // Update stats on checkbox change
                $("input[type=checkbox]").on("change", function() {
                    updateStats();
                });
                
                // Update statistics
                function updateStats() {
                    var totalSites = $(".nppm-site-view tbody tr:visible").length;
                    var totalDisabled = $(":checkbox:checked").length;
                    
                    $("#nppm-total-sites").text(totalSites);
                    $("#nppm-total-disabled").text(totalDisabled);
                }
                
                // Bulk actions
                $("#nppm-bulk-disable-all").on("click", function(e) {
                    e.preventDefault();
                    if (confirm("Disable all network plugins on all subsites?")) {
                        $(":checkbox:visible").prop("checked", true);
                        updateStats();
                    }
                });
                
                $("#nppm-bulk-enable-all").on("click", function(e) {
                    e.preventDefault();
                    if (confirm("Enable all network plugins on all subsites?")) {
                        $(":checkbox:visible").prop("checked", false);
                        updateStats();
                    }
                });
                
                // Initial stats
                updateStats();
            });
        ' );
    }
    
    /**
     * Handle form submission
     */
    public function handle_save() {
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

        update_site_option( $this->option_name, $data );

        wp_redirect( add_query_arg( 
            array( 
                'page' => 'per-site-plugin-overrides', 
                'updated' => '1' 
            ), 
            network_admin_url( 'plugins.php' ) 
        ) );
        exit;
    }
    
    /**
     * Filter active_sitewide_plugins to remove disabled plugins per-site
     */
    public function filter_plugins( $value ) {
        if ( ! is_object( $GLOBALS['current_blog'] ) ) {
            return $value;
        }

        $blog_id = isset( $GLOBALS['current_blog']->blog_id ) ? intval( $GLOBALS['current_blog']->blog_id ) : 0;
        if ( $blog_id <= 0 ) {
            return $value;
        }

        $disabled = get_site_option( $this->option_name, array() );
        if ( empty( $disabled ) || ! isset( $disabled[ $blog_id ] ) ) {
            return $value;
        }

        foreach ( $disabled[ $blog_id ] as $plugin_file => $_ ) {
            if ( isset( $value[ $plugin_file ] ) ) {
                unset( $value[ $plugin_file ] );
            }
        }

        return $value;
    }
    
    /**
     * Get plugin data
     */
    private function get_plugin_data( $plugin_file ) {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all = get_plugins();
        if ( isset( $all[ $plugin_file ] ) ) {
            $p = $all[ $plugin_file ];
            return array(
                'name' => ! empty( $p['Name'] ) ? $p['Name'] : $plugin_file,
                'version' => ! empty( $p['Version'] ) ? $p['Version'] : '',
                'file' => $plugin_file
            );
        }
        return array(
            'name' => $plugin_file,
            'version' => '',
            'file' => $plugin_file
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_die( __( 'Unauthorized', 'nppm' ) );
        }

        $sites = get_sites( array( 
            'number' => 10000,
            'orderby' => 'id',
            'order' => 'ASC'
        ) );
        
        $network_plugins = get_site_option( 'active_sitewide_plugins', array() );
        $disabled = get_site_option( $this->option_name, array() );
        
        $total_sites = count( $sites );
        $total_plugins = count( $network_plugins );
        $total_disabled = 0;
        
        foreach ( $disabled as $site_plugins ) {
            $total_disabled += count( $site_plugins );
        }

        ?>
        <div class="wrap nppm-wrap">
            <div class="nppm-header">
                <h1>Per-Site Plugin Overrides</h1>
            </div>
            
            <?php if ( isset( $_GET['updated'] ) ): ?>
                <div class="nppm-notice success">
                    <strong>✓ Settings saved successfully!</strong> Plugin overrides have been updated.
                </div>
            <?php endif; ?>

            <div class="nppm-notice info">
                <strong>How it works:</strong> Check the boxes for plugins you want to <strong>disable</strong> on specific subsites. 
                Network-activated plugins will be automatically deactivated on the selected sites.
            </div>

            <div class="nppm-stats">
                <div class="nppm-stat-item">
                    <div class="nppm-stat-label">Total Subsites</div>
                    <div class="nppm-stat-value" id="nppm-total-sites"><?php echo $total_sites; ?></div>
                </div>
                <div class="nppm-stat-item">
                    <div class="nppm-stat-label">Network Plugins</div>
                    <div class="nppm-stat-value"><?php echo $total_plugins; ?></div>
                </div>
                <div class="nppm-stat-item">
                    <div class="nppm-stat-label">Active Overrides</div>
                    <div class="nppm-stat-value" id="nppm-total-disabled"><?php echo $total_disabled; ?></div>
                </div>
            </div>

            <?php if ( empty( $network_plugins ) ) : ?>
                <div class="nppm-empty-state">
                    <p style="font-size: 18px; margin-bottom: 10px;">📦 No network-activated plugins found</p>
                    <p>Network-activate some plugins first to manage them here.</p>
                </div>
            <?php else : ?>
                
                <form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=nppm_save' ) ); ?>">
                    <?php wp_nonce_field( 'nppm-save' ); ?>
                    
                    <div class="nppm-controls">
                        <input 
                            type="text" 
                            id="nppm-search" 
                            class="nppm-search" 
                            placeholder="🔍 Search sites or plugins..."
                        />
                        
                        <div class="nppm-view-toggle">
                            <button type="button" class="nppm-view-btn active" data-view="site">
                                📋 By Site
                            </button>
                            <button type="button" class="nppm-view-btn" data-view="matrix">
                                🔢 Matrix View
                            </button>
                        </div>
                        
                        <div class="nppm-bulk-actions" style="margin-left: auto;">
                            <button type="button" id="nppm-bulk-disable-all" class="button">
                                Disable All
                            </button>
                            <button type="button" id="nppm-bulk-enable-all" class="button">
                                Enable All
                            </button>
                        </div>
                    </div>

                    <!-- SITE VIEW (Default) -->
                    <div class="nppm-site-view">
                        <div class="nppm-table-container">
                            <table class="nppm-table">
                                <thead>
                                    <tr>
                                        <th style="width: 30%;">Subsite</th>
                                        <th>Disabled Network Plugins</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $sites as $site ) : 
                                        $blog_id = (int) $site->blog_id;
                                        switch_to_blog( $blog_id );
                                        $site_name = get_bloginfo( 'name' );
                                        $site_url = get_bloginfo( 'url' );
                                        restore_current_blog();
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="nppm-site-name"><?php echo esc_html( $site_name ); ?></div>
                                                <div class="nppm-site-meta">
                                                    <?php echo esc_html( $site_url ); ?><br>
                                                    Site ID: <?php echo $blog_id; ?>
                                                </div>
                                                <a href="#" class="nppm-select-all-site">Select/Deselect All</a>
                                            </td>
                                            <td>
                                                <?php foreach ( $network_plugins as $plugin_file => $val ) :
                                                    $plugin_data = $this->get_plugin_data( $plugin_file );
                                                    $checked = isset( $disabled[ $blog_id ] ) && isset( $disabled[ $blog_id ][ $plugin_file ] );
                                                ?>
                                                    <label class="nppm-plugin-label">
                                                        <input 
                                                            type="checkbox" 
                                                            name="nppm[<?php echo $blog_id; ?>][<?php echo esc_attr( $plugin_file ); ?>]" 
                                                            value="1" 
                                                            <?php checked( $checked ); ?>
                                                        />
                                                        <span class="nppm-plugin-name">
                                                            <?php echo esc_html( $plugin_data['name'] ); ?>
                                                            <?php if ( $plugin_data['version'] ) : ?>
                                                                <span style="color: #999;">v<?php echo esc_html( $plugin_data['version'] ); ?></span>
                                                            <?php endif; ?>
                                                        </span>
                                                        <span class="nppm-plugin-file"><?php echo esc_html( $plugin_file ); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- MATRIX VIEW -->
                    <div class="nppm-matrix-view" style="display: none;">
                        <div class="nppm-table-container">
                            <table class="nppm-table">
                                <thead>
                                    <tr>
                                        <th style="width: 25%;">Plugin</th>
                                        <?php foreach ( $sites as $site ) : 
                                            $blog_id = (int) $site->blog_id;
                                            switch_to_blog( $blog_id );
                                            $site_name = get_bloginfo( 'name' );
                                            restore_current_blog();
                                        ?>
                                            <th class="nppm-checkbox-cell">
                                                <div><?php echo esc_html( wp_trim_words( $site_name, 3, '...' ) ); ?></div>
                                                <a href="#" class="nppm-select-col" data-col="<?php echo ( array_search( $site, $sites ) + 2 ); ?>">
                                                    <small>All</small>
                                                </a>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $network_plugins as $plugin_file => $val ) :
                                        $plugin_data = $this->get_plugin_data( $plugin_file );
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo esc_html( $plugin_data['name'] ); ?></strong>
                                                <?php if ( $plugin_data['version'] ) : ?>
                                                    <span style="color: #999; font-size: 11px;">v<?php echo esc_html( $plugin_data['version'] ); ?></span>
                                                <?php endif; ?>
                                                <div style="color: #666; font-size: 11px; margin-top: 3px;">
                                                    <?php echo esc_html( $plugin_file ); ?>
                                                </div>
                                                <a href="#" class="nppm-select-all-plugin">Select/Deselect All</a>
                                            </td>
                                            <?php foreach ( $sites as $site ) : 
                                                $blog_id = (int) $site->blog_id;
                                                $checked = isset( $disabled[ $blog_id ] ) && isset( $disabled[ $blog_id ][ $plugin_file ] );
                                            ?>
                                                <td class="nppm-checkbox-cell">
                                                    <input 
                                                        type="checkbox" 
                                                        name="nppm[<?php echo $blog_id; ?>][<?php echo esc_attr( $plugin_file ); ?>]" 
                                                        value="1" 
                                                        <?php checked( $checked ); ?>
                                                    />
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="nppm-submit-wrapper">
                        <?php submit_button( 'Save Changes', 'primary large', 'submit', false ); ?>
                        <span style="margin-left: 15px; color: #666;">
                            Changes will take effect immediately after saving.
                        </span>
                    </div>
                </form>
                
            <?php endif; ?>
        </div>
        <?php
    }
}

// Initialize the plugin
if ( is_multisite() ) {
    new Network_Plugin_Manager();
}
