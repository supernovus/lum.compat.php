<?php

namespace Lum;

/**
 * A few static methods that might be useful here and there.
 */
class Util
{
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
   * Populate some options in an array or array-like object based
   * on the contents of a JSON or YAML configuration file.
   *
   * @param string $file       The filename we want to load.
   * @param mixed  &$opts      The array we are reading the options into.
   *   May be a PHP `array`, or an object implementing `ArrayAccess`.
   * @param bool   $overwrite  Should we overwrite existing options?
   *
   * @return void
   */
  static function load_opts_from (string $file, &$opts, $overwrite=false)
  {
    if (file_exists($file) && is_readable($file))
    {
      $text = trim(file_get_contents($file));

      if (empty($text))
      { // Why would you have an 
        throw new Exception("Cannot load empty file '$file'");
      }

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
          throw new Exception("Unknown file format for '$file', cannot parse");
        }
      }

      if ($F == 'j')
      {
        $conf = json_decode($text, true);
        if (!isset($conf))
        {
          throw new Exception("Invalid JSON in '$file', cannot continue.");
        }
      }
      elseif ($F == 'y')
      {
        $yaml = new YAML();
        $conf = $yaml->parse($text);
        if (!isset($conf))
        {
          throw new Exception("The YAML parser returned null, cannot continue.");
        }
      }
      else
      {
        throw new Exception("How did you get here?");
      }

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
  static function get_php_content ($__view_file, $__view_data=NULL)
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