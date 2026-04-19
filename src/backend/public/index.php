<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Polyfill: request_parse_body() is a PHP 8.4 built-in used by Symfony 8.x.
// On PHP 8.3 it does not exist, so we provide a compatible shim here, before
// the autoloader runs (Symfony calls it in Request::createFromGlobals which
// fires on every PUT / DELETE / PATCH request).
if (!function_exists('request_parse_body')) {
    function request_parse_body(): array
    {
        $contentType = strtolower(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]);
        $contentType = trim($contentType);

        if ($contentType === 'application/x-www-form-urlencoded') {
            parse_str(file_get_contents('php://input') ?: '', $post);
            return [$post, []];
        }

        if (str_starts_with($contentType, 'multipart/form-data')) {
            return [$_POST, $_FILES];
        }

        // JSON, text, or any other content type — no extra body parsing needed.
        return [[], []];
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
