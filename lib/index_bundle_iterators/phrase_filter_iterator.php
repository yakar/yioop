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
 * @subpackage iterator
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** 
 *Loads common constants for word indexing
 */
require_once BASE_DIR.'/lib/indexing_constants.php';

/** 
 *Loads base class for iterating
 */
require_once BASE_DIR.'/lib/index_bundle_iterators/index_bundle_iterator.php';

/**
 * Used to iterate through a collection of documents to return only those
 * which have certain restricted_phrases and don't have disallowed_phrases.
 *
 * For restricted_phrases a string like "Chris * Homepage" will match any
 * string where * has been replace by any other string. So for example it will
 * match Chris Pollett's Homepage.
 *
 * disallowed_phrases are really just disallowed words and must be an exact 
 * match
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see IndexArchiveBundle
 */
class PhraseFilterIterator extends IndexBundleIterator
{
    /**
     * The iterator we are using to get documents from
     * @var string
     */
    var $index_bundle_iterator;

    /**
     * This iterator returns only documents containing all the elements of
     * restrict phrases
     * @var array
     */
    var $restrict_phrases;

    /**
     * This iterator returns only documents not containing any the elements of
     * disallow phrases
     * @var array
     */
    var $disallow_phrases;
    /**
     * The number of documents in the current block before filtering
     * by restricted words
     * @var int
     */
    var $count_block_unfiltered;

    /**
     * The number of iterated docs before the restriction test
     * @var int
     */
    var $seen_docs_unfiltered;

    /**
     * Doc block with summaries for current doc block
     * @var array
     */
    var $summaries;

    /**
     * A weighting factor to multiply with each doc SCORE returned from this 
     * iterator
     * @var float
     */
    var $weight;

    /**
     * Creates a phrase filter iterator with the given parameters.
     *
     * @param object $index_bundle_iterator to use as a source of documents
     *      to iterate over
     * @param array $restrict_phrases this iterator returns only documents from
     *      $index_bundle_iterator containing all the elements of restrict 
     *      phrases
     * @param array $disallow_phrases this iterator returns only documents from
     *      $index_bundle_iterator not containing any of the words in disallow 
     *      phrases
     * @param float $weight a quantity to multiply each score returned from
     *      this iterator with
     * @param int $limit the first element to return from the list of docs
     *      iterated over
     */
    function __construct($index_bundle_iterator, $restrict_phrases,
        $disallow_phrases, $weight = 1, $limit = 0)
    {
        $this->index_bundle_iterator = $index_bundle_iterator;
        $this->limit = $limit;
        $this->restrict_phrases = $restrict_phrases;
        $this->disallow_phrases = $disallow_phrases;
        $this->num_docs = $this->index_bundle_iterator->num_docs;
        $this->weight = $weight;
        $this->current_block_fresh = false;
        $this->reset();
    }

    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     */
    function reset()
    {
        $this->index_bundle_iterator->reset();
        $this->seen_docs = 0;
        $this->seen_docs_unfiltered = 0;
        $beneath_limit = true;
        while($beneath_limit == true) {
            $doc_block = $this->currentDocsWithWord();
            if($doc_block == -1 || !is_array($doc_block)) {
                $beneath_limit = false;
                continue;
            }
            if($this->seen_docs + $this->count_block > $this->limit) {
                $beneath_limit = false;
                continue;
            }
            $this->advance();
        }
    }

    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
    function findDocsWithWord()
    {
        $pages = $this->index_bundle_iterator->getSummariesFromCurrentDocs();
        $this->count_block_unfiltered = count($pages);
        if(!is_array($pages)) {
            return $pages;
        }

        $out_pages = array();
        if(count($pages) > 0 ) {
            foreach($pages as $doc_key => $doc_info) {
                if(isset($doc_info[self::SUMMARY_OFFSET])) {
                    /* 
                        if have SUMMARY_OFFSET then should have tried to get 
                        TITLE, etc. 
                    */
                    $page_string = 
                        PhraseParser::extractWordStringPageSummary(
                            $doc_info[self::SUMMARY]);

                    $found = true;

                    if($this->restrict_phrases != NULL) {
                        foreach($this->restrict_phrases as $pre_phrase) {
                            $phrase_parts = explode("*", $pre_phrase);

                            $phrase = "";
                            $first= "";
                            foreach($phrase_parts as $part) {;
                                $phrase .= $first . preg_quote($part);
                                $first= '(.)*';
                            }

                            if(strlen($phrase) > 0 && 
                                mb_eregi($phrase, $page_string)  === false) {
                                $found = false;
                            }
                        }
                    }
                    if($this->disallow_phrases != NULL && 
                        is_array($this->disallow_phrases)) {
                        foreach($this->disallow_phrases as $phrase) {
                            if(strlen($phrase) > 0 && 
                                mb_eregi($phrase, $page_string)  !== false) {
                                $found = false;
                            }
                        }
                    }
                    if($found == true) {
                        $doc_info["WEIGHT"] = $this->weight;
                        $doc_info[self::SCORE] *= $this->weight;
                        $out_pages[$doc_key] = $doc_info;
                    }
                }
            }
            $pages = $out_pages;
        }
        $this->count_block = count($pages);

        if($this->seen_docs < $this->limit) {
            $total_docs = $this->seen_docs + $this->count_block;
            if($total_docs <  $this->limit) {
                $pages =array();
            } else {
                $pages = array_slice($pages, 
                    $this->limit - $this->seen_docs, NULL, true);
            }
        }
        $this->summaries = $pages;
        $this->pages = array();
        foreach($pages as $doc_key => $doc_info) {
            $this->pages[$doc_key] = $doc_info;
            unset($this->pages[$doc_key][self::SUMMARY]);
        }
        return $pages;

    }

    /**
     * Gets the summaries associated with the keys provided the keys
     * can be found in the current block of docs returned by this iterator
     * @param array $keys keys to try to find in the current block of returned
     *      results
     * @return array doc summaries that match provided keys
     */
    function getSummariesFromCurrentDocs($keys = NULL) 
    {
        if($this->current_block_fresh == false) {
            $result = $this->currentDocsWithWord();
            if(!is_array($result)) {
                return $result;
            }
        }
        if(!is_array($this->pages)) {
            return $this->pages;
        }
        if($keys == NULL) {
            $keys = array_keys($this->pages);
        }
        $out_pages = array();
        foreach($keys as $doc_key) {
            if(!isset($this->summaries[$doc_key])) {
                continue;
            } else {
                $out_pages[$doc_key] = $this->summaries[$doc_key];
            }
        }
        return $out_pages;
    }


    /**
     * Forwards the iterator one group of docs
     */
    function advance() 
    {
        $this->advanceSeenDocs();


        	$this->seen_docs_unfiltered += $this->count_block_unfiltered;



        if($this->seen_docs_unfiltered > 0) {
            $this->num_docs = 
                floor(($this->seen_docs*$this->index_bundle_iterator->num_docs)/
                $this->seen_docs_unfiltered);
        } else {
            $this->num_docs = 0;
        }

        $this->index_bundle_iterator->advance();
    }

    /**
     * Returns the index associated with this iterator
     * @return object the index
     */
    function getIndex($key = NULL)
    {
        return $this->index_bundle_iterator->getIndex($key = NULL);
    }
}
?>