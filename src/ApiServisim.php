<?php
declare(strict_types=1);

namespace Fortext\ApiServisim;

use Fortext\ApiServisim\Exception\ApiServisimConnectionError;
use Fortext\ApiServisim\Exception\ApiServisimHTTPError;
use Fortext\ApiServisim\Exception\ApiServisimError;
use JsonException;

final class ApiServisim
{
    public const DEFAULT_BASE_URL = 'https://apiservisim.vercel.app';

    private string $baseUrl;
    private float $timeout;
    private array $headers;
    /** @var callable|null */
    private $transport;

    /**
     * @param array<string, string> $headers
     * @param callable|null $transport Optional test/custom transport: fn($url, $method, $headers, $body, $timeout): array
     */
    public function __construct(
        string $baseUrl = self::DEFAULT_BASE_URL,
        float $timeout = 15.0,
        array $headers = [],
        ?callable $transport = null
    ) {
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            throw new \InvalidArgumentException('baseUrl geçerli bir http:// veya https:// adresi olmalı');
        }
        if (!is_finite($timeout) || $timeout <= 0) {
            throw new \InvalidArgumentException('timeout sıfırdan büyük olmalı');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->headers = array_merge([
            'Accept' => 'application/json, */*;q=0.8',
            'User-Agent' => 'apiservisim-php/0.1.0',
        ], $headers);
        $this->transport = $transport;
    }

    public function makeUrl(string $path, array $params = []): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new \InvalidArgumentException('endpoint yolu boş olamaz');
        }
        if (preg_match('/^[a-z][a-z\d+.-]*:\/\//i', $path) || str_starts_with($path, '//')) {
            throw new \InvalidArgumentException('Tam URL değil, Apiservisim endpoint yolu gönderin');
        }

        $queryAt = strpos($path, '?');
        $route = $queryAt === false ? $path : substr($path, 0, $queryAt);
        $existingQuery = $queryAt === false ? '' : substr($path, $queryAt + 1);
        if ($route === '' || !str_starts_with($route, '/')) {
            $route = '/' . $route;
        }
        if ($route !== '/api' && !str_starts_with($route, '/api/') && !str_starts_with($route, '/Service/')) {
            $route = '/api' . $route;
        }

        $query = [];
        if ($existingQuery !== '') {
            $query[] = $existingQuery;
        }
        foreach ($params as $key => $value) {
            $values = is_array($value) ? $value : [$value];
            foreach ($values as $item) {
                $query[] = rawurlencode((string) $key) . '=' . rawurlencode($this->stringValue($item));
            }
        }

        return $this->baseUrl . $route . ($query ? '?' . implode('&', $query) : '');
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, string> $headers
     */
    public function request(
        string $method,
        string $path,
        array $params = [],
        mixed $json = null,
        ?string $body = null,
        array $headers = []
    ): mixed {
        if ($json !== null && $body !== null) {
            throw new \InvalidArgumentException('json ile body aynı istekte birlikte kullanılamaz');
        }
        $method = strtoupper(trim($method));
        if ($method === '' || preg_match('/\s/', $method)) {
            throw new \InvalidArgumentException('HTTP metodu geçersiz');
        }

        $requestHeaders = array_merge($this->headers, $headers);
        if ($json !== null) {
            try {
                $body = json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } catch (JsonException $error) {
                throw new \InvalidArgumentException('JSON gövdesi kodlanamadı: ' . $error->getMessage(), 0, $error);
            }
            if (!$this->hasHeader($requestHeaders, 'Content-Type')) {
                $requestHeaders['Content-Type'] = 'application/json; charset=utf-8';
            }
        }

        $url = $this->makeUrl($path, $params);
        if ($this->transport !== null) {
            $response = ($this->transport)($url, $method, $requestHeaders, $body, $this->timeout);
        } else {
            $response = $this->curlTransport($url, $method, $requestHeaders, $body);
        }

        $status = (int) ($response['status'] ?? 0);
        $responseBody = (string) ($response['body'] ?? '');
        $reason = (string) ($response['reason'] ?? '');
        if ($status < 200 || $status >= 300) {
            throw new ApiServisimHTTPError($status, $reason, $responseBody);
        }
        if ($responseBody === '') {
            return null;
        }

        $contentType = $this->findHeader((array) ($response['headers'] ?? []), 'content-type');
        if (stripos($contentType, 'json') !== false) {
            try {
                return json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $responseBody;
            }
        }
        return $responseBody;
    }

    public function get(string $path, array $params = [], array $headers = []): mixed
    {
        return $this->request('GET', $path, $params, null, null, $headers);
    }

    public function post(string $path, mixed $json, array $params = [], array $headers = []): mixed
    {
        return $this->request('POST', $path, $params, $json, null, $headers);
    }

    /**
     * Call a catalog name or route template.
     * Options: method, pathParams, params, json, body, headers.
     */
    public function callApi(string $endpoint, array $options = []): mixed
    {
        if (trim($endpoint) === '') {
            throw new \InvalidArgumentException('API adı veya endpoint yolu boş olamaz');
        }
        $route = Endpoints::API_CATALOG[$endpoint] ?? trim($endpoint);
        $pathParams = $options['pathParams'] ?? [];
        preg_match_all('/\{([A-Za-z_][A-Za-z\d_]*)(\.\.\.)?\}/', $route, $matches, PREG_SET_ORDER);
        $expected = array_values(array_unique(array_column($matches, 1)));
        $missing = array_values(array_diff($expected, array_keys($pathParams)));
        $extra = array_values(array_diff(array_keys($pathParams), $expected));
        if ($missing) {
            sort($missing);
            throw new \InvalidArgumentException('Eksik yol parametreleri: ' . implode(', ', $missing));
        }
        if ($extra) {
            sort($extra);
            throw new \InvalidArgumentException('Kullanılmayan yol parametreleri: ' . implode(', ', $extra));
        }

        $route = preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z\d_]*)(\.\.\.)?\}/',
            function (array $match) use ($pathParams): string {
                $name = $match[1];
                $value = $pathParams[$name];
                if ($value === null) {
                    throw new \InvalidArgumentException($name . ' yol parametresi boş olamaz');
                }
                if (($match[2] ?? '') === '...') {
                    $segments = is_array($value) ? $value : explode('/', trim((string) $value, '/'));
                    if (!$segments || in_array('', array_map('strval', $segments), true)) {
                        throw new \InvalidArgumentException($name . ' en az bir yol parçası içermeli');
                    }
                    return implode('/', array_map(static fn ($part): string => rawurlencode((string) $part), $segments));
                }
                if (is_array($value)) {
                    throw new \InvalidArgumentException($name . ' tek bir yol parçası olmalı');
                }
                return rawurlencode((string) $value);
            },
            $route
        );
        if (!is_string($route) || str_contains($route, '{') || str_contains($route, '}')) {
            throw new \InvalidArgumentException('Endpoint şablonundaki yol parametreleri çözümlenemedi');
        }

        return $this->request(
            (string) ($options['method'] ?? 'GET'),
            $route,
            (array) ($options['params'] ?? []),
            $options['json'] ?? null,
            isset($options['body']) ? (string) $options['body'] : null,
            (array) ($options['headers'] ?? [])
        );
    }

    public function tckimlik(array $payload): mixed
    {
        return $this->post('tckimlik', $payload);
    }

    private function curlTransport(string $url, string $method, array $headers, ?string $body): array
    {
        if (!function_exists('curl_init')) {
            throw new ApiServisimConnectionError('PHP cURL extension (ext-curl) yüklü olmalı');
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new ApiServisimConnectionError('cURL isteği başlatılamadı');
        }
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_CONNECTTIMEOUT_MS => (int) min($this->timeout * 1000, 10_000),
            CURLOPT_TIMEOUT_MS => (int) ($this->timeout * 1000),
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }
        $result = curl_exec($handle);
        if ($result === false) {
            $message = curl_error($handle);
            curl_close($handle);
            throw new ApiServisimConnectionError($message ?: 'API bağlantısı başarısız oldu');
        }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        curl_close($handle);
        return [
            'status' => $status,
            'reason' => (string) $status,
            'headers' => ['content-type' => $contentType],
            'body' => (string) $result,
        ];
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) return '';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_scalar($value)) return (string) $value;
        throw new \InvalidArgumentException('Query parametreleri scalar veya scalar dizi olmalı');
    }

    private function hasHeader(array $headers, string $name): bool
    {
        return $this->findHeader($headers, $name) !== '';
    }

    private function findHeader(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) return (string) $value;
        }
        return '';
    }
}
