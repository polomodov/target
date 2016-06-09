# Target
My Target API

Установка с помощью composer:

```json
{
  "require": {
    "mobio/target": "*"
  }
}
```

Пример использования:

```php
<?php
require __DIR__. '/vendor/autoload.php';

$client_id = '...';
$client_secret = '...';

try {
    $mtApi = new \Mobio\Target\Api($client_id, $client_secret);
} catch (Exception $e) {
    echo $e->getMessage();
    die();
}

try {
    $clientsArray = $mtApi->request('/api/v1/clients.json')->toArray();
} catch (Exception $e) {
    echo $e->getMessage();
    die();
}

var_dump($clientsArray);
```

При первой инициализации происходит авторизация в myTarget и получение token (методом client_credentials), который сохраняется в ~/.mobio/myTarget.json. При следующих инициализациях token берется уже из файла, а при наступление следующего календарного дня, token обновляется (методом refresh_token) с сохранением в файл.
