<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KommoService
{
    private string $baseUrl;
    private ?string $token;

    public function __construct()
    {
        $subdomain     = config('services.kommo.subdomain');
        $this->baseUrl = "https://{$subdomain}.kommo.com/api/v4";
        $this->token   = config('services.kommo.long_token');
    }

    public function exchangeCode(string $code): bool
    {
        $subdomain = config('services.kommo.subdomain');

        $response = Http::post("https://{$subdomain}.kommo.com/oauth2/access_token", [
            'client_id'     => config('services.kommo.client_id'),
            'client_secret' => config('services.kommo.client_secret'),
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => config('services.kommo.redirect_uri'),
        ]);

        if ($response->failed()) {
            Log::error('Kommo OAuth exchange failed', ['response' => $response->json()]);
            return false;
        }

        $this->saveTokens($response->json());
        return true;
    }

    public function refreshToken(): bool
    {
        $subdomain = config('services.kommo.subdomain');
        $token     = \App\Models\KommoToken::getCurrent();

        if (!$token) {
            Log::error('Kommo: no hay token almacenado para refrescar');
            return false;
        }

        $response = Http::post("https://{$subdomain}.kommo.com/oauth2/access_token", [
            'client_id'     => config('services.kommo.client_id'),
            'client_secret' => config('services.kommo.client_secret'),
            'grant_type'    => 'refresh_token',
            'refresh_token' => $token->refresh_token,
            'redirect_uri'  => config('services.kommo.redirect_uri'),
        ]);

        if ($response->failed()) {
            Log::error('Kommo token refresh failed', ['response' => $response->json()]);
            return false;
        }

        $this->saveTokens($response->json());
        return true;
    }

    public function getLeads(int $limit = 50, int $page = 1): array
    {
        $response = Http::withToken($this->getActiveToken())
            ->get("{$this->baseUrl}/leads", [
                'limit' => $limit,
                'page'  => $page,
                'with'  => 'contacts',
            ]);

        if ($response->failed()) {
            Log::error('Kommo getLeads failed', ['response' => $response->json()]);
            return [];
        }

        return $response->json('_embedded.leads', []);
    }

    public function getLead(int $leadId): ?array
    {
        $response = Http::withToken($this->getActiveToken())
            ->get("{$this->baseUrl}/leads/{$leadId}", [
                'with' => 'contacts',
            ]);

        if ($response->failed()) {
            Log::error('Kommo getLead failed', [
                'lead_id'  => $leadId,
                'response' => $response->json(),
            ]);
            return null;
        }

        return $response->json();
    }

    public function getContact(int $contactId): ?array
    {
        $response = Http::withToken($this->getActiveToken())
            ->get("{$this->baseUrl}/contacts/{$contactId}");

        if ($response->failed()) {
            Log::error('Kommo getContact failed', [
                'contact_id' => $contactId,
                'response'   => $response->json(),
            ]);
            return null;
        }

        return $response->json();
    }

    private function getActiveToken(): string
    {
        $dbToken = \App\Models\KommoToken::getCurrent();

        if ($dbToken && !$dbToken->isExpired()) {
            return $dbToken->access_token;
        }

        return $this->token ?? '';
    }

    private function saveTokens(array $data): void
    {
        \App\Models\KommoToken::create([
            'subdomain'     => config('services.kommo.subdomain'),
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at'    => now()->addSeconds($data['expires_in']),
        ]);
    }
}