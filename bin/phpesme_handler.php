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
 * This script should always run. It handles multipart messages, delivery reports,
 * performs garbage collection.
 */

define('DELIVRD',1);
define('EXPIRED',2);
define('DELETED',3);
define('UNDELIV',4);
define('ACCEPTD',5);
define('UNKNOWN',6);
define('REJECTD',7);

error_reporting(E_NONE);

define('MYDIR',substr(dirname(__FILE__),0,-4));

$conf = parse_ini_file(MYDIR.'/conf/phpesme.conf', true);

require(MYDIR.'/lib/nimf_logger.php');
require(MYDIR.'/lib/nimf_mysqli.php');

$logger = new NIMF_logger(MYDIR.'/logs/'.$conf['LOGS']['hdlr_logname'],L_ALL^L_DEBG);
function l($msg,$lvl=L_DEBG) {global $logger; if (isset($logger)) $logger->log($msg,$lvl);}

l('phpESME handler has started.', L_INFO);

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
      l('Connected to DB successfully: '.$db->host_info, L_INFO);
      $db->put('SET NAMES "'.$conf['DB']['charset'].'"');
      $db->put('SET SESSION query_cache_type = OFF');
      $db->autocommit(false);
    }
  }
  
  // Combining multipart messages
  $db->commit();
  $msg = $db->get_array('SELECT ref,src,dst,tp,max(ts) ts,count(*) c FROM parts GROUP BY ref,src,dst,tp HAVING c=tp');
  $db->commit();
  
  if (isset($msg['ref'])) {
    
    foreach($msg['ref'] as $k=>$ref) {
      $prts = $db->get_array('SELECT msg FROM parts WHERE ref='.$ref.' ORDER BY pn');
      $db->commit();
      if (count($prts['msg'])) {
        if ($db->put('INSERT INTO inbox(src,dst,msg,ts) VALUES("'.$msg['src'][$k].'","'.$msg['dst'][$k].'","'.$db->real_escape_string(implode('',$prts['msg'])).'",'.$msg['ts'][$k].')')) {
          $db->put('DELETE FROM parts WHERE ref='.$ref);
          $db->commit();
        } else {
          l('Could not write to db: '.$db->errno.' '.$db->error,L_CRIT);
          $db->rollback();
          unset($db); break;
        }
      }
    }
    
  }
  
  // Processing delivery reports
  
  $db->commit();
  $msg = $db->get_array('SELECT id,src,dst,msg,ts FROM inbox WHERE mt=1');
  $db->commit();
  
  if (isset($msg['id'])) {
    
    foreach ($msg['id'] as $k=>$v) {
      $r_id = 0;
      if (preg_match('/^id:([0-9-]+)\s/',$msg['msg'][$k],$match)) {
        $id = $match[1];
        l('SELECT id FROM sent WHERE msg_id="'.$id.'"',L_DEBG);
        $exi = $db->get_one('SELECT id FROM sent WHERE msg_id="'.$id.'"');
        $db->commit();
        if (isset($exi['id'])) {
          l('Got: "'.$exi['id'].'',L_DEBG);
          if (preg_match('/\sstat:([A-Z]+)\s/',$msg['msg'][$k],$match)) $stat = $match[1]; else $stat = 0;
          switch($stat) {
            case 'DELIVRD': $stat = DELIVRD; break;
            case 'EXPIRED': $stat = EXPIRED; break;
            case 'DELETED': $stat = DELETED; break;
            case 'UNDELIV': $stat = UNDELIV; break;
            case 'ACCEPTD': $stat = ACCEPTD; break;
            case 'UNKNOWN': $stat = UNKNOWN; break;
            case 'REJECTD': $stat = REJECTD; break;
            default: $stat = 0;
          }
          l('UPDATE sent SET dl_ts='.$msg['ts'][$k].', dl_stat='.$stat.' WHERE id='.$exi['id'],L_DEBG);
          $db->put('UPDATE sent SET dl_ts='.$msg['ts'][$k].', dl_stat='.$stat.' WHERE id='.$exi['id']);
          $r_id = $exi['id'];
        }
      }
      // put into archive
      l('REPLACE INTO archive(id,src,dst,msg,ts,mt,resp_id) VALUES('.$msg['id'][$k].',"'.$msg['src'][$k].'","'.$msg['dst'][$k].'","'.$db->real_escape_string($msg['msg'][$k]).'",'.$msg['ts'][$k].',1,'.$r_id.')',L_DEBG);
      if ($db->put('REPLACE INTO archive(id,src,dst,msg,ts,mt,resp_id) VALUES('.$msg['id'][$k].',"'.$msg['src'][$k].'","'.$msg['dst'][$k].'","'.$db->real_escape_string($msg['msg'][$k]).'",'.$msg['ts'][$k].',1,'.$r_id.')')) {
        l('DELETE FROM inbox WHERE id='.$msg['id'][$k],L_DEBG);
        $db->put('DELETE FROM inbox WHERE id='.$msg['id'][$k]);
        if (!$db->commit()) {
          l('Could not write to db on delete: '.$db->errno.' '.$db->error,L_CRIT);
          if ($db->errno == 1205) {
            l('Trying next...',L_INFO);
            $db->rollback();
            continue;
          }
          unset($db); break;
        }
      } else {
        l('Could not write to db on replace: '.$db->errno.' '.$db->error,L_CRIT);
        if ($db->errno == 1205) {
          l('Trying next...',L_INFO);
          $db->rollback();
          continue;
        }
        unset($db); break;
      }
    }
    
  }
  
  // Collecting garbage
  $db->put('DELETE FROM parts WHERE ts<'.(time()-86400));
  $db->commit();
  
  // Making stats
  $stts = $db->get_one('SELECT max(ts) ts FROM stats');
  $stts = $stts['ts']; if ($stts<1) $stts = 60*floor((time()-3660)/60);
  
  if ($stts<(time()-120)) {
    $time = time();
    for($i=$stts+60;$i<($time-60);$i+=60) {
      $rec = $db->get_one('SELECT count(*) cnt FROM archive WHERE mt=0 AND proc_ts BETWEEN '.$i.' AND '.($i+59));
      $snt = $db->get_one('SELECT count(*) cnt FROM sent WHERE snt_ts BETWEEN '.$i.' AND '.($i+59));
      $db->put('INSERT INTO stats(ts,rec,sent) VALUES('.$i.','.$rec['cnt'].','.$snt['cnt'].')');
    }
    $db->commit();
  }
  
  sleep(1);
} // infinite loop

function sig_handler($signo) {
  global $db;
  switch ($signo) {
    case SIGTERM:
    case SIGHUP:
    case SIGINT:
    case SIGQUIT:
    case SIGABRT:
      if (isset($db)) $db->close();
      l('phpESME handler has quit.');
      die();
    break;
    default:
    return true;
  }
}
