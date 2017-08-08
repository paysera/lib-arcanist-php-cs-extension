# lib-arcanist-php-cs-extension
Library for php-cs-fixer arcanist lint extension

## Usage

This library integrates `php-cs-fixer` as lint engine to `arcanist`.
It allows developer to automatically run `php-cs-fixer` on `arc diff`.

### Before installing library

To automatically configure your `.arcconfig` add `"Paysera\\Arcanist\\ArcConfigParser::parseArcConfig"` script to `post-install-cmd` and `post-update-cmd`
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

If `php-cs-fixer` reports errors, standard `diff` will be displayed:
```
$ arc lint

>>> Lint for src/Acme/Bundle/AcmeBundle/Controller/DefaultController.php:


   Warning  () src/Acme/Bundle/AcmeBundle/Controller/DefaultController.php
    Applied fixers: 
    array_syntax
    
    --- Original
    +++ New
    @@ @@
     
             return $this->render(
    -            'AcmeAcmeBundle:Controller:default.html.twig', array(
    -                'is_owner' => $owner,
    -                'event' => $event,
    -            )
             );
             return $this->render(
    +            'AcmeAcmeBundle:Controller:default.html.twig',
    +            [
    +                'is_owner' => $owner,
    +                'event' => $event,
    +            ]
             );
         }
     }
```
