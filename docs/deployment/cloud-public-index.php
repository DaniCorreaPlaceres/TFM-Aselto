<?php

// Example for public_html; the application stays in ../private/aselto.
use App\Kernel;

require_once dirname(__DIR__).'/private/aselto/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
