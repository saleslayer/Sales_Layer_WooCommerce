<?php

class slAnalytics 
{

    private $SL_API_URL;

    protected $debug_level;
    
    protected $scopeConfig;
    private $analyticsData = [];
    
    public function __construct()
    {
        global $debug_level;
        $this->debug_level = $debug_level ?? 0;
        $this->SL_API_URL = 'https://'.SLYR_WC_url_API.'?s=conn_plug_analytics';
    }

    /**
     * Get Analytics public key
     *
     * @return string|null Analytics public key
     */
    private function getAnalyticsPublicKey(): ?string
    {
        
        if (!defined("SLYR_ANALYTICS_PUBLIC_KEY")) {
            return null;
        }
        $analyticsPublicKey = SLYR_ANALYTICS_PUBLIC_KEY;

        return $analyticsPublicKey ? base64_decode($analyticsPublicKey) : null;
    }

    /**
     * Load analytics data
     *
     * @param array $analyticsData Analytics data
     * @return bool True if data is loaded, false otherwise
     */
    public function loadAnalyticsData(array $analyticsData): bool
    {

        if ($this->validateData($analyticsData)) {
            $this->analyticsData = $analyticsData;
            return true;
        }

        return false;
    }

    /**
     * Validate data
     *
     * @param array $data Data to validate indexes
     * @return bool True if data is valid, false otherwise
     */
    private function validateData(array $data): bool
    {
        $expectedKeys = [
            'conn_code',
            'comp_id',
            'secret_key',
            'conn_type',
            'last_update',
            'api_item_count',
            'ecommerce_version',
            'plugin_version'
        ];
    
        if (isset($data['plugin_config']['all_analytics_data']) && $data['plugin_config']['all_analytics_data'] == 0){
            if (($keyToRemove = array_search('ecommerce_version', $expectedKeys)) !== false) {
                unset($expectedKeys[$keyToRemove]);
            }
        }

        foreach ($expectedKeys as $key) {
            if (!array_key_exists($key, $data)){
                sl_debug('## Error. Analytics data incomplete. Missing index: '.print_r($key, true));
                return false;
            }
        }

        return true;
    }

    /**
     * Send analytics calls through cURL
     *
     * @return bool True if cURL response is valid, false otherwise
     */
    public function sendAnalyticsData(): bool
    {

        $encryptedPackage = $this->createEncryptedJsonPackage();
        if (!$encryptedPackage) {
            return false;
        }

        $ch = curl_init($this->SL_API_URL);

        curl_setopt_array($ch, [
            CURLOPT_CONNECTTIMEOUT => 1800,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['encryptedPackage' => $encryptedPackage],
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            // Connection error or another cURL error
            sl_debug('## Error. Analytics error connection: '.curl_error($ch));
            curl_close($ch);
            return false;
        }

        // Obtain the answer HTTP status code
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code >= 400) {
            // If the HTTP code is 400 or higher, we consider it an error
            sl_debug('## Error. HTTP Error '.$http_code.'. Response: '.print_r($response, true));
            curl_close($ch);
            return false;
        }

        if ($http_code == 200) {
            // If the HTTP code is 200, we print the response
            sl_debug('Analytics data stored: '.$response);
            curl_close($ch);
            return true;

        }

        curl_close($ch);

        return true;
    }

    /**
     * Encrypt analytics data into a JSON package
     *
     * @return string|false Encrypted JSON package, or false on failure
     */
    private function createEncryptedJsonPackage()
    {
        
        $publicKey = $this->getAnalyticsPublicKey();
        if (!$publicKey) {
            sl_debug('## Error. Invalid public key.');
            return false;
        }

        $encodedData = json_encode($this->analyticsData);

        // Generate an AES key for this operation
        $aesKey = openssl_random_pseudo_bytes(32); // 256 bits for AES-256
       
        // Encrypt data with AES
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $encryptedData = openssl_encrypt($encodedData, 'AES-256-CBC', $aesKey, 0, $iv);

        // Encrypt AES key with RSA public key
        if (!openssl_public_encrypt($aesKey, $encryptedCEKey, $publicKey)) {
            sl_debug('## Error. RSA encryption failed.');
            return false;
        }

        // Generate an HMAC to ensure the message's integrity
        $hmac = hash_hmac('sha256', $encryptedData, $aesKey, true);

        $encryptedPackage = [
            'iv' => base64_encode($iv),
            'encryptedData' => base64_encode($encryptedData),
            'encryptedCEKey' => base64_encode($encryptedCEKey),
            'hmac' => base64_encode($hmac),
        ];

        $encryptedJsonPackage = base64_encode(json_encode($encryptedPackage));
        
        return $encryptedJsonPackage;
    }
}
