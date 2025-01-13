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
            if ('toplevel_page_block-for-mailchimp' === $hook) {
                wp_enqueue_style('mcb-admin-style', MCB_DIR . 'dist/admin.css', false, MCB_PLUGIN_VERSION);
                wp_enqueue_script('mcb-admin-script', MCB_DIR . 'dist/admin.js', ['react', 'react-dom'], MCB_PLUGIN_VERSION, true);
            }
        }

        public function adminMenu()
        {
            $menuIcon = "<svg fill='#fff' width='800px' height='800px' viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'> <path fill-rule='evenodd' d='M21,7.38246601 L21,5 L3,5 L3,7.38199365 L12.0000224,11.8824548 L21,7.38246601 Z M21,9.61853399 L11.9999776,14.1185452 L3,9.61810635 L3,19 L21,19 L21,9.61853399 Z M3,3 L21,3 C22.1045695,3 23,3.8954305 23,5 L23,19 C23,20.1045695 22.1045695,21 21,21 L3,21 C1.8954305,21 1,20.1045695 1,19 L1,5 C1,3.8954305 1.8954305,3 3,3 Z'/></svg>";

            add_menu_page(
                __('Mailchimp', 'block-for-mailchimp'),
                __('Mailchimp', 'block-for-mailchimp'),
                'manage_options',
                'block-for-mailchimp',
                [$this, 'helpPage'],
                'data:image/svg+xml;base64,' . base64_encode($menuIcon),
                6
            );


            if( mcb_fs()->can_use_premium_code() ){
                add_submenu_page(
                    'block-for-mailchimp',
                    __('ShortCode', 'block-for-mailchimp'),
                    __('ShortCode', 'block-for-mailchimp'),
                    'manage_options',
                    'edit.php?post_type=mailchimp-block'
                );
                
            }
        }

        public function helpPage()
        {?>
            <div class='bplAdminHelpPage'></div>
        <?php }
    }
    new mcbAdminMenu();
}