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

} // Compat class
