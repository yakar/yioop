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
 * @subpackage test
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * Load the hash table library we'll be testing
 */
require_once BASE_DIR."/lib/hash_table.php";
/**
 * Load the crawlHash function
 */
require_once BASE_DIR.'/lib/utility.php';
/**
 * Used to test that the HashTable class properly stores key value pairs,
 * handles insert, deletes, collisions okay. It should also detect when
 * table is full
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage test
 */
class HashTableTest extends UnitTest
{
    /**
     * We'll use two different tables one more representative of how the table
     * is going to be used by the web_queue_bundle, the other small enough that
     * we can manually figure out what the result should be
     */
    function setUp()
    {
        $this->test_objects['FILE1'] = new HashTable(
            WORK_DIRECTORY."/hash1.txt", 20000, 8, 8);
        $this->test_objects['FILE2'] = new HashTable(WORK_DIRECTORY.
            "/hash2.txt", 10, 8, 1);
    }
    /**
     * Since a HashTable is a PersistentStructure it periodically saves
     * itself to a file. To clean up we delete the files that might be created
     */
    function tearDown()
    {
        @unlink(WORK_DIRECTORY."/hash1.txt");
        @unlink(WORK_DIRECTORY."/hash2.txt");
    }
    /**
     * Check if for the big hash table we insert something then later look it
     * up, that we in fact find it. Moreover, the value we associated with the
     * insert key is as expected
     */
    function insertLookupTestCase()
    {
        $this->assertTrue(
            $this->test_objects['FILE1']->insert(
                crawlHash("http://www.cs.sjsu.edu/",true),
                    pack("H*","0000147700000000")),
            "Insert (hash(URL), value) succeeded");
        $this->assertEqual(
            $this->test_objects['FILE1']->lookup(
                crawlHash("http://www.cs.sjsu.edu/",true)),
                    pack("H*","0000147700000000"),
            "Lookup value equals insert value");
    }
    /**
     * Checks insert an item, delete that item, then look it up. Make sure we
     * don't find it after deletion.
     */
    function insertDeleteLookupTestCase()
    {
        $this->assertTrue(
            $this->test_objects['FILE1']->insert(
                crawlHash("http://www.cs.sjsu.edu/",true),
                    pack("H*","0000147700000000")),
            "Insert (crawlHash(URL), value) succeeded");
        $this->assertTrue(
            $this->test_objects['FILE1']->delete(
                crawlHash("http://www.cs.sjsu.edu/", true)),
            "delete crawlHash(URL) succeeded");
        $this->assertFalse(
            $this->test_objects['FILE1']->lookup(
                crawlHash("http://www.cs.sjsu.edu/", true)),
            "delete crawlHash(URL) succeeded");
    }
    /**
     * Completety fill table. Next insert should fail. Then delete all the
     * items. Then check that we can't find any of them
     */
    function completeFillTestCase()
    {
        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue(
                $this->test_objects['FILE2']->insert(
                    crawlHash("hi$i", true), "$i"),
                "Insert item ".($i+1)." into table of size 10");
        }

        $this->assertFalse(
            $this->test_objects['FILE2']->insert(
                crawlHash("hi11",true), "a"),
            "Insert item 11 into table of size 10");

        for ($i = 0; $i < 10; $i++) {
            $this->assertEqual(
                $this->test_objects['FILE2']->lookup(
                    crawlHash("hi$i",true)), "$i",
                "Inserted value ".($i+1)." equals lookup value");
        }

        $this->assertFalse(
            $this->test_objects['FILE2']->lookup(
                crawlHash("hi11",true)), "a",
            "Item 11's value should not be in table");

        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue(
                $this->test_objects['FILE2']->delete(crawlHash("hi$i",true)),
                "Item ".($i+1)." delete success");
        }

        for ($i = 0; $i < 11; $i++) {
            $this->assertFalse(
                $this->test_objects['FILE2']->lookup(crawlHash("hi$i",true)),
                "Should not find Item ".($i+1)." after delete");
        }
    }
    /**
     * First check that inserting an item twice does not change its index in
     * the table. Then inserts an item which should hash to the same value. So
     * there is a collision which is resolved by linear offset. Check lookup of
     * new item succeeds.Then delete first insert, check lookup of second insert
     * still works. Check delete of second item, reinsert of first item and
     * lookup. Index should change
     */
    function reinsertCollisionAndIndexTestCase()
    {
        $this->test_objects['FILE2']->insert(crawlHash("hi7",true), "7");
        $index =
            $this->test_objects['FILE2']->lookup(crawlHash("hi7",true),
            HashTable::ALWAYS_RETURN_PROBE);

        $this->test_objects['FILE2']->insert(crawlHash("hi7",true), "z");
        $this->assertTrue(
            $this->test_objects['FILE2']->lookup(
                crawlHash("hi7",true)),
                "z", "Reinsert Item hi7 overwrites old value");

        $index2 =
            $this->test_objects['FILE2']->lookup(crawlHash("hi7",true),
                HashTable::ALWAYS_RETURN_PROBE);
        $this->assertEqual(
            $index, $index2, "Index of reinserted should not change");

        $this->assertTrue(
            $this->test_objects['FILE2']->insert(crawlHash("hi11",true), "8"),
            "Item hi11 which collides with hi7 insert okay");
        $this->assertEqual(
            $this->test_objects['FILE2']->lookup(
                crawlHash("hi11",true), HashTable::ALWAYS_RETURN_PROBE),
                $index2 + 1, "Item hi11 located one after hi7");
        $this->test_objects['FILE2']->delete(crawlHash("hi7",true));
        $this->assertEqual(
            $this->test_objects['FILE2']->lookup(
                crawlHash("hi11",true),HashTable::ALWAYS_RETURN_PROBE),
             $index2 + 1, "Item hi11 looked up succeed after hi7 deleted");
        $this->test_objects['FILE2']->delete(crawlHash("hi11",true));
        $this->test_objects['FILE2']->insert(crawlHash("hi7",true), "7");
        $this->assertEqual(
            $this->test_objects['FILE2']->lookup(
            crawlHash("hi7",true)), "7",
            "Reinserted Item hi7 lookup succeeds");
        $this->assertEqual(
            $this->test_objects['FILE2']->lookup(
                crawlHash("hi7",true), HashTable::ALWAYS_RETURN_PROBE),
                $index2 + 2,
                "New Item hi7 location does not overwrite deleted items");
    }
    /**
     * Test how fast insertion and deletions can be done
     */
    function timingTestCase()
    {
        $start_time = microtime();
        for($i = 0; $i < 10000; $i++) {
            $this->test_objects['FILE1']->insert(crawlHash("hi$i",true),
            "0000".packInt($i));
        }
        $this->assertTrue((changeInMicrotime($start_time) < 2),
            "Insert 10000 into table of size 20000 takes less than 2 seconds");
        $start_time = microtime();
        for($i = 0; $i < 10000; $i++) {
            $this->test_objects['FILE1']->delete(
                crawlHash("hi$i", true));
        }
        $this->assertTrue((changeInMicrotime($start_time) < 2),
            "Delete 10000 from table of size 20000 takes less than 2 seconds");
    }

}
?>
