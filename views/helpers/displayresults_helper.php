<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * @author Priya Gangaraju priya.gangaraju@gmail.com
 * @package seek_quarry
 * @subpackage helper
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * Load base helper class if needed
 */
require_once BASE_DIR."/views/helpers/helper.php";
/**
 * This is a helper class used to handle
 * displaying a web page summary. If the summary has recipe data
 * each ingredient is displayed in seperate line.
 * otherwise display the data.
 *
 * @author Priya Gangaraju
 * @package seek_quarry
 * @subpackage helper
 */
class DisplayresultsHelper extends Helper
{
    /**
     * Used to draw a web page summary/snippets in a search engine result.
     * If the summary has recipe data each ingredient is displayed in
     * seperate line.
     *
     * @param string $summary summary/snippet to draw
     */
    function render($summary)
    {
        $recipe_parts = explode("||", $summary);
        $count = count($recipe_parts);
        if($count > 1){
            foreach($recipe_parts as $value){
                echo $value;
                print("<br />");

            }
        }
        else {
            echo $summary;
        }
    }
}
?>