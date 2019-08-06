<?php

require_once __DIR__.'/../vendor/autoload.php';

use Telemovilperu\Gurtamlib\Gurtam;

$api = new Gurtam('1494fae55b61eee4d8209f07fc6e71cfF7CE179A5CF15C3D0EC910A53FE174805D1452A2');

echo json_encode($api->loginApi());