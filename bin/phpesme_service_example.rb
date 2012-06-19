#!/usr/bin/ruby
# coding: utf-8

require 'mysql'

DB_HOST = 'localhost'
DB_USER = 'user'
DB_PASS = 'password'
DB_SCHEME = 'phpesme'

$dbh = nil

def mysql_is_down?
  begin
    $dbh.query('SELECT 1')
    return false
  rescue
    $dbh = Mysql.real_connect(DB_HOST, DB_USER, DB_PASS, DB_SCHEME)
    if $dbh
      $dbh.charset = 'utf8'
      $dbh.autocommit(false)
      return false
    else
      return true
    end
  end
end

Signal.trap(3) { $dbh.close if $dbh }
Signal.trap(2) { $dbh.close if $dbh }

while true do # service always running
  if mysql_is_down?
    sleep 5
    next
  end
  
  $dbh.commit # this is required to get non-cached result
  $dbh.query('SELECT id,src,dst,msg,ts FROM inbox WHERE mt=0 AND dst LIKE "3333.%"').each_hash do |inbox_msg| # processing incoming messages one by one
    
    reply_text = inbox_msg['msg'].strip.reverse # will reply with original text reversed
    
    begin
      # creating message in outbox
      $dbh.query("INSERT INTO outbox (rel_id, src, dst, msg, ts, rd)
                 VALUES (#{inbox_msg['id']}, '3333.0.1', '#{inbox_msg['src']}','#{$dbh.escape_string(reply_text)}',#{Time.now.to_i},0)")
      # putting original message in archive, providing sent message id as resp_id
      $dbh.query("INSERT INTO archive(id,src,dst,msg,ts,proc_ts,resp_id) VALUES(#{inbox_msg['id']},\"#{inbox_msg['src']}\",\"#{inbox_msg['dst']}\",\"#{$dbh.escape_string(inbox_msg['msg'])}\",#{inbox_msg['ts']},#{Time.now.to_i},#{$dbh.insert_id})")
      # removing original message from inbox
      $dbh.query("DELETE FROM inbox WHERE id=#{inbox_msg['id']}")
      $dbh.commit
    rescue 
      $dbh.rollback
      break
    end
    
  end
  sleep 5
end

$dbh.close if $dbh
