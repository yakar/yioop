<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2013  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2013
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Load in locale specific tokenizing code
 */
foreach(glob(LOCALE_DIR."/*/resources/tokenizer.php")
    as $filename) {
    require_once $filename;
}
$GLOBALS["CHARGRAMS"] = $CHARGRAMS;

/**
 * Reads in constants used as enums used for storing web sites
 */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * Needed for calculateMetas and calculateLinkMetas (used in Fetcher and
 * pageOptions in AdminController)
 */
require_once BASE_DIR."/lib/url_parser.php";

/**
 * For crawlHash
 */
require_once BASE_DIR."/lib/utility.php";

/**
 * Used by numDocsTerm
 */
require_once BASE_DIR."/lib/index_manager.php";

/**
 * Library of functions used to manipulate words and phrases
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
class PhraseParser
{
    /**
     * Converts a summary of a web page into a string of space separated words
     *
     * @param array $page associative array of page summary data. Contains
     *      title, description, and links fields
     * @return string the concatenated words extracted from the page summary
     */
    static function extractWordStringPageSummary($page)
    {
        if(isset($page[CrawlConstants::TITLE])) {
            $title_phrase_string = mb_ereg_replace(PUNCT, " ",
                $page[CrawlConstants::TITLE]);
        } else {
            $title_phrase_string = "";
        }
        if(isset($page[CrawlConstants::DESCRIPTION])) {
            $description_phrase_string = mb_ereg_replace(PUNCT, " ",
                $page[CrawlConstants::DESCRIPTION]);
        } else {
            $description_phrase_string = "";
        }
        $page_string = $title_phrase_string . " " . $description_phrase_string;
        $page_string = preg_replace("/(\s)+/", " ", $page_string);

        return $page_string;
    }

    /**
     * Extracts all phrases (sequences of adjacent words) from $string. Does
     * not extract terms within those phrase. Array key indicates position
     * of phrase
     *
     * @param string $string subject to extract phrases from
     * @param string $lang locale tag for stemming
     * @param string $index_name name of index to be used a s a reference
     *      when extracting phrases
     * @param bool $exact_match whether the match has to be exact or not
     * @return array of phrases
     */
    static function extractPhrases($string, $lang = NULL, $index_name = NULL,
        $exact_match = false)
    {
        self::canonicalizePunctuatedTerms($string, $lang);
        mb_internal_encoding("UTF-8");
        //split first on punctuation as n word grams shouldn't cross punctuation
        $fragments = mb_split(PUNCT, $string);

        $stem_obj = self::getStemmer($lang);

        $t = 0;
        $stems = array();
        foreach($fragments as $fragment) {
            $terms = self::extractTermsFragment($fragment, $lang);
            if($terms == array()) { continue;}
            $num = 0;
            foreach($terms as $term) {
                if(trim($term) == "") continue;
                $pre_stem = mb_strtolower($term);
                if($stem_obj != NULL) {
                    $pre_stem = $stem_obj->stem($pre_stem);
                }
                $stems[] = $pre_stem;
                $num++;
            }
        }
        if($index_name == NULL || $num <= 1) {
            return $stems;
        }
        $whole_phrase = implode(" ", $stems);
        if($exact_match) {
            return array($whole_phrase);
        }
        $count_whole_phrase = self::numDocsTerm(crawlHash($whole_phrase, true),
            $index_name);
        if($count_whole_phrase >= MIN_RESULTS_TO_GROUP
            || $num > MAX_QUERY_TERMS / 2) {
            return array($whole_phrase);
        }
        if($index_name != 'feed' && intval($index_name) < 1367767529) {
            return $stems; //old style index before max phrase extraction
        }

        $out_phrases = array();
        $first = true;
        foreach($stems as $stem) {
            if($first) {
                $first = false;
                $last = $stem;
                $previous = $stem;
                continue;
            }
            if(strcmp($stem, $last) < 0) {
                $pre_phrase = $stem.' * '.$last;
            } else {
                $pre_phrase = $last.' * '.$stem;
            }
            $num_pre_phrase = self::numDocsTerm(crawlHash($pre_phrase, true),
                $index_name, true);
            if($num_pre_phrase == 0) {
                if(self::numDocsTerm(crawlHash($previous, true),
                    $index_name, true) <  3 * MIN_RESULTS_TO_GROUP &&
                    self::numDocsTerm(crawlHash($stem, true),
                    $index_name, true) <  3 * MIN_RESULTS_TO_GROUP) {
                    $pre_phrase = $previous;
                }

            }
            $previous = $stem;
            $out_phrases[] = $pre_phrase;
        }
        if(strpos($pre_phrase, "*") == 0)  {
            $out_phrases[] = $stem;
        }
        return array_merge($out_phrases);
    }

    /**
     *
     */
    static function numDocsTerm($word_key, $index_name, $raw = false)
    {
        if($raw == false) {
            //get rid of out modfied base64 encoding
            $hash = str_replace("_", "/", $word_key);
            $hash = str_replace("-", "+" , $hash);
            $hash .= "=";
            $word_key = base64_decode($hash);
        }
        $index = IndexManager::getIndex($index_name);
        if($index->dictionary) {
            $dictionary_info =
                $index->dictionary->getWordInfo($word_key, true);
        } else {
            return 0;
        }
        $num_generations = count($dictionary_info);
        $total_num_docs = 0;
        for($i = 0; $i < $num_generations; $i++) {
            list(, , , $num_docs) =
                $dictionary_info[$i];
            $total_num_docs += $num_docs;
        }
        return $total_num_docs;
    }

    /**
     * Extracts all phrases (sequences of adjacent words) from $string. Does
     * not extract terms within those phrase. Returns an associative array
     * of phrase => number of occurrences of phrase
     *
     * @param string $string subject to extract phrases from
     * @param string $lang locale tag for stemming
     * @return array pairs of the form (phrase, number of occurrences)
     */
    static function extractPhrasesAndCount($string, $lang = NULL)
    {

        self::canonicalizePunctuatedTerms($string, $lang);

        $phrases = self::extractPhrasesInLists($string, $lang);
        $phrase_counts = array();
        foreach($phrases as $term => $positions) {
            $phrase_counts[$term] = count($positions);
        }

        return $phrase_counts;
    }

    /**
     * Extracts all phrases (sequences of adjacent words) from $string. Does
     * extract terms within those phrase.
     *
     * @param string $string subject to extract phrases from
     * @param string $lang locale tag for stemming
     * @return array word => list of positions at which the word occurred in
     *      the document
     */
    static function extractPhrasesInLists($string, $lang = NULL)
    {
        self::canonicalizePunctuatedTerms($string, $lang);
        return self::extractMaximalTermsAndFilterPhrases($string, $lang);
    }

    /**
     * This functions tries to convert acronyms, e-mail, urls, etc into
     * a format that does not involved punctuation that will be stripped
     * as we extract phrases.
     *
     * @param &$string a string of words, etc which might involve such terms
     * @param $lang a language tag to use as part of the canonicalization
     *      process not used right now
     */
    static function canonicalizePunctuatedTerms(&$string, $lang = NULL)
    {
        //these obscure statics is because php 5.2 does not garbage collect
        //create_function's
        static $replace_function0, $replace_function1, $replace_function2;

        $acronym_pattern = "/\b[A-Za-z](\.\s*[A-Za-z])+(\.|\b)/";
        if(!isset($replace_function0)) {
            $replace_function0 = create_function('$matches', '
                $result = "_".mb_strtolower(
                    mb_ereg_replace("\.\s*", "", $matches[0]));
                return $result;');
        }
        $string = preg_replace_callback($acronym_pattern,
            $replace_function0, $string);
        $ampersand_pattern = "/[A-Za-z]+(\s*(\s(\'n|\'N)\s|\&)\s*[A-Za-z])+/";
        if(!isset($replace_function1)) {
            $replace_function1 = create_function('$matches', '
                $result = mb_strtolower(
                    mb_ereg_replace("\s*(\'n|\'N|\&)\s*", "_and_",$matches[0]));
                return $result;
            ');
        }
        $string = preg_replace_callback($ampersand_pattern, $replace_function1,
            $string);

        $url_or_email_pattern =
            '@((http|https)://([^ \t\r\n\v\f\'\"\;\,<>])*)|'.
            '([A-Z0-9._%-]+\@[A-Z0-9.-]+\.[A-Z]{2,4})@i';
        if(!isset($replace_function2)) {
            $replace_function2 = create_function('$matches', '
                $result =  mb_ereg_replace("\.", "_d_",$matches[0]);
                $result =  mb_ereg_replace("\:", "_c_",$result);
                $result =  mb_ereg_replace("\/", "_s_",$result);
                $result =  mb_ereg_replace("\@", "_a_",$result);
                $result =  mb_ereg_replace("\[", "_bo_",$result);
                $result =  mb_ereg_replace("\]", "_bc_",$result);
                $result =  mb_ereg_replace("\(", "_po_",$result);
                $result =  mb_ereg_replace("\)", "_pc_",$result);
                $result =  mb_ereg_replace("\?", "_q_",$result);
                $result =  mb_ereg_replace("\=", "_e_",$result);
                $result =  mb_ereg_replace("\&", "_a_",$result);
                $result = mb_strtolower($result);
                return $result;
            ');
        }
        $string = preg_replace_callback($url_or_email_pattern,
            $replace_function2, $string);
    }

    /**
     * Splits string according to punctuation and white space then
     * extracts (stems/char grams) of terms and n word grams from the string
     * Uses a notiona of maximal n word gram to dot eh extraction
     *
     * @param string $string to extract terms from
     * @param string $lang IANA tag to look up stemmer under
     * @return array of terms and n word grams in the order they appeared in
     *      string
     */
    static function extractMaximalTermsAndFilterPhrases($string,
        $lang = NULL)
    {
        global $CHARGRAMS;

        mb_internal_encoding("UTF-8");
        $stem_obj = self::getStemmer($lang);
        $t = 0;
        $stems = array();
        $pos_lists = array();
        $maximal_phrases = array();
        $terms = self::extractTermsFragment($string, $lang);
        if($terms == array()) {continue; }
        // make post lists and stem
        foreach($terms as $term) {
            if(trim($term) == "") continue;
            $pre_stem = mb_strtolower($term);
            if($stem_obj != NULL) {
                $pre_stem = $stem_obj->stem($pre_stem);
            }
            $stems[] = $pre_stem;
            $pos_lists[$pre_stem][] = $t;
            $t++;
        }
        $num = count($stems);
        for($i = 0; $i < $num; $i++) {
            $stem = $stems[$i];
            $maximal_phrase = $stem;
            $old_maximal = $stem;
            $pos_list = $pos_lists[$stem];
            $maximal_phrases[$maximal_phrase][] = $i;
            if(!isset($stems[$i + 1])) {
                continue;
            }
            $j = 1;
            $ignore_list = array($i);
            $num_pos_list = count($pos_list);
            $next_term = $stem;
            do {
                $old_term = $next_term;
                $next_term = $stems[$i + $j];
                $is_maximal = false;
                if($num_pos_list == count($ignore_list)) {
                    if(!isset($stems[$i + $j + 1])) {
                        $is_maximal = true;
                    }
                } else {
                    foreach($pos_list as $pos) {
                        if(in_array($pos, $ignore_list)) { continue;}
                        if((isset($stems[$pos + $j]) &&
                            $stems[$pos + $j] != $next_term) ||
                            !isset($stems[$pos + $j])){
                            $ignore_list[] = $pos;
                            $is_maximal = true; 
                            /* don't break since won't to remove all
                               phrases which separate */
                        }
                    }
                }
                if($is_maximal) {
                    if($maximal_phrase != $old_maximal) {
                        $maximal_phrases[$maximal_phrase]["cond_max"] =
                            $old_maximal;
                    }
                    $old_maximal = $maximal_phrase;
                    $maximal_phrases[$maximal_phrase][] = $i;
                    if($maximal_phrase != $stem) {
                        if(strcmp($stem, $old_term) < 0) {
                            $maximal_phrases[' '.$stem.' * '.$old_term][]
                                = $i;
                        } else {
                            $maximal_phrases[' '.$old_term.' * '.$stem][]
                                = $i;
                        }
                    }
                }
                if($maximal_phrase[0] != ' ') {
                    $maximal_phrase = ' ' . $maximal_phrase;
                }
                $maximal_phrase .= " ". $next_term;
                $j++;
            } while(isset($stems[$i + $j]) );//MAX_QUERY_TERMS);

            if($j <  MAX_QUERY_TERMS) {
                $maximal_phrases[$maximal_phrase][] = $i;
                if($maximal_phrase != $stem) {
                    if(strcmp($stem, $next_term) < 0) {
                        $maximal_phrases[' '.$stem.' * '.$next_term][] = $i;
                    } else {
                        $maximal_phrases[' '.$next_term.' * '.$stem][] = $i;
                    }
                }
            }
        }
        $out_phrases = array();
        foreach($maximal_phrases as $phrase => $pos_list) {
            if($phrase[0] == ' ') {
                if(count($pos_list) > 0) {
                    /* if count more than 1 or its in a title and maximal
                        than assume n_word gram */
                     
                    $out_phrases[trim($phrase)] = array_unique($pos_list);
                }
            } else {
                $out_phrases[$phrase] = array_unique($pos_list);
            }
        }
        return $out_phrases;
    }

    /**
     *
     */
    static function extractTermsFragment($fragment, $lang)
    {
        global $CHARGRAMS;

        mb_internal_encoding("UTF-8");

        $pre_terms = mb_split("[[:space:]]|".PUNCT, $fragment);
        if($pre_terms == array()) { return array();}
        $terms = array();
        if(isset($CHARGRAMS[$lang])) {
            foreach($pre_terms as $pre_term) {
                if($pre_term == "") { continue; }
                if(substr($pre_term, 0, 4) == 'http') {
                    $terms[]  = $pre_term; // don't chargram urls
                    continue;
                }
                $ngrams = self::getCharGramsTerm(array($pre_term), $lang);
                if(count($ngrams) > 0) {
                    $terms = array_merge($terms, $ngrams);
                }
            }
        } else {
            $terms = $pre_terms;
        }

        return $terms;
    }

    /**
     * Returns the characters n-grams for the given terms where n is the length
     * Yioop uses for the language in question. If a stemmer is used for
     * language then n-gramming is no done and this just returns an empty array
     *
     * @param array $terms the terms to make n-grams for
     * @param string $lang locale tag to determine n to be used for n-gramming
     *
     * @return array the n-grams for the terms in question
     */
    static function getCharGramsTerm($terms, $lang)
    {
        global $CHARGRAMS;

        mb_internal_encoding("UTF-8");
        if(isset($CHARGRAMS[$lang])) {
            $n = $CHARGRAMS[$lang];
        } else {
            return array();
        }

        $ngrams = array();

        foreach($terms as $term) {
            $pre_gram = $term;
            $last_pos = mb_strlen($pre_gram) - $n;
            if($last_pos < 0) {
                $ngrams[] = $pre_gram;
            } else {
                for($i = 0; $i <= $last_pos; $i++) {
                    $tmp = mb_substr($pre_gram, $i, $n);
                    if($tmp != "") {
                        $ngrams[] = $tmp;
                    }
                }
            }
        }
        return $ngrams;
    }

    /**
     * Splits supplied string based on white space, then stems each
     * terms according to the stemmer for $lanf if exists
     *
     * @param string $string to extract stemmed terms from
     * @param string $lang IANA tag to look up stemmer under
     * @return array stemmed terms if stemmer; terms otherwise
     */
    static function stemTerms($string, $lang)
    {
        $terms = mb_split("[[:space:]]", $string);
        $stem_obj = self::getStemmer($lang);
        $stems = array();
        if($stem_obj != NULL) {
            foreach($terms as $term) {
                $pre_stem = mb_strtolower($term);
                $stems[] = $stem_obj->stem($pre_stem);
            }
        } else {
            foreach($terms as $term) {
                $stems[] = mb_strtolower($term);
            }
        }

        return $stems;
    }

    /**
     * Loads and instantiates a stemmer object for a language if exists
     *
     * @param string $lang IANA tag to look up stemmer under
     * @return object stemmer object
     */
    static function getStemmer($lang)
    {
        mb_regex_encoding('UTF-8');
        mb_internal_encoding("UTF-8");
        $lower_lang = strtolower($lang); //try to avoid case sensitivity issues
        $lang_parts = explode("-", $lang);
        if(isset($lang_parts[1])) {
            $stem_class_name = ucfirst($lang_parts[0]).ucfirst($lang_parts[1]) .
                "Stemmer";
            if(!class_exists($stem_class_name)) {
                $stem_class_name = ucfirst($lang_parts[0])."Stemmer";
            }
        } else {
            $stem_class_name = ucfirst($lang)."Stemmer";
        }
        if(class_exists($stem_class_name)) {
            $stem_obj = new $stem_class_name(); //for php 5.2 compatibility
        } else {
            $stem_obj = NULL;
        }
        return $stem_obj;
    }

    /**
     * Calculates the meta words to be associated with a given downloaded
     * document. These words will be associated with the document in the
     * index for (server:apache) even if the document itself did not contain
     * them.
     *
     * @param array &$site associated array containing info about a downloaded
     *      (or read from archive) document.
     * @return array of meta words to be associate with this document
     */
    static function calculateMetas(&$site, $video_sources = array())
    {
        $meta_ids = array();

        // handles user added meta words
        if(isset($site[CrawlConstants::META_WORDS])) {
            $meta_ids = $site[CrawlConstants::META_WORDS];
        }

        /*
            Handle the built-in meta words. For example
            store the sites the doc_key belongs to,
            so you can search by site
        */
        $url_sites = UrlParser::getHostPaths($site[CrawlConstants::URL]);
        $url_sites = array_merge($url_sites,
            UrlParser::getHostSubdomains($site[CrawlConstants::URL]));
        $meta_ids[] = 'site:all';
        foreach($url_sites as $url_site) {
            if(strlen($url_site) > 0) {
                $meta_ids[] = 'site:'.$url_site;
            }
        }
        $path =  UrlParser::getPath($site[CrawlConstants::URL]);
        if(strlen($path) > 0 ) {
            $path_parts = explode("/", $path);
            $pre_path = "";
            $meta_ids[] = 'path:all';
            $meta_ids[] = 'path:/';
            foreach($path_parts as $part) {
                if(strlen($part) > 0 ) {
                    $pre_path .= "/$part";
                    $meta_ids[] = 'path:'.$pre_path;
                }
            }
        }

        $meta_ids[] = 'info:'.$site[CrawlConstants::URL];
        $meta_ids[] = 'info:'.crawlHash($site[CrawlConstants::URL]);
        $meta_ids[] = 'code:all';
        $meta_ids[] = 'code:'.$site[CrawlConstants::HTTP_CODE];
        if(UrlParser::getHost($site[CrawlConstants::URL])."/" ==
            $site[CrawlConstants::URL]) {
            $meta_ids[] = 'host:all'; //used to count number of distinct hosts
        }
        if(isset($site[CrawlConstants::SIZE])) {
            $meta_ids[] = "size:all";
            $interval = DOWNLOAD_SIZE_INTERVAL;
            $size = floor($site[CrawlConstants::SIZE]/$interval) * $interval;
            $meta_ids[] = "size:$size";
        }
        if(isset($site[CrawlConstants::TOTAL_TIME])) {
            $meta_ids[] = "time:all";
            $interval = DOWNLOAD_TIME_INTERVAL;
            $time = floor(
                $site[CrawlConstants::TOTAL_TIME]/$interval) * $interval;
            $meta_ids[] = "time:$time";
        }
        if(isset($site[CrawlConstants::DNS_TIME])) {
            $meta_ids[] = "dns:all";
            $interval = DOWNLOAD_TIME_INTERVAL;
            $time = floor(
                $site[CrawlConstants::DNS_TIME]/$interval) * $interval;
            $meta_ids[] = "dns:$time";
        }
        if(isset($site[CrawlConstants::LINKS])) {
            $num_links = count($site[CrawlConstants::LINKS]);
            $meta_ids[] = "numlinks:all";
            $meta_ids[] = "numlinks:$num_links";
            $link_urls = array_keys($site[CrawlConstants::LINKS]);
            $meta_ids[] = "link:all";
            foreach($link_urls as $url) {
                    $meta_ids[] = 'link:'.$url;
                    $meta_ids[] = 'link:'.crawlHash($url);
            }
        }
        if(isset($site[CrawlConstants::LOCATION]) &&
            count($site[CrawlConstants::LOCATION]) > 0){
            foreach($site[CrawlConstants::LOCATION] as $location) {
                $meta_ids[] = 'info:'.$location;
                $meta_ids[] = 'info:'.crawlHash($location);
                $meta_ids[] = 'location:all';
                $meta_ids[] = 'location:'.$location;
            }
        }

        if(isset($site[CrawlConstants::IP_ADDRESSES]) ){
            $meta_ids[] = 'ip:all';
            foreach($site[CrawlConstants::IP_ADDRESSES] as $address) {
                $meta_ids[] = 'ip:'.$address;
            }
        }

        $meta_ids[] = 'media:all';
        if($video_sources != array()) {
            if(UrlParser::isVideoUrl($site[CrawlConstants::URL],
                $video_sources)) {
                $meta_ids[] = "media:video";
            } else {
                $meta_ids[] = (stripos($site[CrawlConstants::TYPE],
                    "image") !== false) ? 'media:image' : 'media:text';
            }
        }
        // store the filetype info
        $url_type = UrlParser::getDocumentType($site[CrawlConstants::URL]);
        if(strlen($url_type) > 0) {
            $meta_ids[] = 'filetype:all';
            $meta_ids[] = 'filetype:'.$url_type;
        }
        if(isset($site[CrawlConstants::SERVER])) {
            $meta_ids[] = 'server:all';
            $meta_ids[] = 'server:'.strtolower($site[CrawlConstants::SERVER]);
        }
        if(isset($site[CrawlConstants::SERVER_VERSION])) {
            $meta_ids[] = 'version:all';
            $meta_ids[] = 'version:'.
                $site[CrawlConstants::SERVER_VERSION];
        }
        if(isset($site[CrawlConstants::OPERATING_SYSTEM])) {
            $meta_ids[] = 'os:all';
            $meta_ids[] = 'os:'.strtolower(
                $site[CrawlConstants::OPERATING_SYSTEM]);
        }
        if(isset($site[CrawlConstants::MODIFIED])) {
            $modified = $site[CrawlConstants::MODIFIED];
            $meta_ids[] = 'modified:all';
            $meta_ids[] = 'modified:'.date('Y', $modified);
            $meta_ids[] = 'modified:'.date('Y-m', $modified);
            $meta_ids[] = 'modified:'.date('Y-m-d', $modified);
        }
        if(isset($site[CrawlConstants::TIMESTAMP])) {
            $date = $site[CrawlConstants::TIMESTAMP];
            $meta_ids[] = 'date:all';
            $meta_ids[] = 'date:'.date('Y', $date);
            $meta_ids[] = 'date:'.date('Y-m', $date);
            $meta_ids[] = 'date:'.date('Y-m-d', $date);
            $meta_ids[] = 'date:'.date('Y-m-d-H', $date);
            $meta_ids[] = 'date:'.date('Y-m-d-H-i', $date);
            $meta_ids[] = 'date:'.date('Y-m-d-H-i-s', $date);
        }
        if(isset($site[CrawlConstants::LANG])) {
            $meta_ids[] = 'lang:all';
            $lang_parts = explode("-", $site[CrawlConstants::LANG]);
            $meta_ids[] = 'lang:'.$lang_parts[0];
            if(isset($lang_parts[1])){
                $meta_ids[] = 'lang:'.$site[CrawlConstants::LANG];
            }
        }
        if(isset($site[CrawlConstants::AGENT_LIST])) {
            foreach($site[CrawlConstants::AGENT_LIST] as $agent) {
                $meta_ids[] = 'robot:'.strtolower($agent);
            }
        }
        //Add all meta word for subdoctype
        if(isset($site[CrawlConstants::SUBDOCTYPE])){
            $meta_ids[] = $site[CrawlConstants::SUBDOCTYPE].':all';
        }

        return $meta_ids;
    }


    /**
     * Used to compute all the meta ids for a given link with $url
     * and $link_text that was on a site with $site_url.
     *
     * @param string $url url of the link
     * @param string $link_host url of the host name of the link
     * @param string $link_text text of the anchor tag link came from
     * @param string $site_url url of the page link was on
     */
    static function calculateLinkMetas($url, $link_host, $link_text, $site_url)
    {
        global $IMAGE_TYPES;
        $link_meta_ids = array();
        if(strlen($link_host) == 0) continue;
        if(substr($link_text, 0, 9) == "location:") {
            $location_link = true;
            $link_meta_ids[] = $link_text;
            $link_meta_ids[] = "location:all";
            $link_meta_ids[] = "location:".
                crawlHash($site_url);
        }
        $link_type = UrlParser::getDocumentType($url);
        $link_meta_ids[] = "media:all";
        $link_meta_ids[] = "safe:all";
        if(in_array($link_type, $IMAGE_TYPES)) {
            $link_meta_ids[] = "media:image";
            if(isset($safe) && !$safe) {
                $link_meta_ids[] = "safe:false";
            }
        } else {
            $link_meta_ids[] = "media:text";
        }
        $link_meta_ids[] = "link:all";
        return $link_meta_ids;
    }


    /**
     *  Scores documents according to the lack or nonlack of sexually explicit
     *  terms. Tries to work for several languages.
     *
     *  @param array $word_lists word => pos_list tuples
     *  @param int $len length of text being examined in characters
     *  @return int $score of how explicit document is
     */
    static function computeSafeSearchScore(&$word_lists, $len)
    {
        static $unsafe_phrase = "
XXX sex slut nymphomaniac MILF lolita lesbian sadomasochism
bondage fisting erotic vagina Tribadism penis facial hermaphrodite
transsexual tranny bestiality snuff boob fondle tit
blowjob lap cock dick hardcore pr0n fuck pussy penetration ass
cunt bisexual prostitution screw ass masturbation clitoris clit suck whore bitch
bellaco cachar chingar shimar chinquechar chichar clavar coger culear hundir
joder mámalo singar cojon carajo caray bicho concha chucha chocha
chuchamadre coño panocha almeja culo fundillo fundío puta puto teta
connorito cul pute putain sexe pénis vulve foutre baiser sein nicher nichons
puta sapatão foder ferro punheta vadia buceta bucetinha bunda caralho
mentula cunnus verpa sōpiō pipinna
cōleī cunnilingus futuō copulate cēveō crīsō
scortor meretrīx futatrix minchia coglione cornuto culo inocchio frocio puttana
vaffanculo fok hoer kut lul やりまん 打っ掛け
 二形 ふたなりゴックン ゴックン
ショタコン 全裸 受け 裏本 пизда́ хуй еба́ть
блядь елда́ гондо́н хер манда́ му́ди мудя
пидора́с залу́па жо́па за́дница буфер
雞巴 鷄巴 雞雞 鷄鷄 阴茎 陰莖 胯下物
屌 吊 小鳥 龟头 龜頭 屄 鸡白 雞白 傻屄 老二
那话儿 那話兒 屄 鸡白 雞白 阴道 陰道
阴户 陰戶 大姨妈 淫蟲 老嫖 妓女 臭婊子 卖豆腐
賣豆腐 咪咪 大豆腐 爆乳 肏操
炒饭 炒飯 cặc lồn kaltak orospu siktir sıçmak amcık";
        static $unsafe_terms = array();

        if(count($word_lists) == 0) {
            return 0;
        }

        if($unsafe_terms == array()) {
            $unsafe_lists = PhraseParser::extractPhrasesInLists($unsafe_phrase,
                "en-US");
            $unsafe_terms = array_keys($unsafe_lists);
        }

        $num_unsafe_terms = 0;
        $unsafe_count = 0;
        $words = array_keys($word_lists);

        $unsafe_found = array_intersect($words, $unsafe_terms);

        foreach($unsafe_found as $term) {
            $count = count($word_lists[$term]);
            if($count > 0 ) {
                $unsafe_count += $count;
                $num_unsafe_terms++;
            }
        }

        $score = $num_unsafe_terms * $unsafe_count/($len + 1);
        return $score;
    }
}
