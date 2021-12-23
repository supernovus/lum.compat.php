<?php

namespace Lum;

use Symfony\Component\Yaml\Yaml as SymYaml;
use Symfony\Component\Yaml\Tag\TaggedValue as SymTag;

/**
 * A cheap and dirty YAML front-end.
 *
 * We prefer the YAML extension, but will fall back to Symfony Yaml library.
 * If neither are found, it's game over. This is nowhere near feature-complete,
 * it's simply a quick way to provide two options for working with YAML.
 */
class YAML
{
  const SYM_YAML   = 'Symfony\Component\Yaml';

  const LIB_BINARY = 'yaml.decode_binary';
  const LIB_OBJECT = 'yaml.decode_php';
  const LIB_TIME   = 'yaml.decode_timestamp';
  const LIB_INDENT = 'yaml.output_indent';
  const LIB_WIDTH  = 'yaml.output_width';

  const O_ENC  = 'encoding';
  const O_BR   = 'break';
  const O_IND  = 'indent';
  const O_INL  = 'inline';
  const O_WDTH = 'width';
  const O_POBJ = 'parseObjects';
  const O_EOBJ = 'emitObjects';

  // Note the protected properties use placeholder values until
  // the extension/library that powers them has been determined to be in use.

  protected int $libyaml_encoding = 0;
  protected int $libyaml_break    = 0;

  protected int $symfony_parse_flags = 0;
  protected int $symfony_dump_inline = 8;
  protected int $symfony_dump_indent = 2;
  protected int $symfony_dump_flags  = 0;

  protected array $parse_callbacks = [];
  protected array $emit_callbacks  = [];

  public readonly bool $hasExtension;

  /**
   * Build a YAML instance.
   *
   * @param array $opts  Options to customize the behavior.
   *
   * @throws YAML_Not_Found  An exception if no valid YAML parser is found.
   */
  public function __construct(array $opts=[])
  {
    $this->hasExtension = (function_exists('yaml_parse') 
      && function_exists('yaml_emit'));

    if (!$this->hasExtension && !class_exists(SYM_YAML.'\Yaml'))
    { // Oh dear, I'm afraid we cannot continue.
      throw new YAML_Not_Found();
    }

    if ($this->hasExtension)
    { // Let's set up the libyaml specific settings.
      $this->libyaml_encoding 
        = (isset($opts[self::O_ENC]) && is_int($opts[self::O_ENC]))
        ? $opts[self::O_ENC]
        : YAML_ANY_ENCODING;

      $this->libyaml_break
        = (isset($opts[self::O_BR]) && is_int($opts[self::O_BR]))
        ? $opts[self::O_BR]
        : YAML_ANY_BREAK;

      ini_set(self::LIB_BINARY, '1');
      ini_set(self::LIB_TIME,   '2');

      if (isset($opts[self::O_IND]) && is_int($opts[self::O_IND]))
        ini_set(self::LIB_INDENT, strval($opts[self::O_IND]));
      if (isset($opts[self::O_WDTH]) && is_int($opts[self::O_WDTH]))
        ini_set(self::LIB_WIDTH, strval($opts[self::O_WDTH]));
      if (isset($opts[self::O_POBJ]) && is_bool($opts[self::O_POBJ]))
        ini_set(self::LIB_OBJECT, ($opts[self::O_POBJ] ? '1' : '0'));
    }
    else
    { // No extension, which means Symfony is available.
      $this->symfony_parse_flags 
        = SymYaml::PARSE_DATETIME
        | SymYaml::PARSE_CUSTOM_TAGS;

      $this->symfony_dump_flags
        = SymYaml::DUMP_MULTI_LINE_LITERAL_BLOCK
        | SymYaml::DUMP_BASE64_BINARY_DATA;

      if (isset($opts[self::O_POBJ]) && is_bool($opts[self::O_POBJ]))
        Util::set_flag($this->symfony_parse_flags, SymYaml::PARSE_OBJECT);

      if (isset($opts[self::O_EOBK]) && is_bool($opts[self::O_EOBJ]))
        Util::set_flag($this->symfony_dump_flags, SymYaml::DUMP_OBJECT);
      else
        Util::set_flag($this->symfony_dump_flags, SymYaml::DUMP_OBJECT_AS_MAP);

      if (isset($opts['inline']) && is_int($opts['inline']))
        $this->symfony_dump_inline = $opts['inline'];

      if (isset($opts['indent']) && is_int($opts['indent']))
        $this->symfony_dump_indent = $opts['indent'];
    }

  }

  public function __destruct()
  {
    if ($this->hasExtension)
    {
      ini_restore(self::LIB_BINARY);
      ini_restore(self::LIB_OBJECT);
      ini_restore(self::LIB_TIME);
      ini_restore(self::LIB_INDENT);
      ini_restore(self::LIB_WIDTH);
    }
  }

  public function onParse(string $tagname, callable $callable): void
  {
    $this->parse_callbacks[$tagname] = $callable;
  }

  public function onEmit(string $classname, callable $callable): void
  {
    $this->emit_callbacks[$classname] = $callable;
  }

  public function parse(string $data, array $opts=[]): mixed
  {
    $doc = (isset($opts['doc']) && is_int($opts['doc'])) ? $opts['doc'] : 0;
    if ($this->hasExtension)
    { // With the extension we're not going to do any magic at all.
      return yaml_parse($data, $doc, $ndocs, $this->parse_callbacks);
    }

    // We're going to try to make Symfony work more like the yaml extension.

    $inDocs = preg_split('/^---/', ltrim($data, ' \n\r\t\v\0-'));
    if ($doc === -1)
    { // We want all documents. 
      $outDocs = [];
      foreach ($inDocs as $inDoc)
      {
        $outDocs[] = $this->parseSymDoc($inDoc, $opts);
      }
      return $outDocs;
    }
    elseif (isset($inDocs[$doc]))
    {
      return $this->parseSymDoc($inDocs[$doc], $opts);
    }
    else
    {
      error_log("invalid doc id '$doc' specified");
      return null;
    }
  }

  protected function parseSymDoc(string $input, array $opts): mixed
  {
    $parsed = SymYaml::parse($input, $this->symfony_parse_flags);
    if (is_iterable($parsed))
    {
      return $this->parseSymIterable($parsed, $opts);
    }
    elseif ($parsed instanceof SymTag)
    {
      return $this->parseSymTag($parsed, $opts);
    }
    else
    {
      return $parsed;
    }
  }

  protected function parseSymIterable(iterable $input, array $opts): mixed
  {
    $output = [];
    foreach ($input as $key => $val)
    {
      if (is_iterable($val))
      {
        $output[$key] = $this->parseSymIterable($val, $opts);
      }
      elseif ($val instanceof SymTag)
      {
        $output[$key] = $this->parseSymTag($val, $opts);
      }
      else
      {
        $output[$key] = $val;
      }
    }
    return $output;
  }

  protected function parseSymTag(SymTag $input, array $opts): mixed
  {
    $tag = $input->getTag();
    $val = $input->getValue();
    foreach ([$tag, "!$tag"] as $name)
    {
      if (isset($this->parse_callbacks[$name]))
      {
        $callback = $this->parse_callbacks[$name];
        return $callback($val, $tag, 0);
      }
    }
    // If we reached here, no callback for the tag was found.
    return $input;
  }

  public function parseFile(string $filename, array $opts=[]): mixed
  { // Yeah, normally there's yaml_parse_file() and SycYaml::parseFile()
    // but in this case we want to use the "magic" code in parse().
    return $this->parse(file_get_contents($filename), $opts);
  }

  public static function emitDoc(mixed $data, array $opts=[]): string
  {
    if ($this->hasExtension)
    {
      return yaml_emit($data, $this->libyaml_encoding, $this->libyaml_break,
        $this->emit_callbacks);
    }
    if (is_object($data))
    {
      $data = $this->emitSymObject($data, $opts);
    }
    elseif (is_array($data))
    {
      $data = $this->emitSymIterable($data, $opts);
    }
    return SymYaml::dump($data, $this->symfony_dump_inline, 
      $this->symfony_dump_indent, $this->symfony_dump_flags);
  }

  protected function emitSymIterable(iterable $input, array $opts): mixed
  {
    $output = [];
    foreach ($input as $key => $val)
    {
      if (is_object($val))
      {
        $output[$key] = $this->emitSymObject($val, $opts);
      }
      elseif (is_array($val))
      {
        $output[$key] = $this->emitSymIterable($val, $opts);
      }
      else
      {
        $output[$key] = $val;
      }
    }
    return $output;
  }

  protected function emitSymObject(object $input, array $opts): object
  {
    $classname = get_class($input);
    $basename = array_pop(explode('\\', $classname));
    $find = [$classname, $basename];
    foreach ([$classname, $basename] as $name)
    {
      if (isset($this->emit_callbacks[$name]))
      {
        $callback = $this->emit_callbacks[$name];
        $tagged = $callback($input);
        if (isset($tagged, $tagged['tag'], $tagged['data']))
        {
          return new SymTag($tagged['tag'], $tagged['data']);
        }
      }
    }
    // If we reached here, no callback for the class was found.
    if (is_iterable($input))
    { // It's an iterable object.
      return $this->emitSymIterable($input, $opts);
    }
    // Last ditch effort, return it as is and hope for the best.
    return $input;
  }

  public function emitFile(string $filename, mixed $data, 
    array $opts=[]): bool
  { // Not using yaml_emit_file() for the same reasons as parseFile().
    file_put_contents($filename, $this->emit($data, $opts));
  }
}

class YAML_Not_Found extends Exception
{
  protected $message = 'YAML wrapper requires either the yaml extension or Symfony Yaml component';
}