<?php

/**
 * Plugin Name: Block For MailChimp
 * Description: Connect your MailChimp with your WordPress.
 * Version: 1.1.12
 * Author: bPlugins
 * Author URI: http://bplugins.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: block-for-mailchimp
 * @fs_free_only, bsdk_config.json, /freemius-lite, /includes/admin-menu-free.php
 */
// ABS PATH
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( function_exists( 'mcb_fs' ) ) {
    mcb_fs()->set_basename( false, __FILE__ );
} else {
    // Constant
    define( 'MCB_PLUGIN_VERSION', ( isset( $_SERVER['HTTP_HOST'] ) && 'localhost' === $_SERVER['HTTP_HOST'] ? time() : '1.1.12' ) );
    define( 'MCB_DIR', plugin_dir_url( __FILE__ ) );
    define( 'MCB_DIR_PATH', plugin_dir_path( __FILE__ ) );
    define( 'MCB_ASSETS_DIR', plugin_dir_url( __FILE__ ) . 'assets/' );
    define( 'MCB_IS_FREE', 'block-for-mailchimp/index.php' === plugin_basename( __FILE__ ) );
    define( 'MCB_IS_PRO', file_exists( dirname( __FILE__ ) . '/freemius/start.php' ) );
    if ( !function_exists( 'br_fs' ) ) {
        // Create a helper function for easy SDK access.
        function mcb_fs() {
            global $mcb_fs;
            if ( !isset( $mcb_fs ) ) {
                // Include Freemius SDK.
                if ( MCB_IS_PRO ) {
                    require_once dirname( __FILE__ ) . '/freemius/start.php';
                } else {
                    require_once dirname( __FILE__ ) . '/freemius-lite/start.php';
                }
                $mcbConfig = array(
                    'id'                  => '16870',
                    'slug'                => 'block-for-mailchimp',
                    'premium_slug'        => 'block-for-mailchimp-pro',
                    'type'                => 'plugin',
                    'public_key'          => 'pk_be17ce2b79a810296764efd7ca327',
                    'is_premium'          => true,
                    'premium_suffix'      => 'Pro',
                    'has_premium_version' => true,
                    'has_addons'          => false,
                    'has_paid_plans'      => true, 
                    'trial'               => array(
                        'days'               => 7,
                        'is_require_payment' => false,
                    ),
                    'menu'                => ( MCB_IS_PRO ? array(
                        'slug'       => 'block-for-mailchimp',
                        'first-path' => 'admin.php?page=block-for-mailchimp#/pricing',
                        'support'    => false,
                    ) : array(
                        'slug'       => 'block-for-mailchimp',
                        'first-path' => 'tools.php?page=block-for-mailchimp#/pricing',
                        'support'    => false,
                        'parent'     => array(
                            'slug' => 'tools.php',
                        ),
                    ) ),
                );
                $mcb_fs = ( MCB_IS_PRO ? fs_dynamic_init( $mcbConfig ) : fs_lite_dynamic_init( $mcbConfig ) );
            }
            return $mcb_fs;
        }

        // // Init Freemius.
        mcb_fs();
        // Signal that SDK was initiated.
        do_action( 'mcb_fs_loaded' );
    }
    function mcbIsPremium() {
        return ( MCB_IS_PRO ? mcb_fs()->can_use_premium_code() : false );
    }

    // Mailchimp block
    class MCBMailChimp {
        public function __construct() {
            $this->load_classes();
            add_action( 'enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets'] );
            add_action( 'enqueue_block_assets', [$this, 'mailChimpBlockAssets'] );
            add_action( 'admin_enqueue_scripts', [$this, 'adminEnqueueScripts'] );
            add_action( 'init', [$this, 'onInit'] );
            add_action( 'admin_init', [$this, 'registerMCBSetting'] );
            add_action( 'rest_api_init', [$this, 'registerMCBSetting'] );
            add_action( 'admin_init', [$this, 'add_option_in_general_settings'], 10 );
            if ( !MCB_IS_PRO ) {
                add_filter(
                    'plugin_action_links',
                    [$this, 'plugin_action_links'],
                    10,
                    2
                );
            }
            add_filter(
                'plugin_row_meta',
                array($this, 'insert_plugin_row_meta'),
                10,
                2
            );
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
            if ( MCB_IS_PRO ) {
                require_once plugin_dir_path( __FILE__ ) . '/includes/admin-menu-pro.php';
            } else {
                require_once plugin_dir_path( __FILE__ ) . '/includes/admin-menu-free.php';
            }
            if ( MCB_IS_PRO && mcbIsPremium() ) {
                if ( !get_option( 'mcb_block_option' ) ) {
                    require_once plugin_dir_path( __FILE__ ) . '/shortCode.php';
                }
            }
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
            wp_localize_script( 'mcb-mailchimp-editor-script', 'mcbInfo', [
                'patternsImagePath' => MCB_DIR . 'assets/img/patterns/',
            ] );
        }

        // Short code style
        public function adminEnqueueScripts( $hook ) {
            global $post_type;
            if ( $post_type == "mailchimp-block" ) {
                wp_enqueue_style(
                    'mcbAdmin',
                    MCB_ASSETS_DIR . 'css/admin.css',
                    [],
                    MCB_PLUGIN_VERSION
                );
                wp_enqueue_script(
                    'mcbAdmin',
                    MCB_ASSETS_DIR . 'js/admin.js',
                    ['wp-i18n'],
                    MCB_PLUGIN_VERSION,
                    true
                );
            }
        }

        function add_option_in_general_settings() {
            register_setting( 'general', 'mcb_block_option', 'sanitize_text_field' );
            add_settings_field(
                'mcb_block_option_field',
                'Hide MailChimp Block From Admin Menu',
                array($this, "mcb_block_option_callback"),
                'general'
            );
        }

        function mcb_block_option_callback() {
            // Get the current value from the database, default is 'off'
            $value = get_option( 'mcb_block_option', 'false' );
            ?>
            <label class="switch">
              <input type="checkbox" id="mcb_block_option" name="mcb_block_option" value="true" <?php 
            checked( $value, 'true' );
            ?>>
              <span class="slider round"></span>
            </label>
            <p class="description">Turn this setting on or off.</p>
            <?php 
        }

        public function onInit() {
            register_block_type( __DIR__ . '/build' );
        }

        public function enqueueBlockEditorAssets() {
            wp_add_inline_script( 'mcb-mailchimp-editor-script', "const mcbpipecheck=" . wp_json_encode( mcbIsPremium() ) . ';', 'before' );
        }

    }

    new MCBMailChimp();
}