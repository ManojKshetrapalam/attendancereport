<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

class EasyTimeService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected CookieJar $cookies;
    protected \Illuminate\Http\Client\PendingRequest $client;

    public function __construct()
    {
        // For production, these should be moved to config/services.php or config/easytime.php
        $this->baseUrl = env('EASYTIME_URL', 'http://192.168.0.233');
        $this->username = env('EASYTIME_USERNAME', 'admin');
        $this->password = env('EASYTIME_PASSWORD', 'admin');
        
        $this->cookies = new CookieJar();
        $this->client = Http::withOptions(['cookies' => $this->cookies])->baseUrl($this->baseUrl);
    }

    /**
     * Replicates the JS RC4 algorithm used by EasyTime Pro.
     */
    private function rc4($key, $str)
    {
        $s = array();
        for ($i = 0; $i < 256; $i++) {
            $s[$i] = $i;
        }
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % strlen($key)])) % 256;
            $x = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $x;
        }
        $i = 0;
        $j = 0;
        $res = '';
        for ($y = 0; $y < strlen($str); $y++) {
            $i = ($i + 1) % 256;
            $j = ($j + $s[$i]) % 256;
            $x = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $x;
            $res .= chr(ord($str[$y]) ^ $s[($s[$i] + $s[$j]) % 256]);
        }
        return $res;
    }

    /**
     * Authenticate with the EasyTime Pro device.
     */
    public function login(): bool
    {
        // 1. Get the login page to retrieve the CSRF token
        $response = $this->client->get('/login/');
        
        if (!$response->successful()) {
            throw new \Exception("Could not connect to EasyTime Pro device at {$this->baseUrl}");
        }

        $html = $response->body();
        preg_match("/name='csrfmiddlewaretoken' value='([^']+)'/", $html, $matches);
        
        $csrfToken = $matches[1] ?? null;

        if (!$csrfToken) {
            throw new \Exception("Could not extract CSRF token from login page.");
        }

        // 2. Encrypt the login payload
        $formData = "username={$this->username}&password={$this->password}";
        $encrypted = base64_encode($this->rc4($csrfToken, $formData));

        // 3. Send login request
        $loginResponse = $this->client->asForm()->withHeaders([
            'Referer' => $this->baseUrl . '/login/'
        ])->post('/login/', [
            'encrypt_data' => $encrypted,
            'csrfmiddlewaretoken' => $csrfToken
        ]);

        return $loginResponse->successful();
    }

    /**
     * Fetch paginated employees.
     */
    public function getEmployees($page = 1)
    {
        $response = $this->client->get('/personnel/api/employees/', [
            'page' => $page
        ]);
        
        if ($response->successful()) {
            return $response->json();
        }
        
        return null;
    }

    /**
     * Fetch paginated transactions (attendance logs).
     * @param int $page       Page number
     * @param int $limit      Records per page (max 1000 on EasyTime Pro)
     * @param string|null $startTime  ISO datetime string — only fetch records >= this time
     */
    public function getTransactions($page = 1, $limit = 1000, $startTime = null)
    {
        $params = ['page' => $page, 'limit' => $limit];
        
        if ($startTime) {
            $params['start_time'] = $startTime;
        }
        
        $response = $this->client->get('/iclock/api/transactions/', $params);
        
        if ($response->successful()) {
            return $response->json();
        }
        
        return null;
    }
}
