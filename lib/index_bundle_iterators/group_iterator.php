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
 * @subpackage iterator
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010, 2011
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** 
 *Loads base class for iterating
 */
require_once BASE_DIR.'/lib/index_bundle_iterators/index_bundle_iterator.php';

/**
 * This iterator is used to group together documents or document parts
 * which share the same url. For instance, a link document item and 
 * the document that it links to will both be stored in the IndexArchiveBundle
 * by the QueueServer. This iterator would combine both these items into
 * a single document result with a sum of their score, and a summary, if 
 * returned, containing text from both sources. The iterator's purpose is
 * vaguely analagous to a SQL GROUP BY clause
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see IndexArchiveBundle
 */
class GroupIterator extends IndexBundleIterator
{
    /**
     * The iterator we are using to get documents from
     * @var string
     */
    var $index_bundle_iterator;

    /**
     * The number of documents in the current block before filtering
     * by restricted words
     * @var int
     */
    var $count_block_unfiltered;
    /**
     * The number of documents in the current block after filtering
     * by restricted words
     * @var int
     */
    var $count_block;

    /**
     * hashes of document web pages seen in results returned from the
     * most recent call to findDocsWithWord
     * @var array
     */
    var $current_block_hashes;

    /**
     * The number of iterated docs before the restriction test
     * @var int
     */
    var $seen_docs_unfiltered;

    /**
     * hashed url keys used to keep track of track of groups seen so far
     * @var array
     */
    var $grouped_keys;

    /**
     * hashed of document web pages used to keep track of track of 
     *  groups seen so far
     * @var array
     */
    var $grouped_hashes;

    /**
     * the minimum number of pages to group from a block;
     * this trumps $this->index_bundle_iterator->results_per_block
     */
    const MIN_FIND_RESULTS_PER_BLOCK = 400;

    /**
     * Creates a group iterator with the given parameters.
     *
     * @param object $index_bundle_iterator to use as a source of documents
     *      to iterate over
     */
    function __construct($index_bundle_iterator)
    {
        $this->index_bundle_iterator = $index_bundle_iterator;
        $this->num_docs = $this->index_bundle_iterator->num_docs;
        $this->results_per_block = max(
            $this->index_bundle_iterator->results_per_block,
            self::MIN_FIND_RESULTS_PER_BLOCK);
        $this->reset();
    }

    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     */
    function reset()
    {
        $this->index_bundle_iterator->reset();
        $this->grouped_keys = array();
         $this->grouped_hashes = array();
            // -1 == never save, so file name not used using time to be safer
        $this->seen_docs = 0;
        $this->seen_docs_unfiltered = 0;
    }

    /**
     * Computes a relevancy score for a posting offset with respect to this
     * iterator and generation
     * @param int $generation the generation the posting offset is for
     * @param int $posting_offset an offset into word_docs to compute the
     *      relevance of
     * @return float a relevancy score based on BM25F.
     */
    function computeRelevance($generation, $posting_offset)
    {
        return $this->index_bundle_iterator->computeRelevance($generation,
                $posting_offset);
    }

    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
    function findDocsWithWord()
    {
        // first get a block of documents on which grouping can be done
        $pages =  $this->getPagesToGroup();
        $this->count_block_unfiltered = count($pages);
        if(!is_array($pages)) {
            return $pages;
        }

        $this->current_block_hashes = array();

        $this->current_seen_hashes = array();
        if($this->count_block_unfiltered > 0 ) {
            /* next we group like documents by url and remember which urls we've
               seen this block
            */
            $pre_out_pages = $this->groupByHashUrl($pages);

           /*get doc page for groups of link data if exists and don't have
             also aggregate by hash
           */
           $this->groupByHashAndAggregate($pre_out_pages);
           $this->count_block = count($pre_out_pages);
            /*
                Calculate aggregate values for each field of the groups we found
             */
            $pages = $this->computeBoostAndOutPages($pre_out_pages);
        }
        $this->pages = $pages;

        return $pages;

    }

    /**
     * Gets a sample of a few hundred pages on which to do grouping by URL
     * 
     * @return array of pages of document key --> meta data arrays
     */
    function getPagesToGroup()
    {
        $pages = array();
        $count = 0;
        $done = false;
        do {
            $new_pages = $this->index_bundle_iterator->currentDocsWithWord();
            if(!is_array($new_pages)) {
                $done = true;
                if(count($pages) == 0) {
                    $pages = -1;
                }
            } else {
                $pages = array_merge($pages, $new_pages);
                $count = count($pages);
            }
            if($count < $this->results_per_block && !$done) {
                $this->index_bundle_iterator->advance();
            } else {
                $done = true;
            }
        } while(!$done);
        return $pages;
    }

    /**
     * Groups documents as well as mini-pages based on links to documents by
     * url to produce an array of arrays of documents with same url. Since
     * this is called in an iterator, documents which were already returned by
     * a previous call to currentDocsWithWord() followed by an advance() will 
     * have been remembered in grouped_keys and will be ignored in the return 
     * result of this function.
     *
     * @param array &$pages pages to group
     * @return array $pre_out_pages pages after grouping
     */
    function groupByHashUrl(&$pages)
    {
        $pre_out_pages = array();
        foreach($pages as $doc_key => $doc_info) {
            if(!is_array($doc_info) || $doc_info[self::SUMMARY_OFFSET] == 
                self::NEEDS_OFFSET_FLAG) {continue;}
            $doc_info['KEY'] = $doc_key;
            $hash_url = substr($doc_key, 0, IndexShard::DOC_KEY_LEN);
            $doc_info[self::HASH] = substr($doc_key, 
                IndexShard::DOC_KEY_LEN, IndexShard::DOC_KEY_LEN);
            $doc_info[self::INLINKS] = substr($doc_key, 
                2*IndexShard::DOC_KEY_LEN, IndexShard::DOC_KEY_LEN);
            if($doc_info[self::IS_DOC]) { 
                if(!isset($pre_out_pages[$hash_url])) {
                    $pre_out_pages[$hash_url] = array();
                }
                array_unshift($pre_out_pages[$hash_url], $doc_info);
            } else {
                $pre_out_pages[$hash_url][] = $doc_info;
            }

            if(!isset($this->grouped_keys[$hash_url])) {
               /* 
                    new urls found in this block
                */
                $this->current_block_hashes[] = $hash_url;
            } else {
                unset($pre_out_pages[$hash_url]);
            }
        }
        return $pre_out_pages;
    }

    /**
     * For documents which had been previously grouped by the hash of their
     * url, groups these groups further by the hash of their pages contents.
     * For each group of groups with the same hash summary, this function
     * then selects the subgroup of with the highest aggregate score for
     * that group as its representative. The function then modifies the
     * supplied argument array to make it an array of group representatives.
     *
     * @param array &$pre_out_pages documents previously grouped by hash of url
     */
     function groupByHashAndAggregate(&$pre_out_pages)
     {
         foreach($pre_out_pages as $hash_url => $data) {
            if(!$pre_out_pages[$hash_url][0][self::IS_DOC]) {
                $hash_info_url= 
                    $pre_out_pages[$hash_url][0][self::INLINKS];
                $word_iterator = 
                     new WordIterator($hash_info_url, 
                        $this->getIndex(), true);
                $doc_array = $word_iterator->currentDocsWithWord();

                if(is_array($doc_array) && count($doc_array) == 1) {
                    $relevance =  $this->computeRelevance(
                        $word_iterator->current_generation,
                        $word_iterator->current_offset);
                    $keys = array_keys($doc_array);
                    $key = $keys[0];
                    $item = $doc_array[$key];
                    $item[self::RELEVANCE] = $relevance;
                    $item[self::SCORE] += $relevance;
                    $item['KEY'] = $key;
                    $item[self::HASH] = substr($key, 
                        IndexShard::DOC_KEY_LEN, IndexShard::DOC_KEY_LEN);
                    $item[self::INLINKS] = substr($key, 
                        2*IndexShard::DOC_KEY_LEN, IndexShard::DOC_KEY_LEN);
                    array_unshift($pre_out_pages[$hash_url], $item); 
                } 
            }

            $this->aggregateScores($pre_out_pages[$hash_url]);

            if(isset($pre_out_pages[$hash_url][0][self::HASH])) {
                $hash = $pre_out_pages[$hash_url][0][self::HASH];
                if(isset($this->grouped_hashes[$hash])) {
                    unset($pre_out_pages[$hash_url]);
                } else if(isset($this->current_seen_hashes[$hash])) {
                    $previous_url = $this->current_seen_hashes[$hash];
                    if($pre_out_pages[$previous_url][0][
                        self::HASH_SUM_SCORE] >= 
                        $pre_out_pages[$hash_url][0][self::HASH_SUM_SCORE]) {
                        unset($pre_out_pages[$hash_url]);
                    } else {
                        $this->current_seen_hashes[$hash] = $hash_url;
                        unset($pre_out_pages[$previous_url]);
                    }
                } else {
                    $this->current_seen_hashes[$hash] = $hash_url;
                }
            }
        }

     }

    /**
     * For a collection of grouped pages generates a grouped summary for each
     * group and returns an array of out pages consisting 
     * of single summarized documents for each group. These single summarized 
     * documents have aggregated scores to which a "boost" has been added. 
     * For a single summarized page, its boost is an estimate of the score of
     * the pages that would have been grouped with it had more pages from the
     * underlying iterator been examined. This is calculated by looking at
     * total number of inlinks to the page, estimating how many of these inlinks
     * would have been returned by the current iterator, then assuming the
     * scores of these pages follow a Zipfian distribution and computing the
     * appropriate integral.
     *
     * @param array &$pre_out_pages array of groups of pages for which out pages
     *      are to be generated.
     * @return array $out_pages array of single summarized documents to which a
     *      "boost" has been applied
     */
    function computeBoostAndOutPages(&$pre_out_pages)
    {
        $out_pages = array();
        foreach($pre_out_pages as $hash_url => $group_infos) {
            $out_pages[$hash_url] = $pre_out_pages[$hash_url][0];
            $out_pages[$hash_url][self::SUMMARY_OFFSET] = array();
            unset($out_pages[$hash_url][self::GENERATION]);
            for($i = 0; $i <
                $pre_out_pages[$hash_url][0][self::HASH_URL_COUNT]; $i ++) {
                $doc_info = $group_infos[$i];
                $out_pages[$hash_url][self::SUMMARY_OFFSET][] = 
                    array($doc_info["KEY"], $doc_info[self::GENERATION],
                        $doc_info[self::SUMMARY_OFFSET]);
            }

            if($this->count_block_unfiltered >=$this->results_per_block) {
                /* approximate the scores contributed to this
                   doc for this word search by links we haven't
                   reached in our grouping
                */
                $word_iterator = new WordIterator(
                    $out_pages[$hash_url][self::INLINKS], 
                    $this->getIndex(), true);
                $num_inlinks = $word_iterator->num_docs + 0.1;
                $num_docs_seen = $this->seen_docs_unfiltered + 
                    $this->count_block_unfiltered;

                $hash_count = $out_pages[$hash_url][self::HASH_URL_COUNT];

                /*
                    An attempt to approximate the total number of inlinks
                    to a document which will have the terms in question.

                    A result after grouping consists of a document and inlinks
                    which contain the terms of the base iterator.

                    $hash_count/($num_docs_seen *$num_inlinks)
                    approximates the probability that an inlink for 
                    a particular document happens to 
                    contain the terms of the iterator. After $num_docs_seen
                    many documents, there are $num_inlinks - $hash_count
                    many inlinks which might appear in the remainder of
                    the iterators document list, giving a value
                    for the $num_inlinks_not_seen as per the equation below:
                 */
                $num_inlinks_not_seen = 
                    ($num_inlinks - $hash_count)*$hash_count/
                    ($num_docs_seen * $num_inlinks);
                $total_inlinks_for_doc = $hash_count + $num_inlinks_not_seen;

                /*
                     we estimate score[x] of the xth inlink for this document
                     as approximately score[x] = score[1]x^{-alpha}
                     If n = $total_inlinks_for_doc, then by integrating this
                     from k = self::HASH_URL_COUNT to n, we get an 
                     approximation for the score we haven't seen (which
                     we call the boost). 
                     boost = score[1](n^{1-alpha} - k^{1-alpha})/(1-alpha)
                     let exponent = 1-alpha
                */
                $max_score = $out_pages[$hash_url][self::MAX];
                $exponent = 1 - ((log($max_score) -
                    log($out_pages[$hash_url][self::MIN]))/ log($num_inlinks));
                $boost = $max_score*(pow($total_inlinks_for_doc, $exponent) -
                    pow($hash_count, $exponent))/$exponent;
                /*
                  although relevance is  a log based quantity we want to
                  further penalize docs with a high rank but low relevance for
                  the underlying iterator, so we take a weighted higher order 
                  average
                 */
                $out_pages[$hash_url][self::SCORE] = 
                    ($out_pages[$hash_url][self::HASH_SUM_SCORE] + $boost) *
                    (1 + $out_pages[$hash_url][self::RELEVANCE])/2;
            } else {
                $out_pages[$hash_url][self::SCORE] = 
                    $out_pages[$hash_url][self::HASH_SUM_SCORE] *
                    (1 + $out_pages[$hash_url][self::RELEVANCE])/2;
            }

        }

        return $out_pages;
    }

    /**
     * For a collection of pages each with the same url, computes the page
     * with the min score, max score, as well as the sum of the score,
     * sum of the ranks, sum of the relevance score, and count. Stores this
     * information in the first element of the array of pages.
     */
    function aggregateScores(&$pre_hash_page)
    {
        $sum_score = 0;
        $sum_rank = 0;
        $sum_relevance = 0;
        $min = 1000000; //no score will be this big
        $max = -1;
        foreach($pre_hash_page as $hash_page) {
            if(isset($hash_page[self::SCORE])) {
                $current_score = $hash_page[self::SCORE];
                $min = ($current_score < $min ) ? $current_score : $min;
                $max = ($max < $current_score ) ? $current_score : $max;
                $sum_score += $current_score;
                $sum_rank += $hash_page[self::DOC_RANK];
                $sum_relevance += $hash_page[self::RELEVANCE];
            }
        }
        $pre_hash_page[0][self::MIN] = $min;
        $pre_hash_page[0][self::MAX] = $max;
        $pre_hash_page[0][self::HASH_SUM_SCORE] = $sum_score;
        $pre_hash_page[0][self::DOC_RANK] = $sum_rank;
        $pre_hash_page[0][self::RELEVANCE] = $sum_relevance;
        $pre_hash_page[0][self::HASH_URL_COUNT] = count($pre_hash_page);
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
            if(!isset($this->pages[$doc_key])) {
                continue;
            } else {
                $doc_info = $this->pages[$doc_key];
            }
            if(isset($doc_info[self::SUMMARY_OFFSET]) && 
                is_array($doc_info[self::SUMMARY_OFFSET])) {
                $out_pages[$doc_key] = $doc_info;
                foreach($doc_info[self::SUMMARY_OFFSET] as $offset_array) {
                    list($key, $generation, $summary_offset) = $offset_array;
                    $index = & $this->getIndex($key);
                    $index->setCurrentShard($generation, true);
                    $page = $index->getPage($summary_offset);
                    if($page == array()) {continue;}
                    if(!isset($out_pages[$doc_key][self::SUMMARY])) {
                        $out_pages[$doc_key][self::SUMMARY] = $page;
                    } else if (isset($page[self::DESCRIPTION])) {
                        if(!isset($out_pages[$doc_key][
                            self::SUMMARY][self::DESCRIPTION])) {
                            $out_pages[$doc_key][self::SUMMARY][
                                self::DESCRIPTION] = "";
                        }
                        $out_pages[$doc_key][self::SUMMARY][self::DESCRIPTION].=
                            " .. ".$page[self::DESCRIPTION];
                    }
                }
            }
        }
        return $out_pages;

    }



    /**
     * Forwards the iterator one group of docs
     * @param array $gen_doc_offset a generation, doc_offset pair. If set,
     *      the must be of greater than or equal generation, and if equal the
     *      next block must all have $doc_offsets larger than or equal to 
     *      this value
     */
    function advance($gen_doc_offset = null) 
    {
        $this->advanceSeenDocs();

        $this->seen_docs_unfiltered += $this->count_block_unfiltered;

        if($this->seen_docs_unfiltered > 0) {
            if($this->count_block_unfiltered < $this->results_per_block) {
                $this->num_docs = $this->seen_docs;
            } else {
                $this->num_docs = 
                    floor(
                    ($this->seen_docs*$this->index_bundle_iterator->num_docs)/
                    $this->seen_docs_unfiltered);
            }
        } else {
            $this->num_docs = 0;
        }
        
        
        foreach($this->current_block_hashes as $hash_url) {
            $this->grouped_keys[$hash_url] = true;
        }

        foreach($this->current_seen_hashes as $hash) {
            $this->grouped_hashes[$hash] = true;
        }

        $this->index_bundle_iterator->advance($gen_doc_offset);

    }

    /**
     * Gets the doc_offset and generation for the next document that 
     * would be return by this iterator
     *
     * @return mixed an array with the desired document offset 
     *  and generation; -1 on fail
     */
    function currentGenDocOffsetWithWord() {
        $this->index_bundle_iterator->currentGenDocOffsetWithWord();
    }


    /**
     * Returns the index associated with this iterator
     * @return &object the index
     */
    function &getIndex($key = NULL)
    {
        return $this->index_bundle_iterator->getIndex($key);
    }
}
?>
