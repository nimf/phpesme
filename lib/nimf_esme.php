<?php

/**
 * ESME state: disconnected
 */
define('ESS_DISCONNECTED', 0);
/**
 * ESME state: connected
 */
define('ESS_CONNECTED', 1);
/**
 * ESME state: binded TX
 */
define('ESS_BIND_TX', 2);
/**
 * ESME state: binded RX
 */
define('ESS_BIND_RX', 3);

/**
 * SMPP command: BIND RX
 */
define('BIND_RX',0x00000001);
/**
 * SMPP command: BIND TX
 */
define('BIND_TX',0x00000002);
/**
 * SMPP command: UNBIND
 */
define('UNBIND',0x00000006);
/**
 * SMPP acknowledgement bit. (and GENERIC_NACK also)
 */
define('ACK',0x80000000);
/**
 * SMPP command: SUBMIT SM
 */
define('SUBMIT_SM',0x00000004);
/**
 * SMPP command: DELIVER SM
 */
define('DELIVER_SM',0x00000005);
/**
 * SMPP command: ENQUIRELINK
 */
define('ENQUIRELINK',0x00000015);

/**
 * Nimf's esme class
 * 
 * This file contains class of Nimf's esme.
 * 
 * Requires:
 * nimf_logger     0.0.2+
 * nimf_sockclient 0.0.2+
 * nimf_smppclient 0.0.3+
 * 
 * @package NIMF
 * @subpackage esme
 * @author Nimf <nimfin@gmail.com>
 * @version 0.0.2
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
class NIMF_esme {
  /**
   * SMSC/SMS gateway host
   * @access private
   * @var string
   */
  private $host = 'localhost';
  /**
   * SMSC/SMS gateway port
   * @access private
   * @var int
   */
  private $port = 2775;
  /**
   * Connection socket
   * @access public
   * @var resource
   */
  public $sock = null;
  /**
   * ESME login
   * @access private
   * @var string
   */
  private $login = 'smppclient';
  /**
   * ESME password
   * @access private
   * @var string
   */
  private $pass = 'password';
  /**
   * Sequence number
   * @access public
   * @var int
   */
  public $sqn = 0;
  /**
   * Connection type
   * @access public
   * @var string
   */
  public $dir = 'TX';
  /**
   * ESME state
   * @access public
   * @var int
   */
  public $state = ESS_DISCONNECTED;
  /**
   * ESME System type
   * @access public
   * @var string
   */
  public $system_type = '';
  /**
   * ESME Address range
   * @access public
   * @var string
   */
  public $address_range = '3333';
  /**
   * ESME Address TON
   * @access public
   * @var string
   */
  public $addr_ton = 0;
  /**
   * ESME Address NPI
   * @access public
   * @var string
   */
  public $addr_npi = 1;
  /**
   * Default parameters for SUBMIT_SM. U can redefine
   * stype - service type
   * st    - source number TON
   * sn    - source number NPI
   * src   - source number
   * dt    - destination number TON
   * dn    - destination number NPI
   * proto - protocol id
   * prior - delivery priority
   * sdt   - scheduled delivery time
   * valid - validity period
   * deliv - request delivery report
   * repl  - replace if exists (in store&forward mode)
   * msgid - default message id
   * @access public
   * @var array
   */
  public $defsend = array(
    'stype' => '',
    'st'    => 0,
    'sn'    => 1,
    'src'   => '3333',
    'dt'    => 1,
    'dn'    => 1,
    'proto' => 0,
    'prior' => 0,
    'sdt'   => '',
    'valid' => '',
    'deliv' => 0,
    'repl'  => 0,
    'msgid' => 0
  );
  /**
   * Single-byte coding
   * @access public
   * @var string
   */
  public $sbcoding = 'CP1251';
  /**
   * Multi-byte coding
   * @access public
   * @var string
   */
  public $mbcoding = 'UCS-2BE';
  /**
   * Database coding
   * @access public
   * @var string
   */
  public $dbcoding = 'UTF-8';
  /**
   * Last sent message id
   * @access public
   * @var string
   */
  public $last_msg_id = '';
  /**
   * Last enquire link timestamp
   * @access public
   * @var int
   */
  public $last_enquire = 0;
  
  /**
   * Constructor function. Assigns major settings.
   * @access public
   * @param string $host SMSC/SMS gateway host
   * @param int $port SMSC/SMS gateway port
   * @param string $login ESME login
   * @param string $pass ESME password
   * @return boolean Always TRUE
   */
  public function __construct($host='localhost',$port=2775,$login='smppclient',$pass='password') {
    $this->host = $host;
    $this->port = $port;
    $this->login = $login;
    $this->pass = $pass;
    return true;
  }
  
  /**
   * Connects to SMSC/SMS gateway
   * @access public
   * @return boolean TRUE on success, FALSE on error
   */
  public function connect() {
    if ($this->sock == null) $this->sock = new NIMF_smppclient($this->host,$this->port);
    if (!$this->sock->connect()) return false;
    $this->state = ESS_CONNECTED;
    l('ESME state: ESS_CONNECTED');
    return true;
  }
  
  /**
   * Binds to SMSC/SMS gateway
   * @access public
   * @return boolean TRUE on success, FALSE on error
   */
  public function bind() {
    if ($this->state != ESS_CONNECTED) if (!$this->connect()) return false;
    if ($this->dir == 'TX') $bindcmd = BIND_TX;
    if ($this->dir == 'RX') $bindcmd = BIND_RX;
    $pdu = $this->form_pdu($bindcmd);
    l('ESME sending BIND command...');
    if (!$this->sock->send($pdu)) return false;
    if (false === $pdu = $this->sock->pdu_wait_for($bindcmd|ACK,$this->sqn)) {l('ESME pdu wait failed.',L_WARN); return false;}
    $res = $this->parse_pdu_header(substr($pdu,0,16));
    if ($res['stat'] === 0) {
      if ($this->dir == 'TX') {
        $this->state = ESS_BIND_TX;
        l('ESME state: ESS_BIND_TX');
      } else
      if ($this->dir == 'RX') {
        $this->state = ESS_BIND_RX;
        l('ESME state: ESS_BIND_RX');
      }
      return true;
    } else {
      l('ESME bind failed ('.$res['stat'].'). Exiting...',L_CRIT);
      $this->disconnect();
      sleep(5);
      die();
      return false;
    }
  }
  
  /**
   * Checks if connection is valid
   * @access public
   * @return boolean TRUE on success, FALSE on error
   */
  public function enquirelink() {
    $pdu = $this->form_pdu(ENQUIRELINK);
    l('ESME sending ENQUIRELINK command...');
    if (!$this->sock->send($pdu)) return false;
    if (false === $pdu = $this->sock->pdu_wait_for(ENQUIRELINK|ACK,$this->sqn)) {l('ESME pdu wait failed.',L_WARN); return false;}
    $res = $this->parse_pdu_header(substr($pdu,0,16));
    if ($res['stat'] !== 0) return false;
    return true;
  }
  
  /**
   * Retrieving SMS pdu from socket
   * @access public
   * @return mixed Array on success, FALSE on error
   **/
  public function get_sms() {
    l('Getting sms...');
    foreach($this->sock->pdu_inc as $k=>$pdu) {
      l('Checking incoming array item...');
      $res = $this->parse_pdu_header(substr($pdu,0,16));
      if ($res['stat'] === 0 && $res['cmd'] == DELIVER_SM) {
        l('Found one.');
        unset($this->sock->pdu_inc[$k]);
        return $this->parse_sms($pdu);
      }
    }
    l('Will wait for DELIVER_SM...');
    if (false !== $pdu = $this->sock->pdu_wait_for(DELIVER_SM)) {
      l('Got something');
      $res = $this->parse_pdu_header(substr($pdu,0,16));
      if ($res['stat'] === 0 && $res['cmd'] == DELIVER_SM) {
        l('Yeah, it has right state and command.');
        return $this->parse_sms($pdu);
      }
    }
    return false;
  }
  
  /**
   * Generating and sending acknowledgment about message receiving
   * @access public
   * @param int $sqn Sequence of received message
   * @return boolean TRUE on success, FALSE on error
   **/
  public function reply_sms($sqn) {
    $resp = $this->form_pdu(DELIVER_SM|ACK,array('sqn'=>$sqn));
    l('We formed reply: '.$this->sock->cute_pdu($resp));
    return $this->sock->send($resp);
  }
  
  /**
   * Converts SMS PDU into array
   * @access public
   * @param string $pdu PDU
   * @return array SMS in array format
   **/
  public function parse_sms($pdu) {
    $raw = unpack('C*',substr($pdu,16));
    $out = array('stype'=>$this->till_null($raw,6),
           'st'=>array_shift($raw),
           'sn'=>array_shift($raw),
           'src'=>$this->till_null($raw,21),
           'dt'=>array_shift($raw),
           'dn'=>array_shift($raw),
           'dst'=>$this->till_null($raw,21),
           'esm'=>array_shift($raw),
           'proto'=>array_shift($raw),
           'prio'=>array_shift($raw),
           'sched'=>$this->till_null($raw,17),
           'valid'=>$this->till_null($raw,17),
           'reg'=>array_shift($raw),
           'rep'=>array_shift($raw),
           'dc'=>array_shift($raw),
           'def'=>array_shift($raw),
           'len'=>array_shift($raw)
          );
    $out['msg'] = $this->till_num($raw,$out['len']);
    if ($out['dc'] == 8) $out['msg'] = mb_convert_encoding($out['msg'], $this->dbcoding, $this->mbcoding);
    if ($out['dc'] != 8) $out['msg'] = mb_convert_encoding($out['msg'], $this->dbcoding, $this->sbcoding);
    while(count($raw)) {
      $opt = 256*array_shift($raw) + array_shift($raw);
      switch ($opt) {
        case 0x020C:
          array_shift($raw);array_shift($raw);
          $out['refn'] = 256*array_shift($raw) + array_shift($raw);
        break;
        case 0x020F:
          array_shift($raw);array_shift($raw);
          $out['segn'] = array_shift($raw);
        break;
        case 0x020E:
          array_shift($raw);array_shift($raw);
          $out['tots'] = array_shift($raw);
        break;
        default:
          $skip = 256*array_shift($raw) + array_shift($raw);
          for($i=0;$i<$skip;$i++) array_shift($raw);
      }
    }
    $res = $this->parse_pdu_header(substr($pdu,0,16));
    $out['sqn'] = $res['sqn'];
    return $out;
  }
  
  /**
   * Shifts $num bytes
   * @access public
   * @param string $raw Data
   * @param int $num Bytes count
   * @return string Read string
   **/
  public function till_num(&$raw,$num=0) {
    $str = '';
    $i = 0;
    while ($i<$num) {
      $c = array_shift($raw);
      $str .= chr($c);
      $i++;
    };
    return $str;
  }
  
  /**
   * Shifts bytes until null is fetched or until max length
   * @access public
   * @param string $raw Data
   * @param int $len Max length
   * @return string Read string
   **/
  public function till_null(&$raw,$len=255) {
    $str = '';
    $i = 0;
    do {
      $c = array_shift($raw);
      if ($c!=0) $str .= chr($c);
      $i++;
    } while($i<$len && $c != 0);
    return $str;
  }
  
  /**
   * Sends an SMS. Converts message to single-byte coding if possible. Separates into several parts if needed.
   * Array $msg should contain:
   * dst - destination number
   * text - message text
   * u can also add anything from default parameters for SUBMIT_SM
   * @access public
   * @param array $msg Message with properties
   * @return boolean TRUE on success, FALSE on error
   */
  public function send_sms($msg) {
    if ($this->state != ESS_BIND_TX && $this->state != ESS_BIND_RX) {
      if (!$this->bind()) return false;
    }
    $test = mb_convert_encoding($msg['text'], $this->sbcoding, $this->mbcoding);
    if (preg_match('/[^a-z0-9@\$\^\{\}\\\~\[\] \!\"\'\#\%\&\`\(\)\*\+\,\-\.\/\:\;\<\=\>\?\_\r\n]/i',$test,$match)) {
      $uni = true;
    } else {
      $uni = false;
      $msg['text'] = mb_convert_encoding($msg['text'], $this->sbcoding, $this->mbcoding);
    }
    $prts = $this->split_text($msg['text'],$uni);
    $pc = count($prts);
    l('Parts: '.$pc.'. Uni: '.$uni.' Dest: '.$msg['dst']);
    if ($pc>5) return false;
    $tmp = array();
    foreach($this->defsend as $k=>$v) if (isset($msg[$k])) $tmp[$k] = $msg[$k]; else $tmp[$k] = $v;
    $tmp['dst'] = $msg['dst'];
    if ($pc>1) $tmp['esm'] = 3+0x00000040; else $tmp['esm'] = 3;
    if ($uni) $tmp['dc'] = 8; else $tmp['dc'] = 241;
    foreach($prts as $v) {
      $tmp['text'] = $v;
      $pdu = $this->form_pdu(SUBMIT_SM,$tmp);
      if (!$this->sock->send($pdu)) {
        l('Couldn\'t send pdu',L_WARN);
        $this->state = ESS_DISCONNECTED;
        $this->sock->disconnect();
        return false;
      }
      if (false === $pdu = $this->sock->pdu_wait_for(SUBMIT_SM|ACK,$this->sqn)) {
        l('SUBMIT_SM_ACK pdu wait failed - disconnecting.',L_WARN);
        $this->state = ESS_DISCONNECTED;
        $this->sock->disconnect();
        return false;
      }
      $res = $this->parse_pdu_header(substr($pdu,0,16));
      $pdubody = substr($pdu,16);
      if ($res['stat'] !== 0) {
        l('ESME ack failed ('.$res['stat'].')',L_WARN);
        if ($res['stat'] == 1113) {
          l('We should reconnect after this error. Disconnecting...',L_WARN);
          $this->state = ESS_DISCONNECTED;
          $this->sock->disconnect();
        }
        return false;
      }
    }
    l('All sent ok.');
    $this->last_msg_id = substr($pdubody,0,-1);
    l('Message id is "'.$this->last_msg_id.'"');
    return true;
  }
  
  /**
   * Makes multipart message contents.
   * @access public
   * @param string $text Message text
   * @param boolean $uni Is message encoded in UCS-2BE?
   * @return array Array of message parts
   */
  public function split_text($text,$uni=false) {
    $out = array();
    if ( (!$uni && strlen($text) <= 160) || ($uni && mb_strlen($text) <= 140) ) {
      $out []= $text;
      $this->sqn++;
      return $out;
    }
    if ($uni) {
      $parlen = 134;
      $txtlen = strlen($text);
    } else {
      $parlen = 153;
      $txtlen = strlen($text);
    }
    
    $sqn = ++$this->sqn;
    $prts = ceil($txtlen/$parlen);
    for($i=0;$i<$prts;$i++) {
      $udh = pack("cccccc", 5, 0, 3, $sqn, $prts, ($i+1));
      if ($uni) $out []= $udh.mb_substr($text,$i*$parlen,$parlen);
        else $out []= $udh.substr($text,$i*$parlen,$parlen);
    }
    return $out;
  }
  
  /**
   * Parses PDU header.
   * @access public
   * @param string $header PDU header
   * @return array Array of length, command id, status and sequence no
   */
  public function parse_pdu_header($header) {
    if (strlen($header) != 16) return false;
    return $this->sock->sunpack('Nlen/Ncmd/Nstat/Nsqn',$header);
  } 
  
  /**
   * Forms PDU.
   * @access public
   * @param int $cmd Command id
   * @param array $pars Parameters
   * @return string PDU
   */
  public function form_pdu($cmd,$pars=array()) {
    $pdu = null;
    switch($cmd) {
      case BIND_TX:
      case BIND_RX:
        $pdu = pack(
          'a'.(strlen($this->login)+1).
          'a'.(strlen($this->pass)+1).
          'a'.(strlen($this->system_type)+1).
          'CCCa'.(strlen($this->address_range)+1),
          $this->login, $this->pass, $this->system_type,
          0x34, $this->addr_ton,
          $this->addr_npi, $this->address_range
        );
        $this->sqn++;
      break;
      case SUBMIT_SM:
        $pdu = pack(
          'a'.(strlen($pars['stype'])+1).
          'CCa'.(strlen($pars['src'])+1).
          'CCa'.(strlen($pars['dst'])+1).
          'CCCa'.(strlen($pars['sdt'])+1).
          'a'.(strlen($pars['valid'])+1).
          'CCCCC',
          $pars['stype'],
          $pars['st'],$pars['sn'],$pars['src'],
          $pars['dt'],$pars['dn'],$pars['dst'],
          $pars['esm'],$pars['proto'],$pars['prior'],$pars['sdt'],
          $pars['valid'],
          $pars['deliv'],$pars['repl'],$pars['dc'],$pars['msgid'],strlen($pars['text'])).$pars['text'];
      break;
      case ENQUIRELINK:
      case UNBIND:
        $this->sqn++;
      break;
      case DELIVER_SM|ACK:
        $pdu = pack('a2','0');
      break;
    }
    if (isset($pars['sqn'])) $sqn = $pars['sqn']; else $sqn = $this->sqn;
    return NIMF_esme::form_pdu_header($cmd,strlen($pdu),$sqn).$pdu;
  }
  
  /**
   * Forms PDU header.
   * @access public
   * @param int $cmd Command id
   * @param int $pdulen Length of PDU body
   * @param int $sqn Sequence no
   * @return string PDU header
   */
  public function form_pdu_header($cmd,$pdulen,$sqn) {
    $stat = 0;
    return pack('NNNN',$pdulen+16,$cmd,$stat,$sqn);
  }
  
  /**
   * Desconnects from SMSC/SMS gateway gracefully
   * @access public
   * @return boolean Always TRUE
   */
  public function disconnect() {
    if ($this->state == ESS_BIND_TX || $this->state == ESS_BIND_RX) {
      l('ESME sending UNBIND command...');
      $this->sock->send($this->form_pdu(UNBIND));
      $pdu = $this->sock->pdu_wait_for(UNBIND|ACK,$this->sqn);
      $res = $this->parse_pdu_header(substr($pdu,0,16));
      if ($res['stat'] !== 0) l('UNBIND failed: '.$res['stat'].'.',L_WARN);
        else l('UNBIND done.');
    }
    $this->state = ESS_DISCONNECTED;
    $this->sock->disconnect();
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