<?php
/**
 * GitHub Updater Class
 *
 * @package Alynt_Git_Updater
 */

namespace Alynt_Git_Updater;

if (!defined('ABSPATH')) {
    exit;
}

class Updater {
    /**
     * Plugin file path
     *
     * @var string
     */
    private $file;

    /**
     * Plugin basename
     *
     * @var string
     */
    private $basename;

    /**
     * GitHub username
     *
     * @var string
     */
    private $username;

    /**
     * GitHub repository name
     *
     * @var string
     */
    private $repository;

    /**
     * GitHub branch name
     *
     * @var string
     */
    private $branch;

    /**
     * Plugin data
     *
     * @var array
     */
    private $plugin_data;

    /**
     * GitHub API response
     *
     * @var array
     */
    private $github_response;

    /**
     * Constructor
     *
     * @param string $file Plugin file path
     */
    public function __construct($file) {
        $this->file = $file;
        $this->basename = plugin_basename($file);
        $this->plugin_data = get_plugin_data($file);

        // Parse GitHub information from plugin headers
        $this->parse_github_details();

        // Initialize updater
        $this->init();
    }

    /**
     * Initialize the updater
     */
    private function init() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_popup'], 10, 3);
        add_filter('upgrader_pre_download', [$this, 'pre_download'], 10, 3);
        add_filter('upgrader_source_selection', [$this, 'source_selection'], 10, 4);
        add_filter('plugin_row_meta', [$this, 'modify_plugin_row_meta'], 10, 2);
        add_filter('plugin_action_links', [$this, 'add_plugin_action_links'], 10, 2);
    }

    /**
     * Parse GitHub repository details from plugin headers
     */
    private function parse_github_details() {
        $headers = get_file_data($this->file, [
            'GitHub Repository' => 'GitHub Repository',
            'GitHub Branch' => 'GitHub Branch'
        ]);

        if (!empty($headers['GitHub Repository'])) {
            $repo_parts = explode('/', $headers['GitHub Repository']);
            if (count($repo_parts) === 2) {
                $this->username = trim($repo_parts[0]);
                $this->repository = trim($repo_parts[1]);
            }
        }

        $this->branch = !empty($headers['GitHub Branch']) ? trim($headers['GitHub Branch']) : 'main';
    }

    /**
     * Get GitHub API response
     *
     * @return array|bool
     */
    private function get_github_info() {
        if (!empty($this->github_response)) {
            return $this->github_response;
        }

        $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases/latest',
            $this->username,
            $this->repository
        );

        $response = wp_remote_get($request_uri);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            return false;
        }

        $this->github_response = json_decode($body);
        return $this->github_response;
    }

    /**
     * Check for plugin updates
     *
     * @param object $transient Transient data
     * @return object
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $github_data = $this->get_github_info();
        if (!$github_data) {
            return $transient;
        }

        $version = ltrim($github_data->tag_name, 'v');
        $current_version = $this->plugin_data['Version'];

        if (version_compare($version, $current_version, '>')) {
            $plugin = new \stdClass();
            $plugin->slug = dirname($this->basename);
            $plugin->plugin = $this->basename;
            $plugin->new_version = $version;
            $plugin->tested = get_bloginfo('version');
            $plugin->package = $github_data->zipball_url;
            $plugin->url = $this->plugin_data['PluginURI'];

            if (!empty($github_data->html_url)) {
                $plugin->url = $github_data->html_url;
            }

            $transient->response[$this->basename] = $plugin;
        }

        return $transient;
    }

    /**
     * Handle the plugin details popup
     *
     * @param false|object|array $result The result object or array
     * @param string $action The API action being performed
     * @param object $args Plugin arguments
     * @return false|object
     */
    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== dirname($this->basename)) {
            return $result;
        }

        $github_data = $this->get_github_info();
        if (!$github_data) {
            return $result;
        }

        $plugin_info = new \stdClass();
        $plugin_info->name = $this->plugin_data['Name'];
        $plugin_info->slug = dirname($this->basename);
        $plugin_info->version = ltrim($github_data->tag_name, 'v');
        $plugin_info->author = $this->plugin_data['Author'];
        $plugin_info->homepage = $this->plugin_data['PluginURI'];
        $plugin_info->requires = '5.0';
        $plugin_info->tested = get_bloginfo('version');
        $plugin_info->downloaded = 0;
        $plugin_info->last_updated = $github_data->published_at;
        $plugin_info->sections = [
            'description' => $this->plugin_data['Description'],
            'changelog' => $this->get_changelog($github_data)
        ];
        
        return $plugin_info;
    }

    /**
     * Format changelog from GitHub release
     *
     * @param object $github_data GitHub API response
     * @return string
     */
    private function get_changelog($github_data) {
        $changelog = '<h4>Changes in v' . ltrim($github_data->tag_name, 'v') . '</h4>';
        $changelog .= '<pre>' . esc_html($github_data->body) . '</pre>';
        
        return $changelog;
    }

    /**
     * Pre-download checks
     *
     * @param bool $reply Whether to bail without returning the package
     * @param string $package Package URL
     * @param \WP_Upgrader $upgrader Upgrader instance
     * @return bool|string
     */
    public function pre_download($reply, $package, $upgrader) {
        if (strpos($package, 'api.github.com') === false) {
            return $reply;
        }
        return $reply;
    }

    /**
     * Rename the downloaded directory to match the plugin's directory
     *
     * @param string $source Source directory
     * @param string $remote_source Remote source directory
     * @param \WP_Upgrader $upgrader Upgrader instance
     * @param array $args Extra arguments
     * @return string
     */
    public function source_selection($source, $remote_source, $upgrader, $args = []) {
        global $wp_filesystem;
        
        if (!isset($args['plugin']) || $args['plugin'] !== $this->basename) {
            return $source;
        }

        $desired_slug = dirname($this->basename);
        
        $source_files = $wp_filesystem->dirlist($source);
        if (count($source_files) === 1) {
            $first_file = array_shift($source_files);
            if ($first_file['type'] === 'd') {
                $source_dir = trailingslashit($source) . trailingslashit($first_file['name']);
                $new_source = trailingslashit($remote_source) . $desired_slug;
                
                if ($source_dir !== $new_source) {
                    if ($wp_filesystem->exists($new_source)) {
                        $wp_filesystem->delete($new_source, true);
                    }
                    $wp_filesystem->move($source_dir, $new_source);
                    return $new_source;
                }
            }
        }
        
        return $source;
    }

    /**
     * Modify plugin row meta to replace "View details" with "Changelog"
     *
     * @param array $links Array of plugin row meta links
     * @param string $file Plugin base name
     * @return array Modified links
     */
    public function modify_plugin_row_meta($links, $file) {
        if ($file !== $this->basename) {
            return $links;
        }
    
        // Add changelog link
        $changelog_url = sprintf('https://github.com/%s/%s?tab=readme-ov-file#changelog',
            $this->username,
            $this->repository
        );
        
        // Replace View details link if it exists, otherwise add new changelog link
        $found = false;
        foreach ($links as $key => $link) {
            if (strpos($link, 'plugin-install.php?tab=plugin-information') !== false) {
                $links[$key] = sprintf('<a href="%s" target="_blank">Changelog</a>', esc_url($changelog_url));
                $found = true;
                break;
            }
        }
        
        // Only add new link if we didn't replace an existing one
        if (!$found) {
            $links[] = sprintf('<a href="%s" target="_blank">Changelog</a>', esc_url($changelog_url));
        }
    
        return $links;
    }

    /**
     * Add settings link to plugin actions
     *
     * @param array $actions Plugin action links
     * @param string $plugin_file Plugin file path
     * @return array Modified action links
     */
    public function add_plugin_action_links($actions, $plugin_file) {
        if ($plugin_file === $this->basename) {
            $settings_link = sprintf(
                '<a href="%s">Settings</a>',
                esc_url(admin_url('options-general.php?page=alynt-git-updater'))
            );
            array_unshift($actions, $settings_link);
        }
        return $actions;
    }

}