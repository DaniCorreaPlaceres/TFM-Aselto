<?php

use cebe\openapi\Reader;

error_reporting(E_ALL & ~E_DEPRECATED);

require dirname(__DIR__).'/vendor/autoload.php';

$openapi = Reader::readFromYamlFile(dirname(__DIR__).'/openapi/openapi.yaml');

if ($openapi->validate()) {
    fwrite(STDOUT, "OpenAPI válido.\n");
    exit(0);
}

foreach ($openapi->getErrors() as $error) {
    fwrite(STDERR, $error.PHP_EOL);
}

exit(1);
