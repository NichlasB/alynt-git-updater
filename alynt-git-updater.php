<?php
/**
 * Plugin Name: Alynt Git Updater
 * Plugin URI: https://github.com/NichlasB/alynt-git-updater
 * Description: Enables automatic updates for plugins hosted on GitHub.
 * Version: 1.0.1
 * Author: Alynt
 * Author URI: https://alynt.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: alynt-git-updater
 * Domain Path: /languages
 *
 * @package Alynt_Git_Updater
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('ALYNT_GIT_VERSION', '1.0.1');
define('ALYNT_GIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALYNT_GIT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Simple class loader
require_once ALYNT_GIT_PLUGIN_DIR . 'includes/Updater.php';
require_once ALYNT_GIT_PLUGIN_DIR . 'includes/Webhook.php';

/**
 * Initialize the plugin.
 */
function alynt_git_init() {
    // Create includes directory if it doesn't exist
    if (!file_exists(ALYNT_GIT_PLUGIN_DIR . 'includes')) {
        mkdir(ALYNT_GIT_PLUGIN_DIR . 'includes', 0755);
    }

    // Initialize webhook handler
    new \Alynt_Git_Updater\Webhook();
    
    // Initialize self-updater
    new \Alynt_Git_Updater\Updater(__FILE__);
}
add_action('plugins_loaded', 'alynt_git_init');

/**
 * Initialize updater for plugins with GitHub headers
 */
function alynt_git_plugin_updater() {
    // Get all plugins
    $plugins = get_plugins();
    
    foreach ($plugins as $plugin_file => $plugin) {
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        
        // Check if plugin has GitHub headers
        $headers = get_file_data($plugin_path, ['GitHub Repository' => 'GitHub Repository']);
        
        if (!empty($headers['GitHub Repository'])) {
            new \Alynt_Git_Updater\Updater($plugin_path);
        }
    }
}
add_action('admin_init', 'alynt_git_plugin_updater');

/**
 * Activation hook.
 */
function alynt_git_activate() {
    // Activation tasks if needed
}
register_activation_hook(__FILE__, 'alynt_git_activate');

/**
 * Deactivation hook.
 */
function alynt_git_deactivate() {
    // Deactivation tasks if needed
}
register_deactivation_hook(__FILE__, 'alynt_git_deactivate');