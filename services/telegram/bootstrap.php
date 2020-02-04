<?php
//Check if autoload has been already loaded (in case plugin installed in existing project)
$root = __DIR__;
if (!file_exists($root . '/vendor/autoload.php')) {
    $root = __DIR__ . '/../../../';
}
require __DIR__ . '/../../vendor/autoload.php';
chdir($root);

//Check if root env file hash been loaded (in case plugin installed in existing project)
if (!getenv('SERVER_ADDRESS')) {
    Dotenv\Dotenv::createImmutable(__DIR__)->load();
}
