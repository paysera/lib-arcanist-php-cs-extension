<?php

phutil_register_library_map([
    '__library_version__' => 2,
    'class' => [
        'PhpCsFixerLintEngine' => 'Lint/Engine/PhpCsFixerLintEngine.php',
        'PhpCsFixerLinter' => 'Lint/Linter/PhpCsFixerLinter.php',
        'LinterConfiguration' => 'Lint/Linter/LinterConfiguration.php',
    ],
    'function' => [],
    'xmap' => [
        'PhpCsFixerLintEngine' => 'ArcanistLintEngine',
        'PhpCsFixerLinter' => 'ArcanistLinter',
    ],
]);
