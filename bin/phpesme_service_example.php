#!/usr/bin/php -q
<?php

$conn = null;

define('MYDIR',substr(dirname(__FILE__),0,-4));

$conf = parse_ini_file(MYDIR.'/conf/phpesme.conf', true);

require(MYDIR.'/lib/nimf_mysqli.php');

mb_internal_encoding("UTF-8");

declare(ticks = 1);
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGHUP, "sig_handler");
pcntl_signal(SIGINT, "sig_handler");
pcntl_signal(SIGQUIT, "sig_handler");
pcntl_signal(SIGABRT, "sig_handler");

while (true) {
  
  if (!isset($db)) {
    $db = new NIMF_mysqli($conf['DB']['host'], $conf['DB']['login'], $conf['DB']['pass'], $conf['DB']['db']);
    if (mysqli_connect_errno()) {
      unset($db);
      sleep(5);
      continue;
    } else {
      $db->put('SET NAMES "'.$conf['DB']['charset'].'"');
      $db->put('SET SESSION query_cache_type = OFF');
      $db->autocommit(false);
    }
  }
  
  // Get messages
  $db->commit(); // <-- this is required for getting non-cached result
  $in = $db->get_array('SELECT id,src,dst,msg,ts FROM inbox WHERE mt=0 AND dst LIKE "3333.%"');
  
  if (isset($in['id'])) {
    
    // Process messages
    foreach ($in['id'] as $k=>$inbox_msg_id) {
      
      $reply = strrev(trim($in['msg'][$k])); // will reply with original text reversed
      
      $time = time();
      // creating message in outbox
      $db->put('INSERT INTO outbox(rel_id,src,dst,msg,ts,rd) VALUES('.$inbox_msg_id.',"'.$in['dst'][$k].'","'.$in['src'][$k].'","'.$db->real_escape_string($reply).'",'.$time.',0)');
      // putting original message in archive, providing sent message id as resp_id
      $db->put('INSERT INTO archive(id,src,dst,msg,ts,proc_ts,resp_id) VALUES('.$inbox_msg_id.',"'.$in['src'][$k].'","'.$in['dst'][$k].'","'.$db->real_escape_string($in['msg'][$k]).'",'.$in['ts'][$k].','.$time.','.$db->get_id().')');
      // removing original message from inbox
      $db->put('DELETE FROM inbox WHERE id='.$inbox_msg_id);
      if (!$db->commit()) {
        unset($db); break;
      }
      
    }
    
  }
  
  sleep(5);
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
      die();
    break;
    default:
     return true;
  }
}
