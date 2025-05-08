<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\Request;

class ZohoApiService
{
    protected $clientId;
    protected $clientSecret;
    protected $grantToken;
    protected $accountsUrl;
    protected $apiDomain;
    protected $redirectUri;
    protected $scope;

    // Cache keys
    const REFRESH_TOKEN_CACHE_KEY = 'zoho_refresh_token';
    const ACCESS_TOKEN_CACHE_KEY = 'zoho_access_token';
    const TOKEN_EXPIRY_CACHE_KEY = 'zoho_token_expiry';

    public function __construct()
    {
        $this->clientId = env('ZOHO_CLIENT_ID');
        $this->clientSecret = env('ZOHO_CLIENT_SECRET');
        $this->grantToken = env('ZOGO_GRANT_TOKEN');
        $this->accountsUrl = env('ZOHO_ACCOUNTS_URL', 'https://accounts.zoho.com');
        $this->apiDomain = env('ZOHO_API_DOMAIN', 'https://www.zohoapis.com');
        $this->redirectUri = env('ZOHO_REDIRECT_URI');
        // $this->scope = env('ZOHO_SCOPE', 'ZohoBooks.fullaccess.all');

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception('Zoho API credentials are not properly configured');
        }
    }

    /**
     * Get current access token with automatic refresh
     */
    public function getAccessToken(): ?string
    {
        // Check if we have a valid cached access token
        $accessToken = Cache::get(self::ACCESS_TOKEN_CACHE_KEY);
        $expiryTime = Cache::get(self::TOKEN_EXPIRY_CACHE_KEY);

        if ($accessToken && $expiryTime && now()->lt($expiryTime)) {
            return $accessToken;
        }

        // Try to refresh the token
        return $this->refreshAccessToken();
    }

    /**
     * Get current refresh token from cache or env (for initial setup)
     */
    protected function getRefreshToken(): ?string
    {
        return Cache::get(self::REFRESH_TOKEN_CACHE_KEY) ?? env('ZOHO_REFRESH_TOKEN');
    }

    /**
     * Refresh access token automatically
     */
    protected function refreshAccessToken(): ?string
    {
        $refreshToken = $this->getRefreshToken();
        
        if (empty($refreshToken)) {
            Log::error('No refresh token available for Zoho OAuth');
            return null;
        }

        try {
            $response = Http::asForm()->post("{$this->accountsUrl}/oauth/v2/token", [
                'refresh_token' => $refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->successful()) {
                return $this->storeTokens(
                    $response->json('access_token'),
                    $response->json('expires_in'),
                    $refreshToken // Refresh token remains the same until re-authorization
                );
            }

            Log::error('Failed to refresh Zoho token', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
        } catch (Exception $e) {
            Log::error('Exception refreshing Zoho token: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Generate new tokens using authorization code
     */
    public function generateNewTokens(): bool
    {
        try {
            $response = Http::asForm()->post("{$this->accountsUrl}/oauth/v2/token", [
                'code' => $this->grantToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'authorization_code',
            ]);

            if ($response->successful()) {
                $this->storeTokens(
                    $response->json('access_token'),
                    $response->json('expires_in'),
                    $response->json('refresh_token')
                );
                return true;
            }

            Log::error('Failed to generate new Zoho tokens', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
        } catch (Exception $e) {
            Log::error('Exception generating new Zoho tokens: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Store tokens in cache
     */
    protected function storeTokens(string $accessToken, int $expiresIn, string $refreshToken): string
    {
        $expiryTime = now()->addSeconds($expiresIn - 60); // Subtract 1 minute as buffer

        // Store tokens in cache
        Cache::put(self::ACCESS_TOKEN_CACHE_KEY, $accessToken, $expiryTime);
        Cache::put(self::TOKEN_EXPIRY_CACHE_KEY, $expiryTime, $expiryTime);
        Cache::forever(self::REFRESH_TOKEN_CACHE_KEY, $refreshToken);

        return $accessToken;
    }

    // /**
    //  * Get authorization URL for initial setup
    //  */
    // public function getAuthorizationUrl(string $state): string
    // {
    //     return "{$this->accountsUrl}/oauth/v2/auth?" . http_build_query([
    //         'scope' => $this->scope,
    //         'client_id' => $this->clientId,
    //         'state' => $state,
    //         'response_type' => 'code',
    //         'redirect_uri' => $this->redirectUri,
    //         'access_type' => 'offline',
    //         'prompt' => 'consent'
    //     ]);
    // }

    /**
     * Make API call to Zoho services
     */
    public function makeApiCall(string $method, string $endpoint, array $data = [], array $queryParams = [])
    {
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            throw new Exception('Unable to obtain valid access token');
        }

        $url = rtrim($this->apiDomain, '/') . '/' . ltrim($endpoint, '/');
        
        return Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])
            ->{strtolower($method)}($url, !empty($queryParams) ? array_merge($data, ['query' => $queryParams]) : $data);
    }

    /**
     * Clear all stored tokens
     */
    public function clearTokens(): void
    {
        Cache::forget(self::ACCESS_TOKEN_CACHE_KEY);
        Cache::forget(self::TOKEN_EXPIRY_CACHE_KEY);
        Cache::forget(self::REFRESH_TOKEN_CACHE_KEY);
    }
}
