# lib-arcanist-php-cs-extension
Library for php-cs-fixer extension

## Installation

* `composer require paysera/lib-arcanist-php-cs-extension`.
* Add `"Paysera\\Arcanist\\ArcConfigParser::parseArcConfig"` script to `post-install-cmd` and `post-update-cmd`
 or other `scipts` - just make sure this script is executed on `composer install`.
* Make sure `.php_cs` file is in project directory.
* Make sure `.arcconfig` file contains: `"lint.engine": "PhpCsFixerLintEngine"`, `"load": [ "src/"]` and `"lint.fixer_paths" : ["src/"]`
* Add `"lint.php_cs_file": ".php_cs"` to `.arcconfig` for custom configuration.

## Usage

Lint will run after `arc diff` command.

In order to skip add `--nolint` flag.

Fixing paths are specified in `.arcconfig` file on `lint.fixer_paths`.
