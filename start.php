<?php
/**
 * Plugin Name: Network Plugin Manager (per-site)
 * Description: Admin UI to selectively disable network-enabled plugins on individual subsites.
 * Version: 1.0
 * Author: UltiWP ( Previously Homescriptone Solutions Ltd )
 * Author URI: https://ultiwp.com
 * Author Email: contact@homescriptone.com
 * Network: true
 * License: GPL2
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Subsite_Plugin_Manager {
    
    private $option_name = 'spm_disabled_plugins';
    
    public function __construct() {
        // Add admin menu
        add_action('network_admin_menu', array($this, 'add_admin_menu'));
        
        // Filter to disable plugins per site
        add_filter('site_option_active_sitewide_plugins', array($this, 'modify_sitewide_plugins'));
        
        // Handle form submission
        add_action('admin_init', array($this, 'handle_form_submission'));
        
        // Enqueue admin styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }
    
    /**
     * Add admin menu to Network Admin
     */
    public function add_admin_menu() {
        add_submenu_page(
            'settings.php',
            'Subsite Plugin Manager',
            'Subsite Plugins',
            'manage_network_options',
            'subsite-plugin-manager',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        if ($hook !== 'settings_page_subsite-plugin-manager') {
            return;
        }
        
        wp_enqueue_style('spm-admin-style', false);
        wp_add_inline_style('spm-admin-style', '
            .spm-container { max-width: 1200px; margin: 20px 0; }
            .spm-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .spm-table th { background: #f7f7f7; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #ddd; }
            .spm-table td { padding: 12px; border-bottom: 1px solid #eee; }
            .spm-table tr:hover { background: #f9f9f9; }
            .spm-checkbox { margin: 0; }
            .spm-plugin-name { font-weight: 500; color: #2271b1; }
            .spm-site-name { font-weight: 500; }
            .spm-header { margin-bottom: 20px; }
            .spm-success { padding: 10px 15px; background: #d4edda; border-left: 4px solid #28a745; margin: 15px 0; }
            .spm-info { padding: 15px; background: #e7f3ff; border-left: 4px solid #2271b1; margin: 15px 0; }
            .spm-submit-wrapper { margin-top: 20px; padding: 15px; background: #f7f7f7; }
        ');
        
        wp_enqueue_script('spm-admin-script', false);
        wp_add_inline_script('spm-admin-script', '
            jQuery(document).ready(function($) {
                // Select all checkboxes for a plugin
                $(".spm-select-all-plugin").on("change", function() {
                    var plugin = $(this).data("plugin");
                    var isChecked = $(this).prop("checked");
                    $(".spm-checkbox[data-plugin=\"" + plugin + "\"]").prop("checked", isChecked);
                });
                
                // Select all checkboxes for a site
                $(".spm-select-all-site").on("change", function() {
                    var site = $(this).data("site");
                    var isChecked = $(this).prop("checked");
                    $(".spm-checkbox[data-site=\"" + site + "\"]").prop("checked", isChecked);
                });
            });
        ');
    }
    
    /**
     * Handle form submission
     */
    public function handle_form_submission() {
        if (!isset($_POST['spm_submit']) || !isset($_POST['spm_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['spm_nonce'], 'spm_save_settings')) {
            return;
        }
        
        if (!current_user_can('manage_network_options')) {
            return;
        }
        
        $disabled_plugins = isset($_POST['disabled_plugins']) ? $_POST['disabled_plugins'] : array();
        
        // Sanitize the input
        $sanitized_data = array();
        foreach ($disabled_plugins as $site_id => $plugins) {
            $site_id = intval($site_id);
            $sanitized_data[$site_id] = array_map('sanitize_text_field', $plugins);
        }
        
        update_site_option($this->option_name, $sanitized_data);
        
        // Set a transient for success message
        set_transient('spm_settings_saved', true, 30);
        
        wp_redirect(add_query_arg('page', 'subsite-plugin-manager', network_admin_url('settings.php')));
        exit;
    }
    
    /**
     * Modify sitewide plugins based on settings
     */
    public function modify_sitewide_plugins($value) {
        global $current_blog;
        
        $disabled_plugins = get_site_option($this->option_name, array());
        
        if (isset($disabled_plugins[$current_blog->blog_id]) && is_array($disabled_plugins[$current_blog->blog_id])) {
            foreach ($disabled_plugins[$current_blog->blog_id] as $plugin) {
                unset($value[$plugin]);
            }
        }
        
        return $value;
    }
    
    /**
     * Get all network activated plugins
     */
    private function get_network_plugins() {
        $active_sitewide_plugins = get_site_option('active_sitewide_plugins', array());
        $plugins = array();
        
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        
        foreach ($active_sitewide_plugins as $plugin_file => $timestamp) {
            if (isset($all_plugins[$plugin_file])) {
                $plugins[$plugin_file] = $all_plugins[$plugin_file]['Name'];
            }
        }
        
        return $plugins;
    }
    
    /**
     * Get all subsites
     */
    private function get_subsites() {
        $sites = get_sites(array(
            'number' => 1000,
            'orderby' => 'id',
            'order' => 'ASC'
        ));
        
        $subsites = array();
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            $subsites[$site->blog_id] = get_bloginfo('name') . ' (' . get_bloginfo('url') . ')';
            restore_current_blog();
        }
        
        return $subsites;
    }
    
    /**
     * Render the admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_network_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $network_plugins = $this->get_network_plugins();
        $subsites = $this->get_subsites();
        $disabled_plugins = get_site_option($this->option_name, array());
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (get_transient('spm_settings_saved')): ?>
                <div class="spm-success">
                    <strong>Settings saved successfully!</strong>
                </div>
                <?php delete_transient('spm_settings_saved'); ?>
            <?php endif; ?>
            
            <div class="spm-info">
                <strong>How to use:</strong> Check the boxes for plugins you want to <strong>disable</strong> on specific subsites. 
                Network-activated plugins will be hidden on the selected subsites.
            </div>
            
            <div class="spm-container">
                <form method="post" action="">
                    <?php wp_nonce_field('spm_save_settings', 'spm_nonce'); ?>
                    
                    <table class="spm-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Plugin</th>
                                <?php foreach ($subsites as $site_id => $site_name): ?>
                                    <th style="text-align: center;">
                                        <div class="spm-site-name"><?php echo esc_html($site_name); ?></div>
                                        <label style="font-weight: normal; font-size: 11px;">
                                            <input type="checkbox" class="spm-select-all-site" data-site="<?php echo esc_attr($site_id); ?>">
                                            Select All
                                        </label>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($network_plugins)): ?>
                                <tr>
                                    <td colspan="<?php echo count($subsites) + 1; ?>">
                                        No network-activated plugins found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($network_plugins as $plugin_file => $plugin_name): ?>
                                    <tr>
                                        <td>
                                            <div class="spm-plugin-name"><?php echo esc_html($plugin_name); ?></div>
                                            <div style="color: #666; font-size: 12px; margin-top: 3px;">
                                                <?php echo esc_html($plugin_file); ?>
                                            </div>
                                            <label style="font-size: 11px; margin-top: 5px; display: inline-block;">
                                                <input type="checkbox" class="spm-select-all-plugin" data-plugin="<?php echo esc_attr($plugin_file); ?>">
                                                Disable on all sites
                                            </label>
                                        </td>
                                        <?php foreach ($subsites as $site_id => $site_name): ?>
                                            <td style="text-align: center;">
                                                <?php
                                                $is_checked = isset($disabled_plugins[$site_id]) && 
                                                             in_array($plugin_file, $disabled_plugins[$site_id]);
                                                ?>
                                                <input 
                                                    type="checkbox" 
                                                    class="spm-checkbox" 
                                                    name="disabled_plugins[<?php echo esc_attr($site_id); ?>][]" 
                                                    value="<?php echo esc_attr($plugin_file); ?>"
                                                    data-plugin="<?php echo esc_attr($plugin_file); ?>"
                                                    data-site="<?php echo esc_attr($site_id); ?>"
                                                    <?php checked($is_checked); ?>
                                                >
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <div class="spm-submit-wrapper">
                        <?php submit_button('Save Settings', 'primary', 'spm_submit', false); ?>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
if (is_multisite()) {
    new Subsite_Plugin_Manager();
}
