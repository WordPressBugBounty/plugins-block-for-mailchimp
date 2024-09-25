<?php

class MailChimpApi
{

    public function __construct()
    {
        add_action('wp_ajax_mcbAudienceList', [$this, 'mcbAudienceList']);
        add_action('wp_ajax_nopriv_mcbAudienceList', [$this, 'mcbAudienceList']);

        add_action('wp_ajax_mcb_get_access_token', [$this, 'mcb_get_access_token']);
        add_action('wp_ajax_nopriv_mcb_get_access_token', [$this, 'mcb_get_access_token']);

        add_action('wp_ajax_mcbSubmit_Form_Data', [$this, 'mcbSubmit_Form_Data']);
        add_action('wp_ajax_nopriv_mcbSubmit_Form_Data', [$this, 'mcbSubmit_Form_Data']);

        add_action('wp_ajax_mcbSubmit_Form_AudienceId', [$this, 'mcbSubmit_Form_AudienceId']);
        add_action('wp_ajax_nopriv_mcbSubmit_Form_AudienceId', [$this, 'mcbSubmit_Form_AudienceId']);
    }

    public function mcbAudienceList() {
        if (!wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'wp_rest')) {
            wp_die();
        }
    
        $accessToken = sanitize_text_field($_GET['accessToken']) ?? false;
    
        if (!$accessToken) {
            wp_die('Access token is required', '', ['response' => 400]);
        }
    
        $response = wp_remote_get("https://login.mailchimp.com/oauth2/metadata", [
            "method" => "GET",
            "headers" => [
                "Authorization" => "Bearer " . $accessToken,
            ]
        ]);
    
        if (is_wp_error($response)) {
            wp_die('Failed to fetch data from Mailchimp', '', ['response' => 500]);
        }
    
        $body = wp_remote_retrieve_body($response);
        $metadata = json_decode($body, true);
    
        if (isset($metadata['api_endpoint'])) {
            $endpoint_url = $metadata['api_endpoint'];
            $url = "$endpoint_url/3.0/lists";
            $response = wp_remote_get("$url", [
                "method" => "GET",
                "headers" => [
                    "Authorization" => "Bearer " . $accessToken,
                ]
            ]);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            $data['endpoint_url'] = $url;
            wp_send_json_success($data);
            
        } else {
            wp_die('Invalid response from Mailchimp', '', ['response' => 500]);
        }
    
        wp_die();
    }
    

    public function mcb_get_access_token () {

        if (!wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'wp_rest')) {
            wp_die();
        }
        $state = sanitize_text_field($_GET['state']) ?? '';

        $response = wp_remote_get("https://api.bplugins.com/wp-json/mailchimp/v1/get-token/?state=$state");

        echo wp_remote_retrieve_body($response);
        wp_die();
    }

    // public function mcbSubmit_Form_Data()
    // {

    //     if (!wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'wp_rest')) {
    //         wp_die();
    //     }

    //     $apiKey = sanitize_text_field($_GET['apiKey']) ?? false;
    //     $audienceId = sanitize_text_field($_GET['audienceId']) ?? false;
    //     $email = sanitize_text_field($_GET['email']) ?? false;
    //     $fName = sanitize_text_field($_GET['fName']) ?? false;
    //     $lName = sanitize_text_field($_GET['lName']) ?? false;
    //     $endpoint_url = sanitize_text_field($_GET['endpoint_url']) ?? false;
    //     $dc = substr($apiKey, strpos($apiKey, '-') + 1);

    //     $mailDataCenterList = ["us1", "us2", "us3", "us4", "us5", "us6", "us7", "us8", "us9", "us10", "us11", "us12", "us13", "us14", "us15", "us16", "us17", "us18", "us19", "us20"];

        
    //     if (!in_array($dc, $mailDataCenterList)) {
    //         echo wp_json_encode(['success' => false, 'status' => 502, 'message' => 'Invalid API Key!']);
    //         wp_die();
    //     }

    //     if (!$audienceId) {
    //         echo wp_json_encode(['success' => false, 'status' => 510, 'message' => 'Audience ID Required!']);
    //         wp_die();
    //     }

    //     $url = '';
    //     if(!$apiKey) {
    //         $url = $endpoint_url;
    //     }else  {
    //         $url = "https://$dc.api.mailchimp.com/3.0/lists/$audienceId/members";
    //     }


    //     $response = wp_remote_post($url, [
    //         "method" => "POST",
    //         "headers" => [
    //             "Authorization" => "apikey " . $apiKey,
    //             "Content-Type" => "application/json",
    //         ],
    //         "body" => wp_json_encode(
    //             [
    //                 "email_address" => $email,
    //                 "status" => "subscribed",
    //                 'merge_fields' => [
    //                     'FNAME' => $fName,
    //                     'LNAME' => $lName,
    //                 ],
    //             ]
    //         ),
    //     ]);

    //     echo wp_remote_retrieve_body($response);
    //     wp_die();

    // }

    public function mcbSubmit_Form_Data() {
        if (!wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'wp_rest')) {
            wp_die();
        }
    
        $apiKey = sanitize_text_field($_GET['apiKey']) ?? false;
        $audienceId = sanitize_text_field($_GET['audienceId']) ?? false;
        $email = sanitize_text_field($_GET['email']) ?? false;
        $fName = sanitize_text_field($_GET['fName']) ?? false;
        $lName = sanitize_text_field($_GET['lName']) ?? false;
        $endpoint_url = sanitize_text_field($_GET['endpoint_url']) ?? false;
        $accessToken = sanitize_text_field($_GET['accessToken']) ?? false;
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
    
        $response = wp_remote_post($url, [
            "method" => "POST",
            "headers" => $headers,
            "body" => wp_json_encode([
                "email_address" => $email,
                "status" => "subscribed",
                'merge_fields' => [
                    'FNAME' => $fName,
                    'LNAME' => $lName,
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

        if (!wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'wp_rest')) {
            wp_die();
        }

        $apiKey = sanitize_text_field($_GET['apiKey']) ?? false;
        $dc = substr($apiKey, strpos($apiKey, '-') + 1);

        $mailDataCenterList = ["us1", "us2", "us3", "us4", "us5", "us6", "us7", "us8", "us9", "us10", "us11", "us12", "us13", "us14", "us15", "us16", "us17", "us18", "us19", "us20"];

        if (!in_array($dc, $mailDataCenterList)) {
            echo wp_json_encode(['success' => false, 'status' => 502, 'message' => 'Invalid API Key!']);
            wp_die();
        }

        $res = wp_remote_get("https://$dc.api.mailchimp.com/3.0/lists?count=1000&offset=0", [
            "headers" => [
                "Authorization" => "Basic " . $apiKey,
                "Content-Type" => "application/json",
            ],
        ]);

        echo wp_send_json($res['body']);
    }
}
new MailChimpApi();


// add_action('wp_footer', function () {

//     $response = wp_remote_get("https://api.bplugins.com/wp-json/mailchimp/v1/get-token/?state=ksafdasdikktestalamn");

//     echo wp_remote_retrieve_body($response);
     
// });