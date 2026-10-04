<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$router = $app['router'];
$request = Illuminate\Http\Request::create('/dashboard', 'GET');
$route = $router->getRoutes()->match($request);
echo $route->uri(), PHP_EOL;
echo $route->getActionName(), PHP_EOL;
