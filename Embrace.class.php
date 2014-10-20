<?php

/* 
 * Embrace 1.0.0
 *
 * Copyright (c) 2014, The Pipe Cat
 * All rights reserved.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
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
 */
class Embrace
{
//------------------------------------------------------------------------------
// Constants:
//------------------------------------------------------------------------------
  const VERSION = '1.0.0';
  
  const DELIMITER_SEPARATOR = ',';
  const ARGUMENTS_SEPARATOR = ':';
  const ATTRIBUTE_SEPARATOR = '.';
  
//------------------------------------------------------------------------------
// Static variables:
//------------------------------------------------------------------------------
  
  protected static $cache = FALSE;
  protected static $cache_prepend = '';
  protected static $cache_append  = '.embraced';
  
  public static $debug = TRUE;
  
//------------------------------------------------------------------------------
// Static methods:
//------------------------------------------------------------------------------
  
  /**
   * Enable template cache.
   */
  public static function enableCache ()
  {
    static::$cache = TRUE;
  }
  
  /**
   * Disable template cache.
   */
  public static function disableCache ()
  {
    static::$cache = FALSE;
  }
  
//------------------------------------------------------------------------------
// Instance variables:
//------------------------------------------------------------------------------
  
  private $delimiter = '[[,]]';
  private $file = NULL;
  private $compiled = NULL;
  private $cache_life = 86400; // Defaults ONE day
  
  private $data = array ();
  private $call = array ();
  
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
  
  public function __get ($name)
  {
    if (isset($this->data[$name]))
      return $this->data[$name];
    else if (isset($this->call[$name]))
      return $this->call[$name];
      
    return NULL;
  }
  
  public function __set ($name, $value)
  {
    if (is_callable($value))
      $this->call[$name] = $value;
    else
      $this->data[$name] = $value;
  }
  
  public function __isset($name)
  {
    if (isset($this->data[$name]))
      return TRUE;
    if (isset($this->call[$name]))
      return TRUE;
    
    return FALSE;
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
   * @throws \Exception
   */
  public function load ($file)
  {
    if (!file_exists($file))
      throw new \Exception(sprintf(__('Template file "%s" not exists.'), $file));
    
    if (!is_readable($file))
      throw new \Exception(sprintf(__('Template file "%s" is unreachable.'), $file));
    
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
   * @throws \Exception
   */
  public function setDelimiter ($delimiters)
  {
    if (empty($delimiters))
      throw new \Exception(__('No delimiter setted.'));
    
    if (!is_string($delimiters))
      throw new \Exception(__('Invalid delimiter type.'));
    
    $delimiters_found = explode(self::DELIMITER_SEPARATOR, $delimiters);
    
    if (count($delimiters_found) !== 2)
      throw new \Exception(__('Invalid delimiter count.'));
    
    array_walk($delimiters_found, 'trim');
    
    $this->delimiter = implode(self::DELIMITER_SEPARATOR, $delimiters_found);
  }
  
  /**
   * Return cache life.
   * 
   * @return int
   */
  public function cacheLife ()
  {
    return $this->cache_life;
  }
  
  /**
   * Define cache life.
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
    
    $delimiters = explode(self::DELIMITER_SEPARATOR, $this->delimiter);
    
    $delimiter_open  = $delimiters[0];
    $delimiter_close = $delimiters[1];
    
    $analise = $content;
    
    $init = -1;
    $last_end = 0;
    
    while (($init = strpos($analise, $delimiter_open)) !== FALSE)
    {
      $end = strpos($analise, $delimiter_close, $init);
      
      if ($end === FALSE)
        break;
      
      $analise_tag = str_slice($analise, $init + strlen($delimiter_open), $end);
      $tag_args = explode(self::ARGUMENTS_SEPARATOR, $analise_tag);
      
      $tag_open = array_shift($tag_args);
      
      $end += strlen($delimiter_close);
      
//------------------------------------------------------------------------------
// Verify if has close tag.
//------------------------------------------------------------------------------
      $close_init = strpos($analise, $delimiter_open . '/' . str_replace($ctrl_chars, '', $tag_open), $end);
      
      $tag_inner = NULL;
      
      if ($close_init !== FALSE)
      {
        $tag_inner = str_slice($analise, $end, $close_init);
        
        $close_end = strpos($analise, $delimiter_close, $close_init);
        
        $end = $close_end + strlen($delimiter_close);
      }
      
      $tag = str_slice($analise, $init, $end);
      
      $tag_info = array (
        'init'   => ($init + $last_end),
        'length' => strlen($tag),
        'tag'    => $tag_open,
        'full'   => $tag
      );
      
      if (!empty($tag_args))
        $tag_info['args'] = $tag_args;
      
      if (!empty($tag_inner))
        $tag_info['inner'] = $tag_inner;
      
      $tag_info['replace'] = $this->analise($tag_info, $context);
      
      $found[] = $tag_info;
      
      $analise = substr($analise, $end);
      
      $last_end += $end;
    }
    
    return $found;
  }
  
  /**
   * Analise tag and return relative context content.
   * 
   * @param array|object $tag_info
   * - "init"  Initial integer position.
   * - "end"   Ending integer position.
   * - "tag"   Tag opening string.
   * - "args"  Opening arguments array.
   * - "full"  All tag content string.
   * - "inner" Inner tag content string.
   * @param object $context
   * @return string
   */
  protected function analise ($tag_info, &$context = NULL)
  {
    if (empty($tag_info) || (!is_object($tag_info) && !is_array($tag_info)))
      return NULL;
    
    if (is_array($tag_info))
      $tag_info = (object) $tag_info;
    
    if (empty($context) || !is_object($context))
      $context = &$this;
    
    $info = '';
    
    $control_char = $tag_info->tag[0];
    
    if (ctype_alpha($control_char) || $control_char === '$')
    {
//------------------------------------------------------------------------------
// Variable analises:
//------------------------------------------------------------------------------
      $tag = explode(self::ATTRIBUTE_SEPARATOR, str_replace('$', '', $tag_info->tag));
      
      if (isset($context->{$tag[0]}))
      {
        $found_info = $context->{$tag[0]};

        array_shift($tag);
        
        if (is_array($found_info) || is_object($found_info))
        {
          $found_info = (object) $found_info;
          
          $var_count = count($tag);
          
          for ($i = 0; $i < $var_count; $i++)
          {
            $var = $tag[$i];
            
            $found_info = $found_info->{$var};
            
            if (!empty($found_info))
            {
              if (($i + 1) < $var_count)
                continue;
              
              if (!empty($tag_info->inner))
              {
                if (is_array($found_info) || is_object($found_info))
                {
                  $index = 0;
                  
                  foreach ($found_info as $name => $value)
                  {
                    $info .= str_ireplace(array (
                      '[[index]]',
                      '[[name]]',
                      '[[value]]'
                    ), array (
                      $index,
                      $name,
                      $value
                    ), $tag_info->inner);
                    
                    $index++;
                  }
                }
              }
              else
              {
                if ((is_array($found_info) || is_object($found_info)) === FALSE)
                {
                  $info = $found_info;

                  break;
                }
              }
            }
            elseif (static::$debug)
            {
              $info = __('(not found)');
              
              break;
            }
          }
        }
        else
          $info = $found_info;
      }
    }
    else
    {
      switch ($control_char)
      {
        case '#':
//------------------------------------------------------------------------------
// Methods analise:
//------------------------------------------------------------------------------
          $call = str_replace($control_char, '', $tag_info->tag);
          
          if (function_exists($call))
          {
            $info = call_user_func_array($call, array (
              $tag_info->inner, $tag_info->tag
            ));
          }
          
          break;
        case '!':
//------------------------------------------------------------------------------
// Condidional analise:
//------------------------------------------------------------------------------
          if (empty($tag_info->inner))
            break;
          
          $ref_tag_info = clone ( $tag_info );
          
          $ref_tag_info->tag = str_replace($control_char, '', $ref_tag_info->tag);
          unset ($ref_tag_info->inner);
          
          $_debug = static::$debug;
          
          static::$debug = FALSE;
          
          $found_info = $this->analise($ref_tag_info, $context);
          
          static::$debug = $_debug;
          
          if (empty($found_info))
            $info = $tag_info->inner;
          else
            $info = $found_info;
          
          break;
      }
    }
    
    return $info;
  }
  
  /**
   * Compile content using given context or instance context.
   * 
   * @param string $content
   * @param object $context
   * @return string
   */
  protected function compile ($content = NULL, $context = NULL)
  {
    if (empty($content) && empty($this->file))
      return NULL;
    
    if (empty($context))
      $context = $this;
    
    if (empty($content))
    {
      ob_start();

      include $this->file;

      $content = ob_get_clean();
    }
    
    $embrace_found = $this->grab($content);
    
    $compiled = $content;
    
    $diff = 0;
    
    foreach ($embrace_found as $embraced)
    {
      $replace = $embraced['replace'];
      
      if (!empty($embraced['args']))
      {
        foreach ($embraced['args'] as $arg)
        {
          if (function_exists($arg))
            $replace = call_user_func($arg, $replace);
          elseif (static::$debug)
            $replace = __('(undefined function)');
        }
      }
      
      if (!empty($replace))
        $replace = $this->compile($replace, $context);
      
      $length = $embraced['length'];
      $init = $embraced['init'] + $diff;
      
      $compiled = substr_replace($compiled, $replace, $init, $length);
      
      $diff += strlen($replace) - $length;
    }
    
    return $compiled;
  }
  
  /**
   * Verify if has cached content.
   * 
   * @return boolean
   * @throws \Exception
   */
  protected function cached ()
  {
    if (empty($this->file))
      return FALSE;
    
    $file_dir  = dirname($this->file);
    $file_name = basename($this->file);
    
    $cache_file = $file_dir . DS . static::$cache_prepend . $file_name . static::$cache_append;
    
    if (!file_exists($cache_file))
      return FALSE;
    
    if (!is_readable($cache_file))
      throw new \Exception(sprintf(__('Cache file "%s" is not readable.'), $cache_file));
    
    if ((filemtime($cache_file) + $this->cache_life) < time())
    {
      unlink($cache_file);
      
      return FALSE;
    }
    
    $this->compiled = file_get_contents($cache_file);
    
    return TRUE;
  }
  
  /**
   * Cache compiled content.
   * 
   * @return boolean
   * @throws \Exception
   */
  protected function cache ()
  {
    if (empty($this->compiled))
      return FALSE;
    
    $file_dir  = dirname($this->file);
    $file_name = basename($this->file);
    
    $cache_file = $file_dir . DS . static::$cache_prepend . $file_name . static::$cache_append;
    
    if (!is_writable($file_dir))
      throw new \Exception(sprintf(__('Cache directory "%s" is not writeable.'), $file_dir));
    
    return file_put_contents($cache_file, $this->compiled, LOCK_EX) === FALSE ? FALSE : TRUE;
  }
  
  /**
   * Return the proccessed content.
   * 
   * @param boolean $renew
   * @return string
   * @throws \Exception
   */
  public function render ($renew = FALSE)
  {
    if (empty($this->file))
      throw new \Exception(__('There is no template file to render.'));
    
    if (!$renew && !empty($this->compiled))
      return $this->compiled;
    
    if ((static::$cache && !$renew && $this->cached()) === FALSE)
      $this->compiled = $this->compile();
    
    return $this->compiled;
  }
}

//------------------------------------------------------------------------------
// Support functions:
//------------------------------------------------------------------------------

if (!function_exists('ctype_alpha'))
{
  function ctype_alpha ($text)
  {
    return preg_match('/^[a-z]+$/i',$text);
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
