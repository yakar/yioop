<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
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
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";
/** 
 * Crawl data is stored in an IndexArchiveBundle, 
 * so load the definition of this class
 */
require_once BASE_DIR."/lib/index_archive_bundle.php";
/**
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
class IndexManager implements CrawlConstants
{
    /**
     *
     */
    static function getIndex($index_name)
    {
        static $indexes = array();
        if(!isset($indexes[$index_name])) {
            $index_archive_name =self::index_data_base_name . $index_name;
            $indexes[$index_name] = 
                new IndexArchiveBundle(CRAWL_DIR.'/cache/'.$index_archive_name);
            $indexes[$index_name]->setCurrentShard(0, true);
        }
        return $indexes[$index_name];
    }

}
?>