<?php
declare(strict_types=1);

require __DIR__ . '/../src/Exception/ApiServisimError.php';
require __DIR__ . '/../src/Exception/ApiServisimHTTPError.php';
require __DIR__ . '/../src/Exception/ApiServisimConnectionError.php';
require __DIR__ . '/../src/Endpoints.php';
require __DIR__ . '/../src/ApiServisim.php';

use Fortext\ApiServisim\ApiServisim;
use Fortext\ApiServisim\Endpoints;
use Fortext\ApiServisim\Exception\ApiServisimHTTPError;

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    echo "PASS: {$message}\n";
}

check(count(Endpoints::API_CATALOG) === 36, 'all 36 catalog services are present');
check(count(Endpoints::API_ENDPOINTS) === 88, 'all 88 public route templates are present');
foreach (Endpoints::API_CATALOG as $route) {
    check(in_array($route, Endpoints::API_ENDPOINTS, true), 'catalog route has a public route entry: ' . $route);
}

$last = [];
$transport = static function ($url, $method, $headers, $body, $timeout) use (&$last): array {
    $last = compact('url', 'method', 'headers', 'body', 'timeout');
    return ['status' => 200, 'reason' => 'OK', 'headers' => ['content-type' => 'application/json'], 'body' => '{"ok":true}'];
};
$api = new ApiServisim(timeout: 3.0, transport: $transport);
check($api->get('haberler', ['q' => 'İstanbul', 'kategori' => ['spor', 'ekonomi']]) === ['ok' => true], 'GET decodes JSON');
check(str_contains($last['url'], 'q=%C4%B0stanbul') && str_contains($last['url'], 'kategori=spor&kategori=ekonomi'), 'query values are encoded and arrays are repeated');
check($last['method'] === 'GET', 'GET method is preserved');

$api->callApi('Hava Durumu API', ['pathParams' => ['sehir' => 'İstanbul'], 'params' => ['days' => 3]]);
check(str_contains($last['url'], '/api/hava/%C4%B0stanbul?days=3'), 'named catalog route encodes dynamic path segment');
$api->callApi('/api/saat/{sehir...}', ['pathParams' => ['sehir' => 'Europe/Paris']]);
check(str_contains($last['url'], '/api/saat/Europe/Paris'), 'catch-all route preserves slash separators');
$api->callApi('T.C. Kimlik No Kontrol API', ['method' => 'POST', 'json' => ['ad' => 'Çağrı']]);
check($last['method'] === 'POST' && str_contains($last['headers']['Content-Type'], 'application/json'), 'catalog POST serializes JSON');
check(json_decode($last['body'], true) === ['ad' => 'Çağrı'], 'JSON body preserves UTF-8');

try {
    $api->callApi('Hava Durumu API');
    throw new RuntimeException('FAIL: missing path parameter was accepted');
} catch (InvalidArgumentException $error) {
    check(str_contains($error->getMessage(), 'sehir'), 'missing path parameters are rejected');
}

$httpErrorApi = new ApiServisim(transport: static fn () => [
    'status' => 400,
    'reason' => 'Bad Request',
    'headers' => ['content-type' => 'application/json'],
    'body' => '{"hata":"gecersiz"}',
]);
try {
    $httpErrorApi->get('tckimlik');
    throw new RuntimeException('FAIL: HTTP error was not raised');
} catch (ApiServisimHTTPError $error) {
    check($error->statusCode === 400 && str_contains($error->body, 'gecersiz'), 'HTTP exception contains status and body');
}

$binaryApi = new ApiServisim(transport: static fn () => [
    'status' => 200,
    'reason' => 'OK',
    'headers' => ['content-type' => 'image/png'],
    'body' => "\x89PNG\r\n",
]);
check($binaryApi->get('qr') === "\x89PNG\r\n", 'non-JSON response is returned as bytes in a string');

echo "All PHP SDK tests passed.\n";
