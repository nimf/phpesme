<?php

 /**
 * Nimf's smpp client
 * 
 * This file contains class of Nimf's smpp client.
 * 
 * Requires:
 * nimf_logger     0.0.2+
 * nimf_sockclient 0.0.2+
 * 
 * @package NIMF
 * @subpackage smppclient
 * @author Nimf <nimfin@gmail.com>
 * @version 0.0.4
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

class NIMF_smppclient extends NIMF_sockclient {
  /**
   * Array of received PDUs
   * @access public
   * @var array
   */
  public $pdu_inc = array();
  /**
   * Buffer for incoming data
   * @access public
   * @var string
   */
  public $pdu_ib   = null;
  
  /**
   * Waits for particular PDU (with known command id and sequence no) for some period of time ($to).
   * All unmatched PDUs go to $this->pdu_inc array
   * @access public
   * @param int $cmd Command id
   * @param int $sqn Sequence no
   * @param int $to Timeout (miliseconds)
   * @return mixed PDU on success, FALSE on error
   */
  public function pdu_wait_for($cmd,$sqn=null,$to=60000) {
    if ($this->state != CSS_CONNECTED) {return false;}
    l('Will wait for '.$this->translate_cmd($cmd).' with sqn no '.$sqn);
    list($usec, $sec) = explode(' ', microtime());
    $now = $start = $sec + $usec;
    while($now-$start < ($to/1000)) {
      if (false !== $pdu = $this->pdu_read()) {
        $hdr = $this->sunpack('Nlen/Ncmd/Nstat/Nsqn',substr($pdu,0,16));
        l('Got '.$this->translate_cmd($hdr['cmd']).' ('.$hdr['cmd'].') with sqn no '.$hdr['sqn']);
        if ( $hdr['cmd'] == $cmd && ($hdr['sqn'] == $sqn || $sqn == null) ) {
          l('Thats it. Returned.');
          return $pdu;
        } elseif ($hdr['cmd'] == 0x00000015) {
          // Got ENQUIRELINK - replying
          if (!$this->send(NIMF_esme::form_pdu(ENQUIRELINK|ACK,array('sqn'=>$hdr['sqn'])))) {
            l('Could not send ENQUIRELINK_ACK');
          }
        } else $this->pdu_inc []= $pdu;
      } else {
        if ($this->state != CSS_CONNECTED) {
          l('Disconnection happened.');
          return false;
        }
      }
      list($usec, $sec) = explode(' ', microtime());
      $now = $sec + $usec;
    }
    l('PDU we were waiting for didn\'t arrive.');
    return false;
  }
  
  /**
   * Tries to read PDU completly in $timeout miliseconds
   * @access public
   * @param int $timeout Timeout (miliseconds)
   * @return mixed PDU on success, FALSE on error
   */
  public function pdu_read($timeout=5000){
    // first we should check in buffer
    if (strlen($this->pdu_ib)>=16) {
        $hdr = $this->sunpack('Nlen',substr($this->pdu_ib,0,4));
        if (strlen($this->pdu_ib) >= $hdr['len']) {
          if ($this->v) l('Read PDU:'."\n".$this->cute_pdu(substr($this->pdu_ib,0,$hdr['len'])));
          $read = substr($this->pdu_ib,0,$hdr['len']);
          $this->pdu_ib = substr($this->pdu_ib,$hdr['len']);
          return $read;
        }
      }
    // if nothing complete - reading
    list($usec, $sec) = explode(' ', microtime());
    $now = $start = $sec + $usec;
    while ($this->select($timeout)) {
      if (false === $buf = $this->read(1024000)) {l('Could not read from socket - disconnected.',L_WARN); $this->state = CSS_CREATED; return false;}
      $this->pdu_ib .= $buf;
      if (strlen($this->pdu_ib)>=16) {
        $hdr = $this->sunpack('Nlen',substr($this->pdu_ib,0,4));
        if (strlen($this->pdu_ib) >= $hdr['len']) {
          if ($this->v) l('Read PDU:'."\n".$this->cute_pdu(substr($this->pdu_ib,0,$hdr['len'])));
          $read = substr($this->pdu_ib,0,$hdr['len']);
          $this->pdu_ib = substr($this->pdu_ib,$hdr['len']);
          return $read;
        }
      }
      list($usec, $sec) = explode(' ', microtime());
      $now = $sec + $usec;
      if ($now-$start > ($timeout/1000)) break;
    }
    if (strlen($this->pdu_ib)) l('Could not get complete PDU in '.($timeout/1000).' second(s). Remains('.$this->pdu_ib.')',L_WARN);
      else l('There were no incoming data for '.($timeout/1000).' second(s)');
    return false;
  }
  
  /**
   * For cute logging. Formats PDU gracefully.
   * @access public
   * @param string $pdu PDU
   * @return string PDU formatted for print out
   */
  public function cute_pdu($pdu) {
    $hdr = substr($pdu,0,16);
    $res = $this->sunpack('Nlen/Ncmd/Nstat/Nsqn',$hdr);
    $out = 'Header: '.$this->format_hex($hdr,4,16)."\n".'Length: '.$res['len'].
' Command: '.$this->translate_cmd($res['cmd']).' Status: '.$res['stat'].' Sequence:'.$res['sqn'];
    if ($res['len']>16) $out .= ' Body:'."\n".$this->format_hex(substr($pdu,16),1,16,' ')."\n".substr($pdu,16);
    return $out;
  }
  
  /**
   * For cute logging. Formats sequence of bytes.
   * @access public
   * @param string $num Bytes sequence
   * @param int $grp Group by (count)
   * @param int $lb Bytes per line
   * @param string $sep Group separator
   * @return string Formatted string
   */
  public function format_hex($num,$grp=1,$lb=16,$sep=':') {
    $ar = unpack("C*",$num);
    $out = '';
    foreach($ar as $k=>$v){
      $out .= sprintf('%02X',$v);
      if (($k)%$lb == 0) {$out .= "\n"; continue;}
      if (($k)%$grp == 0) $out .= $sep;
    }
    if (substr($out,-1) == $sep) return substr($out,0,-1);
      else if (substr($out,-1) == "\n") return substr($out,0,-1);
        else return $out;
  }
  
  /**
   * For cute logging. Gets text representation of a command id.
   * @access public
   * @param int $cmd Command id
   * @return string Text representation
   */
  public function translate_cmd($cmd) {
    $out = '';
    if (hexdec($cmd) >= 0x80000000) {
      if ($cmd == 0x80000000) return 'GENERIC_NACK';
        else $out = '_ACK';
      $cmd ^= 0x80000000;
    }
    switch($cmd) {
      case 0x00000001: return 'BIND_RX'.$out;
      case 0x00000002: return 'BIND_TX'.$out;
      case 0x00000004: return 'SUBMIT_SM'.$out;
      case 0x00000005: return 'DELIVER_SM'.$out;
      case 0x00000006: return 'UNBIND'.$out;
      case 0x00000015: return 'ENQUIRELINK'.$out;
    }
  }
  
  /**
   * For safe unpacking (restoring longs from negative)
   * @access public
   * @param string $pattern Pattern
   * @param string $what What to unpack
   * @return array Unpacked values
   */
  public function sunpack($pattern,$what) {
    $res = unpack($pattern,$what);
    // This code is needed on some systems, for example Solaris
    //foreach($res as $k=>$v) {
    //  if ($v<0) $res[$k] = $v + 4294967296;
    //}
    return $res;
  }
}