<?php

/*
 * Embrace (PHP)
 * 
 * Is under the open source MIT License (MIT)
 * 
 * Copyright (c) 2014, The Pipe Cat
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

if (!defined ('DS'))
  define ('DS', DIRECTORY_SEPARATOR);

define ('EMBRACE_ROOT', __DIR__ . DS);

/**
 * Embrace template class.
 * 
 * Embrace class is the main class for templates management.
 * 
 * @copyright (c) 2014, The Pipe Cat
 * @since 1.0.0
 * @version 1.0.0
 * @author Pedro Xudre <xudre at pipe.cat>
 */
class Embrace
{
//------------------------------------------------------------------------------
// Constants:
//------------------------------------------------------------------------------
  const VERSION = '1.0.0';
  
  const DELIMITER_SEPARATOR = ','; // Tag separator delimiter.
  const ARGUMENTS_SEPARATOR = ':'; // Tag arguments separator.
  const ATTRIBUTE_SEPARATOR = '.'; // Tag attributes inheritance separator.
  
//------------------------------------------------------------------------------
// Static variables:
//------------------------------------------------------------------------------
  
  protected static $global_cache  = TRUE;    // Global cache validation flag.
  protected static $cache_prepend = '~';     // Cache file name prepend string.
  protected static $cache_append  = '.html'; // Cache file name append string.
  
  public static $debug = FALSE;              // On debug mode flag.
  
//------------------------------------------------------------------------------
// Static methods:
//------------------------------------------------------------------------------
  
  /**
   * Enable template cache.
   */
  public static function enableCache ()
  {
    static::$global_cache = TRUE;
  }
  
  /**
   * Disable template cache.
   */
  public static function disableCache ()
  {
    static::$global_cache = FALSE;
  }
  
  /**
   * Verify is cache is globaly enabled.
   * 
   * @return boolean
   */
  public static function isCacheEnabled ()
  {
    return static::$global_cache;
  }
  
//------------------------------------------------------------------------------
// Instance variables:
//------------------------------------------------------------------------------
// -- Private
  private $delimiter = '[[,]]'; // Delimiters used in template analysis.
  private $file = NULL;         // Template file path.
  private $compiled = NULL;     // Compiled content.
  private $cached = NULL;       // Cache colected content.
  private $cache_life = 86400;  // Defaults cache life to ONE day.
  private $cache_dir  = NULL;   // Cache files directory.
  private $cache_file = NULL;   // Cache file name. AUTO GENERATED
  private $cache_path = NULL;   // Cache file path. AUTO GENERATED
  
  private $parent = NULL;       // Parent Embrace instance.
  
  private $data = array ();     // Variable data pool.
  private $call = array ();     // Functions callback pool.
  
// -- Public
  private $cache = TRUE;        // Cache verify / create flag.
  
//------------------------------------------------------------------------------
// Instance magic methods:
//------------------------------------------------------------------------------
  
  public function __construct ($file = NULL)
  {
    if (!empty($file))
      $this->load($file);
  }
  
  public function __destruct ()
  {
    unset ($this->data);
    unset ($this->call);
  }
  
  public function &__get ($name)
  {
    $value = NULL;
    
    if (isset($this->data[$name]))
      $value = &$this->data[$name];
    else if (isset($this->call[$name]))
      $value = &$this->call[$name];
      
    return $value;
  }
  
  public function __set ($name, $value)
  {
    if ($value instanceof Embrace)
    {
      $value->setParent($this);
      
      $this->data[$name] = &$value;
    }
    elseif (is_callable($value))
      $this->call[$name] = $value;
    else
      $this->data[$name] = $value;
  }
  
  public function __isset($name)
  {
    if (isset($this->data[$name]))
      return TRUE;
    elseif (isset($this->call[$name]))
      return TRUE;
    
    return FALSE;
  }
  
  public function __unset($name)
  {
    if (isset($this->data[$name]))
      unset ($this->data[$name]);
    elseif (isset($this->call[$name]))
      unset ($this->call[$name]);
  }
  
  public function __invoke()
  {
    // Easy channeling for Embrace::render() method.
    $this->render();
  }
  
//------------------------------------------------------------------------------
// Instance methods:
//------------------------------------------------------------------------------
  
  /**
   * Load template file.
   * 
   * @param string $file
   * @throws Exception
   */
  public function load ($file)
  {
    if (!file_exists($file))
      throw new Exception(sprintf(__('Template file "%s" not exists.'), $file));
    
    if (!is_readable($file))
      throw new Exception(sprintf(__('Template file "%s" is unreachable.'), $file));
    
    $this->file = $file;
  }
  
  /**
   * Return emrbace delimiter.
   * 
   * @return string
   */
  public function delimiter ()
  {
    return $this->delimiter;
  }
  
  /**
   * Define embrace delimiters.
   * 
   * @param string $delimiters
   * @throws Exception
   */
  public function setDelimiter ($delimiters)
  {
    if (empty($delimiters))
      throw new Exception(__('No delimiter setted.'));
    
    if (!is_string($delimiters))
      throw new Exception(__('Invalid delimiter type.'));
    
    $delimiters_found = explode(static::DELIMITER_SEPARATOR, $delimiters);
    
    if (count($delimiters_found) !== 2)
      throw new Exception(__('Invalid delimiter count.'));
    
    array_walk($delimiters_found, 'trim');
    
    $this->delimiter = implode(static::DELIMITER_SEPARATOR, $delimiters_found);
  }
  
  /**
   * Return cache file life time.
   * 
   * @return int
   */
  public function cacheLife ()
  {
    return $this->cache_life;
  }
  
  /**
   * Define cache file life time.
   * 
   * @param int $seconds
   * @return boolean
   */
  public function setCacheLife ($seconds)
  {
    if (empty($seconds) || !is_numeric($seconds))
      return FALSE;
    
    if ($seconds < 0)
      $seconds = 0;
    
    $this->cache_life = $seconds;
    
    return TRUE;
  }
  
  /**
   * Define parent Embrace cache policy.
   * 
   * @param boolean $value
   */
  private function setParentCache ($value)
  {
    if (!empty($this->parent) && is_bool($value))
      $this->parent->setCache($value);
  }
  
  /**
   * Return Embrace parent.
   * 
   * @return Embrace
   */
  public function &parent ()
  {
    return $this->parent;
  }
  
  /**
   * Define an Embrace parent.
   * 
   * @param Embrace $parent
   * @throws Exception
   */
  public function setParent (Embrace &$parent)
  {
    if (! ($parent instanceof Embrace))
      throw new Exception(__('Parent is not a valid Embrace instance.'));
    
    $this->parent = &$parent;
  }
  
  /**
   * Return tags info found for content.
   * 
   * @param string $content
   * @param object $context
   * @return array
   */
  public function grab ($content, &$context = NULL)
  {
    $found = array ();
    
    $ctrl_chars = array (
      '$', '#', '!'
    );
    
    if (empty($content) || !is_string($content))
      return $found;
    
    if (empty($context) || !is_object($context))
      $context = &$this;
    
    $delimiters = explode(static::DELIMITER_SEPARATOR, $this->delimiter);
    
    $delimiter_open  = $delimiters[0];
    $delimiter_close = $delimiters[1];
    
    $analyse = $content;
    
    $init = -1;
    $last_end = 0;
    
    while (($init = strpos($analyse, $delimiter_open)) !== FALSE)
    {
      $end = strpos($analyse, $delimiter_close, $init);
      
      if ($end === FALSE)
        break;
      
      $analyse_tag = str_slice($analyse, $init + strlen($delimiter_open), $end);
      $tag_args = explode(static::ARGUMENTS_SEPARATOR, $analyse_tag);
      
      $tag_open = array_shift($tag_args);
      $tag_logical = NULL;
      
      // Verify if has logical analysis:
      $logical_analysis = array (
        ' === ' => 'EQ', // EQUAL
        ' eq '  => 'EQ', // EQUAL
        ' !== ' => 'NE', // NOT EQUAL
        ' ne '  => 'NE', // NOT EQUAL
        ' > '   => 'GT', // GREATER THAN
        ' gt '  => 'GT', // GREATER THAN
        ' < '   => 'LT', // LESS THAN
        ' lt '  => 'LT', // LESS THAN
        ' >= '  => 'GE', // GREATER THAN OR EQUAL TO
        ' ge '  => 'GE', // GREATER THAN OR EQUAL TO
        ' <= '  => 'LE', // LESS THAN OR EQUAL TO
        ' le '  => 'LE', // LESS THAN OR EQUAL TO
      );
      
      foreach ($logical_analysis as $logical => $operator)
      {
        if (strpos($tag_open, $logical) === FALSE)
          continue;
        
        $tag_open_pieces = explode($logical, $tag_open);
        
        if (count($tag_open_pieces) != 2)
          throw new Exception(sprintf(__('Template logical tag "%s" must have two attributes.'), $tag_open));
        
        $tag_logical = array (
          'operator' => $operator,
          'than' => trim($tag_open_pieces[1])
        );
        
        $tag_open = trim($tag_open_pieces[0]);
        
        unset($tag_open_pieces);
        
        break;
      }
      
      $end += strlen($delimiter_close);
      
//------------------------------------------------------------------------------
// Verify if has close tag.
//------------------------------------------------------------------------------
      $close_init = strpos($analyse, $delimiter_open . '/' . str_replace($ctrl_chars, '', $tag_open) . $delimiter_close, $end);
      
      $tag_inner = NULL;
      
      if ($close_init !== FALSE)
      {
        $tag_inner = str_slice($analyse, $end, $close_init);
        
        $close_end = strpos($analyse, $delimiter_close, $close_init);
        
        $end = $close_end + strlen($delimiter_close);
      }
      
      $tag = str_slice($analyse, $init, $end);
      
      $tag_info = (object) array (
        'init'    => ($init + $last_end),
        'length'  => strlen($tag),
        'tag'     => $tag_open,
        'tag_n'   => strtolower($tag_open),
        'full'    => $tag,
        'inner'   => rtrim($tag_inner), // Strip line end
        'replace' => '',
        'logical' => $tag_logical
      );
      
      if (!empty($tag_args))
        $tag_info->args = $tag_args;
      
      if ($tag_info->tag_n === 'literal')
        $tag_info->literal = $tag_info->inner;
      else
        $tag_info->replace = $this->analyse($tag_info, $context);
      
      if (!isset($tag_info->cache))
      {
        if ($tag_info->tag_n === 'include')
          $tag_info->cache = $tag_info->full;
        else
          $tag_info->cache = $tag_info->replace;
      }
      
      $found[] = $tag_info;
      
      $analyse = substr($analyse, $end);
      
      $last_end += $end;
    }
    
    return $found;
  }
  
  /**
   * Analyse tag and return relative context content.
   * 
   * @param object $tag_info
   * - "init"      Initial integer position.
   * - "end"       Ending integer position.
   * - "tag"       Tag opening string.
   * - "tag_n"     Tag name normalized string.
   * - "args"      Opening arguments array.
   * - "full"      All tag content string.
   * - "inner"     Inner tag content string.
   * - "replace"   Final replacement content string.
   * - "logical"   Logical structure comparison, if has:
   * -- "operator" Kind of logical operation, e.g. === (EQ).
   * -- "than"     Right side operand for comparison.
   * @param object $context
   * @return string
   */
  public function analyse (&$tag_info, &$context = NULL)
  {
    if (empty($tag_info) || !is_object($tag_info))
      return NULL;
    
    if (empty($context) || !is_object($context))
      $context = &$this;
    
//------------------------------------------------------------------------------
// Parentage check first:
//------------------------------------------------------------------------------
    if (!empty($this->parent))
    {
      $info = $this->parent->analyse($tag_info);
      
      if (!empty($info))
        return $info;
    }
    
    $info = '';
    $me = &$this;
    $on_debug = static::$debug;
    
    $__process_var = function & (&$info, &$context) use ($tag_info, $me, $on_debug)
    {
      // $__process_var :: BEGIN
      if (isset($info))
      {
        if (!empty($tag_info->inner) && empty($tag_info->logical))
        {
          if ((is_array($info) || is_object($info)) &&
              (empty($tag_info->args) || !in_array('no-loop', $tag_info->args)))
          {
            $index = 0;
            $total = count($info);

            $return = '';

            foreach ($info as $name => $value)
            {
              $inner_context = clone ($context);

              $inner_context->index = $index;
              $inner_context->name  = $name;
              $inner_context->value = $value;
              $inner_context->last  = ($index + 1 >= $total) ? 1 : 0;

              $return .= $me->compile($tag_info->inner, $inner_context);

              $index++;
            }

            return $return;
          }
          else
          {
            return $me->compile($tag_info->inner, $context);
          }
        }
        else
        {
          if (!(is_array($info) || is_object($info)))
            return $info;
          elseif ($info instanceof Embrace)
          {
            $info->setParent($me);

            return $info;
          }
        }
      }
      elseif ($on_debug)
        return sprintf(__('%s not found'), print_r($info));
      else
        return $info;
      // $__process_var :: END
    };
    
    $__normalize_output = function ($info)
    {
      // $__normalize_output :: BEGIN
      if ($info instanceof Embrace)
        $info = $info->render();

      if (!empty($info))
        $info = ltrim($info, "\n\r");

      if (!empty($info))
        $info = rtrim($info);
      
      return $info;
      // $__normalize_output :: END
    };

    $tag_n = $tag_info->tag_n;
    
//------------------------------------------------------------------------------
// [[include:{file}:{cache-policy}]]
//------------------------------------------------------------------------------
    if ($tag_n === 'include')
    {
      if (empty($tag_info->args))
        throw new Exception(__('Include tag file attribute is missing.'));
      
      $embrace_file = $tag_info->args[0];
      
      if (!empty($this->file))
        $embrace_file = dirname($this->file) . DS . $embrace_file;
      
      if (strpos(basename($embrace_file), '.') === FALSE)
        $embrace_file .= '.php';
      
      $embrace_file_n = realpath($embrace_file);
      
      if ($embrace_file_n === FALSE)
        throw new Exception(sprintf(__('Template path "%s" is invalid.'), $embrace_file));
      
      unset ($embrace_file);
      
      if (!file_exists($embrace_file_n))
        throw new Exception(sprintf(__('Template file "%s" not found.'), $embrace_file_n));
      
      $cache_policy = 'included';
      
      $embrace = new Embrace($embrace_file_n);
      
      unset ($embrace_file_n);
      
      $embrace_cache_file = $embrace->cacheFilePath();
      
      if (in_array('no-cache', $tag_info->args))
        $cache_policy = 0;
      elseif (isset($tag_info->args[1]))
        $cache_policy = $tag_info->args[1] + 0;
      
      if ($cache_policy !== 'included')
      {
        if ($cache_policy === 0)
          $embrace->setCache(FALSE);
        
        $tag_info->cache = $tag_info->full;
      }
      
      $now = time();

      if (file_exists($embrace_cache_file) && (filemtime($embrace_cache_file) + $cache_policy) > $now)
        $info = file_get_contents($embrace_cache_file);
      else
      {
        $embrace->setParent($this);
        
        $info = $embrace->render();
      }
      
      unset ($embrace);
      
      return $__normalize_output ( $info );
    }

//------------------------------------------------------------------------------
// [[cache:{policy(integer|"none")}:{naming(string)}]]{content}[[/cache]]
//------------------------------------------------------------------------------
    if ($tag_n === 'cache' && !empty($tag_info->inner))
    {
      $cache_content = $__normalize_output ( $__process_var($tag_info->inner, $context) );
      
      if ($this->cache === FALSE)
        return $cache_content;
      
      $cache_policy = $this->cache_life;
      $cache_naming = NULL;
      
      if (!empty($tag_info->args))
      {
        foreach ($tag_info->args as $cache_arg)
        {
          if (is_numeric($cache_arg) || $cache_arg === 'none')
            $cache_policy = $cache_arg + 0;
          elseif (is_string($cache_arg))
            $cache_naming = trim($cache_arg);
        }
      }
      
      if (empty($cache_naming))
        $cache_naming = md5($tag_info->inner);
      
      $path_info = pathinfo( $this->cacheFilePath() );
      
      $cache_dir  = $path_info['dirname'];
      $cache_file = $path_info['filename'] . '-' . $cache_naming . static::$cache_append;
      $cache_path = $cache_dir . DS . $cache_file;
      $ref_dir = get_relative_path($cache_dir, dirname($this->file));
      
      unset ($path_info);

      if (!is_writable($cache_dir))
        throw new Exception(sprintf(__('Unable to write cache file "%s".'), $cache_path));

      unset ($cache_dir);

      file_put_contents($cache_path, $tag_info->inner, LOCK_EX);

      $tag_info->cache = '[[include:' . $ref_dir .  DS . $cache_file . ':' . $cache_policy . ']]';

      unset ($cache_path);
      unset ($cache_file);
      unset ($cache_naming);
      unset ($cache_policy);
      
      return $cache_content;
    }
    
//------------------------------------------------------------------------------
// [[php]]{code}[[/php]]
//------------------------------------------------------------------------------
    if ($tag_n === 'php')
    {
      if (empty($tag_info->inner))
        return $info;
      
      ob_start();
      
      eval ($tag_info->inner);
      
      $info = $__normalize_output ( ob_get_clean() );
      
      return $info;
    }
    
    $control_char = $tag_info->tag[0];
    
    if (ctype_alpha($control_char) || $control_char === '$')
    {
//------------------------------------------------------------------------------
// Variable analysis:
//------------------------------------------------------------------------------
      $tag = explode(static::ATTRIBUTE_SEPARATOR, ($control_char === '$' ?
                                                   str_replace($tag_info->tag, 1) :
                                                   $tag_info->tag));
      
      if (isset($context->{$tag[0]}))
      {
        $found_info = $context->{$tag[0]};

        array_shift($tag);
        
        if (is_array($found_info) || is_object($found_info))
        {
          $found_info = (object) $found_info;
          
          $var_count = count($tag);
          
          if ($var_count < 1)
          {
            $info = $__process_var($found_info, $context);
          }
          else
          {
            for ($i = 0; $i < $var_count; $i++)
            {
              $var = $tag[$i];
              
              $found_info = is_array($found_info) ? $found_info[$var] : $found_info->{$var};
              
              if (!empty($found_info) && ($i + 1) < $var_count)
                continue;
              
              $info = $__process_var($found_info, $context);
              
              if (!empty($info))
                break;
            }
          }
        }
        else
          $info = $found_info;
        
        if (!empty($tag_info->logical) &&
            isset($tag_info->logical['operator']) &&
            isset($tag_info->logical['than']))
        {
          if ($info instanceof Embrace)
            throw new Exception(__('Embrace class could not be used in logical comparison.'));
          
          if (!is_string($info) && !is_numeric($info) && !is_null($info))
            throw new Exception(__('Variable kind could not be used in logical comparison.'));
          
          $operator = $tag_info->logical['operator'];
          $than     = &$tag_info->logical['than'];
          
          if (is_numeric($info))
            $info = $info + 0;
          elseif (is_null($info))
            $info = 0;
          
          if (is_numeric($than))
            $than = $than + 0;
          elseif (is_null($than))
            $than = 0;
          
          $logical_pass = FALSE;
          
          switch ($operator)
          {
            case 'EQ': // EQUAL
            {
              $logical_pass = ( $info === $than );
              break;
            }
            case 'NQ': // NOT EQUAL
            {
              $logical_pass = ( $info !== $than );
              break;
            }
            case 'GT': // GREATER THAN
            {
              $logical_pass = ( $info > $than );
              break;
            }
            case 'LT': // LESS THAN
            {
              $logical_pass = ( $info < $than );
              break;
            }
            case 'GE': // GREATER THAN OR EQUAL TO
            {
              $logical_pass = ( $info >= $than );
              break;
            }
            case 'LE': // LESS THAN OR EQUAL TO
            {
              $logical_pass = ( $info <= $than );
              break;
            }
          }
          
          if (!empty($tag_info->inner))
          {
            if ($logical_pass)
              $info = $tag_info->inner;
            else
              $info = '';
          }
          else if (!$logical_pass)
            $info = '';
        }
      }
    }
    else
    {
      switch ($control_char)
      {
        case '#':
//------------------------------------------------------------------------------
// Methods analyse:
//------------------------------------------------------------------------------
          $call = substr($tag_info->tag, 1);
          
          if (function_exists($call))
          {
            $info = call_user_func_array($call, array (
              $tag_info->inner, $tag_info->tag
            ));
          }
          
          break;
        case '!':
//------------------------------------------------------------------------------
// Condidional analyse:
//------------------------------------------------------------------------------
          if (empty($tag_info->inner))
            break;
          
          $ref_tag_info = clone ( $tag_info );
          
          $ref_tag_info->tag = substr($ref_tag_info->tag, 1);
          
          $inverse_logical = FALSE;
          
          if ($ref_tag_info->tag[0] === '!')
          {
            $inverse_logical = TRUE;
            $ref_tag_info->tag = substr($ref_tag_info->tag, 1);
          }
          
          unset ($ref_tag_info->inner);
          
          $_debug = static::$debug;
          
          static::$debug = FALSE;
          
          $found_info = $this->analyse($ref_tag_info, $context);
          
          static::$debug = $_debug;
          
          if ((!$inverse_logical && empty($found_info)) ||
              ($inverse_logical && !empty($found_info)))
            $info = $tag_info->inner;
          else
            $info = '';
          
          break;
      }
    }
    
    return $__normalize_output($info);
  }
  
  /**
   * Compile content using given context or instance context.
   * 
   * @param string $content
   * @param object $context
   * @param string $tocache
   * @return string
   */
  public function compile ($content = NULL, &$context = NULL, &$tocache = NULL)
  {
    if (empty($content) && empty($this->file))
      return NULL;
    
    if (empty($context))
      $context = &$this;
    
    if (empty($content))
    {
      ob_start();

      include $this->file;

      $content = ob_get_clean();
    }
    
    $embrace_found = $this->grab($content, $context);
    
    if (isset($tocache))
      $tocache = '' . $content;
    
    $compiled = $content;
    
    $diff = 0;
    $cache_diff = 0;
    
    foreach ($embrace_found as $embraced)
    {
      $replace = $embraced->replace;
      
      if (!empty($embraced->args))
      {
        foreach ($embraced->args as $arg)
        {
          if (function_exists($arg))
            $replace = call_user_func($arg, $replace);
          elseif (static::$debug)
            $replace = __('(undefined function)');
        }
      }
      
      if (!empty($replace))
        $replace = $this->compile($replace, $context);
      elseif (isset($embraced->literal))
        $replace = $embraced->literal;
      
      $length = $embraced->length;
      $init = $embraced->init + $diff;
      
      if (isset($tocache) && is_string($tocache))
      {
        $cache = $embraced->cache;
        $cache_init = $embraced->init + $cache_diff;
        
        $tocache = substr_replace($tocache, $cache, $cache_init, $length);

        $cache_diff += strlen($cache) - $length;
      }
      
      $compiled = substr_replace($compiled, $replace, $init, $length);
      
      $diff += strlen($replace) - $length;
    }
    
    return $compiled;
  }
  
  /**
   * Define cache instance policy.
   * 
   * @param boolean $value
   */
  public function setCache ($value)
  {
    if (is_bool($value))
    {
      $this->cache = $value;
      
      if (!empty($this->parent) && $value === FALSE)
        $this->parent->setCache(FALSE);
    }
  }
  
  /**
   * Return cache instance policy.
   * 
   * @return boolean
   */
  public function cache ()
  {
    return $this->cache;
  }
  
  /**
   * Verify if has cached content.
   * 
   * @param type $template_file
   * @return boolean
   * @throws Exception
   */
  protected function cached ($template_file = NULL)
  {
    if (!$this->cache || (empty($this->file) && empty($template_file)))
      return FALSE;
    
    if (empty($template_file))
      $template_file = $this->file;
    
    $cache_file = $this->cacheFilePath($template_file);
    
    if (!file_exists($cache_file))
      return FALSE;
    
    if (!is_readable($cache_file))
      throw new Exception(sprintf(__('Cache file "%s" is not readable.'), $cache_file));
    
    if ((filemtime($cache_file) + $this->cache_life) < time())
    {
      unlink($cache_file);
      
      unset ($cache_file);
      
      return FALSE;
    }
    
    $this->cached = file_get_contents($cache_file);

    unset ($cache_file);
    
    return TRUE;
  }
  
  /**
   * Cache compiled content.
   * 
   * @param string $cache_content
   * @return boolean
   * @throws Exception
   */
  protected function cacheCreate ($cache_content = NULL)
  {
    if (!$this->cache)
      return FALSE;
    
    if (empty($this->compiled))
      return FALSE;
    
    if (empty($cache_content))
      $cache_content = $this->compiled;
    
    $cache_file = $this->cacheFilePath();
    
    if (!is_writable($this->cache_dir))
      throw new Exception(sprintf(__('Cache directory "%s" is not writeable.'), $this->cache_dir));
    
    return file_put_contents($cache_file, $cache_content, LOCK_EX) === FALSE ? FALSE : TRUE;
  }
  
  /**
   * Returns cache file path for current template.
   * 
   * @param string $template_file
   * @return string
   * @throws Exception
   */
  public function cacheFilePath ($template_file = NULL)
  {
    if (!empty($this->cache_path))
      return $this->cache_path;
    
    if (empty($template_file) && empty($this->file))
      throw new Exception(__('Template file not defined.'));
    
    if (empty($template_file))
      $template_file = $this->file;
    else
      $template_file = realpath($template_file);
    
    $file_info = pathinfo($template_file);
    
    $cache_dir = $this->cacheDir();
    
    if (empty($cache_dir))
      $this->setCacheDir($file_info['dirname']);
    
    $this->cache_file = static::$cache_prepend . $file_info['filename'] . static::$cache_append;
    $this->cache_path = $cache_dir . DS . $this->cache_file;
    
    return $this->cache_path;
  }
  
  /**
   * Return the cache directory.
   * 
   * @return string
   */
  public function cacheDir ()
  {
    if (!empty($this->parent))
      $this->cache_dir = $this->parent->cacheDir();
    
    return $this->cache_dir;
  }
  
  /**
   * Defines the cache directory.
   * 
   * @param string $dir_path
   * @throws Exception
   */
  public function setCacheDir ($dir_path)
  {
    if (file_exists($dir_path) && !is_dir($dir_path))
      throw new Exception(__('File path exists and not is a valid directory.'));
    
    if (!is_dir($dir_path) && !mkdir($dir_path, 0777, TRUE))
      throw new Exception(__('Could not create cache dir.'));
    
    if (!is_writable($dir_path))
      throw new Exception(__('Cache directory not is writable.'));
    
    $this->cache_dir = $dir_path;
  }
  
  /**
   * Return the proccessed content.
   * 
   * @param boolean $renew
   * @return string
   * @throws Exception
   */
  public function render ($renew = FALSE)
  {
    if (empty($this->file))
      throw new Exception(__('There is no template file to render.'));
    
    if (!$renew && !empty($this->compiled))
      return $this->compiled;
    
    if ((static::$global_cache && !$renew && $this->cached()) === FALSE)
    {
      $to_cache = '';
      
      $this->compiled = $this->compile(NULL, $this, $to_cache);
      
      // Global and local "do cache" check.
      if (static::$global_cache && $this->cache)
        $this->cacheCreate($to_cache);
    }
    else
      $this->compiled = $this->compile($this->cached, $this);
    
    return $this->compiled;
  }
}

//------------------------------------------------------------------------------
// Support functions:
//------------------------------------------------------------------------------

if (!function_exists('get_relative_path'))
{
  function get_relative_path ($path_from, $path_to)
  {
    if (empty($path_from))
      throw new Exception(__('Path origin must be defined.'));
    
    if (empty($path_to))
      throw new Exception(__('Path destiny must be defined.'));
    
    $DS = DIRECTORY_SEPARATOR;
    
    $path_from_n = trim($path_from, "\t\n\r\0\x0B" . $DS);
    $path_to_n   = trim($path_to,   "\t\n\r\0\x0B" . $DS);
    
    unset ($path_from);
    unset ($path_to);
    
    if (strpos($path_from_n, $path_to_n) === 0)
      return substr($path_from_n, (strlen($path_to_n) + 1));

    $relative_path    = array ();
    $path_from_pieces = explode($DS, $path_from_n);
    $path_to_pieces   = explode($DS, $path_to_n);

    foreach ($path_to_pieces as $index => $part)
    {
      if (isset($path_from_pieces[$index]) && $path_from_pieces[$index] == $part)
        continue;

      $relative_path[] = '..';
    }

    foreach ($path_from_pieces as $index => $part)
    {
      if (isset($path_to_pieces[$index]) && $path_to_pieces[$index] == $part)
        continue;

      $relative_path[] = $part;
    }

    return implode($DS, $relative_path);
  }
}

if (!function_exists('ctype_alpha'))
{
  /**
   * Return de ASCII equivalent value for character.
   * 
   * @param string $c
   * @return integer
   */
  function uniord ($c)
  {
    $h = ord($c{0});
    
    if ($h <= 0x7F)
      return $h;
    else if ($h < 0xC2)
      return FALSE;
    else if ($h <= 0xDF)
      return ($h & 0x1F) << 6 | (ord($c{1}) & 0x3F);
    else if ($h <= 0xEF)
      return ($h & 0x0F) << 12 | (ord($c{1}) & 0x3F) << 6 | (ord($c{2}) & 0x3F);
    else if ($h <= 0xF4)
      return ($h & 0x0F) << 18 | (ord($c{1}) & 0x3F) << 12 | (ord($c{2}) & 0x3F) << 6 | (ord($c{3}) & 0x3F);
    else
      return FALSE;
  }

  // This is a substitutive function behaves as if the input $text is in UTF-8.
  function ctype_alpha ($string)
  {
    if (!is_string($string))
      return FALSE;
    
    $string_len = strlen($string);
    
    if ($string_len < 1)
      return FALSE;
    
    for ($c = 0; $c < $string_len; $c++)
    {
      $c_val = uniord($string{$c});
      
      if (!($c_val >= 0x41 && $c_val <= 0x5A) && // Upper case
          !($c_val >= 0x61 && $c_val <= 0x7A))   // Lower case
        return FALSE;
    }
    
    return TRUE;
  }
}

if (!function_exists('__'))
{
  function __ ($string)
  {
    return $string;
  }
}

if (!function_exists('str_slice'))
{
  function str_slice ($str, $start, $end)
  {
    return substr($str, $start, ($end - $start));
  }
}
