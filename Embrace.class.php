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

const EMBRACE_ROOT = __DIR__;

class Embrace
{
  const VERSION = '1.0.0';
  
//------------------------------------------------------------------------------
// Static variables:
//------------------------------------------------------------------------------
  
  private static $cache = FALSE;
  
//------------------------------------------------------------------------------
// Static mathods:
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
  
//------------------------------------------------------------------------------
// Instance methods:
//------------------------------------------------------------------------------
  
  public function __construct ()
  {
    
  }
  
  public function __destruct ()
  {
    
  }
}
