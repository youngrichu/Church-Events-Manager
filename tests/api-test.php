<?php
/**
 * API Test Script
 * 
 * Usage: php api-test.php your-site.com your-jwt-token
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

if ($argc < 3) {
    die("Usage: php api-test.php your-site.com your-jwt-token\n");
}

$site_url = $argv[1];
$jwt_token = $argv[2];

class APITester {
    private $base_url;
    private $jwt_token;
    private $test_event_id;

    public function __construct($site_url, $jwt_token) {
        $this->base_url = "https://{$site_url}/wp-json/church-events/v1";
        $this->jwt_token = $jwt_token;
    }

    public function run_tests() {
        echo "Starting API Tests...\n\n";

        // Test 1: Get All Events
        $this->test_get_events();

        // Test 2: Get Single Event
        $this->test_get_single_event();

        // Test 3: Create RSVP
        $this->test_create_rsvp();

        // Test 4: Get RSVP Status
        $this->test_get_rsvp_status();

        // Test 5: Update RSVP
        $this->test_update_rsvp();

        // Test 6: Delete RSVP
        $this->test_delete_rsvp();

        echo "\nAll tests completed!\n";
    }

    private function make_request($endpoint, $method = 'GET', $data = null) {
        $ch = curl_init();
        
        $url = $this->base_url . $endpoint;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $headers = ['Content-Type: application/json'];
        if ($this->jwt_token) {
            $headers[] = "Authorization: Bearer {$this->jwt_token}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $http_code,
            'body' => json_decode($response, true)
        ];
    }

    private function test_get_events() {
        echo "Testing GET /events...\n";
        $response = $this->make_request('/events');
        
        if ($response['code'] === 200 && is_array($response['body'])) {
            echo "✅ Success: Retrieved events list\n";
            if (!empty($response['body'])) {
                $this->test_event_id = $response['body'][0]['id'];
            }
        } else {
            echo "❌ Error: Failed to get events\n";
        }
    }

    private function test_get_single_event() {
        if (!$this->test_event_id) {
            echo "⚠️ Skipping single event test: No test event ID\n";
            return;
        }

        echo "Testing GET /events/{$this->test_event_id}...\n";
        $response = $this->make_request("/events/{$this->test_event_id}");
        
        if ($response['code'] === 200 && isset($response['body']['id'])) {
            echo "✅ Success: Retrieved single event\n";
        } else {
            echo "❌ Error: Failed to get single event\n";
        }
    }

    private function test_create_rsvp() {
        if (!$this->test_event_id) {
            echo "⚠️ Skipping RSVP creation test: No test event ID\n";
            return;
        }

        echo "Testing POST /events/{$this->test_event_id}/rsvp...\n";
        $response = $this->make_request(
            "/events/{$this->test_event_id}/rsvp",
            'POST',
            ['status' => 'attending']
        );
        
        if ($response['code'] === 200 && $response['body']['success']) {
            echo "✅ Success: Created RSVP\n";
        } else {
            echo "❌ Error: Failed to create RSVP\n";
        }
    }

    private function test_get_rsvp_status() {
        if (!$this->test_event_id) {
            echo "⚠️ Skipping RSVP status test: No test event ID\n";
            return;
        }

        echo "Testing GET /events/{$this->test_event_id}/rsvp-status...\n";
        $response = $this->make_request("/events/{$this->test_event_id}/rsvp-status");
        
        if ($response['code'] === 200) {
            echo "✅ Success: Retrieved RSVP status\n";
        } else {
            echo "❌ Error: Failed to get RSVP status\n";
        }
    }

    private function test_update_rsvp() {
        if (!$this->test_event_id) {
            echo "⚠️ Skipping RSVP update test: No test event ID\n";
            return;
        }

        echo "Testing PUT /events/{$this->test_event_id}/rsvp...\n";
        $response = $this->make_request(
            "/events/{$this->test_event_id}/rsvp",
            'PUT',
            ['status' => 'not_attending']
        );
        
        if ($response['code'] === 200 && $response['body']['success']) {
            echo "✅ Success: Updated RSVP\n";
        } else {
            echo "❌ Error: Failed to update RSVP\n";
        }
    }

    private function test_delete_rsvp() {
        if (!$this->test_event_id) {
            echo "⚠️ Skipping RSVP deletion test: No test event ID\n";
            return;
        }

        echo "Testing DELETE /events/{$this->test_event_id}/rsvp...\n";
        $response = $this->make_request(
            "/events/{$this->test_event_id}/rsvp",
            'DELETE'
        );
        
        if ($response['code'] === 200 && $response['body']['success']) {
            echo "✅ Success: Deleted RSVP\n";
        } else {
            echo "❌ Error: Failed to delete RSVP\n";
        }
    }
}

$tester = new APITester($site_url, $jwt_token);
$tester->run_tests(); 