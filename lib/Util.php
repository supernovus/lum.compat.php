<?php

namespace Lum;

/**
 * A few static methods that might be useful here and there.
 */
class Util
{
  const LOAD_MISSING_FATAL = 1;
  const LOAD_MISSING_LOG   = 2;
  const LOAD_INVALID_FATAL = 4;
  const LOAD_INVALID_LOG   = 8;
  const LOAD_EMPTY_FATAL   = 16;
  const LOAD_EMPTY_LOG     = 32;
  const LOAD_NOT_IT_FATAL  = 64;
  const LOAD_NOT_IT_LOG    = 128;

  const LOAD_OPTS_DEFAULTS
    = self::LOAD_INVALID_FATAL
    | self::LOAD_EMPTY_FATAL
    | self::LOAD_NOT_IT_FATAL;

  const LOAD_ALL_FATAL = self::LOAD_OPTS_DEFAULTS | self::LOAD_MISSING_FATAL;

  const LOAD_ALL_LOG
    = self::LOAD_MISSING_LOG
    | self::LOAD_INVALID_LOG
    | self::LOAD_EMPTY_LOG
    | self::LOAD_NOT_IT_LOG;

  /**
   * Manipulate a set of binary flags.
   *
   * @param int &$flags  The flags we are modifying (passed by reference.)
   * @param int $flag    The flag we are adding or removing to $flags.
   * @param bool $value  (true) If true, add the flag, if false remove it.
   * 
   * @return void
   */
  static function set_flag (int &$flags, int $flag, bool $value=true): void
  {
    if ($value)
      $flags = $flags | $flag;
    else
      $flags = $flags - ($flags & $flag);
  }

  /**
   * Handle errors differently depending on flags.
   *
   * @param string $message  The error message.
   * @param int $flags  Currently set flags.
   * @param int $fflag  The flag indicating errors should be fatal.
   * @param int $lflag  The flag indicating errors should be logged.
   *
   * @return null  This always returns null if fatal wasn't true.
   *
   * @throws Exception  This always throws if fatal was true.
   */
  static function flagErr (string $message, int $flags, 
    int $fflag, int $lflag)
  {
    $fatal = boolval($flags & $fflag);
    $log   = boolval($flags & $lflag);

    if ($fatal)
    {
      throw new Exception($message);
    }

    if ($log)
    {
      error_log($message);
    }

    return null;
  }

  /**
   * Load a JSON or YAML file and return the contents.
   * Uses a few forms of format auto-detection.
   *
   * @param string $file  The filename we want to load.
   * @param int $flags    (Optional) Flags controlling how errors as handled.
   *                      Default: `self::LOAD_ALL_FATAL`
   *
   *   I need to document this properly.
   *
   * @return ?iterable  Either a valid array/object or null.
   */
  static function load_data_from (string $file, 
    int $flags=self::LOAD_ALL_FATAL): ?iterable
  {
    $err = function(string $m, int $f, int$l) use ($flags)
    {
      return static::flagErr($m, $flags, $f, $l);
    };

    if (file_exists($file) && is_readable($file))
    {
      $text = trim(file_get_contents($file));

      if (empty($text))
      { // Why would you have an empty file? Who knows.
        $fatal = self::LOAD_EMPTY_FATAL;
        $log = self::LOAD_EMPTY_LOG; 
        return $err("Cannot load empty file '$file'", $fatal, $log);
      }

      $fatal = self::LOAD_INVALID_FATAL;
      $log = self::LOAD_INVALID_LOG;

      $pi = pathinfo($file);
      if (isset($pi['extension']))
      { // Lets see if we recognize the extension.
        switch (strtolower($pi['extension']))
        {
          case 'jsn':
          case 'json':
            $F = 'j';
            break;
          case 'yml':
          case 'yaml':
            $F = 'y';
            break;
          default:
            $F = null;
        }
      }

      if (!isset($F))
      { // Attempt to use really cheap and not very reliable format detection.
        $fc = substr($text, 0, 1);
        if ($fc == '[' || $fc == '{')
        { // Assuming JSON
          $F = 'j';
        }
        elseif ($fc == '%' || $fc == '-' || $fc == '#')
        { // Assuming YAML
          $F = 'y';
        }
        else
        { // If we've made it this far and haven't found anything...
          return $err("Unknown file format for '$file'", $fatal, $log);
        }
      }

      if ($F == 'j')
      {
        $conf = json_decode($text, true);
        if (!isset($conf))
        {
          return $err("Invalid JSON in '$file'", $fatal, $log);
        }
      }
      elseif ($F == 'y')
      {
        $yaml = new YAML();
        $conf = $yaml->parse($text);
        if (!isset($conf))
        {
          return $err("The YAML parser returned null", $fatal, $log);
        }
      }
      else
      { // This one will always throw, because WTF?!
        throw new Exception("How did you get here?");
      }

      if (is_iterable($conf))
      { // We got what we need.
        return $conf;
      }
      else
      {
        $fatal = self::LOAD_NOT_IT_FATAL;
        $log = self::LOAD_NOT_IT_LOG;
        return $err("Data from '$file' was not iterable", $fatal, $log);
      }
    }
    else
    { // No file, no content.
      $fatal = self::LOAD_MISSING_FATAL;
      $log = self::LOAD_MISSING_LOG;
      return $err("File '$file' not found", $fatal, $log);
    }
  }

  /**
   * Populate some options in an array or array-like object based
   * on the contents of a JSON or YAML configuration file.
   *
   * @param string $file       The filename we want to load.
   * @param mixed  &$opts      The array we are reading the options into.
   *   May be a PHP `array`, or an object implementing `ArrayAccess`.
   * @param bool   $overwrite  (Optional) Should we overwrite existing options?
   *                           Default: `false`
   * @param int    $flags      (Optional) Flags to send to `load_data_from`
   *                           Default: `self::LOAD_OPTS_DEFAULTS`
   *
   * @return void
   */
  static function load_opts_from (
    string $file, 
    mixed &$opts, 
    bool $overwrite=false,
    int $flags=self::LOAD_OPTS_DEFAULTS): void
  {
    $conf = static::load_data_from($file, $flags);
    if (isset($conf))
    {
      foreach ($conf as $ckey => $cval)
      {
        if ($overwrite || !isset($opts[$ckey]))
        {
          $opts[$ckey] = $cval;
        }
      }
    }
  }

  /** 
   * Get the output content from a PHP file.
   * This is used as the backend function for all View related methods.
   *
   * @param string $__view_file  The PHP file to get the content from.
   * @param mixed  $__view_data  Associative array of variables.
   *
   * @return string  The output from the PHP file.
   */
  static function get_php_content ($__view_file, $__view_data=NULL): string
  { 
    // First, start saving the buffer.
    ob_start();
    if (isset($__view_data))
    { // First let's see if we have set a local name for the full data.
      if (isset($__view_data['__data_alias']))
      {
        $__data_alias = $__view_data['__data_alias'];
        $$__data_alias = $__view_data;
      }
      if ($__view_data instanceof \ArrayObject)
      {
        extract($__view_data->getArrayCopy());
      }
      elseif (is_array($__view_data))
      {
        extract($__view_data);
      }
    }
    // Now, let's load that template file.
    include $__view_file;
    // Okay, now let's get the contents of the buffer.
    $buffer = ob_get_contents();
    // Clean out the buffer.
    @ob_end_clean();
    // And return out processed view.
    return $buffer;
  }

}