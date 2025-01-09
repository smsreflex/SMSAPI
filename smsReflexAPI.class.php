<?php
class SmsApi {
    const URL_DEV = 'https://dev-api.smsreflex.com/api';
    const URL_PROD = 'https://api.smsreflex.com/api';

    private $authPass;
    private $authAk;
    private $authCk;
    private $baseUrl;
    private $curl;
    private $timestamp;
    private $hash;

    public function __construct($authPass, $authAk, $authCk, $environment = 'dev') {
        $this->authPass = $authPass;
        $this->authAk = $authAk;
        $this->authCk = $authCk;
        $this->baseUrl = $this->getBaseUrlFromEnvironment($environment);
        $this->curl = curl_init();
        $this->initializeAuth();
    }

    private function getBaseUrlFromEnvironment($environment) {
        switch (strtolower($environment)) {
            case 'prod':
            case 'production':
                return self::URL_PROD;
            case 'dev':
            case 'development':
                return self::URL_DEV;
            default:
                throw new Exception('Invalid environment. Use "dev" or "prod".');
        }
    }

    private function initializeAuth() {
        curl_setopt_array($this->curl, array(
            CURLOPT_URL => $this->baseUrl . '/auth/time',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET'
        ));

        $this->timestamp = curl_exec($this->curl);
        if ($this->timestamp === false) {
            throw new Exception('Failed to get timestamp: ' . curl_error($this->curl));
        }

        $key = join('-', array($this->authAk, $this->authPass, $this->authCk, $this->timestamp));
        $this->hash = '$1$' . hash('sha256', $key, false);
    }

    private function getAuthHeaders() {
        return array(
            "x-auth-ak: {$this->authAk}",
            "x-auth-ck: {$this->authCk}",
            "x-auth-ts: {$this->timestamp}",
            "x-auth-signature: {$this->hash}",
            "Content-Type: application/json"
        );
    }

    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $this->getAuthHeaders()
        );

        if ($data !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($this->curl, $options);
        
        $response = curl_exec($this->curl);
        $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        
        if ($response === false) {
            throw new Exception('API request failed: ' . curl_error($this->curl));
        }

        return array(
            'status' => $httpCode,
            'data' => json_decode($response, true)
        );
    }

    // Messages
    public function sendSms($data) {
        return $this->makeRequest('messages', 'POST', $data);
    }

    public function getSmsDetails($messageId) {
        return $this->makeRequest("messages/{$messageId}");
    }

    public function getMessagesStats($params = []) {
        $query = http_build_query($params);
        return $this->makeRequest("messages/stats?{$query}");
    }

    // Campaigns
    public function createCampaign($data) {
        return $this->makeRequest('new-campaigns', 'POST', $data);
    }

    public function getCampaignDetails($campaignId) {
        return $this->makeRequest("new-campaigns/{$campaignId}");
    }

    public function updateCampaign($campaignId, $data) {
        return $this->makeRequest("new-campaigns/{$campaignId}", 'PUT', $data);
    }

    public function getCampaignStats($campaignId) {
        return $this->makeRequest("new-campaigns/{$campaignId}/stats");
    }

    public function uploadCampaignFiles($campaignId, $files) {
        return $this->makeRequest("new-campaigns/{$campaignId}/files", 'POST', $files);
    }

    public function getCampaignFiles($campaignId) {
        return $this->makeRequest("new-campaigns/{$campaignId}/files");
    }

    public function getCampaignFile($campaignId, $fileId) {
        return $this->makeRequest("new-campaigns/{$campaignId}/files/{$fileId}");
    }

    // Chats
    public function getAllChats() {
        return $this->makeRequest("chats");
    }

    public function getChatByDestination($destination) {
        return $this->makeRequest("chats/{$destination}");
    }

    public function getChatList() {
        return $this->makeRequest("messages/chat-list");
    }

    public function getMessagesByChat($chatId) {
        return $this->makeRequest("messages/chat/{$chatId}");
    }

    public function sendReply($data) {
        return $this->makeRequest("messages/reply", 'POST', $data);
    }

    // Stats
    public function getStats($fromDate, $toDate) {
        $query = http_build_query(['from' => $fromDate, 'to' => $toDate]);
        return $this->makeRequest("stats?{$query}");
    }

    // Short URLs
    public function createShortUrl($url) {
        return $this->makeRequest('shorturls', 'POST', ['url' => $url]);
    }

    // Verification
    public function sendVerificationCode($destination, $params = []) {
        $data = array_merge(['destination' => $destination], $params);
        return $this->makeRequest("verification/send/{$destination}", 'POST', $data);
    }

    public function verifyCode($destination, $code) {
        return $this->makeRequest("verification/verify/{$destination}", 'PUT', ['code' => $code]);
    }

    public function cancelVerification($destination) {
        return $this->makeRequest("verification/cancel/{$destination}", 'DELETE');
    }

    // Utils
    public function countMessageChars($message) {
        return $this->makeRequest('utils/count', 'POST', ['message' => $message]);
    }

    // Webhooks
    public function setWebhook($url, $method = 'post') {
        return $this->makeRequest('store-webhook', 'POST', [
            'url' => $url,
            'method' => strtolower($method)
        ]);
    }

    public function getWebhook() {
        return $this->makeRequest('retrieve-webhook');
    }

    public function __destruct() {
        if ($this->curl) {
            curl_close($this->curl);
        }
    }
}
?>
