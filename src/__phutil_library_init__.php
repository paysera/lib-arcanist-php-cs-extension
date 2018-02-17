<?php

$vendorDir = null;
if (file_exists(__DIR__ . '/../vendor/')) {
    $vendorDir = __DIR__ . '/../vendor/';
} elseif (file_exists(__DIR__ . '/../../../../vendor/')) {
    $vendorDir = __DIR__ . '/../../../../vendor/';
}

spl_autoload_register(function($className) use ($vendorDir) {
    if ($vendorDir === null) {
        return;
    }
    if (strpos($className, 'ptlis\\DiffParser\\') !== false) {
        $subPath = strtr(substr($className, strlen('ptlis\DiffParser\\')), ['\\' => '/']);
        require_once $vendorDir . 'ptlis/diff-parser/src/' . $subPath . '.php';
    }
});

phutil_register_library('php-cs-fixer-lint-engine', __FILE__);
