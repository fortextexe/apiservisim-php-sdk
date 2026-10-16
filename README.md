# Apiservisim PHP SDK

Apiservisim SDK 0.2.4: Python, Node.js ve PHP için 36 API servisi ve 88 public endpoint şablonu.

## Ortak kapsam ve özellikler

Python, Node.js ve PHP paketleri aynı API kataloğunu ve **0.2.4** ortak sürümünü kullanır.

- Katalogdaki servis adıyla veya doğrudan endpoint şablonuyla çağrı yapar.
- Dinamik yol ve query parametrelerini doğru biçimde kodlar.
- GET ve JSON POST isteklerini destekler; JSON dışı yanıtları dilin yerel veri türünde döndürür.
- Base URL, zaman aşımı ve özel header ayarlarını destekler.
- Gereksinimler: Python 3.10+ (standart kütüphane), Node.js 18+ (yerleşik `fetch`), PHP 8.1+ (`ext-curl`).

## Kurulum

```bash
composer require fortextexe/apiservisim-sdk:0.2.4
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

$api = new ApiServisim();
$hava = $api->callApi('Hava Durumu API', [
    'pathParams' => ['sehir' => 'istanbul'],
]);

print_r($hava);
```

Aynı istemciyle farklı servisleri de çağırabilirsiniz:

```php
$kurlar = $api->get('doviz');
$kargo = $api->callApi('Kargo API', [
    'pathParams' => ['carrier' => 'aras', 'takip_no' => 'TAKIP-NO'],
]);
```

## JSON POST, ikili yanıt ve özel ayarlar

```php
$sonuc = $api->callApi('T.C. Kimlik No Kontrol API', [
    'method' => 'POST',
    'json' => ['tc' => '00000000000', 'ad' => 'Test', 'soyad' => 'Demo', 'dogum_yili' => 1990],
]);

$png = $api->get('qr', ['data' => 'Merhaba', 'size' => 600]);
// JSON yanıtları PHP array/scalar; JSON olmayan yanıtları byte içeren string olarak döner.

$ozel = new ApiServisim(
    baseUrl: 'https://apiservisim.vercel.app',
    timeout: 20.0,
    headers: ['X-Client' => 'ornek']
);
```

Gerçek kişisel verileri URL query’sine koymayın; hassas girdiler için POST JSON kullanın.

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

`request()` ile GET, POST, PUT, PATCH, DELETE veya katalogda henüz listelenmeyen bir endpoint çağrılabilir. `Endpoints::API_CATALOG` 36 servis adını; `Endpoints::API_ENDPOINTS` 88 public route şablonunu sunar. Ayrıntılı parametre ve yanıt bilgileri [Apiservisim API kataloğunda](https://apiservisim.vercel.app/#api-reference) yer alır.

## Test

```bash
composer test
```
