<?php

phutil_register_library_map([
    '__library_version__' => 2,
    'class' => [
        'PhpCsFixerLintEngine' => 'lint/engine/PhpCsFixerLintEngine.php',
        'PhpCsFixerLinter' => 'lint/linter/PhpCsFixerLinter.php',
    ],
    'function' => [],
    'xmap' => [
        'PhpCsFixerLintEngine' => 'ArcanistLintEngine',
        'PhpCsFixerLinter' => 'ArcanistLinter',
    ],
]);
