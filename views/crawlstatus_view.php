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
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage view
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * This view is used to display information about
 * crawls that have been made by this seek_quarry instance
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */
class CrawlstatusView extends View
{
    /**
     * An Ajax call from the Manage Crawl Element in Admin View triggers
     * this view to be instantiated. The renderView method then draws statistics
     * about the currently active crawl.The $data is supplied by the crawlStatus
     * method of the AdminController.
     *
     * @param array $data   info about the current crawl status
     */
    function renderView($data) {
        $base_url = "?c=admin&amp;a=manageCrawls&amp;".
            CSRF_TOKEN."=".$data[CSRF_TOKEN]."&amp;arg=";
        ?>
        <h2><?php e(tl('crawlstatus_view_currently_processing')); ?></h2>
        <p><b><?php e(tl('crawlstatus_view_description')); ?></b> <?php
        if(isset($data['DESCRIPTION']) && $data["CRAWL_RUNNING"]) {
            switch($data['DESCRIPTION'])
            {
                case 'BEGIN_CRAWL':
                    e(tl('crawlstatus_view_starting_crawl'));?>&nbsp;&nbsp;
                <button class="button-box" type="button"
                    onclick="javascript:document.location = '<?php
                    e($base_url); ?>stop'" ><?php
                    e(tl('managecrawls_element_stop_crawl'))?></button>
                <?php
                break;
                case 'RESUME_CRAWL':
                    e(tl('crawlstatus_view_resuming_crawl'));?>&nbsp;&nbsp;
                <button class="button-box" type="button"
                    onclick="javascript:document.location = '<?php
                    e($base_url); ?>stop'" ><?php
                    e(tl('managecrawls_element_stop_crawl'))?></button>
                <?php
                break;
                case 'SHUTDOWN_QUEUE':
                    e(tl('crawlstatus_view_shutdown_queue'));
                break;
                case 'SHUTDOWN_DICTIONARY':
                    e(tl('crawlstatus_view_closing_dict'));
                break;
                case 'SHUTDOWN_RUNPLUGINS':
                    e(tl('crawlstatus_view_run_plugins'));
                break;
                default:
                    e($data['DESCRIPTION']);
                ?>&nbsp;&nbsp;
                <button class="button-box" type="button"
                    onclick="javascript:document.location = '<?php
                    e($base_url); ?>stop'" ><?php
                    e(tl('managecrawls_element_stop_crawl'))?></button>
                <?php
            }
            ?><br />
                <?php
                if( $data['CRAWL_TIME'] != $data['CURRENT_INDEX']) { ?>
                   [<a href="<?php e($base_url); ?>index&amp;timestamp=<?php
                        e($data['CRAWL_TIME']); ?>"><?php
                        e(tl('crawlstatus_view_set_index')); ?></a>]
                <?php
                } else { ?>
                    [<?php e(tl('crawlstatus_view_search_index')); ?>]
                <?php
                }
                ?>
                [<a href="?c=admin&amp;a=manageCrawls<?php
                ?>&amp;arg=options&amp;<?php
                e(CSRF_TOKEN."=".$data[CSRF_TOKEN]); ?>&amp;ts=<?php
                e($data['CRAWL_TIME']); ?>"><?php
                e(tl('crawlstatus_view_changeoptions')); ?></a>]<?php
        } else {
            e(tl('crawlstatus_view_no_description'));
        }
        ?></p>
        <?php
        if(isset($data['CRAWL_TIME'])) { ?>
            <p><b><?php e(tl('crawlstatus_view_timestamp')); ?></b>
            <?php e($data['CRAWL_TIME']);  ?></p>
            <p><b><?php e(tl('crawlstatus_view_time_started')); ?></b>
            <?php e(date("r",$data['CRAWL_TIME']));?> </p>
        <?php
        } ?>
        <?php if(isset($data['SCHEDULER_PEAK_MEMORY']) &&
            isset($data['QUEUE_PEAK_MEMORY'])) { ?>
            <p><b><?php e(tl('crawlstatus_view_indexer_memory')); ?></b>
            <?php e($data['QUEUE_PEAK_MEMORY']); ?></p>
            <p><b><?php e(tl('crawlstatus_view_scheduler_memory')); ?></b>
            <?php e($data['SCHEDULER_PEAK_MEMORY']); ?></p>
        <?php } else { ?>
            <p><b><?php e(tl('crawlstatus_view_queue_memory')); ?></b>
            <?php
            if(isset($data['QUEUE_PEAK_MEMORY'])) {
                e($data['QUEUE_PEAK_MEMORY']);
            } else {
                e(tl('crawlstatus_view_no_mem_data'));
            } ?>
            </p>
        <?php } ?>
        <p><b><?php e(tl('crawlstatus_view_fetcher_memory')); ?></b>
        <?php
        if(isset($data['FETCHER_PEAK_MEMORY'])) {
            e($data['FETCHER_PEAK_MEMORY']);
        } else {
            e(tl('crawlstatus_view_no_mem_data'));
        } ?>
        </p>
        <p><b><?php e(tl('crawlstatus_view_webapp_memory')); ?></b>
        <?php
        if(isset($data['WEBAPP_PEAK_MEMORY'])) {
            e($data['WEBAPP_PEAK_MEMORY']);
        } else {
            e(tl('crawlstatus_view_no_mem_data'));
        } ?>
        </p>
        <p><b><?php e(tl('crawlstatus_view_urls_per_hour')); ?></b> <?php
            if(isset($data['VISITED_URLS_COUNT_PER_HOUR'])) {
                e(number_format($data['VISITED_URLS_COUNT_PER_HOUR'],
                    2, ".", ""));
            } else {
                e("0.00");
            }
            ?></p>
        <p><b><?php e(tl('crawlstatus_view_visited_urls')); ?></b> <?php
            if(isset($data['VISITED_URLS_COUNT'])) {
                e($data['VISITED_URLS_COUNT']); } else {e("0");}
            ?></p>
        <p><b><?php e(tl('crawlstatus_view_total_urls')); ?></b> <?php
            if(isset($data['COUNT'])) { e($data['COUNT']); } else {e("0");}
            ?></p>
        <p><b><?php e(tl('crawlstatus_view_most_recent_fetcher')); ?></b>

        <?php
        if(isset($data['MOST_RECENT_FETCHER'])) {
            e($data['MOST_RECENT_FETCHER']);
            if(isset($data['MOST_RECENT_TIMESTAMP'])) {
                e(" @ ".date("r", $data['MOST_RECENT_TIMESTAMP']));
            }
        } else {
            e(tl('crawlstatus_view_no_fetcher'));
        }
        ?></p>

        <h2><?php e(tl('crawlstatus_view_most_recent_urls')); ?></h2>
        <?php
        if(isset($data['MOST_RECENT_URLS_SEEN']) &&
            count($data['MOST_RECENT_URLS_SEEN']) > 0) {
            e('<pre>');
            foreach($data['MOST_RECENT_URLS_SEEN'] as $url) {
                e(htmlentities(wordwrap($url, 60, "\n", true))."\n");
            }
            e('</pre>');
        } else {
            e("<p>".tl('crawlstatus_view_no_recent_urls')."</p>");
        }

        $data['TABLE_TITLE'] = tl('crawlstatus_view_previous_crawls');
        $data['ACTIVITY'] = 'manageCrawls';
        $data['VIEW'] = $this;
        $data['NO_FLOAT_TABLE'] = true;
        $data['FORM_TYPE'] = NULL;
        $data['NO_SEARCH'] = true;
        $this->helper("pagingtable")->render($data);
        if(isset($data['RECENT_CRAWLS']) && count($data['RECENT_CRAWLS']) > 0) {
            ?>
            <table class="crawls-table">
            <tr><th><?php e(tl('crawlstatus_view_description'));?></th><?php
            if(!MOBILE) {?>
                <th><?php
                e(tl('crawlstatus_view_timestamp')); ?></th>
                <th><?php e(tl('crawlstatus_view_url_counts'));?></th>
            <?php
            }
            ?>
            <th colspan="3"><?php e(tl('crawlstatus_view_actions'));?></th></tr>
            <?php
            foreach($data['RECENT_CRAWLS'] as $crawl) {
                $description = (MOBILE) ? wordwrap($crawl['DESCRIPTION'],
                    10, "<br />\n", true) :
                    $crawl['DESCRIPTION'];
            ?>
                <tr><td><b><?php e($description); ?></b><br />
                    [<a href="?<?php e(CSRF_TOKEN."=".$data[CSRF_TOKEN]);
                        ?>&amp;c=statistics&amp;its=<?php
                    e($crawl['CRAWL_TIME']); ?>"><?php
                    e(tl('crawlstatus_view_statistics')); ?></a>]</td><?php
                    if(!MOBILE) { ?>
                    <td><?php
                        e("<b>{$crawl['CRAWL_TIME']}</b><br />");
                        e("<small>".date("r", $crawl['CRAWL_TIME']).
                            "</small>"); ?></td>
                        <td> <?php e( (isset($crawl["VISITED_URLS_COUNT"]) ?
                            $crawl['VISITED_URLS_COUNT'] : 0) ."/".
                            $crawl['COUNT']); ?></td>
                    <?php
                    }
                    ?>
                    <td><?php if($crawl['RESUMABLE']) { ?>
                        <a href="<?php e($base_url);
                            ?>resume&amp;timestamp=<?php
                            e($crawl['CRAWL_TIME']); ?>"><?php
                            e(tl('crawlstatus_view_resume'));?></a>
                        <?php } else {
                                e(tl('crawlstatus_view_no_resume'));
                              }?></td>
                <td>
                <?php
                if( $crawl['CRAWL_TIME'] != $data['CURRENT_INDEX']) { ?>
                    <a href="<?php e($base_url); ?>index&amp;timestamp=<?php
                        e($crawl['CRAWL_TIME']); ?>"><?php
                        e(tl('crawlstatus_view_set_index')); ?></a>
                <?php
                } else { ?>
                    <?php e(tl('crawlstatus_view_search_index')); ?>
                <?php
                }
                ?>
                </td>
                <td><a href="<?php e($base_url);
                    ?>delete&timestamp=<?php e($crawl['CRAWL_TIME']);
                    ?>"><?php e(tl('crawlstatus_view_delete')); ?></a></td>
                </tr>
            <?php
            }
            ?></table>
        <?php
        } else {
            e("<p class='red'>".
                tl('crawlstatus_view_no_previous_crawl')."</p>");
        }
        ?>
    <?php
    }
}
?>