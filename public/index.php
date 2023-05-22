<?php

require __DIR__.'/../vendor/autoload.php';
echo 'dsffsdfsd';
$client = new \MemcachedClient\MemcachedClient('localhost', 11211);
$client->set('key777','val777');
var_dump($client->get('key777'));

