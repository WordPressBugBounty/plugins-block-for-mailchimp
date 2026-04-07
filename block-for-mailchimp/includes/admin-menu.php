<?php
if (!defined('ABSPATH')) {exit;}
if(!class_exists('MCBAdminMenu')) {

    class MCBAdminMenu {

        public function __construct() {
            add_action( 'admin_enqueue_scripts', [$this, 'adminEnqueueScripts'] );
            add_action( 'admin_menu', [$this, 'adminMenu'] );
        }

        public function adminEnqueueScripts($hook) {
             
            // if( strpos( $hook, 'block-for-mailchimp' ) ){
                wp_enqueue_style( 'mcb-admin-dashboard', MCB_DIR . 'build/admin-dashboard.css', [], MCB_PLUGIN_VERSION );
                wp_enqueue_script( 'mcb-admin-dashboard', MCB_DIR . 'build/admin-dashboard.js', [ 'react', 'react-dom', 'wp-data', "wp-api", "wp-util", "wp-i18n" ], MCB_PLUGIN_VERSION, true );
                wp_set_script_translations( 'mcb-admin-dashboard', 'block-for-mailchimp', MCB_DIR_PATH . 'languages' );   
            // }


        }

        public function adminMenu(){
             
            add_submenu_page(
                'edit.php?post_type=block-for-mailchimp',
                __('Demo & Help', 'block-for-mailchimp'),
                __('Demo & Help', 'block-for-mailchimp'),
                'manage_options',
                'mcb',
                [$this, 'bsbHelpPage'],
            );   
            
        }

        public function bsbHelpPage()
        {?>
            <div
                id='mcbDashboard'
                data-info='<?php echo esc_attr( wp_json_encode( [
                    'version' => MCB_PLUGIN_VERSION,
                    'isPremium' => mcbIsPremium(),
                    'hasPro' => MCB_IS_PRO,
                    'nonce' => wp_create_nonce( 'apbCreatePage' ),
		            'licenseActiveNonce' => wp_create_nonce( 'bPlLicenseActivation' )
                ] ) ); ?>'
            >
            </div>
        <?php } 
    }
    new MCBAdminMenu();
}