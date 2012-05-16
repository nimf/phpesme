<?php

/**
* Nimf's mysql extension
* 
* This file contains Nimf's extension of MySQLi class.
* 
* @package NIMF
* @subpackage mysqli
* @author Nimf <nimfin@gmail.com>
* @version 0.0.2
* @copyright Copyright (c) 2008, Nimf
* 
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
* 
*   http://www.apache.org/licenses/LICENSE-2.0
* 
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and
* limitations under the License.
*/
 
class NIMF_mysqli extends mysqli {
  
  /**
   * Runs a query, returns single-dimensional array
   * @access public
   * @param string $query SQL query
   * @return mixed Array of result row on success, FALSE on error
   */
  public function get_one($query) {
    if (false !== $res = $this->query($query.' LIMIT 1')) {
      return $res->fetch_assoc();
    } else return false;
  }
  
  /**
   * Runs a query, returns two-dimensional array
   * @access public
   * @param string $query SQL query
   * @return mixed Array of result rows on success, FALSE on error
   */
  public function get_array($query) {
    $out = Array(Array());
    if (false !== $res = $this->query($query)) {
      $rc = $res->num_rows;
      if ($rc) {
        for($i=0;$i<$rc;$i++) {
          $row = $res->fetch_assoc();
          foreach($row as $k=>$v) $out[$k][$i] = $v;
        }
      }
    } else return false;
    return $out;
  }
  
  /**
   * Gets last inserted id (autoincrement)
   * @access public
   * @return mixed id on success, FALSE on error
   */
  public function get_id() {
    return $this->insert_id;
  }
  
  /**
   * Runs a query
   * @access public
   * @param string $query SQL query
   * @return boolean MySQL result on success, FALSE on error
   */
  public function put($query) {
    return $this->query($query);
  }
  
  /**
   * Destructor. Closes connection
   * @access public
   * @return boolean parent result
   */
  public function __destruct() {
    $this->close();
  }
}