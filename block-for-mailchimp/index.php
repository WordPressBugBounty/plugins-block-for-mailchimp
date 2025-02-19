<?php
/**
 * Plugin Name: Block For MailChimp
 * Description: Connect your MailChimp with your WordPress.
 * Version: 1.1.7
 * Author: bPlugins
 * Author URI: http://bplugins.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: block-for-mailchimp
  * @fs_free_only, bsdk_config.json
 */

// ABS PATH
if (!defined('ABSPATH')) {exit;}


if (function_exists('mcb_fs')) {

    register_activation_hook(__FILE__, function () {
        if (is_plugin_active('block-for-mailchimp/index.php')) {
            deactivate_plugins('block-for-mailchimp/index.php');
        }
        if (is_plugin_active('block-for-mailchimp-pro/index.php')) {
            deactivate_plugins('block-for-mailchimp-pro/index.php');
        }
    });

} else {
    // Constant
    define( 'MCB_PLUGIN_VERSION', isset( $_SERVER['HTTP_HOST'] ) && 'localhost' === $_SERVER['HTTP_HOST'] ? time() : '1.1.7' );
    define('MCB_DIR', plugin_dir_url(__FILE__));
    define('MCB_DIR_PATH', plugin_dir_path(__FILE__));
    define('MCB_ASSETS_DIR', plugin_dir_url(__FILE__) . 'assets/');
    define('MCB_IS_FREE', 'block-for-mailchimp/index.php' === plugin_basename(__FILE__));
    define('MCB_IS_PRO', 'block-for-mailchimp-pro/index.php' === plugin_basename(__FILE__)); 
     
    // Create a helper function for easy SDK access.
    function mcb_fs()
    {
        global $mcb_fs;

        if (!isset($mcb_fs)) {
            // Include Freemius SDK.
            if (file_exists(dirname(__FILE__) . '/bplugins_sdk/init.php')) {
                require_once dirname(__FILE__) . '/bplugins_sdk/init.php';
            }
            if (file_exists(dirname(__FILE__) . '/freemius/start.php')) {
                require_once dirname(__FILE__) . '/freemius/start.php';
            }

            $mcb_fs = fs_lite_dynamic_init(array(
                'id'                  => '16870',
                'slug'                => 'block-for-mailchimp',
                'premium_slug'        => 'block-for-mailchimp-pro',
                'type'                => 'plugin',
                'public_key'          => 'pk_be17ce2b79a810296764efd7ca327',
                'is_premium'          => true,
                'premium_suffix'      => 'Pro',
                // If your plugin is a serviceware, set this option to false.
                'has_premium_version' => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'trial'               => array(
                    'days'               => 7,
                    'is_require_payment' => false,
                ),
                'menu'                => array(
                    'slug'           => 'block-for-mailchimp',
                    'contact'        => false,
                    'support'        => false,
                ),
            ));
        }

        return $mcb_fs;
    }

    // // Init Freemius.
    mcb_fs();
    // Signal that SDK was initiated.
    do_action('mcb_fs_loaded');

    if (MCB_IS_PRO) {
        if(!get_option('mcb_block_option')) {
            require_once MCB_DIR_PATH . 'AdminMenu.php';
            require_once plugin_dir_path(__FILE__) . '/shortCode.php';
        }
    }

    // Mailchimp block
    class MCBMailChimp
    {
        public function __construct()
        {
            add_action('enqueue_block_assets', [$this, 'mailChimpBlockAssets']);
            add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);

            add_action('init', [$this, 'onInit']);
            add_action('admin_init', [$this, 'registerMCBSetting']);
            add_action('rest_api_init', [$this, 'registerMCBSetting']);


            add_action('wp_ajax_mcbPipeChecker', [$this, 'mcbPipeChecker']);
            add_action('wp_ajax_nopriv_mcbPipeChecker', [$this, 'mcbPipeChecker']);
            add_action('admin_init', [$this, 'registerSettings']);
            add_action('rest_api_init', [$this, 'registerSettings']);

            add_action( 'admin_init', [$this, 'add_option_in_general_settings'], 10 );

            if(!MCB_IS_PRO){
                add_filter( 'plugin_action_links', [$this, 'plugin_action_links'], 10, 2 ); 
            }
            add_filter('plugin_row_meta', array($this, 'insert_plugin_row_meta'), 10, 2);
        }

        public function plugin_action_links($links, $file) {
        
            if( plugin_basename( __FILE__ ) == $file ) {
                $links['go_pro'] = sprintf( '<a href="%s" style="%s" target="__blank">%s</a>', 'https://bplugins.com/products/mailchimp-block/#pricing', 'color:#4527a4;font-weight:bold', __( 'Go Pro!', 'block-for-mailchimp' ) );
            }
    
            return $links;
        }


        // Extending row meta 
        public function insert_plugin_row_meta($links, $file)
        {
            if (plugin_basename( __FILE__ ) == $file) {
                // docs & faq
                $links[] = sprintf('<a href="https://bplugins.com/docs/mailchimp-block/" target="_blank">' . __('Docs & FAQs', 'block-for-mailchimp') . '</a>');

                // Demos
                $links[] = sprintf('<a href="https://bplugins.com/products/mailchimp-block/#demos" target="_blank">' . __('Demos', 'block-for-mailchimp') . '</a>');
            }

            return $links;
        }

        public function mcbPipeChecker() {
    		// Get and sanitize the nonce
    		$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';

    		// Verify the nonce for security
			if (!wp_verify_nonce($nonce, 'wp_ajax')) {
				wp_send_json_error(__('Invalid Request', 'block-for-mailchimp'));
			}

			// Prepare the response data
			$is_pipe = defined('MCB_IS_PRO') && MCB_IS_PRO 
				? \mcb_fs()->is__premium_only() && \mcb_fs()->can_use_premium_code()
				: false;

			wp_send_json_success([
				'isPipe' => $is_pipe,
			]);
		}

        public function registerSettings() {
            register_setting('mcbUtils', 'mcbUtils', [
                'show_in_rest' => [
                    'name' => 'mcbUtils',
                    'schema' => ['type' => 'string'],
                ],
                'type' => 'string',
                'default' => wp_json_encode(['nonce' => wp_create_nonce('wp_ajax')]),
                'sanitize_callback' => 'sanitize_text_field',
            ]);
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
            wp_register_script('mcb-script', MCB_DIR . 'dist/script.js', ['react', 'react-dom'], MCB_PLUGIN_VERSION, true);

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

            wp_localize_script('mcb-script', 'mcbInfo', [
                'patternsImagePath' => MCB_DIR . 'assets/img/patterns/',
            ]);

        }

        // Short code style
        public function adminEnqueueScripts($hook)
        {
            global $post_type;

            if ( $post_type =="mailchimp-block") {
                wp_enqueue_style('mcbAdmin', MCB_ASSETS_DIR . 'css/admin.css', [], MCB_PLUGIN_VERSION);
                wp_enqueue_script('mcbAdmin', MCB_ASSETS_DIR . 'js/admin.js', ['wp-i18n'], MCB_PLUGIN_VERSION, true);
            }
        }

        function add_option_in_general_settings(){
			register_setting(
                'general',    
                'mcb_block_option', 
                'sanitize_text_field' 
		    );
  
		    add_settings_field(
                'mcb_block_option_field', 
                'Hide MailChimp Block From Admin Menu',    
                array($this , "mcb_block_option_callback"), 
                'general'                 
		    );
        }

        function mcb_block_option_callback() {
            // Get the current value from the database, default is 'off'
            $value = get_option( 'mcb_block_option', 'false' );
            ?>
            <label class="switch">
              <input type="checkbox" id="mcb_block_option" name="mcb_block_option" value="true" <?php checked( $value, 'true' ); ?>>
              <span class="slider round"></span>
            </label>
            <p class="description">Turn this setting on or off.</p>
            <?php
        }
    
        public function onInit()
        {
            wp_register_style('mcb-mailchimp-editor-style', plugins_url('dist/editor.css', __FILE__), ['mcb-mailchimp-style'], MCB_PLUGIN_VERSION); // Backend Style

            register_block_type(__DIR__, [
                'editor_style' => 'mcb-mailchimp-editor-style',
                'render_callback' => [$this, 'render'],
            ]); // Register Block

            wp_set_script_translations('mcb-mailchimp-editor-script', 'block-for-mailchimp', plugin_dir_path(__FILE__) . 'languages'); // Translate
        }

        

        public function render($attributes)
        {
            extract($attributes);

            $className = $className ?? '';
            $mcbBlockClassName = 'wp-block-mcb-mailchimp ' . $className . ' align' . $align;

            wp_enqueue_style('mcb-mailchimp-style');
            wp_enqueue_script('mcb-script');
            
            ob_start();?>
            <div class='<?php echo esc_attr($mcbBlockClassName); ?>' id='mcbMailChimp-<?php echo esc_attr($cId) ?>' data-attributes='<?php echo esc_attr(wp_json_encode($attributes)); ?>'></div>

            <?php return ob_get_clean();
        } // Render
    }
    new MCBMailChimp();
    require_once plugin_dir_path(__FILE__) . '/mailchimp/API.php';
}