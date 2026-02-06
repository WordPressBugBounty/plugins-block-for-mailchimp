<?php
if (!defined('ABSPATH')) {exit;}

if(!class_exists('MailChimpApi')) { 
    class MailChimpApi
    {

        public function __construct()
        {
            add_action('wp_ajax_mcbAudienceList', [$this, 'mcbAudienceList']);

            add_action('wp_ajax_mcb_get_access_token', [$this, 'mcb_get_access_token']);
             
            add_action('wp_ajax_mcbSubmit_Form_Data', [$this, 'mcbSubmit_Form_Data']);
            add_action('wp_ajax_nopriv_mcbSubmit_Form_Data', [$this, 'mcbSubmit_Form_Data']);

            add_action('wp_ajax_mcbSubmit_Form_AudienceId', [$this, 'mcbSubmit_Form_AudienceId']);
        }

        public function mcbAudienceList() {
            // Check if the nonce parameter exists
            if (!isset($_GET['nonce'])) {
                wp_die(esc_html__('Nonce is missing', 'block-for-mailchimp'), '', ['response' => 400]);
            }
        
            // Unslash and sanitize the nonce
            $nonce = sanitize_text_field(wp_unslash($_GET['nonce']));
        
            // Verify the nonce
            if (!wp_verify_nonce($nonce, 'mcbAllAudienceList')) {
                wp_die(esc_html__('Invalid nonce', 'block-for-mailchimp'), '', ['response' => 403]);
            }
        
            // Check if the access token parameter exists
            if (!isset($_GET['accessToken'])) {
                wp_die(esc_html__('Access token is required', 'block-for-mailchimp'), '', ['response' => 400]);
            }
        
            // Unslash and sanitize the access token
            $accessToken = sanitize_text_field(wp_unslash($_GET['accessToken']));
        
            // Make the first API request to get metadata
            $response = wp_safe_remote_get("https://login.mailchimp.com/oauth2/metadata", [
                "method" => "GET",
                "headers" => [
                    "Authorization" => "Bearer " . $accessToken,
                ],
            ]);
        
            if (is_wp_error($response)) {
                wp_die(esc_html__('Failed to fetch data from Mailchimp', 'block-for-mailchimp'), '', ['response' => 500]);
            }
        
            $body = wp_remote_retrieve_body($response);
            $metadata = json_decode($body, true);
        
            // Check if the API endpoint exists in the metadata
            if (isset($metadata['api_endpoint'])) {
                $endpoint_url = $metadata['api_endpoint'];
                $url = "$endpoint_url/3.0/lists";
        
                // Make the second API request to fetch audience lists
                $response = wp_remote_get("$url?count=1000&offset=0", [
                    "method" => "GET",
                    "headers" => [
                        "Authorization" => "Bearer " . $accessToken, 
                    ],
                ]);
        
                if (is_wp_error($response)) {
                    wp_die(esc_html__('Failed to fetch audience lists from Mailchimp', 'block-for-mailchimp'), '', ['response' => 500]);
                }
        
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
        
                // Include the endpoint URL in the response
                $data['endpoint_url'] = esc_url($url);

                
        
                wp_send_json_success($data);
            } else {
                wp_die(esc_html__('Invalid response from Mailchimp', 'block-for-mailchimp'), '', ['response' => 500]);
            }
        
            wp_die();
        }
        
        public function mcb_get_access_token () {

            if ( ! wp_verify_nonce( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ): '', 'mcbAccessTokenGet' ) ) {
                wp_die();
            }
            $state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

             try {
                 $response = wp_remote_get("https://api.bplugins.com/wp-json/mailchimp/v1/get-token/?state=$state");
                 wp_send_json_success($response);
             } catch (\Throwable $th) {
                //throw $th;
                wp_send_json_error('something went wrong!');
             }


            // echo wp_remote_retrieve_body($response);
            // wp_die();
        }

        public function mcbSubmit_Form_Data() {

            if (!wp_verify_nonce(isset($_GET['nonce'])?sanitize_text_field(wp_unslash($_GET['nonce'])): '', 'mcbFormData' ) ) {
                wp_die();
            }

            $data =  get_option('mcb-email-collect');
            $info = json_decode($data, true);
        
            $apiKey = $info['key'];
            $audienceId = isset( $_GET['audienceId'] ) ? sanitize_text_field( wp_unslash( $_GET['audienceId'] ) ) : '';
            $email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
            $fName = isset( $_GET['fName'] ) ? sanitize_text_field( wp_unslash( $_GET['fName'] )) : '';
            $lName = isset( $_GET['lName'] ) ? sanitize_text_field( wp_unslash( $_GET['lName'] )) : '';
            $endpoint_url = isset( $_GET['endpoint_url'] ) ? sanitize_text_field( wp_unslash($_GET['endpoint_url']) ) : '';
            $accessToken = $info['accessToken'];
            
            $dc = substr($apiKey, strpos($apiKey, '-') + 1);

            $mailDataCenterList = ["us1", "us2", "us3", "us4", "us5", "us6", "us7", "us8", "us9", "us10", "us11", "us12", "us13", "us14", "us15", "us16", "us17", "us18", "us19", "us20"];
        
            if (!in_array($dc, $mailDataCenterList) && !$endpoint_url) {
                echo wp_json_encode(['success' => false, 'status' => 502, 'message' => 'Invalid API Key or endpoint URL!']);
                wp_die();
            }
        
            if (!$audienceId) {
                echo wp_json_encode(['success' => false, 'status' => 510, 'message' => 'Audience ID Required!']);
                wp_die();
            }
        
            if (!$email) {
                echo wp_json_encode(['success' => false, 'status' => 511, 'message' => 'Email Address Required!']);
                wp_die();
            }
        
            $url = '';
            $headers = ["Content-Type" => "application/json"];

            if ($apiKey) {
                $url = "https://$dc.api.mailchimp.com/3.0/lists/$audienceId/members";
                $headers["Authorization"] = "apikey " . $apiKey;
            } else if ($endpoint_url) {
                $url = "$endpoint_url/$audienceId/members";
                $headers["Authorization"] = "Bearer " .$accessToken; // Assuming Bearer token for endpoint URL
            } else {
                echo wp_json_encode(['success' => false, 'status' => 500, 'message' => 'API Key or endpoint URL Required!']);
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
                echo wp_json_encode(['success' => false, 'status' => 500, 'message' => 'Failed to connect to Mailchimp']);
                wp_die();
            }
        
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
        
            if (isset($data['status']) && $data['status'] == 'subscribed') {
                echo wp_json_encode(['status' => $data['status'], 'message' => 'Successfully subscribed']);
            } else {
                echo wp_json_encode(['status' => $data['status'], 'message' => 'Failed to subscribe', 'data' => $data, ]);
            }
        
            wp_die();
        }

        public function mcbSubmit_Form_AudienceId()
        {
            if ( ! wp_verify_nonce( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '', 'mcbAudienceIDList' ) ) {
                wp_die();
            }

            $apiKey = isset( $_GET['apiKey'] ) ? sanitize_text_field( wp_unslash( $_GET['apiKey'] ) ) : '';
            $dc = substr($apiKey, strpos($apiKey, '-') + 1);

            $mailDataCenterList = ["us1", "us2", "us3", "us4", "us5", "us6", "us7", "us8", "us9", "us10", "us11", "us12", "us13", "us14", "us15", "us16", "us17", "us18", "us19", "us20"];

            if (!in_array($dc, $mailDataCenterList)) {
                echo wp_json_encode(['success' => false, 'status' => 502, 'message' => 'Invalid API Key!']);
                wp_die();
            }

            try {
                $res = wp_remote_get("https://$dc.api.mailchimp.com/3.0/lists?count=1000&offset=0", [
                    "headers" => [
                        "Authorization" => "Basic " . $apiKey,
                        "Content-Type" => "application/json",
                    ],
                ]);
                wp_send_json_success($res['body']);
            } catch (\Throwable $th) {
                //throw $th;
                wp_send_json_error('Something went wrong!');
            }
        }
    }
    new MailChimpApi();
}
 