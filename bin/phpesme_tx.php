#!/usr/bin/php -q
<?php

/*
 * Copyright 2008 Nimf
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
 * 
 */

/**
 * This script should always run. It checks db for outgoing messages and send them to SMSC/SMPP gateway.
 */

error_reporting(E_ALL);

define('MYDIR',substr(dirname(__FILE__),0,-4));

$conf = parse_ini_file(MYDIR.'/conf/phpesme.conf', true);

require(MYDIR.'/lib/nimf_logger.php');
require(MYDIR.'/lib/nimf_sockclient.php');
require(MYDIR.'/lib/nimf_smppclient.php');
require(MYDIR.'/lib/nimf_mysqli.php');
require(MYDIR.'/lib/nimf_esme.php');

$logger = new NIMF_logger(MYDIR.'/logs/'.$conf['LOGS']['tx_logname'],L_ALL);
function l($msg,$lvl=L_DEBG) {global $logger; if (isset($logger)) $logger->log($msg,$lvl);}

l('phpESME transmitter has started.');

declare(ticks = 1);
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGHUP, "sig_handler");
pcntl_signal(SIGINT, "sig_handler");
pcntl_signal(SIGQUIT, "sig_handler");
pcntl_signal(SIGABRT, "sig_handler");


while (true) {
  
  // check for db availability
  if (!isset($db)) {
    $db = new NIMF_mysqli($conf['DB']['host'], $conf['DB']['login'], $conf['DB']['pass'], $conf['DB']['db']);
    if (mysqli_connect_errno()) {
      l('Could not connect to DB: '.mysqli_connect_error(),L_EMRG);
      unset($db);
      sleep(5);
      continue;
    } else {
      l('Connected to DB successfully: '.$db->host_info);
      $db->put('SET NAMES "'.$conf['DB']['charset'].'"');
      $db->autocommit(false);
    }
  }
  
  // checking for outgoing messages
  
  $db->commit(); // <-- This is required to see non cached outbox result
  if (false === $send = $db->get_array('SELECT * FROM outbox WHERE (src IN ('.$conf['SMPP']['nums'].') OR src LIKE \'77%\') AND try_ts<'.(time()-3600).' ORDER BY id LIMIT '.$conf['ESME']['txlimit'])) {
    l('Could not read from db',L_CRIT);
    unset($db); continue;
  }
  
  if (isset($send['id'])) {
    
    // check for SMPP connect
    if (!isset($esme)) {
      $esme = new NIMF_esme($conf['SMPP']['host'],$conf['SMPP']['port'],$conf['SMPP']['login'],$conf['SMPP']['pass']);
      $esme->dir = 'TX';
      $esme->address_range = $conf['SMPP']['addrange'];
      $esme->addr_ton = $conf['SMPP']['at'];
      $esme->addr_npi = $conf['SMPP']['an'];
      $esme->dbcoding = $conf['ESME']['dbcoding'];
      $esme->sbcoding = $conf['ESME']['sbcoding'];
      $esme->mbcoding = $conf['ESME']['mbcoding'];
    }
    
    if ($esme->state > ESS_CONNECTED && $esme->last_enquire < (time()-$conf['ESME']['enquire'])) {
      if (!$esme->enquirelink()) {
        $esme->sock->disconnect();
        $esme->state = ESS_DISCONNECTED;
      } else {
        $esme->last_enquire = time();
      }
    }
    
    if ($esme->state <= ESS_CONNECTED) {
      if ($esme->bind()) l('SMPP bind successfull.');
        else {
          l('ESME bind failed. Exiting...',L_CRIT);
          sleep(5);
          die();
        }
    }
    
    // sending messages
    
    foreach($send['id'] as $k=>$v) {
      l('Sending '.$v.'...',L_INFO);
      $src = explode('.',$send['src'][$k]);
      $dst = explode('.',$send['dst'][$k]);
      if ($esme->send_sms(
        array(
          'st' => $src[1],
          'sn' => $src[2],
          'src' => $src[0],
          'dt' => $dst[1],
          'dn' => $dst[2],
          'dst' => $dst[0],
          'text' => mb_convert_encoding($send['msg'][$k], $conf['ESME']['mbcoding'], $conf['ESME']['dbcoding']),
          'deliv' => $send['rd'][$k]
        )
      )) {
        // sent ok
        if ($db->put('REPLACE INTO sent(id,rel_id,src,dst,msg,ts,rd,snt_ts,msg_id) SELECT id,rel_id,src,dst,msg,ts,rd,'.time().' AS snt_ts,"'.$esme->last_msg_id.'" AS msg_id FROM outbox WHERE id='.$v) &&
        $db->put('DELETE FROM outbox WHERE id='.$v) && $db->commit()) {
          // Save ok
        } else {
          l('Could not write to db',L_CRIT);
          unset($db); break;
        }
      } else {
        // sending failed - try next message
        if (!($db->put('UPDATE outbox SET try_ts='.(time()).' WHERE id='.$v) && $db->commit())) {
          l('Could not write to db',L_CRIT);
          unset($db); break;
        }
        if ($send['ts'][$k] < (time()-86400)) {
          l('Too old. Moving...',L_INFO);
          if ($db->put('REPLACE INTO sent(id,rel_id,src,dst,msg,ts,rd,snt_ts,msg_id,dl_ts,dl_stat) SELECT id,rel_id,src,dst,msg,ts,rd,'.time().' AS snt_ts,"" AS msg_id, '.time().' AS dl_ts, 2 AS dl_stat FROM outbox WHERE id='.$v) &&
          $db->put('DELETE FROM outbox WHERE id='.$v) && $db->commit()) {
            // Save ok
          } else {
            l('Could not write to db',L_CRIT);
            unset($db); break;
          }
        }
      }
    }
  } else {
    if (isset($esme) && $esme->state == ESS_BIND_TX) {
      $esme->sock->pdu_wait_for(UNBIND,null,1);
    }
  }
  
  sleep(1);
} // infinite loop

function sig_handler($signo) {
  global $esme;
  global $db;
  switch ($signo) {
    case SIGTERM:
    case SIGHUP:
    case SIGINT:
    case SIGQUIT:
    case SIGABRT:
      if (isset($db)) $db->close();
      if (isset($esme)) $esme->sock->disconnect();
      l('phpESME receiver has quit.');
      die();
    break;
    default:
     return true;
  }
}
