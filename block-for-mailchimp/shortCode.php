<?php
if (!defined('ABSPATH')) {exit;}
if(!class_exists("BPBFM_Custom_Post")) {
	class BPBFM_Custom_Post {
		public $post_type = 'block-for-mailchimp';
		
		public function __construct(){
			add_action( 'init', [$this, 'onInit'], 20 );
			add_shortcode( 'mcb-block', [$this, 'onAddShortcode'], 20 );
			add_filter( 'manage_block-for-mailchimp_posts_columns', [$this, 'manageMCBPostsColumns'], 10 );
			add_action( 'manage_block-for-mailchimp_posts_custom_column', [$this, 'manageMCBPostsCustomColumns'], 10, 2 );
			add_action( 'use_block_editor_for_post', [$this, 'useBlockEditorForPost'], 999, 2 );		
		}

		function onInit(){
			$menuIcon = "<svg fill='#fff' width='800px' height='800px' viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'> <path fill-rule='evenodd' d='M21,7.38246601 L21,5 L3,5 L3,7.38199365 L12.0000224,11.8824548 L21,7.38246601 Z M21,9.61853399 L11.9999776,14.1185452 L3,9.61810635 L3,19 L21,19 L21,9.61853399 Z M3,3 L21,3 C22.1045695,3 23,3.8954305 23,5 L23,19 C23,20.1045695 22.1045695,21 21,21 L3,21 C1.8954305,21 1,20.1045695 1,19 L1,5 C1,3.8954305 1.8954305,3 3,3 Z'/></svg>";

			register_post_type( $this->post_type, [
				'labels'				=> [
					'name'			=> __( 'Mailchimp', 'block-for-mailchimp' ),
					'singular_name'	=> __( 'Mailchimp', 'block-for-mailchimp' ),
					'menu_name'     => __( 'Mailchimp', 'block-for-mailchimp' ),
					'all_items'     => __( 'All Mailchimp Blocks', 'block-for-mailchimp' ),
					'add_new'		=> __( 'Add New', 'block-for-mailchimp' ),
					'add_new_item'	=> __( '&#8627; Add New', 'block-for-mailchimp' ),
					'edit_item'		=> __( 'Edit', 'block-for-mailchimp' ),
					'new_item'		=> __( 'New', 'block-for-mailchimp' ),
					'view_item'		=> __( 'View', 'block-for-mailchimp' ),
					'search_items'	=> __( 'Search', 'block-for-mailchimp'),
					'not_found'		=> __( 'Sorry, we couldn\'t find the that you are looking for.', 'block-for-mailchimp' )
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
				'rewrite'				=> [ 'slug' => 'block-for-mailchimp' ],
				'supports'				=> [ 'title', 'editor' ],
				'template'				=> [ ['mcb/mailchimp'] ],
				'template_lock'			=> 'all',
			]); // Register Post Type
		}

		/**
		 * B-01: Validate shortcode attributes with shortcode_atts() and absint().
		 */
		public function onAddShortcode( $atts ) {
			$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'mcb-block' );
			$post_id = absint( $atts['id'] );

			if ( ! $post_id ) {
				return '';
			}

			$post = get_post( $post_id );
			if ( !$post ) {
				return '';
			}
			if ( post_password_required( $post ) ) {
				return get_the_password_form( $post );
			}
			switch ( $post->post_status ) {
				case 'publish':
					return $this->displayContent( $post );
				case 'private':
					if (current_user_can('read_private_posts')) {
						return $this->displayContent( $post );
					}
					return '';
				case 'draft':
				case 'pending':
				case 'future':
					if ( current_user_can( 'edit_post', $post_id ) ) {
						return $this->displayContent( $post );
					}
					return '';
				default:
					return '';
			}
		}

		/**
		 * B-02: Guard against empty post content or posts with no blocks.
		 */
		public function displayContent( $post ){
			$blocks = parse_blocks( $post->post_content );
			if ( empty( $blocks ) || empty( $blocks[0]['blockName'] ) ) {
				return '';
			}
			return render_block( $blocks[0] );
		}

		public function manageMCBPostsColumns( $defaults ) {
			unset( $defaults['date'] );
			$defaults['shortcode'] = __( 'ShortCode', 'block-for-mailchimp' );
			$defaults['date'] = __( 'Date', 'block-for-mailchimp' );
			return $defaults;
		}

		public function manageMCBPostsCustomColumns( $column_name, $post_ID ) {
			$post_id = esc_attr( $post_ID ); // Escape the post ID for safe output
			if ( $column_name == 'shortcode' ) {
				// Escape the shortcode and HTML attributes properly
				echo "<div class='mcbFrontShortcode' id='mcbFrontShortcode-" . esc_attr( $post_id ) . "'>
						<input value='[mcb-block id=" . esc_attr( $post_id ) . "]' onclick='mcbHandleShortcode(" . esc_js( $post_id ) . ")'>
						<span class='tooltip'>" . esc_html__( 'Copy To Clipboard', 'block-for-mailchimp' ) . "</span>
					  </div>";
			}
		}

		public function useBlockEditorForPost($use, $post){
			if ($this->post_type === $post->post_type) {
				return true;
			}
			return $use;
		}
	 
	}
	new BPBFM_Custom_Post();
}
