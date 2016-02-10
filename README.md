# Target
My Target API

Установка с помощью composer:

{
  "require": {
    "MobioInc/myTarget": "dev-master"
  },
  "repositories":[
    {
      "type":"git",
      "url":"https://github.com/MobioInc/myTarget"
    }
  ]
}

Пример использования:

<?php
include __DIR__. "/vendor/autoload.php";

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
