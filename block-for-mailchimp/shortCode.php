<?php
if (!defined('ABSPATH')) {exit;}
if(!class_exists("mcbCustomPost")) {
	class mcbCustomPost{
		public $post_type = 'mailchimp-block';
		
		public function __construct(){
			 
				add_action( 'init', [$this, 'onInit'], 20 );
				add_shortcode( 'mcb-block', [$this, 'onAddShortcode'], 20 );
				add_filter( 'manage_mailchimp-block_posts_columns', [$this, 'manageMCBPostsColumns'], 10 );
				add_action( 'manage_mailchimp-block_posts_custom_column', [$this, 'manageMCBPostsCustomColumns'], 10, 2 );
				add_action( 'use_block_editor_for_post', [$this, 'useBlockEditorForPost'], 999, 2 );
				
		}

		function onInit(){
			$menuIcon = "<svg fill='#fff' width='800px' height='800px' viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'> <path fill-rule='evenodd' d='M21,7.38246601 L21,5 L3,5 L3,7.38199365 L12.0000224,11.8824548 L21,7.38246601 Z M21,9.61853399 L11.9999776,14.1185452 L3,9.61810635 L3,19 L21,19 L21,9.61853399 Z M3,3 L21,3 C22.1045695,3 23,3.8954305 23,5 L23,19 C23,20.1045695 22.1045695,21 21,21 L3,21 C1.8954305,21 1,20.1045695 1,19 L1,5 C1,3.8954305 1.8954305,3 3,3 Z'/></svg>";

			register_post_type( $this->post_type, [
				'labels'				=> [
					'name'			=> __( 'Mailchimp Block', 'mail-collections'),
					'singular_name'	=> __( 'Mailchimp Block', 'mail-collections' ),
					'add_new'		=> __( 'Add New', 'mail-collections' ),
					'add_new_item'	=> __( 'Add New', 'mail-collections' ),
					'edit_item'		=> __( 'Edit', 'mail-collections' ),
					'new_item'		=> __( 'New', 'mail-collections' ),
					'view_item'		=> __( 'View', 'mail-collections' ),
					'search_items'	=> __( 'Search', 'mail-collections'),
					'not_found'		=> __( 'Sorry, we couldn\'t find the that you are looking for.', 'mail-collections' )
				],
				'public'				=> false,
				'show_ui'				=> true, 		
				'show_in_rest'			=> true,							
				'publicly_queryable'	=> false,
				'exclude_from_search'	=> true,
				'menu_position'			=> 14,
				'menu_icon'				=> 'data:image/svg+xml;base64,' . base64_encode( $menuIcon ),		
				'has_archive'			=> false,
				'hierarchical'			=> false,
				'capability_type'		=> 'page',
				'rewrite'				=> [ 'slug' => 'mailchimp-block' ],
				'supports'				=> [ 'title', 'editor' ],
				'template'				=> [ ['mcb/mailchimp'] ],
				'template_lock'			=> 'all',
				'show_in_menu'          => false
			]); // Register Post Type
		}

		function onAddShortcode( $atts ) {
			$post_id = $atts['id'];
			$post = get_post( $post_id );

			$blocks = parse_blocks( $post->post_content );

			return render_block( $blocks[0] );
		}

		function manageMCBPostsColumns( $defaults ) {
			unset( $defaults['date'] );
			$defaults['shortcode'] = 'ShortCode';
			$defaults['date'] = 'Date';
			return $defaults;
		}

		function manageMCBPostsCustomColumns( $column_name, $post_ID ) {
			if ( $column_name == 'shortcode' ) {
				echo "<div class='mcbFrontShortcode' id='mcbFrontShortcode-$post_ID'>
					<input value='[mcb-block id=$post_ID]' onclick='mcbHandleShortcode( $post_ID )'>
					<span class='tooltip'>Copy To Clipboard</span>
				</div>";
			}
		}

		function useBlockEditorForPost($use, $post){
			if ($this->post_type === $post->post_type) {
				return true;
			}
			return $use;
		}

		// function add_option_in_general_settings(){
		// 	register_setting(
		// 	'general',    
		// 	'mcb_block_option', 
		// 	'sanitize_text_field' 
		// );
  
		// add_settings_field(
		// 	'mcb_block_option_field', 
		// 	'Hide MailChimp Block From Admin Menu',    
		// 	array($this , "mcb_block_option_callback"), 
		// 	'general'                 
		// );
  
		// }
  
	 
	}
	new mcbCustomPost();
}