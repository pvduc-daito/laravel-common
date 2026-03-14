<?php

require __DIR__ . '/vendor/autoload.php';

$container = new Illuminate\Container\Container();
Illuminate\Container\Container::setInstance($container);

$container->instance('config', new Illuminate\Config\Repository(array(
	'barcode' => array(
		'store_path' => sys_get_temp_dir(),
	),
)));

$base64 = Daito\Lib\DaitoBarcode::generateBarcodeQrCode('HELLO-QR');

echo substr($base64, 0, 80) . PHP_EOL;
echo 'length: ' . strlen($base64) . PHP_EOL;

echo Daito\Lib\DaitoMath::div(1, 2) . PHP_EOL;