# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## 1.3.3
### Fixed
- Add a newline at the end of `.arcconfig` and `.arclint` files.

## 1.3.2

### Fixed
- properly handle dependencies in `setLinterConfigurationValue`

## 1.2.0

### Added
- Support of `unified diff` format since `php-cs-fixer:^2.8.0`. 
  - Arcanist now will ask you to apply provided fixes. 
    Please always double-check this as it is not perfect!
