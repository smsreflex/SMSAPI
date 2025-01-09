<?php
//usage_example.php
require_once 'SmsApi.php';

$config = require 'config_sample.php';

$api = new SmsApi(
    $config['auth']['pass'],
    $config['auth']['ak'],
    $config['auth']['ck'],
    $config['environment']
);

try {
    $response = $api->sendSms([
        'To' => '33601020304',
        'MessageText' => 'Test message'
    ]);
    print_r($response);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
