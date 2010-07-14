<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010  Chris Pollett chris@pollett.org
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
 * @subpackage bin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

define("BASE_DIR", 
    substr($_SERVER['DOCUMENT_ROOT'].$_SERVER['PWD'].$_SERVER["SCRIPT_NAME"],
    0, -strlen("bin/fetcher.php")));

ini_set("memory_limit","500M"); //so have enough memory to crawl big pages

/** Load in global configuration settings */
require_once BASE_DIR.'/configs/config.php';
if(!PROFILE) {
    echo "Please configure the search engine instance by visiting its web interface on localhost.\n";
    exit();
}

/** get the database library based on the current database type */
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php"; 
/** caches of web pages are stored in a web archive bundle, so we load in its definition */
require_once BASE_DIR."/lib/web_archive_bundle.php"; 

/** get processors for different file types */
foreach(glob(BASE_DIR."/lib/processors/*_processor.php") as $filename) { 
    require_once $filename;
}

/** */
require_once BASE_DIR."/lib/porter_stemmer.php";
/** */
require_once BASE_DIR."/lib/url_parser.php";
/** */
require_once BASE_DIR."/lib/phrase_parser.php";
/** for crawlHash and crawlLog */
require_once BASE_DIR."/lib/utility.php"; 
/** for crawlDaemon function */
require_once BASE_DIR."/lib/crawl_daemon.php"; 
/** */
require_once BASE_DIR."/lib/fetch_url.php";
/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";


/*
 *  We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

 
/**
 *  This class is responsible for fetching web pages for the SeekQuarry/Yioop search engine
 *
 *  Fetcher periodically queries the queue server asking for web pages to fetch. It gets at most
 *  MAX_FETCH_SIZE many web pages from the queue_server in one go. It then fetches these
 *  pages. Pages are fetched in batches of NUM_MULTI_CURL_PAGES many pages.
 *  Each SEEN_URLS_BEFORE_UPDATE_SCHEDULER many downloaded pages (not including robot pages), 
 *  the fetcher sends summaries back to the machine on which the queue_server lives. It does
 *  this by making a request of the web server on that machine and POSTs the data to the 
 *  seek quarry web app. This data is handled by the FetchController class. The summary data can 
 *  include up to four things: (1) robot.txt data, (2) summaries of each web page downloaded in the
 *  batch, (3), a list of future urls to add to the to-crawl queue, and (4) a partial inverted index
 *  saying for each word that occurred in the current SEEN_URLS_BEFORE_UPDATE_SCHEDULER documents 
 *  batch, what documents it occurred in. The inverted index also associates to each word document
 *  pair several scores. More information on these scores can be found in the documentation for
 *  {@link buildMiniInvertedIndex()}
 *
 *  @author Chris Pollett
 *  @package seek_quarry
 *  @see buildMiniInvertedIndex()
 */
class Fetcher implements CrawlConstants
{
    var $db;

    var $queue_server;
    var $indexed_file_types;
    var $page_processors;
    var $web_archive;
    var $crawl_time;
    var $to_crawl;
    var $found_sites;
    var $schedule_time;
    var $sum_seen_site_description_length;
    var $sum_seen_title_length;
    var $sum_seen_site_link_length;
    var $num_seen_sites;
    var $crawl_order;


    /**
     *
     */
    function __construct($indexed_file_types, $page_processors, $queue_server) 
    {
        $db_class = ucfirst(DBMS)."Manager";
        $this->db = new $db_class();

        $this->indexed_file_types = $indexed_file_types;
        $this->queue_server = $queue_server;
        $this->page_processors = $page_processors;

        $this->web_archive = NULL;
        $this->crawl_time = NULL;
        $this->schedule_time = NULL;

        $this->to_crawl = array();
        $this->found_sites = array();

        $this->sum_seen_title_length = 0;
        $this->sum_seen_description_length = 0;
        $this->sum_seen_site_link_length = 0;
        $this->num_seen_sites = 0;

        //we will get the correct values for the next two things from the queue_server
        $this->crawl_order = "OPIC";
    }
   

    /**
     *  This is the function that should be called to get the fetcher to start fetching.
     *  Calls init to handle the command line arguments then enters the fetcher's main loop
     */
    function start()
    {
        global $argv;

        declare(ticks=1);
        CrawlDaemon::init($argv, "fetcher");

        $this->loop();
    }

    /**
     *
     */
    function loop()
    {
        crawlLog("In Fetch Loop", "fetcher");
        
        $info[self::STATUS] = self::CONTINUE_STATE;
        $this->checkCrawlTime();
        
        while ($info[self::STATUS] != self::STOP_STATE) {

            if(file_exists(CRAWL_DIR."/schedules/fetcher_messages.txt")) {
                $info = unserialize(file_get_contents(CRAWL_DIR."/schedules/fetcher_messages.txt"));
                unlink(CRAWL_DIR."/schedules/fetcher_messages.txt");
                if(isset($info[self::STATUS]) && $info[self::STATUS] == self::STOP_STATE) {continue;}
            }
            
            $info = $this->checkScheduler();
            if(!isset($info[self::STATUS])) {
                $info[self::STATUS] = self::CONTINUE_STATE;
            }

            if($info[self::STATUS] == self::NO_DATA_STATE) {
                crawlLog("No data from queue server. Sleeping...");
                sleep(5);
                continue;
            }

            if($this->web_archive == NULL || (isset($info[self::CRAWL_TIME]) && 
                    $this->web_archive->dir_name != CRAWL_DIR."/cache/".self::archive_base_name.$info[self::CRAWL_TIME])) {
                if(isset($this->web_archive->dir_name)) {
                    crawlLog("Old name: ".$this->web_archive->dir_name);
                }
                $this->web_archive = new WebArchiveBundle(CRAWL_DIR.'/cache/'.self::archive_base_name.$info[self::CRAWL_TIME], 
                        URL_FILTER_SIZE, NUM_ARCHIVE_PARTITIONS);
                $this->crawl_time = $info[self::CRAWL_TIME];
                $this->sum_seen_title_length = 0;
                $this->sum_seen_description_length = 0;
                $this->sum_seen_site_link_length = 0;
                $this->num_seen_sites = 0;

                crawlLog("New name: ".$this->web_archive->dir_name);
                crawlLog("Switching archive...");

            }

            if(isset($info[self::SAVED_CRAWL_TIMES])) {
                $this->deleteOldCrawls($info[self::SAVED_CRAWL_TIMES]);
            }

            $start_time = microtime();
            $sites = $this->getFetchSites();
            if(!$sites) {
                crawlLog("No seeds to fetch...");
                sleep(max(0, ceil(MINIMUM_FETCH_LOOP_TIME - changeInMicrotime($start_time))));
                continue;
            }

            $site_pages = FetchUrl::getPages($sites, true);
            
            $deduplicated_pages = $this->deleteSeenPages($site_pages);

            $start_time = microtime();
            $summarized_site_pages = $this->processFetchPages($deduplicated_pages);
              
            crawlLog("Number summarize pages".count($summarized_site_pages));

            $this->updateFoundSites($summarized_site_pages);

            sleep(max(0, ceil(MINIMUM_FETCH_LOOP_TIME - changeInMicrotime($start_time))));
        } //end while

        crawlLog("Fetcher shutting down!!");
    }
    
    /**
     */
    function deleteOldCrawls(&$still_active_crawls)
    {
        $dirs = glob(CRAWL_DIR.'/cache/*', GLOB_ONLYDIR);

        foreach($dirs as $dir) {
            if(strlen($pre_timestamp = strstr($dir, self::archive_base_name)) > 0) {
                $time = substr($pre_timestamp, strlen(self::archive_base_name));
                if(!in_array($time, $still_active_crawls) ){
                    $this->db->unlinkRecursive($dir);
                }
            }
        }

    }

    /**
     * Makes a request of the queue server machine to get the timestamp of the currently running crawl to see if changed
     *
     * Get the timestamp from queue_server of the currently running crawl, if the timestamp has changed
     * drop the rest of the current fetch batch.
     */
   function checkCrawlTime()
    {
        $queue_server = $this->queue_server;

        $start_time = microtime();
        $time = time();
        $session = md5($time . AUTH_KEY);

        //if just restarted, check to make sure the crawl hasn't changed, if it has bail
        $request =  $queue_server."?c=fetch&a=crawlTime&time=$time&session=$session";

        $info_string = FetchUrl::getPage($request);
        $info = @unserialize(trim($info_string));

        if(isset($info[self::CRAWL_TIME]) && $info[self::CRAWL_TIME] != $this->crawl_time) {
            $this->to_crawl = array(); // crawl has changed. Dump rest of batch.
        }
    
    }
    
    /**
     */
    function checkScheduler() 
    {    
        $info = array();

        if(count($this->to_crawl) > 0) {
            $info[self::STATUS]  = self::CONTINUE_STATE;
            return; 
        }

        $queue_server = $this->queue_server;

        $start_time = microtime();
        $time = time();
        $session = md5($time . AUTH_KEY);

        $request =  $queue_server."?c=fetch&a=schedule&time=$time&session=$session";

        $info_string = FetchUrl::getPage($request);
        $info = unserialize(trim($info_string));

        if(isset($info[self::CRAWL_ORDER])) {
            $this->crawl_order = $info[self::CRAWL_ORDER];
        }

        if(isset($info[self::SITES])) {
            $this->to_crawl = $info[self::SITES];
        }

        if(isset($info[self::SCHEDULE_TIME])) {
              $this->schedule_time = $info[self::SCHEDULE_TIME];
        }
        crawlLog("  Time to check Scheduler ".(changeInMicrotime($start_time)));

        return $info;
    }
     
    /**
     */
    function getFetchSites() 
    {

        $web_archive = $this->web_archive;
            
        $start_time = microtime();

        $seeds = array();
        $delete_indices = array();
        $num_items = count($this->to_crawl);
        reset($this->to_crawl);

        if($num_items > NUM_MULTI_CURL_PAGES) {
            $num_items = NUM_MULTI_CURL_PAGES;
        }

        $i = 0;
        while ($i < $num_items) {
            if(!isset($site_pair)) {
                $site_pair = each($this->to_crawl);
                $old_pair['key'] = $site_pair['key'] - 1;

            } else {
                $old_pair =  $site_pair;
                $site_pair = each($this->to_crawl);
            }

            if($old_pair['key'] + 1 == $site_pair['key']) {
                $delete_indices[] = $site_pair['key'];

                if($site_pair['value'][0] != self::DUMMY) {
                    $seeds[$i][self::URL] = $site_pair['value'][0];
                    $seeds[$i][self::WEIGHT] = $site_pair['value'][1];

                    if(UrlParser::getDocumentFilename($seeds[$i][self::URL]).
                        ".".UrlParser::getDocumentType($seeds[$i][self::URL]) 
                        == "robots.txt") {
                        $seeds[$i][self::ROBOT_PATHS] = array();
                    }
                } else {
                    $num_items--;
                }
                $i++;
            } else {
             $i = $num_items;
            }
        } //end while

        foreach($delete_indices as $delete_index) {
            unset($this->to_crawl[$delete_index]);
        }

        crawlLog("  Fetch Seed Time ".(changeInMicrotime($start_time)));

        return $seeds;
    }

    /**
     */
    function deleteSeenPages(&$site_pages)
    {
        $start_time = microtime();
        crawlLog("  Delete duplicated pages time".(changeInMicrotime($start_time)));

        $deduplicated_pages = array();
        $unseen_page_hashes = $this->web_archive->differencePageKeysFilter($site_pages, self::HASH);

        foreach($site_pages as $site) {
            if( isset($site[self::ROBOT_PATHS])) {
                $deduplicated_pages[] = $site;
            } else if (isset($site[self::HASH]) && in_array($site[self::HASH], $unseen_page_hashes)) {
                $this->web_archive->addPageFilter(self::HASH, $site);
                $deduplicated_pages[] = $site;
            }

        }

        return $deduplicated_pages;
    }

    /**
     */
    function processFetchPages($site_pages)
    {
        $PAGE_PROCESSORS = $this->page_processors;

        $start_time = microtime();

        $stored_site_pages = array();
        $summarized_site_pages = array();

        $num_items = $this->web_archive->count;

        $i = 0;
        
        foreach($site_pages as $site) {
            $response_code = $site[self::HTTP_CODE]; 

            //process robot.txt files separately
            if(isset($site[self::ROBOT_PATHS])) {
                if($response_code >= 200 && $response_code < 300) {
                    $site = $this->processRobotPage($site);
                }
                $site[self::GOT_ROBOT_TXT] = true;
                $stored_site_pages[$i] = $site;
                $summarized_site_pages[$i] = $site;
                $i++;
                continue;
            }

            if($response_code < 200 || $response_code >= 300) {
                crawlLog($site[self::URL]." response code $response_code");

                /* we print out errors to std output. We still go ahead and
                   process the page. Maybe it is a cool error page, also
                   this makes sure we don't crawl it again 
                */
            }

            $type =  $site[self::TYPE];

            if(isset($PAGE_PROCESSORS[$type])) { 
                $page_processor = $PAGE_PROCESSORS[$type];
            } else {
                continue;
            }

            $processor = new $page_processor();

            $doc_info = $processor->process($site[self::PAGE], $site[self::URL]);

            if($doc_info) {

                $site[self::DOC_INFO] = $doc_info;

                if(!is_dir(CRAWL_DIR."/cache")) {
                    mkdir(CRAWL_DIR."/cache");
                    $htaccess = "Options None\nphp_flag engine off\n";
                    file_put_contents(CRAWL_DIR."/cache/.htaccess", $htaccess);

                }

                if($site[self::TYPE] != "text/html" ) {
                    if(isset($doc_info[self::PAGE])) {
                        $site[self::PAGE] = $doc_info[self::PAGE];
                    } else {
                        $site[self::PAGE] = NULL;
                    }

                }


                $stored_site_pages[$i][self::URL] = $site[self::URL];
                $stored_site_pages[$i][self::TIMESTAMP] = $site[self::TIMESTAMP];
                $stored_site_pages[$i][self::TYPE] = $site[self::TYPE];
                if(isset($site[self::ENCODING])) {
                    $encoding = $site[self::ENCODING];
                } else {
                    $encoding = "UTF-8";
                }
                $stored_site_pages[$i][self::ENCODING] = $encoding;
                $stored_site_pages[$i][self::HTTP_CODE] = $site[self::HTTP_CODE];
                $stored_site_pages[$i][self::HASH] = $site[self::HASH];
                $stored_site_pages[$i][self::PAGE] = $site[self::PAGE];

                $summarized_site_pages[$i][self::URL] = strip_tags($site[self::URL]);
                $summarized_site_pages[$i][self::TITLE] = strip_tags($site[self::DOC_INFO][self::TITLE]); // stripping html to be on the safe side
                $summarized_site_pages[$i][self::DESCRIPTION] = strip_tags($site[self::DOC_INFO][self::DESCRIPTION]);
                $summarized_site_pages[$i][self::TIMESTAMP] = $site[self::TIMESTAMP];
                $summarized_site_pages[$i][self::ENCODING] = $encoding;
                $summarized_site_pages[$i][self::HASH] = $site[self::HASH];
                $summarized_site_pages[$i][self::TYPE] = $site[self::TYPE];
                $summarized_site_pages[$i][self::HTTP_CODE] = $site[self::HTTP_CODE];
                $summarized_site_pages[$i][self::WEIGHT] = $site[self::WEIGHT];

                if(isset($site[self::DOC_INFO][self::LINKS])) {
                    $summarized_site_pages[$i][self::LINKS] = $site[self::DOC_INFO][self::LINKS];
                }

                if(isset($site[self::DOC_INFO][self::THUMB])) {
                    $summarized_site_pages[$i][self::THUMB] = $site[self::DOC_INFO][self::THUMB];
                }
                $i++;
            }
        } // end for
        $stored_site_pages = $this->web_archive->addPages(self::HASH, self::OFFSET, $stored_site_pages);

        $num_pages = count($stored_site_pages);

        for($i = 0; $i < $num_pages; $i++) {
            $summarized_site_pages[$i][self::INDEX] = $num_items + $i;
            if(isset($stored_site_pages[$i][self::OFFSET])) {
                $summarized_site_pages[$i][self::OFFSET] = $stored_site_pages[$i][self::OFFSET];
            }
        }

        crawlLog("  Process pages time".(changeInMicrotime($start_time)));

        return $summarized_site_pages;
    }

    /**
     *
     */
    function processRobotPage($robot_site)
    {
        $web_archive = $this->web_archive;

        $host_url = UrlParser::getHost($robot_site[self::URL]);

        if(isset($robot_site[self::PAGE])) {
            $robot_page = $robot_site[self::PAGE];
            $lines = explode("\n", $robot_page);

            $add_rule_state = false;
            $rule_added_flag = false;
            $delay_flag = false;

            $robot_rows = array();
            foreach($lines as $line) {
                if(stristr($line, "User-agent") && (stristr($line, ":*") 
                    || stristr($line, " *") || stristr($line, USER_AGENT_SHORT) || $add_rule_state)) {
                    $add_rule_state = ($add_rule_state) ? false : true;
                }
                
                if($add_rule_state) {
                    if(stristr($line, "Disallow")) {
                        $path = trim(preg_replace('/Disallow\:/i', "", $line));

                        $rule_added_flag = true;

                        if(strlen($path) > 0) {
                        $robot_site[self::ROBOT_PATHS][] = $path; 
                        }
                    }
                    
                    if(stristr($line, "Crawl-delay")) {
                      
                        $delay_string = trim(preg_replace('/Crawl\-delay\:/i', "", $line));
                        $delay_flag = true;
                    }
                }
            }
            
            if($delay_flag) {
                $delay = intval($delay_string);
                if($delay > MAXIMUM_CRAWL_DELAY)  {
                    $robot_site[self::ROBOT_PATHS][] = "/";
                } else {
                    $robot_site[self::CRAWL_DELAY] = $delay;
                }
            }
        }
        return $robot_site;
    
    }
    
    /**
    */
    function updateFoundSites($sites) 
    {
        $start_time = microtime();


        for($i = 0; $i < count($sites); $i++) {
            $site = $sites[$i];
            
            if(isset($site[self::ROBOT_PATHS])) {
                $host = UrlParser::getHost($site[self::URL]);
                $this->found_sites[self::ROBOT_TXT][$host][self::PATHS] = $site[self::ROBOT_PATHS];
                if(isset($site[self::CRAWL_DELAY])) {
                    $this->found_sites[self::ROBOT_TXT][$host][self::CRAWL_DELAY] = $site[self::CRAWL_DELAY];
                }
            } else {
                $this->found_sites[self::SEEN_URLS][] = $site;
                if(isset($site[self::LINKS])) {
                    if(!isset($this->found_sites[self::TO_CRAWL])) {
                        $this->found_sites[self::TO_CRAWL] = array();
                    }
                    $link_urls = array_keys($site[self::LINKS]);
                    $num_links = count($link_urls);

                    switch($this->crawl_order) {
                        case self::BREADTH_FIRST:
                            $weight= $site[self::WEIGHT] + 1;
                        break;

                        case self::PAGE_IMPORTANCE:
                        default:
                            if($num_links > 0 ) {
                                $weight= $site[self::WEIGHT]/$num_links;
                            } else {
                                $weight= $site[self::WEIGHT];
                            }
                        break;
                    }

                    foreach($link_urls as $link_url) {
                        if(strlen($link_url) > 0) {
                            $this->found_sites[self::TO_CRAWL][] = array($link_url, $weight);
                        }
                    }

                }
            } //end else

            if(isset($this->found_sites[self::TO_CRAWL])) {
                $this->found_sites[self::TO_CRAWL] = array_filter($this->found_sites[self::TO_CRAWL]);
            }
            crawlLog($site[self::INDEX].". ".$site[self::URL]);
         
        } // end for


        if(count($this->to_crawl) <= 0 || 
            ( isset($this->found_sites[self::SEEN_URLS]) && count($this->found_sites[self::SEEN_URLS]) > SEEN_URLS_BEFORE_UPDATE_SCHEDULER)) {
            $this->updateScheduler();
        }

        crawlLog("  Update Found Sites Time ".(changeInMicrotime($start_time)));
    
    }

    /**
     *
     */
    function updateScheduler() 
    {
        $queue_server = $this->queue_server;


        if(count($this->to_crawl) <= 0) {
            $this->found_sites[self::SCHEDULE_TIME] = $this->schedule_time;
        }

        if(isset($this->found_sites[self::SEEN_URLS]) && count($this->found_sites[self::SEEN_URLS]) > 0 ) {
            $this->buildMiniInvertedIndex();
        }

        $post_data = array('c'=>'fetch', 'a'=>'update', 'crawl_time' => $this->crawl_time, 'machine_uri' => WEB_URI);
        $post_data['found'] = urlencode(base64_encode(gzcompress(serialize($this->found_sites))));
        $bytes_to_send = strlen($post_data['found']);

        $this->found_sites = array(); // reset found_sites so have more space.

        $sleep = false;
        do {
            
            if($sleep == true) {
                crawlLog("Trouble sending to the scheduler\n $info_string...");
                sleep(5);
            }
            $sleep = true;

            $time = time();
            $session = md5($time . AUTH_KEY);
            $post_data['time'] = $time;
            $post_data['session'] = $session;

            $info_string = FetchUrl::getPage($queue_server, $post_data);
            crawlLog("Updating Queue Server, sending approximately $bytes_to_send bytes:");

            $info = unserialize(trim($info_string));
            crawlLog("Queue Server info response code: ".$info[self::STATUS]);
            crawlLog("Queue Server's crawl time is: ".$info[self::CRAWL_TIME]);

        } while(!isset($info[self::STATUS]) || $info[self::STATUS] != self::CONTINUE_STATE);

        if(isset($info[self::CRAWL_TIME]) && $info[self::CRAWL_TIME] != $this->crawl_time) {
            $this->to_crawl = array(); // crawl has changed. Dump rest of batch.
        }

    }

    /**
     *
     */
    function buildMiniInvertedIndex()
    {
        $start_time = microtime();
        $words = array();
        $doc_statistics = $this->computeDocumentStatistics();
        $average_title_length = $doc_statistics[self::AVERAGE_TITLE_LENGTH];
        $average_description_length = $doc_statistics[self::AVERAGE_DESCRIPTION_LENGTH];
        $average_total_link_text_length = $doc_statistics[self::AVERAGE_TOTAL_LINK_TEXT_LENGTH];

        foreach($doc_statistics as $doc_key => $info) {

            $title_length = $info[self::TITLE_LENGTH];
            $description_length = $info[self::DESCRIPTION_LENGTH];
            $link_length = $info[self::LINK_LENGTH];

            $title_ratio = $title_length/$average_title_length;
            $description_ratio = $description_length/$average_description_length;
            $link_ratio = $link_length/$average_total_link_text_length;

            if(isset($info[self::TITLE_WORDS])) {
                foreach($info[self::TITLE_WORDS] as $word_key => $num_occurrences) {
                    $title_frequency = $num_occurrences/$title_length;

                    $words[crawlHash($word_key)][$doc_key][self::TITLE_WORD_SCORE] = 
                        number_format(3 * $title_frequency/($title_frequency + .5 + 1.5* $title_ratio),
                        PRECISION);
                    $words[crawlHash($word_key)][$doc_key][self::DESCRIPTION_WORD_SCORE] = 0; // will set in a moment if has value
                    $words[crawlHash($word_key)][$doc_key][self::LINK_WORD_SCORE] = 0;
                }
            }

            if(isset($info[self::DESCRIPTION_WORDS])) {
                foreach($info[self::DESCRIPTION_WORDS] as $word_key => $num_occurrences) {
                    $description_frequency = $num_occurrences/$description_length;

                    $words[crawlHash($word_key)][$doc_key][self::DESCRIPTION_WORD_SCORE] = 
                        number_format(3 * $description_frequency/($description_frequency 
                        + .5 + 1.5* $description_ratio), PRECISION);
                    if(!isset($words[crawlHash($word_key)][$doc_key][self::TITLE_WORD_SCORE])) {
                        $words[crawlHash($word_key)][$doc_key][self::TITLE_WORD_SCORE] = 0;
                    }
                 
                    $words[crawlHash($word_key)][$doc_key][self::LINK_WORD_SCORE] = 0;
                }
            }

            if(isset($info[self::LINK_WORDS])) {
                foreach($info[self::LINK_WORDS] as $word_key => $num_occurrences) {
                    $link_frequency = $num_occurrences/$link_length;

                    $words[crawlHash($word_key)][$doc_key][self::LINK_WORD_SCORE] = 
                        number_format(3 * $link_frequency/($link_frequency + .5 + 1.5* $link_ratio), PRECISION);

                    if(!isset($words[crawlHash($word_key)][$doc_key][self::TITLE_WORD_SCORE])) {
                        $words[crawlHash($word_key)][$doc_key][self::TITLE_WORD_SCORE] = 0;
                    }

                    if(!isset($words[crawlHash($word_key)][$doc_key][self::DESCRIPTION_WORD_SCORE])) {
                        $words[crawlHash($word_key)][$doc_key][self::DESCRIPTION_WORD_SCORE] = 0;
                    }
                }
            }

        } // end foreach
         
        foreach($words as $word_key => $docs_info) {
            foreach($docs_info as $doc_key => $info) {
                $doc_depth =  $doc_statistics[$doc_key][self::DOC_DEPTH];
                $doc_rank = (11 - $doc_depth) + $doc_statistics[$doc_key][self::URL_WEIGHT];
                $words[$word_key][$doc_key][self::DOC_RANK] = number_format($doc_rank, PRECISION); //our proxy for page rank

                $orphan = (isset($info[self::LINK_WORDS]) && count($info[self::LINK_WORDS]) > 0 ) ? 1 : .5;

                $words[$word_key][$doc_key][self::SCORE] = number_format(
                    .8*($doc_rank) 
                    + $info[self::TITLE_WORD_SCORE]
                    + 2*$info[self::DESCRIPTION_WORD_SCORE]*$orphan 
                    + 1.5*$info[self::LINK_WORD_SCORE],  PRECISION);

            }
        }
         
        if(STORE_INLINKS_IN_DICTIONARY && isset($doc_statistics[self::INLINKS])) {
            foreach($doc_statistics[self::INLINKS] as $url_word_key => $docs_info) {
                foreach($docs_info as $doc_key) {
                    $doc_depth =  $doc_statistics[$doc_key][self::DOC_DEPTH] + 1;
                    $words[$url_word_key][$doc_key][self::TITLE_WORD_SCORE] = 0;
                    $words[$url_word_key][$doc_key][self::DESCRIPTION_WORD_SCORE] = 0;
                    $words[$url_word_key][$doc_key][self::LINK_WORD_SCORE] = 0;
                    $words[$url_word_key][$doc_key][self::DOC_RANK] = number_format(11 - $doc_depth, PRECISION);
                    $words[$url_word_key][$doc_key][self::SCORE] = number_format(11 - $doc_depth, PRECISION);
                }
            }
        }

        $this->found_sites[self::INVERTED_INDEX] = $words;

        crawlLog("  Build mini inverted index time ".(changeInMicrotime($start_time)));
    }


    /**
     *
     */
    function computeDocumentStatistics()
    {
        $doc_statistics = array();
        $this->num_seen_sites += count($this->found_sites[self::SEEN_URLS]);
        foreach($this->found_sites[self::SEEN_URLS] as $site) {
            $doc_key = crawlHash($site[self::URL]);

            $doc_statistics[$doc_key][self::URL_WEIGHT] =  3 - log(strlen($site[self::URL])); //negative except for short urls

            $title_phrase_string = mb_ereg_replace("[[:punct:]]", " ", $site[self::TITLE]); 
            $doc_statistics[$doc_key][self::TITLE_WORDS] = PhraseParser::extractPhrasesAndCount($title_phrase_string); 
            $doc_statistics[$doc_key][self::TITLE_LENGTH] = $this->sumCountArray($doc_statistics[$doc_key][self::TITLE_WORDS]);
            $this->sum_seen_site_title_length += $doc_statistics[$doc_key][self::TITLE_LENGTH];

            $description_phrase_string = mb_ereg_replace("[[:punct:]]", " ", $site[self::DESCRIPTION]);
            $doc_statistics[$doc_key][self::DESCRIPTION_WORDS] = PhraseParser::extractPhrasesAndCount($description_phrase_string);
            $doc_statistics[$doc_key][self::DESCRIPTION_LENGTH] = $this->sumCountArray($doc_statistics[$doc_key][self::DESCRIPTION_WORDS]);
            $this->sum_seen_site_description_length += $doc_statistics[$doc_key][self::DESCRIPTION_LENGTH];

            $link_phrase_string = "";
            $link_urls = array(); 
            foreach($site[self::LINKS] as $url => $link_text) {
                $link_phrase_string .= " $link_text";
                if(STORE_INLINKS_IN_DICTIONARY) {
                    $doc_statistics[self::INLINKS][crawlHash($url)][] = $doc_key;
                }
            }
            $link_phrase_string = mb_ereg_replace("[[:punct:]]", " ", $link_phrase_string);
            $doc_statistics[$doc_key][self::LINK_WORDS] = PhraseParser::extractPhrasesAndCount($link_phrase_string);
            $doc_statistics[$doc_key][self::LINK_LENGTH] = $this->sumCountArray($doc_statistics[$doc_key][self::LINK_WORDS]);
            $this->sum_seen_site_link_length += $doc_statistics[$doc_key][self::LINK_LENGTH];

            $doc_statistics[$doc_key][self::DOC_DEPTH] = log($site[self::INDEX]*NUM_FETCHERS, 10); //our proxy for page rank, 10=average links/page
        }

        $doc_statistics[self::AVERAGE_TITLE_LENGTH] = $this->sum_seen_site_title_length/$this->num_seen_sites; 

        $doc_statistics[self::AVERAGE_DESCRIPTION_LENGTH] = $this->sum_seen_site_description_length/$this->num_seen_sites; 
        
        $doc_statistics[self::AVERAGE_TOTAL_LINK_TEXT_LENGTH] = $this->sum_seen_site_link_length/$this->num_seen_sites; 
        
        crawlLog("AVERAGE TITLE LENGTH".$doc_statistics[self::AVERAGE_TITLE_LENGTH]);
        crawlLog("AVERAGE DESCRIPTION LENGTH".$doc_statistics[self::AVERAGE_DESCRIPTION_LENGTH]);
        crawlLog("AVERAGE TOTAL LINK TEXT LENGTH".$doc_statistics[self::AVERAGE_TOTAL_LINK_TEXT_LENGTH]);
        return $doc_statistics;
    }

    /**
     *
     */
    function sumCountArray(&$arr)
    {
        $sum = 0;
        foreach($arr as $key => $value) {
            $sum += $value;
        }

        return $sum;
    }
}

/**
 *
 */
$fetcher =  new Fetcher($INDEXED_FILE_TYPES, $PAGE_PROCESSORS, QUEUE_SERVER);
$fetcher->start();

?>
