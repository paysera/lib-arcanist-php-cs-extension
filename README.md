# lib-arcanist-php-cs-extension
Library for php-cs-fixer arcanist extension

## Installation

* Add `"Paysera\\Arcanist\\ArcConfigParser::parseArcConfig"` script to `post-install-cmd` and `post-update-cmd`
 or other `scipts` - just make sure this script is executed on `composer install`.
* `composer require paysera/lib-arcanist-php-cs-extension`.
* Make sure `.php_cs` file is in project directory.
* Make sure `.arcconfig` file contains following configurable entries:
  * `"lint.engine": "PhpCsFixerLintEngine"`
  * `"load": ["vendor/paysera/lib-arcanist-php-cs-extension/src/"]` 
  * `"lint.php_cs_fixer.fix_paths" : ["src/"]`
  * `"lint.php_cs_fixer.php_cs_binary" : "{your-bin-dir}/php-cs-fixer"`
  * `"lint.php_cs_fixer.php_cs_file": ".php_cs"`

## Usage

Lint will run after `arc diff` command.

In order to skip add `--nolint` flag.

Fixing paths are specified in `.arcconfig` file on `lint.fixer_paths`.
