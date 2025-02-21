<?php

namespace App\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Crunch
{
    private $baseUrl;

    private $tokenUrl;
    private $basicAuth;
    private $etpCookie;


    public function __construct()
    {
        $this->baseUrl = get_setting('crunch_base_url');
        $this->tokenUrl = get_setting('crunch_token_endpoint');
        $this->basicAuth = get_setting('crunch_basic_auth');
        $this->etpCookie = get_setting('crunch_etp_cookie');
        if (! Cache::get('crunch_access_token')) {
            $this->authenticate();
        }
    }

    public function getAnimeList($offset = 0)
    {
        $data = [
            'start' => $offset,
            'n' => 36,
            'sort_by' => 'popularity',
            'ratings' => 'true',
            'preferred_audio_language' => 'ja-JP',
            'locale' => 'en-US',
        ];

        return $this->sendRequest('get', get_setting('crunch_browse_endpoint'), $data, true);
    }

    /**
     * Authenticate using OAuth2 and store the token.
     */
    public function authenticate()
    {
        $response = Http::asForm()->withHeaders([
                'Authorization' => "Basic $this->basicAuth",
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept-Language' => 'en-US',
                'Cookie' => "etp_rt=$this->etpCookie",
                'User-Agent' => "PostmanRuntime/7.43.0"
            ])
            ->post("{$this->baseUrl}{$this->tokenUrl}", [
                'grant_type' => get_setting('crunch_grant_type'),
                'device_id' => get_setting('crunch_device_id'),
                'device_type' => get_setting('crunch_device_type'),
            ]);

        if ($response->successful()) {
            Cache::put('crunch_access_token', $response->json('access_token'), $response->json('expires_in'));
            Cache::put('crunch_refresh_token', $response->json('refresh_token'));
            Setting::updateOrCreate([
                'key' => 'crunch_access_token',
            ], [
                'value' => $response->json('access_token'),
            ]);
            Setting::updateOrCreate([
                'key' => 'crunch_refresh_token',
            ], [
                'value' => $response->json('refresh_token'),
            ]);
        } else {
            throw new Exception('Authentication failed: ' . $response->body());
        }
    }

    /**
     * Send an authorized API request.
     */
    private function sendRequest($method, $endpoint, $data, $withHeaders = false)
    {
        $token = Cache::get('crunch_access_token');
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
            'User-Agent' => "PostmanRuntime/7.43.0"
        ])->{$method}("{$this->baseUrl}{$endpoint}", $data);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('API request failed: ' . $response->body());
    }
}
