<?php

/**
 * Nimf's socket client
 * 
 * This file contains class of Nimf's socket client.
 * 
 * Requires:
 * nimf_logger     0.0.2+
 * 
 * @package NIMF
 * @subpackage sockclient 
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
 * Client socket state: not exsists
 */
define('CSS_NOTEXISTS', 0);
/**
 * Client socket state: created
 */
define('CSS_CREATED', 1);
/**
 * Client socket state: connected
 */
define('CSS_CONNECTED', 2);

/**
 * Nimf's socket client class
 * 
 * @package NIMF
 * @subpackage sockclient
 * @author Nimf <nimfin@gmail.com>
 */
class NIMF_sockclient {
  /**
   * Host to connect to
   * @access private
   * @var string
   */
  private $host = null;
  /**
   * Port for connection
   * @access private
   * @var int
   */
  private $port = null;
  /**
   * Socket handler
   * @access public
   * @var resource
   */
  public $sock = null;
  /**
   * State of the socket
   * @access public
   * @var string
   */
  public $state = CSS_NOTEXISTS;
  /**
   * Verbose logging
   * @access public
   * @var boolean
   */
  public $v = false;
  
  /**
   * Constructor function. Sets properties for class.
   * @access public
   * @param string $host Host to connect to
   * @param int $port Port to connect to
   * @return boolean Always TRUE
   */
  public function __construct($host,$port) {
    $this->host = $host;
    $this->port = $port;
    return true;
  }

  /**
   * Establishing connection
   * @access public
   * @return boolean TRUE on success, FALSE on error
   */
  public function connect() {
    if ($this->state != CSS_CREATED) if (!$this->create()) return false;
    l('Trying to connect to '.$this->host.':'.$this->port.'...');
    if (false === socket_connect($this->sock, $this->host, $this->port)) {
      l('Failed to connect to '.$this->host.':'.$this->port.'. Error #'.socket_last_error($this->sock).': '.trim(socket_strerror(socket_last_error($this->sock))),L_WARN);
      $this->disconnect();
      return false;
    } else {
      l('Connected successfully.');
      $this->state = CSS_CONNECTED;
      return true;
    }
  }
  
  /**
   * Creates a socket for connection
   * @access public
   * @param int $domain Domain
   * @param int $type Type
   * @param int $protocol Protocol
   * @return boolean TRUE on success, FALSE on error
   */
  public function create($domain=AF_INET,$type=SOCK_STREAM,$protocol=SOL_TCP) {
    if ($this->state == CSS_CREATED || $this->state == CSS_CONNECTED) {
      $this->disconnect();
    }
    l('Trying to create a socket (dom='.$domain.',type='.$type.',proto='.$protocol.')...');
    if (false === $this->sock = socket_create($domain,$type,$protocol)) {
      l('Socket creation failed.',L_WARN);
      $this->state = CSS_NOTEXISTS;
      return false;
    } else {
      l('Socket created successfully.');
      $this->state = CSS_CREATED;
      return true;
    }
  }
  
  /**
   * Automatic connection function.
   * If it is failed to connect reconnection will be attempted in specified interval.
   * Each time interval is raising by 1.5
   * Will not return untill connection succeeded.
   * @access public
   * @param int $to Timeout in seconds (interval)
   * @return boolean Always TRUE
   */
  public function connect_anyway($to=5) {
    while(!$this->connect()) {sleep($to); $to*=1.5;}
    return true;
  }
  
  /**
   * Sends data to a socket
   * @access public
   * @param string $data Data to send
   * @return boolean TRUE on success, FALSE on error
   */
  public function send($data) {
    $len = strlen($data);
    if ($this->v) l('Sending '.$len.' byte(s): '.$data);
    $sent = socket_write($this->sock, $data, $len);
    return ($sent === $len);
  }
  
  /**
   * Reads data from a socket
   * @access public
   * @param int $size Limit in bytes
   * @return mixed String read from socket or FALSE on error
   */
  public function read($size=1024) {
    return socket_read($this->sock,$size,PHP_BINARY_READ);
  }
  
  /**
   * Performs select on socket for reading.
   * @access public
   * @param int $timeout Timeout in miliseconds
   * @return boolean TRUE if socket is ready for reading, FALSE if it's not
   */
  public function select($timeout=1000) {
    $arr = array($this->sock);
    $narr = null;
    if (socket_select($arr,$narr,$narr,floor($timeout/1000),$timeout%1000)) return true;
    return false;
  }

  /**
   * Closes socket
   * @access public
   * @return boolean Always TRUE
   */
  public function disconnect() {
    if ($this->sock) {
      l('Disconnecting from socket '.$this->host.':'.$this->port.'...');
      socket_close($this->sock);
      $this->sock = null;
      $this->state = CSS_NOTEXISTS;
      l('Disconnected.');
    }
    return true;
  }
  
  /**
   * Destructor
   * @access public
   * @return boolean Always TRUE
   */
  public function __destruct() {
    return $this->disconnect();
  }
}