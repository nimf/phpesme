<?php

/**
 * Nimf's logger
 * 
 * This file contains classes of Nimf's logger.
 * 
 * @package NIMF
 * @subpackage logger 
 * @author Nimf <nimfin@gmail.com>
 * @version 0.0.3
 * @copyright Copyright (c) 2008, Nimf
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *    http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Emergenency message level
 */
define('L_EMRG',1);
/**
 * Alert message level
 */
define('L_ALRT',2);
/**
 * Critical message level
 */
define('L_CRIT',4);
/**
 * Error message level
 */
define('L_ERR' ,8);
/**
 * Warning message level
 */
define('L_WARN',16);
/**
 * Notice message level
 */
define('L_NOTC',32);
/**
 * Information message level
 */
define('L_INFO',64);
/**
 * Debug message level
 */
define('L_DEBG',128);
/**
 * Bitmask for all message levels
 */
define('L_ALL' ,255);

/**
 * Nimf's logger class
 * 
 * Usage example:
 * 
 * <code>
 * // Init logger
 * $logger = new NIMF_logger('./logs/%Y-%m-%d.log',L_ALL);
 * function l($msg,$lvl=L_DEBG) {global $logger; if (isset($logger)) $logger->log($msg,$lvl);}
 * 
 * l('Saying something');
 * </code>
 * 
 * @package NIMF
 * @subpackage logger
 * @author Nimf <nimfin@gmail.com>
 * @uses NIMF_logger_stream
 */
class NIMF_logger {
  /**
   * Logger's streams
   * @access private
   * @var array
   */
  private $streams = Array();
  
  /**
   * Constructor function. Creates logging stream immediately.
   * @access public
   * @param string $fname Filename mask
   * @param integer $lvl Emergency level bitmask
   * @param string $ts Timestamp used in log file
   * @return boolean TRUE on success, FALSE on error
   */
  public function __construct($fname,$lvl=127,$ts='d.m.y H:i:s') {
    return $this->add_stream($fname,$lvl,$ts);
  }
  
  /**
   * Creates new logging stream.
   * @access public
   * @param string $fname Filename mask
   * @param integer $lvl Emergency level bitmask
   * @param string $ts Timestamp used in log file
   * @return boolean TRUE on success, FALSE on error
   */
  public function add_stream($fname,$lvl=127,$ts='d.m.y H:i:s') {
    $stream = new NIMF_logger_stream($fname,$lvl,$ts);
    $this->streams []= $stream;
    return true;
  }
  
  /**
   * Logs message to file(s)
   * @access public
   * @param string $msg Message to log
   * @param integer $lvl Emergency level bitmask
   * @return boolean TRUE on success, FALSE on error
   */
  public function log($msg=null,$lvl=L_DEBG) {
    if (count($this->streams)) foreach($this->streams as $key=>$stream) {
      if ($stream->lvl & $lvl)
        $this->streams[$key]->log($msg,$lvl);
    }
    return true;
  }
  
  /**
   * Destructor. Shutdowns logger, closing all streams
   * @access public
   * @return boolean TRUE on success, FALSE on error
   */
  public function __destruct() {
    foreach($this->streams as $key=>$stream) {
      unset($this->streams[$key]);
    }
    return true;
  }
}

/**
 * Nimf's logger stream. Used by Nimf's logger class.
 * @package NIMF
 * @subpackage logger
 * @author Nimf <nimfin@gmail.com>
 */
class NIMF_logger_stream {
  /**
   * Emergency level bitmask
   * @access public
   * @var integer
   */
  public $lvl = null;
  /**
   * File pointer
   * @access private
   * @var resource
   */
  private $fp = null;
  /**
   * Filename mask
   * @access private
   * @var string
   */
  private $fname = null;
  /**
   * Current filename
   * @access private
   * @var string
   */
  private $cname = null;
  /**
   * Last logging time
   * @access private
   * @var integer
   */
  private $ltime = null;
  /**
   * Timestamp mask
   * @access private
   * @var string
   */
  private $ts = 'd.m.y H:i:s';
  /**
   * Show miliseconds in log file
   * @access public
   * @var boolean
   */
  private $mili = true;
  
  /**
   * Constructor function.
   * @access public
   * @param string $fname Filename mask
   * @param integer $lvl Emergency level bitmask
   * @param string $ts Timestamp used in log file
   * @return boolean TRUE on success, FALSE on error
   */
  public function __construct($fname,$lvl=127,$ts='d.m.y H:i:s') {
    $this->fname = $fname;
    $this->lvl   = $lvl;
    $this->ts    = $ts;
    return true;
  }
  
  /**
   * Logs message
   * @access public
   * @param string $msg Message to log
   * @param integer $lvl Emergency level bitmask
   * @return boolean TRUE on success, FALSE on error
   */
  public function log($msg,$lvl=L_DEBG) {
    if ($this->ltime!=time()) $this->refresh_name();
    if (!$this->fp) $this->open();
    if ($this->mili) {list($add, $sec) = explode(' ', microtime()); $add = sprintf('.%03d',floor($add*1000));}
      else $add = '';
    return fputs($this->fp,date($this->ts).$add.' <'.$this->get_lvl($lvl).'> '.$msg."\n");
  }
  
  /**
   * Determining log version of emergency level.
   * @access private
   * @param integer $lvl Emergency level
   * @return string Textual representation for log message
   */
  private function get_lvl($lvl) {
    switch($lvl) {
      case 1:   return 'EMERG';
      case 2:   return 'ALERT';
      case 4:   return 'CRITC';
      case 8:   return 'ERROR';
      case 16:  return 'WARNG';
      case 32:  return 'NOTIC';
      case 64:  return 'INFOM';
      case 128: return 'DEBUG';
      default:  return 'UNKNW';
    }
  }
  
  /**
   * Refreshing filename if needed, updating last logging time
   * @access private
   * @return boolean TRUE on success, FALSE on error
   */
  private function refresh_name() {
    $short = Array('%%','%Y','%y','%m','%d','%H','%i','%s');
    $full = explode('|',date('%|Y|y|m|d|H|i|s'));
    $nname = str_replace($short,$full,$this->fname);
    if ($nname != $this->cname) {
      $this->cname = $nname;
      $this->close();
    }
    $this->ltime = time();
    return true;
  }
  
  /**
   * Opens file pointer. Creates directories if needed
   * @access private
   * @return boolean TRUE on success, FALSE on error
   */
  private function open() {
    $path = explode('/',$this->cname);
    $pc = count($path);
    $done = null;
    for ($i=0;$i<$pc-1;$i++) {
      $done .= $path[$i];
      if (!is_dir($done)) @mkdir($done);
      $done .= '/';
    }
    $this->fp = fopen($this->cname,'a');
    return true;
  }
  
  /**
   * Closes file pointer
   * @access public
   * @return boolean TRUE on success, FALSE on error
   */
  public function close() {
    if ($this->fp) {
      fclose($this->fp);
      $this->fp = null;
    }
    return true;
  }
  
  /**
   * Destructor. Closes file pointer
   * @access public
   * @return boolean TRUE on success, FALSE on error
   */
  public function __destruct() {
    return $this->close();
  }
}