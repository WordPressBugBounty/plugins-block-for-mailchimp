<?php
/**
 * Plugin Name: Block for Mailchimp – Add Email Subscription Forms and Collect Leads
 * Description: Connect your MailChimp with your WordPress.
 * Version: 1.1.16
 * Author: bPlugins
 * Author URI: http://bplugins.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: block-for-mailchimp
 */
// ABS PATH
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( function_exists( 'bpbfm_fs' ) ) {
    bpbfm_fs()->set_basename( false, __FILE__ );
} else {
    // Constant
    define( 'BPBFM_PLUGIN_VERSION', ( isset( $_SERVER['HTTP_HOST'] ) && 'localhost' === $_SERVER['HTTP_HOST'] ? time() : '1.1.16' ) );
    define( 'BPBFM_DIR', plugin_dir_url( __FILE__ ) );
    define( 'BPBFM_DIR_PATH', plugin_dir_path( __FILE__ ) );
    define( 'BPBFM_ASSETS_DIR', plugin_dir_url( __FILE__ ) . 'assets/' );
    
    if ( !function_exists( 'bpbfm_fs' ) ) {
        // Create a helper function for easy SDK access.
        function bpbfm_fs() {
            global $bpbfm_fs;
            if ( !isset( $bpbfm_fs ) ) {
                // Include Freemius SDK.
                 
                    require_once dirname( __FILE__ ) . '/vendor/freemius-lite/start.php';
                
                $mcbConfig = array(
                    'id'                  => '16870',
                    'slug'                => 'block-for-mailchimp',
                    'type'                => 'plugin',
                    'public_key'          => 'pk_be17ce2b79a810296764efd7ca327',
                    'is_premium'          => false,
                    'menu'                => array(
                        'slug'           => 'edit.php?post_type=block-for-mailchimp',
                        'first-path'     => 'edit.php?post_type=block-for-mailchimp&page=mcb#/pricing',
                        'support'        => false,
                    ),
                );
                $bpbfm_fs =  fs_lite_dynamic_init( $mcbConfig );
            }
            return $bpbfm_fs;
        }

        // // Init Freemius.
        bpbfm_fs();
        // Signal that SDK was initiated.
        do_action( 'bpbfm_fs_loaded' );
    }
     

    // Mailchimp block
    class MCBMailChimp {
        public function __construct() {
            $this->load_classes();
            add_action( 'enqueue_block_assets', [$this, 'mailChimpBlockAssets'] );
            add_action( 'admin_enqueue_scripts', [$this, 'adminEnqueueScripts'] );
            add_action( 'init', [$this, 'onInit'] );
            add_action( 'admin_init', [$this, 'registerMCBSetting'] );
            add_action( 'rest_api_init', [$this, 'registerMCBSetting'] );
            add_filter( 'plugin_action_links', [$this, 'plugin_action_links'], 10, 2);
            add_filter( 'plugin_row_meta', array($this, 'insert_plugin_row_meta'), 10, 2);
        }

        public function plugin_action_links( $links, $file ) {
            if ( plugin_basename( __FILE__ ) == $file ) {
                $links['go_pro'] = sprintf(
                    '<a href="%s" style="%s" target="__blank">%s</a>',
                    'https://bplugins.com/products/mailchimp-block/#pricing',
                    'color:#4527a4;font-weight:bold',
                    __( 'Go Pro!', 'block-for-mailchimp' )
                );
            }
            return $links;
        }

        public function load_classes() {
            require_once plugin_dir_path( __FILE__ ) . '/mailchimp/API.php';
            require_once plugin_dir_path( __FILE__ ) . '/includes/admin-menu.php';
            require_once plugin_dir_path( __FILE__ ) . '/shortCode.php';
        }

        // Extending row meta
        public function insert_plugin_row_meta( $links, $file ) {
            if ( plugin_basename( __FILE__ ) == $file ) {
                // docs & faq
                $links[] = sprintf( '<a href="https://bplugins.com/docs/mailchimp-block/" target="_blank">' . __( 'Docs & FAQs', 'block-for-mailchimp' ) . '</a>' );
                // Demos
                $links[] = sprintf( '<a href="https://bplugins.com/products/mailchimp-block/#demos" target="_blank">' . __( 'Demos', 'block-for-mailchimp' ) . '</a>' );
            }
            return $links;
        }

        public function registerMCBSetting() {
            register_setting( 'mcb-email-collect', 'mcb-email-collect', array(
                'show_in_rest'      => array(
                    'name'   => 'mcb-email-collect',
                    'schema' => array(
                        'type' => 'string',
                    ),
                ),
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => function() {
                    return current_user_can( 'manage_options' );
                },
            ) );
        }

        public function mailChimpBlockAssets() {
            wp_localize_script( 'mcb-mailchimp-view-script', 'mcbData', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mcbFormData' ),
            ] );
            wp_localize_script( 'mcb-mailchimp-editor-script', 'mcbAudienceId', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mcbAudienceIDList' ),
            ] );
            wp_localize_script( 'mcb-mailchimp-editor-script', 'mcbAccessToken', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mcbAccessTokenGet' ),
            ] );
            wp_localize_script( 'mcb-mailchimp-editor-script', 'mcbAudienceList', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mcbAllAudienceList' ),
            ] );
        }

        // Short code style
        public function adminEnqueueScripts( $hook ) {
            global $post_type;
            if ( $post_type == "mailchimp-block" ) {
                wp_enqueue_style( 'mcbAdmin', BPBFM_ASSETS_DIR . 'css/admin.css', [], BPBFM_PLUGIN_VERSION);
                wp_enqueue_script( 'mcbAdmin', BPBFM_ASSETS_DIR . 'js/admin.js', ['wp-i18n'], BPBFM_PLUGIN_VERSION,true);
            }
        }

        public function onInit() {
            register_block_type( __DIR__ . '/build' );
        }
    }
    new MCBMailChimp();
}