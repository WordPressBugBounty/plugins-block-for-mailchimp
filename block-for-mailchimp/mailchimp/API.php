<?php
if (!defined('ABSPATH')) {exit;}

if(!class_exists('BPBFM_Mailchimp_API')) {
    class BPBFM_Mailchimp_API
    {

        public function __construct()
        {
            add_action('wp_ajax_mcbAudienceList', [$this, 'mcbAudienceList']);

            add_action('wp_ajax_mcb_get_access_token', [$this, 'mcb_get_access_token']);
             
            add_action('wp_ajax_mcbSubmit_Form_Data', [$this, 'mcbSubmit_Form_Data']);
            add_action('wp_ajax_nopriv_mcbSubmit_Form_Data', [$this, 'mcbSubmit_Form_Data']);

            add_action('wp_ajax_mcbSubmit_Form_AudienceId', [$this, 'mcbSubmit_Form_AudienceId']);
        }

        /**
         * Fetch audience lists via OAuth access token.
         * S-01: Added capability check.
         * S-03: Changed from $_GET to $_POST for sensitive data.
         * S-07: Changed wp_remote_get to wp_safe_remote_get.
         */
        public function mcbAudienceList() {
            // S-01: Capability check
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( esc_html__( 'Unauthorized', 'block-for-mailchimp' ), 403 );
            }

            // Check if the nonce parameter exists (S-03: now from POST)
            if (!isset($_POST['nonce'])) {
                wp_send_json_error( esc_html__( 'Nonce is missing', 'block-for-mailchimp' ), 400 );
            }
        
            // Unslash and sanitize the nonce
            $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
        
            // Verify the nonce
            if (!wp_verify_nonce($nonce, 'mcbAllAudienceList')) {
                wp_send_json_error( esc_html__( 'Security check failed.', 'block-for-mailchimp' ), 403 );
            }
        
            // Check if the access token parameter exists (S-03: now from POST)
            if (!isset($_POST['accessToken'])) {
                wp_send_json_error( esc_html__( 'Access token is required', 'block-for-mailchimp' ), 400 );
            }
        
            // Unslash and sanitize the access token
            $accessToken = sanitize_text_field(wp_unslash($_POST['accessToken']));
        
            // Make the first API request to get metadata
            $response = wp_safe_remote_get("https://login.mailchimp.com/oauth2/metadata", [
                "method" => "GET",
                "headers" => [
                    "Authorization" => "Bearer " . $accessToken,
                ],
            ]);
        
            if (is_wp_error($response)) {
                wp_send_json_error( esc_html__( 'Failed to fetch data from Mailchimp', 'block-for-mailchimp' ), 500 );
            }
        
            $body = wp_remote_retrieve_body($response);
            $metadata = json_decode($body, true);
        
            // Check if the API endpoint exists in the metadata
            if (isset($metadata['api_endpoint'])) {
                $endpoint_url = $metadata['api_endpoint'];

                // S-02: Validate that the endpoint matches Mailchimp's domain pattern
                if ( ! preg_match( '#^https://[a-z0-9]+\.api\.mailchimp\.com$#i', $endpoint_url ) ) {
                    wp_send_json_error( esc_html__( 'Invalid Mailchimp API endpoint', 'block-for-mailchimp' ), 400 );
                }

                $url = "$endpoint_url/3.0/lists";
        
                // S-07: Use wp_safe_remote_get instead of wp_remote_get
                $response = wp_safe_remote_get("$url?count=1000&offset=0", [
                    "method" => "GET",
                    "headers" => [
                        "Authorization" => "Bearer " . $accessToken, 
                    ],
                ]);
        
                if (is_wp_error($response)) {
                    wp_send_json_error( esc_html__( 'Failed to fetch audience lists from Mailchimp', 'block-for-mailchimp' ), 500 );
                }
        
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
        
                // Include the endpoint URL in the response
                $data['endpoint_url'] = esc_url($url);

                wp_send_json_success($data);
            } else {
                wp_send_json_error( esc_html__( 'Invalid response from Mailchimp', 'block-for-mailchimp' ), 500 );
            }
        }
        
        /**
         * Get access token from bPlugins OAuth relay.
         * S-01: Added capability check.
         * S-03: Changed from $_GET to $_POST for sensitive data.
         * S-08: Proper error response on nonce failure.
         */
        public function mcb_get_access_token () {
            // S-01: Capability check
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( esc_html__( 'Unauthorized', 'block-for-mailchimp' ), 403 );
            }

            // S-03: Nonce from POST, S-08: Proper error response
            if ( ! wp_verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ): '', 'mcbAccessTokenGet' ) ) {
                wp_send_json_error( esc_html__( 'Security check failed.', 'block-for-mailchimp' ), 403 );
            }

            // S-03: State from POST
            $state = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';

             try {
                 // S-07: Use wp_safe_remote_get for external requests
                 $response = wp_safe_remote_get("https://api.bplugins.com/wp-json/mailchimp/v1/get-token/?state=$state");
                 wp_send_json_success($response);
             } catch (\Throwable $th) {
                wp_send_json_error( esc_html__( 'Something went wrong!', 'block-for-mailchimp' ) );
             }
        }

        /**
         * Handle form submission — subscribe a user to Mailchimp.
         * S-03: Changed from $_GET to $_POST for sensitive data (email, names).
         * S-02: Validate endpoint_url against Mailchimp domain pattern.
         * S-08: Proper error response on nonce failure.
         */
        public function mcbSubmit_Form_Data() {
            // S-03: Nonce from POST, S-08: Proper error response
            if (!wp_verify_nonce(isset($_POST['nonce'])?sanitize_text_field(wp_unslash($_POST['nonce'])): '', 'mcbFormData' ) ) {
                wp_send_json_error( esc_html__( 'Security check failed.', 'block-for-mailchimp' ), 403 );
            }

            $data =  get_option('mcb-email-collect');
            $info = json_decode($data, true);
        
            $apiKey = isset($info['key']) ? $info['key'] : '';
            // S-03: All form data from POST
            $audienceId = isset( $_POST['audienceId'] ) ? sanitize_text_field( wp_unslash( $_POST['audienceId'] ) ) : '';
            $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
            $fName = isset( $_POST['fName'] ) ? sanitize_text_field( wp_unslash( $_POST['fName'] )) : '';
            $lName = isset( $_POST['lName'] ) ? sanitize_text_field( wp_unslash( $_POST['lName'] )) : '';
            // S-02/S-09: Validate endpoint_url with esc_url_raw
            $endpoint_url = isset( $_POST['endpoint_url'] ) ? esc_url_raw( wp_unslash($_POST['endpoint_url']) ) : '';
            $accessToken = isset($info['accessToken']) ? $info['accessToken'] : '';
            
            $dc = substr($apiKey, strpos($apiKey, '-') + 1);

            $mailDataCenterList = ["us1", "us2", "us3", "us4", "us5", "us6", "us7", "us8", "us9", "us10", "us11", "us12", "us13", "us14", "us15", "us16", "us17", "us18", "us19", "us20"];
        
            if (!in_array($dc, $mailDataCenterList) && !$endpoint_url) {
                echo wp_json_encode(['success' => false, 'status' => 502, 'message' => esc_html__('Invalid API Key or endpoint URL!', 'block-for-mailchimp')]);
                wp_die();
            }
        
            if (!$audienceId) {
                echo wp_json_encode(['success' => false, 'status' => 510, 'message' => esc_html__('Audience ID Required!', 'block-for-mailchimp')]);
                wp_die();
            }
        
            if (!$email) {
                echo wp_json_encode(['success' => false, 'status' => 511, 'message' => esc_html__('Email Address Required!', 'block-for-mailchimp')]);
                wp_die();
            }
        
            $url = '';
            $headers = ["Content-Type" => "application/json"];

            if ($apiKey) {
                $url = "https://$dc.api.mailchimp.com/3.0/lists/$audienceId/members";
                $headers["Authorization"] = "apikey " . $apiKey;
            } else if ($endpoint_url) {
                // S-02: Validate endpoint_url matches Mailchimp domain pattern before use
                if ( ! preg_match( '#^https://[a-z0-9]+\.api\.mailchimp\.com/3\.0/lists#i', $endpoint_url ) ) {
                    echo wp_json_encode(['success' => false, 'status' => 400, 'message' => esc_html__('Invalid endpoint URL!', 'block-for-mailchimp')]);
                    wp_die();
                }
                $url = "$endpoint_url/$audienceId/members";
                $headers["Authorization"] = "Bearer " . $accessToken;
            } else {
                echo wp_json_encode(['success' => false, 'status' => 500, 'message' => esc_html__('API Key or endpoint URL Required!', 'block-for-mailchimp')]);
                wp_die();
            }
        
            $response = wp_safe_remote_post($url, [
                "method" => "POST",
                "headers" => $headers,
                "body" => wp_json_encode([
                    "email_address" => $email,
                    "status" => "subscribed",
                    'merge_fields' => [
                        'FNAME' => $fName,
                        'LNAME' => $lName
                    ],
                ]),
            ]);
        
            if (is_wp_error($response)) {
                echo wp_json_encode(['success' => false, 'status' => 500, 'message' => esc_html__('Failed to connect to Mailchimp', 'block-for-mailchimp')]);
                wp_die();
            }
        
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
        
            if (isset($data['status']) && $data['status'] == 'subscribed') {
                echo wp_json_encode(['status' => $data['status'], 'message' => esc_html__('Successfully subscribed', 'block-for-mailchimp')]);
            } else {
                echo wp_json_encode(['status' => isset($data['status']) ? $data['status'] : 'error', 'message' => esc_html__('Failed to subscribe', 'block-for-mailchimp')]);
            }
        
            wp_die();
        }

        /**
         * Fetch audience IDs via API key.
         * S-01: Added capability check.
         * S-03: Changed from $_GET to $_POST for sensitive data.
         * S-07: Changed wp_remote_get to wp_safe_remote_get.
         * S-08: Proper error response on nonce failure.
         */
        public function mcbSubmit_Form_AudienceId()
        {
            // S-01: Capability check
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( esc_html__( 'Unauthorized', 'block-for-mailchimp' ), 403 );
            }

            // S-03: Nonce from POST, S-08: Proper error response
            if ( ! wp_verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'mcbAudienceIDList' ) ) {
                wp_send_json_error( esc_html__( 'Security check failed.', 'block-for-mailchimp' ), 403 );
            }

            // S-03: API key from POST
            $apiKey = isset( $_POST['apiKey'] ) ? sanitize_text_field( wp_unslash( $_POST['apiKey'] ) ) : '';
            $dc = substr($apiKey, strpos($apiKey, '-') + 1);

            $mailDataCenterList = ["us1", "us2", "us3", "us4", "us5", "us6", "us7", "us8", "us9", "us10", "us11", "us12", "us13", "us14", "us15", "us16", "us17", "us18", "us19", "us20"];

            if (!in_array($dc, $mailDataCenterList)) {
                echo wp_json_encode(['success' => false, 'status' => 502, 'message' => esc_html__('Invalid API Key!', 'block-for-mailchimp')]);
                wp_die();
            }

            try {
                // S-07: Use wp_safe_remote_get instead of wp_remote_get
                $res = wp_safe_remote_get("https://$dc.api.mailchimp.com/3.0/lists?count=1000&offset=0", [
                    "headers" => [
                        "Authorization" => "Basic " . $apiKey,
                        "Content-Type" => "application/json",
                    ],
                ]);
                wp_send_json_success($res['body']);
            } catch (\Throwable $th) {
                wp_send_json_error( esc_html__( 'Something went wrong!', 'block-for-mailchimp' ) );
            }
        }
    }
    new BPBFM_Mailchimp_API();
}