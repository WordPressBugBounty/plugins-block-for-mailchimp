<?php
if (!defined('ABSPATH')) {exit;}
if(!class_exists('BPBFM_AdminMenu')) {

    class BPBFM_AdminMenu {

        public function __construct() {
            add_action( 'admin_enqueue_scripts', [$this, 'adminEnqueueScripts'] );
            add_action( 'admin_menu', [$this, 'adminMenu'] );
        }

        public function adminEnqueueScripts($hook) {
            // WP-01: Only load on our own admin page (Demo & Help submenu)
            if ( 'block-for-mailchimp_page_mcb' !== $hook ) {
                return;
            }
            wp_enqueue_style( 'mcb-admin-dashboard', BPBFM_DIR . 'build/admin-dashboard.css', [], BPBFM_PLUGIN_VERSION );
            wp_enqueue_script( 'mcb-admin-dashboard', BPBFM_DIR . 'build/admin-dashboard.js', [ 'react', 'react-dom', 'wp-data', "wp-api", "wp-util", "wp-i18n" ], BPBFM_PLUGIN_VERSION, true );
            wp_set_script_translations( 'mcb-admin-dashboard', 'block-for-mailchimp', BPBFM_DIR_PATH . 'languages' );   
        }

        public function adminMenu(){
            add_submenu_page(
                'edit.php?post_type=block-for-mailchimp',
                __('Demo & Help', 'block-for-mailchimp'),
                __('Demo & Help', 'block-for-mailchimp'),
                'manage_options',
                'mcb',
                [$this, 'bsbHelpPage']
            );   
        }

        public function bsbHelpPage()
        {?>
            <div id='mcbDashboard'
                data-info='<?php echo esc_attr( wp_json_encode( [
                    'version' => BPBFM_PLUGIN_VERSION,
                    'adminUrl' => admin_url()
                ] ) ); ?>'
            >
            </div>
        <?php } 
    }
    new BPBFM_AdminMenu();
}