<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010, 2011  Chris Pollett chris@pollett.org
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
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010, 2011
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Element responsible for displaying info about a given crawl mix
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class EditmixElement extends Element
{

    /**
     * Draw form to start a new crawl, has div place holder and ajax code to 
     * get info about current crawl
     *
     * @param array $data  form about about a crawl such as its description
     */
    public function render($data) 
    {?>
        <div class="currentactivity">
        <div class="<?php e($data['leftorright']);?>">
        <a href="?c=admin&amp;a=mixCrawls&amp;YIOOP_TOKEN=<?php 
            e($data['YIOOP_TOKEN']) ?>"
        ><?php e(tl('editmix_element_back_to_mix'))?></a>
        </div>
        <h2><?php e(tl('mixcrawls_element_edit_mix'))?></h2>
        <form id="mixForm" method="get" action=''>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" /> 
        <input type="hidden" name="a" value="mixCrawls" />
        <input type="hidden" name="arg" value="editmix" />
        <input type="hidden" name="update" value="update" />
        <input type="hidden" name="mix[COMPONENTS]" value="" />
        <input type="hidden" name="mix[MIX_TIMESTAMP]" 
            value="<?php e($data['MIX']['MIX_TIMESTAMP']);?>" />
        <div class="topmargin"><label for="mix-name"><?php 
            e(tl('mixcrawls_element_mix_name')); ?></label> 
            <input type="text" id="mix-name" name="mix[MIX_NAME]" 
                value="<?php if(isset($data['MIX']['MIX_NAME'])) {
                    e($data['MIX']['MIX_NAME']); } ?>" maxlength="80" 
                    class="widefield"/>
        </div>
        <h3><?php e(tl('mixcrawls_element_mix_components'))?></h3>
         <table id="mix-table" class="mixestable">
        <tr><th><?php e(tl('editcrawl_view_weight'));?></th>
        <th><?php e(tl('editcrawl_view_name'));?></th>
        <th><?php e(tl('editcrawl_view_actions'));?></th></tr>
         <?php
         foreach($data['MIX']['COMPONENTS'] as $component) {
             $crawl_name = $data['available_crawls'][
                $component['CRAWL_TIMESTAMP']];
             e("<tr id='".$component['CRAWL_TIMESTAMP']
                ."'><td>");
            $this->view->optionsHelper->render(
                "crawl-weight", "mix[COMPONENTS][".
                $component['CRAWL_TIMESTAMP']."]", 
                $data['allowed_weights'], $component['WEIGHT']);
             e("</td><td>".$crawl_name."</td>");
             e("<td><a href='javascript:removeCrawl(".
                $component['CRAWL_TIMESTAMP'].",\"".$crawl_name. "\" )' >".
                tl('editcrawl_view_delete')."</a></td></tr>");
             unset($data['available_crawls'][$component['CRAWL_TIMESTAMP']]);
         }
         ?>
         </table>
        <div class="topmargin"><label for="add-crawls"><?php 
            e(tl('crawloptions_element_add_crawls'))?></label><?php
            $this->view->optionsHelper->render("add-crawls", "add_crawls", 
                $data['available_crawls'], 0);
        ?></div>
        <div class="center slightpad"><button class="buttonbox" 
            type="submit"><?php 
                e(tl('mixcrawls_element_save_button')); ?></button></div>
        </form>


        </div>
    <?php 
    }
}
?>
