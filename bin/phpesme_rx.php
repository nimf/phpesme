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
 * This script should always run. It receives messages from SMSC/SMPP gateway and puts them into db.
 */

//error_reporting(E_NONE);

define('MYDIR',substr(dirname(__FILE__),0,-4));

$conf = parse_ini_file(MYDIR.'/conf/phpesme.conf', true);

require(MYDIR.'/lib/nimf_logger.php');
require(MYDIR.'/lib/nimf_sockclient.php');
require(MYDIR.'/lib/nimf_smppclient.php');
require(MYDIR.'/lib/nimf_mysqli.php');
require(MYDIR.'/lib/nimf_esme.php');

$logger = new NIMF_logger(MYDIR.'/logs/'.$conf['LOGS']['rx_logname'],L_ALL);
function l($msg,$lvl=L_DEBG) {global $logger; if (isset($logger)) $logger->log($msg,$lvl);}

l('phpESME receiver has started.');

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
      $db->put('SET SESSION query_cache_type = OFF');
      $db->autocommit(false);
    }
  }
  
  // check for SMPP connect
  if (!isset($esme)) {
    $esme = new NIMF_esme($conf['SMPP']['host'],$conf['SMPP']['port'],$conf['SMPP']['login'],$conf['SMPP']['pass']);
    $esme->dir = 'RX';
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
  
  // checking for incoming messages
  
  if (false !== $sms = $esme->get_sms()) {
    l('Got SMS: '.print_r($sms,true));
    
    // Check if it's a delivery report
    if ($sms['esm'] == 4) {
      $mt = 1;
    } else {
      $mt = 0;
    }
    
    // Check if it's a multipart message
    if ($sms['segn'] > 0 && $sms['tots'] > 0) {
      // Saving part
      if ($db->put('INSERT INTO parts(src,dst,msg,ts,ref,pn,tp) VALUES("'.$sms['src'].'.'.$sms['st'].'.'.$sms['sn'].'","'.$sms['dst'].'.'.$sms['dt'].'.'.$sms['dn'].'","'.$db->real_escape_string($sms['msg']).'",'.time().','.$sms['refn'].','.$sms['segn'].','.$sms['tots'].')') && $db->commit()) {
        $esme->reply_sms($sms['sqn']);
      } else {
        l('Could not write to db',L_CRIT);
        unset($db); continue;
      }
    } else {
      // Saving message
      if ($db->put('INSERT INTO inbox(src,dst,msg,ts,mt) VALUES("'.$sms['src'].'.'.$sms['st'].'.'.$sms['sn'].'","'.$sms['dst'].'.'.$sms['dt'].'.'.$sms['dn'].'","'.$db->real_escape_string($sms['msg']).'",'.time().','.$mt.')') && $db->commit()) {
        $esme->reply_sms($sms['sqn']);
      } else {
        l('Could not write to db',L_CRIT);
        unset($db); continue;
      }
    }
    
  } else {
    l('No SMS in last 60 seconds or error.');
    $esme->last_enquire = 0;
  }
  
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
