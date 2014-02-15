<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
 *
 *  LICENSE:
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage datasource_manager
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**  For timer function, if debug level set to include query statistics */
require_once BASE_DIR."/lib/utility.php";

/**
 *
 * This abstract class defines the interface through which
 * the seek_quarry program communicates with a database and the
 * filesystem.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage datasource_manager
 */
abstract class DatasourceManager
{
    /**
     * Used to store statistics about what queries have been run depending on
     * the debug level
     * @var string
     */
    var $query_log;
    /**
     * Used to store the total time taken to execute queries
     * @var int
     */
    var $total_time;

    /** Sets up the query_log for query statistics */
    function __construct() {
        $this->query_log = array();
        $this->total_time = 0;
    }

    /**
     * Connects to a database on a DBMS using data provided or from config.php
     *
     * @param string $db_host the hostname of where the database is located
     *      (not used in all dbms's)
     * @param string $db_user the user to connect as
     * @param string $db_password the password of the user to connect as
     * @param string $db_name the name of the database on host we are
     *  connecting to
     * @return mixed return false if not successful and some kind of
     *      connection object/identifier otherwise
     */

    abstract function connect($db_host = DB_HOST,
        $db_user = DB_USER, $db_password = DB_PASSWORD, $db_name = DB_NAME);

    /**
     *  Closes connections to DBMS
     *
     */
    abstract function disconnect();

    /**
     * Hook Method for execute(). Executes the sql command on the database
     *
     * This method operates on either query or data manipulation statements
     *
     * @param string $sql  SQL statement to execute
     * @return mixed false if query fails, resource or true otherwise
     */
    abstract function exec($sql);

    /**
     * Returns the number of rows affected by the last sql statement
     *
     * @return int the number of rows affected by the last
     * insert, update, delete
     */
    abstract function affectedRows();

    /**
     * Returns the ID generated by the last insert statement
     * if table has an auto increment key column
     *
     * @return string  the ID of the insert
     */
    abstract function insertID();

    /**
     * Returns the next row from the provided result set
     *
     * @param resource $result   result set reference of a query
     * @return array the next row from the result set as an
     * associative array in the form column_name => value
     */
    abstract function fetchArray($result);

    /**
     * Used to escape strings before insertion in the
     * database to avoid SQL injection
     *
     * @param string $str  string to escape
     * @return string a string which is safe to insert into the db
     */
    abstract function escapeString($str);

    /**
     * Executes the supplied sql command on the database, depending on debug
     * levels computes query statistics
     *
     * This method operates either query or data manipulation statements
     *
     * @param string $sql  SQL statement to execute
     * @param array $param bind_name => value values to interpolate into
     *  the $sql to be executes
     * @return mixed false if query fails, resource or true otherwise

     */
    function execute($sql, $params = array())
    {
        if(QUERY_STATISTICS) {
            $query_info = array();
            $query_info['QUERY'] = $sql;
            if($params != array()) {
                $query_info['QUERY'] .= "<br />".print_r($params, true);
            }
            $start_time = microtime();
        }
        $result =$this->exec($sql, $params);
        if(QUERY_STATISTICS) {
            $query_info['ELAPSED_TIME'] = changeInMicrotime($start_time);
            $this->total_time += $query_info['ELAPSED_TIME'];
            $this->query_log[] = $query_info;
        }
        return $result;
    }

    /**
     * Recursively delete a directory
     *
     * @param string $dir Directory name
     * @param boolean $deleteRootToo Delete specified top directory as well
     */
    function unlinkRecursive($dir, $deleteRootToo = true)
    {
        $this->traverseDirectory($dir, "deleteFileOrDir", $deleteRootToo);
    }

    /**
     * Recursively chmod a directory to 0777
     *
     * @param string $dir Directory name
     * @param boolean $chmodRootToo chmod specified top-level directory as well
     */
    function setWorldPermissionsRecursive($dir, $chmodRootToo = true)
    {
        $this->traverseDirectory($dir, "setWorldPermissions", $chmodRootToo);
    }

    /**
     * Returns arrays of filesizes and file modifcations times of files in
     * a directory
     */
    function fileInfoRecursive($dir, $chmodRootToo = true)
    {
        return $this->traverseDirectory($dir,
            "fileInfo", $chmodRootToo);
    }

    /**
     * Recursively copies a source directory to a destination directory
     *
     * It would have been cool to use traverseDirectory to implement this, but
     * it was a little bit too much of a stretch to shoehorn the code to match
     *
     * @param string $source_dir the name of the source directory
     * @param string $desitnation_dir the name of the destination directory
     */
    function copyRecursive($source_dir, $destination_dir)
    {
        if(!$dh = @opendir($source_dir)) {
            return;
        }
        if(!file_exists($destination_dir)) {
            @mkdir($destination_dir);
            if(!file_exists($destination_dir)) {
                return;
            }
            chmod($destination_dir, 0777);
        }
        while(false !== ( $obj = readdir($dh)) ) {
            if (( $obj != '.' ) && ( $obj != '..' )) {
                if ( is_dir($source_dir . '/' . $obj) ) {
                    $this->copyRecursive($source_dir . '/' .
                        $obj, $destination_dir . '/' . $obj);
                }
                else {
                    copy($source_dir . '/' .
                        $obj, $destination_dir . '/' . $obj);
                    chmod($destination_dir . '/' . $obj, 0777);
                }
            }
        }
        closedir($dh);
    }


    /**
     * Recursively traverse a directory structure and call a callback function
     *
     * @param string $dir Directory name
     * @param function $callback Function to call as traverse structure
     * @return array results computed by performing the traversal
     */
    function traverseDirectory($dir, $callback, $rootToo = true)
    {
        $results = array();
        if(!is_dir($dir) || !$dh = @opendir($dir)) {
            return $results;
        }

        while (false !== ($obj = readdir($dh))) {
            if($obj == '.' || $obj == '..') {
                continue;
            }
            if (is_dir($dir . '/' . $obj)) {
                $subdir_results =
                    $this->traverseDirectory($dir.'/'.$obj, $callback, true);
                $results = array_merge($results, $subdir_results);
            }
            $obj_results = @$callback($dir . '/' . $obj);
            if(is_array($obj_results)) {
                $results = array_merge($results, $obj_results);
            }

        }

        closedir($dh);

        if ($rootToo) {
            $obj_results = @$callback($dir);
            if(is_array($obj_results)) {
                $results = array_merge($results, $obj_results);
            }
        }

        return $results;

    }

    /**
     * Returns string for given DBMS CREATE TABLE equivalent to auto_increment
     * (at least as far as Yioop requires).
     *
     * @param array $dbinfo contains strings DBMS, DB_HOST, DB_USER, DB_PASSWORD
     * @return string to achieve auto_increment function for the given DBMS
     */
    function autoIncrement($dbinfo)
    {
        $auto_increment = "AUTOINCREMENT";
        if(in_array($dbinfo['DBMS'], array("mysql"))) {
            $auto_increment = "AUTO_INCREMENT";
        }
        if(in_array($dbinfo['DBMS'], array("sqlite"))) {
            $auto_increment = "";
                /* in sqlite2 a primary key column will act
                   as auto_increment if don't give value
                 */
        }
        if(stristr($dbinfo['DBMS'], 'pdo')) {
            if(stristr($dbinfo['DB_HOST'], 'SQLITE')) {
                $auto_increment = "";
            } else if(stristr($dbinfo['DB_HOST'], 'PGSQL')) { //POSTGRES
                $auto_increment = "";
            } else if(stristr($dbinfo['DB_HOST'], 'OCI')) { // ORACLE
                $auto_increment = "DEFAULT SYS_GUID()";
            } else if(stristr($dbinfo['DB_HOST'], 'IBM')) { //DB2
                $auto_increment = "GENERATED ALWAYS AS IDENTITY ".
                    "(START WITH 1 INCREMENT BY 1)";
            } else if(stristr($dbinfo['DB_HOST'], 'DBLIB')) { //MS SQL
                $auto_increment = "IDENTITY (1,1)";
            }
        }

        return $auto_increment;
    }

    /**
     *
     */
    function serialType($dbinfo)
    {
        $serial = "INTEGER"; //ONLY POSTGRES IS WEIRD
        if($dbinfo['DBMS'] == 'pdo' && stristr($dbinfo['DB_HOST'], 'PGSQL')) {
            $serial = "SERIAL"; //POSTGRES
        }
        return $serial;
    }

    /**
     *
     */
    function limitOffset($dbinfo, $limit, $num)
    {
        $bounds = "$limit , $num";
        if($dbinfo['DBMS'] == 'pdo' && stristr($dbinfo['DB_HOST'], 'PGSQL')) {
            $bounds = "$num OFFSET $limit"; //POSTGRES
        }
        return $bounds;
    }
}
?>
