# lum.compat.php

## Summary

A meta-package to be used by my other Lum libraries to determine their
PHP compatibility level, and if any polyfills are required.

All libraries that update to the next `lum-compat` version _must_ bump their
major version at the same time. Even if there are no changes to the library
at all, changing the target `lum-compat` version is breaking compatibility.

## Versions

Each version is stored in its own branch, and there will be tags made
for each branch as updates are made. 

* `v2.x`
    - Requires PHP 8.1 as minimum version.
    - Requires the `mbstring` extension.
    - May provide polyfills for *some* PHP 9 features in the future.

* `v1.x`
    - Requires PHP 7.4 as minimum version.
    - Requires the `json` extension (which is core in PHP 8.)
    - Will provide polyfills for *some* PHP 8 features in PHP 7 runtimes.

## Usage

This meta-package isn't really useful outside the other Lum PHP libraries.

The production `composer.json` will use a _caret version range_ operator 
in the `requires` property, e.g. `^2.0` to specify the `v2.x` branch.

Internal `composer-dev*` files will use a _dev branch_ specifier instead,
so for example, `2.x-dev` would point to the `v2.x` branch. 

## Official URLs

This package can be found in two places:

 * [Github](https://github.com/supernovus/lum.compat.php)
 * [Packageist](https://packagist.org/packages/lum/lum-compat)

## Author

Timothy Totten

## License

[MIT](https://spdx.org/licenses/MIT.html)
