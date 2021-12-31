<?php


namespace Lum;

/**
 * A very small final class for compatibility helpers.
 */
final class Compat
{
  /**
   * The major Lum PHP API version.
   */
  const API_VERSION = 2;

  /**
   * Show a deprecation message using an E_USER_DEPRECATED warning.
   *
   * @param string $msg  The deprecation message to display.
   *
   */
  static function deprecate (string $msg): bool
  {
    return trigger_error($msg, E_USER_DEPRECATED);
  }

  /**
   * Check if 'uopz' is loaded.
   *
   * @param ?bool $allow_exit  (Optional) If set, call `uopz_allow_exit()`
   *
   * @return bool  Was uopz loaded?
   */
  static function uopz(?bool $allow_exit=null): bool
  {
    if (function_exists('uopz_allow_exit'))
    { // If the function is found, assume the extension is loaded.
      if (isset($allow_exit))
      {
        uopz_allow_exit($allow_exit);
      }
      return true;
    }
    else
    { // No function, no extension.
      return false;
    }
  }

} // Compat class
