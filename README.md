# lib-arcanist-php-cs-extension

This library integrates [PHP CS Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer) as lint engine to `arcanist`.
It allows developer to automatically run `php-cs-fixer` on `arc diff`.

### Before installing library

To automatically configure your `.arcconfig` add `"Paysera\\Composer\\ArcConfigParser::parseArcConfig"` script to `post-install-cmd` and `post-update-cmd`
 or other `scipts` - just make sure this script is executed on `composer install`.

### Installation

* `composer require --dev paysera/lib-arcanist-php-cs-extension`.
* Make sure `.php_cs` file is in project directory.
* Make sure `.arcconfig` file contains following configurable default entries:
  * `"lint.engine": "PhpCsFixerLintEngine"`
  * `"load": ["vendor/paysera/lib-arcanist-php-cs-extension/src/"]` 
  * `"lint.php_cs_fixer.fix_paths" : ["src/"]` - list of directories to run `php-cs-fixer` on.
  * `"lint.php_cs_fixer.php_cs_binary" : "{your-bin-dir}/php-cs-fixer"` - location for `php-cs-fixer` executable.
  * `"lint.php_cs_fixer.php_cs_file": ".php_cs"` - location for `.php_cs` file.

### Example output

In case `php-cs-fixer` found no problems:
```
$ arc lint
 OKAY  No lint warnings.
```

If `php-cs-fixer` reports errors, arcanist `diff` will be displayed:
```
$ arc lint

>>> Lint for src/Acme/Bundle/AcmeBundle/Controller/DefaultController.php:


   Warning  (PHP_CS_FIXER) pre_increment, phpdoc_separation, phpdoc_align
    Please consider applying these changes:
    ```
    - * @param array $fixData
    + * @param array  $fixData
    + *
    ```

               4 {
               5     /**
               6      * @param string $path
    >>>        7      * @param array $fixData
               8      * @return \ArcanistLintMessage[]
               9      */
              10     public function buildLintMessages($path, array $fixData)

   Warning  (PHP_CS_FIXER) pre_increment, phpdoc_separation, phpdoc_align
    Please consider applying these changes:
    ```
    - for ($i = 0; $i < count($rows); $i++) {
    + for ($i = 0; $i < count($rows); ++$i) {
    ```

              13         $rows = array_map('trim', file($path));
              14 
              15         $messages = [];
    >>>       16         for ($i = 0; $i < count($rows); $i++) {
              17             foreach ($diffParts as $diffPart) {
              18                 if (isset($diffPart['informational'])) {
              19                     $matchedInformational = 0;

```
If `Excuse` message will be provided, these messages will be sent to `Phabricator`.
