# Apiservisim PHP SDK

Apiservisim'in **36 API kartına ve 88 public endpoint şablonuna** PHP ile erişmek için istemci. PHP 8.1+ ve `ext-curl` gerekir. Dış Composer/HTTP kütüphanesi bağımlılığı yoktur.

## Kurulum

Packagist üzerinden kurulum:

```bash
composer require fortextexe/apiservisim-sdk
```

Uygulamanızda Composer autoloader’ını ekleyin:

```php
require __DIR__ . '/vendor/autoload.php';
```

## Hızlı başlangıç

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Fortext\ApiServisim\ApiServisim;

$api = new ApiServisim(timeout: 10.0);
$kurlar = $api->get('doviz');

$hava = $api->callApi('Hava Durumu API', [
    'pathParams' => ['sehir' => 'istanbul'],
    'params' => ['days' => 3],
]);
```

Servis adı yerine doğrudan endpoint veya şablon da verilebilir:

```php
$kargo = $api->callApi('Kargo API', [
    'pathParams' => ['carrier' => 'aras', 'takip_no' => 'TAKIP-NO'],
]);
```

Tek segmentli route parametreleri URL-encode edilir; `{path...}` catch-all şablonunda `/` ayırıcıları korunur. Query dizileri aynı anahtarı tekrar ederek gönderilir.

## JSON POST, ikili yanıt ve özel ayarlar

```php
$sonuc = $api->tckimlik([
    'tc' => '00000000000',
    'ad' => 'Test',
    'soyad' => 'Demo',
    'dogum_yili' => 1990,
]);

$png = $api->get('qr', ['data' => 'Merhaba', 'size' => 600]);
// JSON yanıtları PHP array/scalar; JSON olmayan yanıtları byte içeren string olarak döner.

$ozel = new ApiServisim(
    baseUrl: 'https://apiservisim.vercel.app',
    timeout: 20.0,
    headers: ['X-Client' => 'ornek']
);
```

Gerçek kişisel verileri URL query'sine koymayın; hassas girdiler için POST JSON kullanın.

## Hata yönetimi

```php
use Fortext\ApiServisim\Exception\ApiServisimHTTPError;
use Fortext\ApiServisim\Exception\ApiServisimConnectionError;

try {
    $hava = $api->get('hava/istanbul');
} catch (ApiServisimHTTPError $e) {
    echo $e->statusCode . ' ' . $e->body;
} catch (ApiServisimConnectionError $e) {
    echo 'Bağlantı/zaman aşımı: ' . $e->getMessage();
}
```

`request()` ile GET, POST, PUT, PATCH, DELETE veya katalogda henüz listelenmeyen bir endpoint çağrılabilir. `Endpoints::API_CATALOG` 36 servis adını; `Endpoints::API_ENDPOINTS` 88 public route şablonunu sunar. İki katalog da Python SDK manifestinden `python3 scripts/sync-sdk-manifests.py` komutuyla yenilenir. Ayrıntılı parametre, yanıt ve hata açıklamaları [Python SDK referansında](../python-sdk/README.md) yer alır.

## Test

```bash
composer test
```
