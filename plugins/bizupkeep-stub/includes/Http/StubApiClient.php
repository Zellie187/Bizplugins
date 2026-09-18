<?php

declare(strict_types=1);

namespace BizHub\Stub\Http;

use BizHub\Stub\Contracts\StubApiClientInterface;
use BizHub\Stub\Exceptions\StubApiException;

/**
 * Talks to Stub's Connect API (https://developers.stub.africa,
 * https://connect.stub.africa/api/docs/index.html). Mirrors
 * bizupkeep-payments' YocoGateway request/response handling exactly:
 * wp_remote_*() with a 20s timeout, is_wp_error() -> StubApiException,
 * non-2xx or malformed body -> StubApiException.
 *
 * Credentials/environment are read from WP options (never hardcoded),
 * set on the Stub Settings admin page - the same convention every
 * other gateway in this codebase uses.
 *
 * @package BizHub\Stub\Http
 */
final class StubApiClient implements StubApiClientInterface
{
    public function verifyApiKey(): bool
    {
        $response = wp_remote_get(
            $this->url('/api/verify/apikey') . '?' . http_build_query([
                'apikey' => $this->apiKey(),
                'appid' => $this->appId(),
            ]),
            ['timeout' => 20]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        return $status >= 200 && $status < 300;
    }

    public function requestAuthToken(?string $uid = null): array
    {
        $body = ['apikey' => $this->apiKey(), 'appid' => $this->appId()];

        if ($uid !== null) {
            $body['uid'] = $uid;
        }

        $response = $this->post('/api/auth/token', $body);

        $token = $response['token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new StubApiException('Stub auth-token response did not include a token.');
        }

        return ['token' => $token];
    }

    public function pushBusiness(array $data): array
    {
        $response = $this->post('/api/push/business', [
            'apikey' => $this->apiKey(),
            'appid' => $this->appId(),
            'data' => $data,
        ]);

        $uid = $response['uid'] ?? null;

        if (! is_string($uid) || $uid === '') {
            throw new StubApiException('Stub push/business response did not include a uid.');
        }

        return $response;
    }

    public function pushMany(string $uid, array $income, array $expenses): array
    {
        return $this->post('/api/push/many', [
            'apikey' => $this->apiKey(),
            'appid' => $this->appId(),
            'uid' => $uid,
            'data' => [
                'income' => $income,
                'expenses' => $expenses,
            ],
        ]);
    }

    public function realtimeSummary(string $token, string $uid): array
    {
        return $this->post('/api/realtime/summary', [
            'token' => $token,
            'uid' => $uid,
            'appid' => $this->appId(),
        ]);
    }

    public function realtimeInsights(string $token, string $uid): array
    {
        return $this->post('/api/realtime/insights', [
            'token' => $token,
            'uid' => $uid,
            'appid' => $this->appId(),
        ]);
    }

    public function realtimeExpenses(string $token, string $uid): array
    {
        return $this->get('/api/realtime/expenses', ['token' => $token, 'uid' => $uid, 'appid' => $this->appId()]);
    }

    public function realtimeIncome(string $token, string $uid): array
    {
        return $this->get('/api/realtime/income', ['token' => $token, 'uid' => $uid, 'appid' => $this->appId()]);
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function post(string $path, array $body): array
    {
        $response = wp_remote_post($this->url($path), [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
        ]);

        return $this->decode($response, $path);
    }

    /**
     * @param array<string,string> $query
     * @return array<string,mixed>
     */
    private function get(string $path, array $query): array
    {
        $response = wp_remote_get($this->url($path) . '?' . http_build_query($query), ['timeout' => 20]);

        return $this->decode($response, $path);
    }

    /**
     * @param array<string,mixed>|\WP_Error $response
     * @return array<string,mixed>
     */
    private function decode($response, string $path): array
    {
        if (is_wp_error($response)) {
            throw new StubApiException(sprintf(
                'Stub API request to %s failed: %s',
                $path,
                $response->get_error_message()
            ));
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status < 200 || $status >= 300 || ! is_array($body)) {
            throw new StubApiException(sprintf('Stub API request to %s returned HTTP %d.', $path, $status));
        }

        if (($body['error'] ?? false) === true) {
            throw new StubApiException(sprintf(
                'Stub API request to %s reported an error: %s',
                $path,
                is_string($body['message'] ?? null) ? $body['message'] : 'unknown'
            ));
        }

        return $body;
    }

    private function url(string $path): string
    {
        $host = 'live' === $this->environment() ? 'connect.stub.africa' : 'test.connect.stub.africa';

        return 'https://' . $host . $path;
    }

    private function apiKey(): string
    {
        $key = get_option('bizupkeep_stub_api_key');

        return is_string($key) ? $key : '';
    }

    private function appId(): string
    {
        $appId = get_option('bizupkeep_stub_app_id');

        return is_string($appId) ? $appId : '';
    }

    private function environment(): string
    {
        $env = get_option('bizupkeep_stub_environment', 'test');

        return is_string($env) ? $env : 'test';
    }
}
