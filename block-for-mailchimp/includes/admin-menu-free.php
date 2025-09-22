<?php
if (!defined('ABSPATH')) {exit;}
if(!class_exists('mcbAdminMenu')) {

    class mcbAdminMenu
    {
        public function __construct()
        {
            add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
            add_action('admin_menu', [$this, 'adminMenu']);
        }

        public function adminEnqueueScripts($hook)
        {
            if( strpos( $hook, 'block-for-mailchimp' ) ){
                wp_enqueue_style( 'mcb-admin-dashboard', MCB_DIR . 'build/admin-dashboard.css', [], MCB_PLUGIN_VERSION );
                wp_enqueue_script( 'mcb-admin-dashboard', MCB_DIR . 'build/admin-dashboard.js', [ 'react', 'react-dom',], MCB_PLUGIN_VERSION, true );
                wp_set_script_translations( 'mcb-admin-dashboard', 'block-for-mailchimp', MCB_DIR_PATH . 'languages' );   
            }
        }

        public function adminMenu(){

            add_submenu_page(
                'tools.php',
                __('Mailchimp Block', 'block-for-mailchimp'),
                __('Mailchimp Block', 'block-for-mailchimp'),
                'manage_options',
                'block-for-mailchimp',
                [$this, 'helpPage']
            ); 
        }

        public function helpPage()
        {?>
            <div id='mcbDashboard'
            data-info='<?php echo esc_attr( wp_json_encode( [
                    'version' => MCB_PLUGIN_VERSION,
                    'isPremium' => mcbIsPremium(),
                    'hasPro' => MCB_IS_PRO
                ] ) ); ?>'
            ></div>
        <?php }
    }
    new mcbAdminMenu();
}