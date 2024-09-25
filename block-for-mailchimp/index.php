<?php
/**
 * Plugin Name: Mailchimp block
 * Description: Connect your MailChimp with your WordPress.
 * Version: 1.1.0
 * Author: bPlugins
 * Author URI: http://bplugins.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: mail-collections
 */

// ABS PATH
if (!defined('ABSPATH')) {exit;}

// Constant
if (isset($_SERVER['HTTP_HOST']) && 'localhost' === $_SERVER['HTTP_HOST']) {
    $plugin_version = time();
} else {
    $plugin_version = '1.1.0';

}
define('MCB_PLUGIN_VERSION', $plugin_version);

// define('MCB_PLUGIN_VERSION', 'localhost' === $_SERVER['HTTP_HOST'] ? time() : '1.0.10');
define('MCB_DIR', plugin_dir_url(__FILE__));
define('MCB_ASSETS_DIR', plugin_dir_url(__FILE__) . 'assets/');

if (!function_exists('mcb_init')) {
    function mcb_init()
    {
        global $mcb_bs;
        require_once plugin_dir_path(__FILE__) . 'bplugins_sdk/init.php';
        $mcb_bs = new BPlugins_SDK(__FILE__);
    }
    mcb_init();
} else {
    $mcb_bs->uninstall_plugin(__FILE__);
}

// Mailchimp block
class MCBMailChimp
{
    public function __construct()
    {
        add_action('enqueue_block_assets', [$this, 'mailChimpBlockAssets']);
        add_action('init', [$this, 'onInit']);
        add_action('admin_init', [$this, 'registerMCBSetting']);
        add_action('rest_api_init', [$this, 'registerMCBSetting']);
    }

    public function registerMCBSetting()
    {
        register_setting('mcb-email-collect', 'mcb-email-collect', array(
            'show_in_rest' => array(
                'name' => 'mcb-email-collect',
                'schema' => array(
                    'type' => 'string',
                ),
            ),
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ));
    }

    public function mailChimpBlockAssets()
    {

        wp_register_style('mcb-mailchimp-style', plugins_url('dist/style.css', __FILE__), [], MCB_PLUGIN_VERSION);
        wp_register_script('mcb-script', MCB_DIR . 'dist/script.js', ['react', 'react-dom'], MCB_PLUGIN_VERSION);

        wp_localize_script('mcb-script', 'mcbData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        wp_localize_script('mcb-script', 'mcbAudienceId', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        wp_localize_script('mcb-script', 'mcbAccessToken', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        wp_localize_script('mcb-script', 'mcbAudienceList', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

    }

    public function onInit()
    {
        wp_register_style('mcb-mailchimp-editor-style', plugins_url('dist/editor.css', __FILE__), ['mcb-mailchimp-style'], MCB_PLUGIN_VERSION); // Backend Style

        register_block_type(__DIR__, [
            'editor_style' => 'mcb-mailchimp-editor-style',
            'render_callback' => [$this, 'render'],
        ]); // Register Block

        wp_set_script_translations('mcb-mailchimp-editor-script', 'mail-collections', plugin_dir_path(__FILE__) . 'languages'); // Translate
    }

    public function render($attributes)
    {
        extract($attributes);

        $className = $className ?? '';
        $mcbBlockClassName = 'wp-block-mcb-mailchimp ' . $className . ' align' . $align;

        wp_enqueue_style('mcb-mailchimp-style');
        wp_enqueue_script('mcb-script');

        ob_start();?>
		<div class='<?php echo esc_attr($mcbBlockClassName); ?>' id='mcbMailChimp-<?php echo esc_attr($cId) ?>' data-attributes='<?php echo esc_attr(wp_json_encode($attributes)); ?>' data-mcbInfo='<?php echo esc_attr(get_option('mcb-email-collect')); ?>'></div>

		<?php return ob_get_clean();
    } // Render
}
new MCBMailChimp();
require_once plugin_dir_path(__FILE__) . '/mailchimp/API.php';
