<?php
/*
Plugin Name: Network Plugin Manager (per-site)
Description: Admin UI to selectively disable network-enabled plugins on individual subsites.
Version: 1.10
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
    private $version = '1.10';
    
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

        // Enqueue Dashicons (Built-in WP)
        wp_enqueue_style( 'dashicons' );

        // Enqueue Google Fonts
        wp_enqueue_style( 'nppm-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', array(), null );
        
        // Inline CSS - Attach to nppm-google-fonts for guaranteed output
        $css = '
            :root {
                --nppm-primary: #6366f1;
                --nppm-primary-light: #818cf8;
                --nppm-primary-bg: #eef2ff;
                --nppm-success: #10b981;
                --nppm-info: #0ea5e9;
                --nppm-warning: #f59e0b;
                --nppm-danger: #ef4444;
                --nppm-bg: #f8fafc;
                --nppm-card: #ffffff;
                --nppm-text: #1e293b;
                --nppm-text-muted: #64748b;
                --nppm-border: #e2e8f0;
                --nppm-radius: 12px;
                --nppm-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
                --nppm-shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
                --nppm-glass: rgba(255, 255, 255, 0.8);
            }

            .nppm-wrap { 
                max-width: 1400px; 
                margin: 20px auto; 
                font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                color: var(--nppm-text);
            }

            .nppm-wrap h1 {
                font-size: 28px !important;
                font-weight: 700 !important;
                color: var(--nppm-text) !important;
                margin-bottom: 24px !important;
                padding: 0 !important;
            }

            /* Notices */
            .nppm-notice { 
                padding: 16px; 
                margin: 20px 0; 
                border-radius: var(--nppm-radius);
                border-left: 5px solid;
                background: white;
                box-shadow: var(--nppm-shadow);
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .nppm-notice.success { border-color: var(--nppm-success); background: #f0fdf4; color: #166534; }
            .nppm-notice.info { border-color: var(--nppm-info); background: #f0f9ff; color: #075985; }

            /* Stats Bar */
            .nppm-stats { 
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            .nppm-stat-item { 
                background: var(--nppm-card);
                padding: 24px;
                border-radius: var(--nppm-radius);
                box-shadow: var(--nppm-shadow);
                border: 1px solid var(--nppm-border);
                transition: transform 0.2s;
            }
            .nppm-stat-item:hover { transform: translateY(-2px); }
            .nppm-stat-label { font-size: 13px; color: var(--nppm-text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
            .nppm-stat-value { font-size: 32px; font-weight: 700; color: var(--nppm-primary); margin-top: 8px; }

            /* Toolbar */
            .nppm-toolbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: var(--nppm-card);
                padding: 16px 20px;
                border-radius: var(--nppm-radius);
                border: 1px solid var(--nppm-border);
                margin-bottom: 24px;
                box-shadow: var(--nppm-shadow);
                position: sticky;
                top: 32px;
                z-index: 100;
                backdrop-filter: blur(8px);
                background: var(--nppm-glass);
            }

            .nppm-controls { display: flex; gap: 16px; align-items: center; }
            .nppm-search-wrapper { position: relative; }
            .nppm-search { 
                padding: 10px 16px 10px 40px !important; 
                width: 320px; 
                border: 1px solid var(--nppm-border) !important; 
                border-radius: 8px !important;
                font-size: 14px !important;
                transition: all 0.2s !important;
                height: auto !important;
                box-sizing: border-box !important;
            }
            .nppm-search:focus { border-color: var(--nppm-primary) !important; box-shadow: 0 0 0 3px var(--nppm-primary-bg) !important; outline: none; }
            .nppm-search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--nppm-text-muted); z-index: 1; }

            .nppm-btn {
                padding: 8px 16px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                border: 1px solid var(--nppm-border);
                background: white;
                transition: all 0.2s;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
            }
            .nppm-btn:hover { background: #f1f5f9; border-color: var(--nppm-text-muted); }
            .nppm-btn.primary { background: var(--nppm-primary); color: white; border: none; }
            .nppm-btn.primary:hover { background: var(--nppm-primary-hover); box-shadow: var(--nppm-shadow); }
            
            .nppm-view-btn.active { background: var(--nppm-primary-bg); color: var(--nppm-primary); border-color: var(--nppm-primary-light); }

            /* Site Cards View */
            .nppm-cards-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
                gap: 24px;
            }
            .nppm-site-card {
                background: var(--nppm-card);
                border-radius: var(--nppm-radius);
                border: 1px solid var(--nppm-border);
                box-shadow: var(--nppm-shadow);
                overflow: hidden;
                display: flex;
                flex-direction: column;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .nppm-site-card:hover { box-shadow: var(--nppm-shadow-lg); transform: translateY(-4px); }
            
            .nppm-card-header {
                padding: 20px;
                background: #f8fafc;
                border-bottom: 1px solid var(--nppm-border);
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
            }
            .nppm-site-info h3 { margin: 0; font-size: 18px; font-weight: 700; color: var(--nppm-text); }
            .nppm-site-info span { font-size: 12px; color: var(--nppm-text-muted); display: block; margin-top: 4px; }
            
            .nppm-card-body { padding: 20px; }
            
            /* Custom Toggles */
            .nppm-plugin-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 8px;
                background: #ffffff;
                border: 1px solid #f1f5f9;
                transition: all 0.2s;
            }
            .nppm-plugin-item:hover { background: #f8fafc; border-color: var(--nppm-border); }
            
            .nppm-plugin-meta { flex: 1; }
            .nppm-plugin-title { font-weight: 600; font-size: 14px; display: block; }
            .nppm-plugin-version { font-size: 11px; color: var(--nppm-text-muted); margin-top: 2px; }

            /* Switch Styling */
            .nppm-switch {
                position: relative;
                display: inline-block;
                width: 44px;
                height: 24px;
                flex-shrink: 0;
            }
            .nppm-switch input { opacity: 0; width: 0; height: 0; }
            .nppm-slider {
                position: absolute;
                cursor: pointer;
                top: 0; left: 0; right: 0; bottom: 0;
                background-color: #e2e8f0;
                transition: .4s;
                border-radius: 24px;
            }
            .nppm-slider:before {
                position: absolute;
                content: "";
                height: 18px; width: 18px;
                left: 3px; bottom: 3px;
                background-color: white;
                transition: .4s;
                border-radius: 50%;
                box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }
            input:checked + .nppm-slider { background-color: var(--nppm-danger); }
            input:checked + .nppm-slider:before { transform: translateX(20px); }
            
            /* Matrix View Modernization */
            .nppm-matrix-container {
                background: white;
                border-radius: var(--nppm-radius);
                border: 1px solid var(--nppm-border);
                box-shadow: var(--nppm-shadow);
                overflow: auto;
                max-height: 80vh;
            }
            .nppm-modern-table { width: 100%; border-collapse: collapse; min-width: 800px; }
            .nppm-modern-table th { 
                background: #f8fafc; 
                padding: 16px; 
                text-align: left; 
                font-weight: 700; 
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--nppm-text-muted);
                border-bottom: 2px solid var(--nppm-border);
                position: sticky;
                top: 0;
                z-index: 20;
            }
            .nppm-modern-table td { padding: 16px; border-bottom: 1px solid var(--nppm-border); vertical-align: middle; }
            .nppm-modern-table tr:hover { background: #fdfdfd; }
            
            .nppm-sticky-col { position: sticky; left: 0; background: white !important; z-index: 10; border-right: 2px solid var(--nppm-border); }

            /* Footer Wrapper */
            .nppm-footer {
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: var(--nppm-glass);
                backdrop-filter: blur(10px);
                padding: 12px 24px;
                border-radius: 100px;
                box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
                border: 1px solid var(--nppm-border);
                display: flex;
                align-items: center;
                gap: 20px;
                z-index: 1000;
                width: fit-content;
            }
            
            .nppm-footer-text { font-size: 13px; color: var(--nppm-text-muted); font-weight: 500; }
            
            /* Animations */
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .nppm-animate { animation: fadeIn 0.4s ease-out forwards; }
            
            .hidden { display: none !important; }
        ';
        wp_add_inline_style( 'nppm-google-fonts', $css );
        
        // Inline JavaScript - Attach to jquery-core
        $js = '
            jQuery(document).ready(function($) {
                // View toggle logic
                $(".nppm-view-btn").on("click", function() {
                    const view = $(this).data("view");
                    $(".nppm-view-btn").removeClass("active");
                    $(this).addClass("active");
                    
                    if (view === "cards") {
                        $(".nppm-cards-grid").removeClass("hidden");
                        $(".nppm-matrix-container").addClass("hidden");
                    } else {
                        $(".nppm-cards-grid").addClass("hidden");
                        $(".nppm-matrix-container").removeClass("hidden");
                    }
                });
                
                // Search functionality with debounce
                let searchTimeout;
                $("#nppm-search").on("input", function() {
                    clearTimeout(searchTimeout);
                    const searchTerm = $(this).val().toLowerCase();
                    
                    searchTimeout = setTimeout(() => {
                        $(".nppm-site-card").each(function() {
                            const text = $(this).find(".nppm-site-info").text().toLowerCase();
                            $(this).toggleClass("hidden", text.indexOf(searchTerm) === -1);
                        });
                        
                        $(".nppm-matrix-container table tbody tr").each(function() {
                            const text = $(this).find("td:first").text().toLowerCase();
                            $(this).toggleClass("hidden", text.indexOf(searchTerm) === -1);
                        });
                        
                        updateStats();
                    }, 200);
                });
                
                $(".nppm-select-all-site").on("click", function(e) {
                    e.preventDefault();
                    const checkboxes = $(this).closest(".nppm-site-card").find("input[type=checkbox]");
                    const someUnchecked = checkboxes.filter(":not(:checked)").length > 0;
                    checkboxes.prop("checked", someUnchecked).trigger("change");
                });

                $(".nppm-select-col").on("click", function(e) {
                    e.preventDefault();
                    const colIndex = $(this).parent().index() + 1;
                    const checkboxes = $(".nppm-modern-table tbody tr td:nth-child(" + colIndex + ") input[type=checkbox]");
                    const someUnchecked = checkboxes.filter(":not(:checked)").length > 0;
                    checkboxes.prop("checked", someUnchecked).trigger("change");
                });
                
                $("#nppm-bulk-disable").on("click", function() {
                    if(confirm("Disable all network plugins on visible sites?")) {
                        $(".nppm-site-card:not(.hidden) input[type=checkbox]").prop("checked", true).trigger("change");
                    }
                });
                
                $("#nppm-bulk-enable").on("click", function() {
                    if(confirm("Enable all network plugins on visible sites?")) {
                        $(".nppm-site-card:not(.hidden) input[type=checkbox]").prop("checked", false).trigger("change");
                    }
                });

                $("input[type=checkbox]").on("change", function() {
                    updateStats();
                });
                
                function updateStats() {
                    $("#nppm-total-sites").text($(".nppm-site-card:not(.hidden)").length);
                    $("#nppm-total-disabled").text($(":checkbox:checked").length);
                }
                updateStats();
            });
        ';
        wp_add_inline_script( 'jquery-core', $js );
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
            <header class="nppm-header">
                <h1>Per-Site Plugin Overrides</h1>
            </header>
            
            <?php if ( isset( $_GET['updated'] ) ): ?>
                <div class="nppm-notice success nppm-animate">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <div><strong>Success!</strong> Your plugin overrides have been saved and applied.</div>
                </div>
            <?php endif; ?>

            <div class="nppm-notice info nppm-animate">
                <span class="dashicons dashicons-info-outline"></span>
                <div>
                    <strong>Pro Tip:</strong> Toggle the switches to <strong>disable</strong> network-activated plugins on specific sites.
                </div>
            </div>

            <div class="nppm-stats nppm-animate">
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
                    <div class="nppm-stat-value" id="nppm-total-disabled" style="color: var(--nppm-danger);"><?php echo $total_disabled; ?></div>
                </div>
            </div>

            <?php if ( empty( $network_plugins ) ) : ?>
                <div class="nppm-empty-state nppm-animate">
                    <div style="font-size: 64px; margin-bottom: 20px;">📦</div>
                    <p style="font-size: 20px; font-weight: 600;">No Network Plugins Found</p>
                </div>
            <?php else : ?>
                
                <form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=nppm_save' ) ); ?>">
                    <?php wp_nonce_field( 'nppm-save' ); ?>
                    
                    <div class="nppm-toolbar nppm-animate">
                        <div class="nppm-controls">
                            <div class="nppm-search-wrapper">
                                <span class="dashicons dashicons-search nppm-search-icon"></span>
                                <input type="text" id="nppm-search" class="nppm-search" placeholder="Search sites..." />
                            </div>
                            
                            <div class="nppm-view-toggle">
                                <button type="button" class="nppm-btn nppm-view-btn active" data-view="cards">
                                    <span class="dashicons dashicons-grid-view"></span> Cards
                                </button>
                                <button type="button" class="nppm-btn nppm-view-btn" data-view="matrix">
                                    <span class="dashicons dashicons-media-spreadsheet"></span> Matrix
                                </button>
                            </div>
                        </div>
                        
                        <div class="nppm-bulk-actions">
                            <button type="button" id="nppm-bulk-disable" class="nppm-btn">Disable All</button>
                            <button type="button" id="nppm-bulk-enable" class="nppm-btn">Enable All</button>
                        </div>
                    </div>

                    <div class="nppm-cards-grid nppm-animate">
                        <?php foreach ( $sites as $site ) : 
                            $blog_id = (int) $site->blog_id;
                            switch_to_blog( $blog_id );
                            $site_name = get_bloginfo( 'name' );
                            $site_url = get_bloginfo( 'url' );
                            restore_current_blog();
                        ?>
                            <div class="nppm-site-card">
                                <div class="nppm-card-header">
                                    <div class="nppm-site-info">
                                        <h3><?php echo esc_html( $site_name ); ?></h3>
                                        <span><?php echo esc_html( parse_url($site_url, PHP_URL_HOST) ); ?></span>
                                    </div>
                                    <a href="#" class="nppm-select-all-site"><span class="dashicons dashicons-image-rotate"></span></a>
                                </div>
                                <div class="nppm-card-body">
                                    <?php foreach ( $network_plugins as $plugin_file => $val ) :
                                        $plugin_data = $this->get_plugin_data( $plugin_file );
                                        $checked = isset( $disabled[ $blog_id ] ) && isset( $disabled[ $blog_id ][ $plugin_file ] );
                                    ?>
                                        <div class="nppm-plugin-item">
                                            <div class="nppm-plugin-meta">
                                                <span class="nppm-plugin-title"><?php echo esc_html( $plugin_data['name'] ); ?></span>
                                                <span class="nppm-plugin-version">v<?php echo esc_html( $plugin_data['version'] ); ?></span>
                                            </div>
                                            <label class="nppm-switch">
                                                <input type="checkbox" name="nppm[<?php echo $blog_id; ?>][<?php echo esc_attr( $plugin_file ); ?>]" value="1" <?php checked( $checked ); ?> />
                                                <span class="nppm-slider"></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="nppm-matrix-container hidden nppm-animate">
                        <table class="nppm-modern-table">
                            <thead>
                                <tr>
                                    <th class="nppm-sticky-col">Plugin</th>
                                    <?php foreach ( $sites as $site ) : 
                                        $blog_id = (int) $site->blog_id;
                                        switch_to_blog( $blog_id );
                                        $site_name = get_bloginfo( 'name' );
                                        restore_current_blog();
                                    ?>
                                        <th style="text-align: center;"><div class="nppm-select-col"><?php echo esc_html( wp_trim_words( $site_name, 2, "" ) ); ?></div></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $network_plugins as $plugin_file => $val ) :
                                    $plugin_data = $this->get_plugin_data( $plugin_file );
                                ?>
                                    <tr>
                                        <td class="nppm-sticky-col"><strong><?php echo esc_html( $plugin_data['name'] ); ?></strong></td>
                                        <?php foreach ( $sites as $site ) : 
                                            $blog_id = (int) $site->blog_id;
                                            $checked = isset( $disabled[ $blog_id ] ) && isset( $disabled[ $blog_id ][ $plugin_file ] );
                                        ?>
                                            <td style="text-align: center;">
                                                <label class="nppm-switch" style="transform: scale(0.8);">
                                                    <input type="checkbox" name="nppm[<?php echo $blog_id; ?>][<?php echo esc_attr( $plugin_file ); ?>]" value="1" <?php checked( $checked ); ?> />
                                                    <span class="nppm-slider"></span>
                                                </label>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="nppm-footer">
                        <div class="nppm-footer-text">Changes take effect immediately.</div>
                        <button type="submit" name="submit" class="nppm-btn primary">Save Configuration</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
}

if ( is_multisite() ) {
    new Network_Plugin_Manager();
}
