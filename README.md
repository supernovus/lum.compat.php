# lum.compat.php

## Summary

A meta-package to be used by my other Lum libraries to determine their
PHP compatibility level, and if any polyfills are required.

* 1.x
    - Requires PHP 7.4 as minimum version.
    - Requires the json extension (which is core in PHP 8.)
    - Will provide polyfills for some PHP 8 features in PHP 7 runtimes.
* 2.x
    - Requires PHP 8.1 as minimum version.
    - Requires the mbstring extension.
    - May provide polyfills for some PHP 9 features in the future.

## Official URLs

This library can be found in two places:

 * [Github](https://github.com/supernovus/lum.compat.php)
 * [Packageist](https://packagist.org/packages/lum/lum-compat)

## Author

Timothy Totten

## License

[MIT](https://spdx.org/licenses/MIT.html)
