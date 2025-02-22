<?php
/**
 * GitHub Webhook Handler
 *
 * @package Alynt_Git_Updater
 */

namespace Alynt_Git_Updater;

if (!defined('ABSPATH')) {
    exit;
}

class Webhook {
    /**
     * Webhook secret key
     *
     * @var string
     */
    private $webhook_secret;

    /**
     * Constructor
     */
    public function __construct() {
        $this->webhook_secret = get_option('alynt_git_webhook_secret', '');
        $this->init();
    }

    /**
     * Initialize the webhook
     */
    private function init() {
        add_action('init', [$this, 'add_webhook_endpoint']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_settings_page']);
    }

    /**
     * Add webhook endpoint
     */
    public function add_webhook_endpoint() {
        add_rewrite_rule(
            'alynt-git-webhook/?$',
            'index.php?alynt_git_webhook=1',
            'top'
        );

        add_filter('query_vars', function($vars) {
            $vars[] = 'alynt_git_webhook';
            return $vars;
        });

        add_action('parse_request', [$this, 'handle_webhook_request']);
    }

    /**
     * Handle webhook request
     *
     * @param \WP $wp Current WordPress environment instance
     */
    public function handle_webhook_request($wp) {
        if (!isset($wp->query_vars['alynt_git_webhook'])) {
            return;
        }

        // Only accept POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            status_header(405);
            die('Method not allowed');
        }

        // Verify GitHub signature
        if (!$this->verify_github_signature()) {
            status_header(403);
            die('Invalid signature');
        }

        // Get payload
        $payload = json_decode(file_get_contents('php://input'));
        if (!$payload) {
            status_header(400);
            die('Invalid payload');
        }

        // Process the webhook
        $this->process_webhook($payload);

        status_header(200);
        die('Webhook processed');
    }

    /**
     * Verify GitHub webhook signature
     *
     * @return bool
     */
    private function verify_github_signature() {
        if (empty($this->webhook_secret)) {
            return true; // Skip verification if no secret is set
        }

        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        if (empty($signature)) {
            return false;
        }

        $payload = file_get_contents('php://input');
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $this->webhook_secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Process webhook payload
     *
     * @param object $payload Webhook payload
     */
    private function process_webhook($payload) {
        // Only process release events
        if ($payload->action !== 'published' || !isset($payload->release)) {
            return;
        }

        // Clear the transient to force update check
        delete_site_transient('update_plugins');

        // Optionally, trigger an immediate update check
        wp_schedule_single_event(time(), 'alynt_git_check_updates');
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_options_page(
            'GitHub Updater Settings',
            'GitHub Updater',
            'manage_options',
            'alynt-git-updater',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('alynt_git_updater', 'alynt_git_webhook_secret');
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>GitHub Updater Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('alynt_git_updater');
                do_settings_sections('alynt_git_updater');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="alynt_git_webhook_secret">Webhook Secret</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="alynt_git_webhook_secret" 
                                   name="alynt_git_webhook_secret" 
                                   value="<?php echo esc_attr($this->webhook_secret); ?>" 
                                   class="regular-text">
                            <p class="description">
                                Enter the secret key that you configured in your GitHub webhook settings.
                                Leave empty to disable signature verification.
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2>Webhook URL</h2>
            <p>Configure your GitHub webhook to point to this URL:</p>
            <code><?php echo esc_url(home_url('alynt-git-webhook')); ?></code>
        </div>
        <?php
    }
}