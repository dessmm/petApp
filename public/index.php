<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// Trust the local loopback proxy (useful when tunnelling with ngrok)
// You can override via the TRUSTED_PROXIES env var if needed.
$trusted = $_SERVER['TRUSTED_PROXIES'] ?? null;
    if ($trusted) {
        $proxies = array_map('trim', explode(',', $trusted));
        Request::setTrustedProxies($proxies, Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PREFIX);
    } else {
        // trust local proxies by default (ngrok tunnels usually connect via loopback)
        Request::setTrustedProxies(['127.0.0.1', '::1'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PREFIX);
    }

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
