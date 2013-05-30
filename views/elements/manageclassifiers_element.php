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
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * This element renders the page that lists classifiers, provides a form to
 * create new ones, and provides per-classifier action links to edit, finalize,
 * and delete the associated classifier.
 *
 * @author Shawn Tice
 * @package seek_quarry
 * @subpackage element
 */
class ManageclassifiersElement extends Element
{
    /**
     * Draws the "new classifier" form and table of existing classifiesr
     *
     * @param array $data used to pass the list of existing classifier
     *  instances
     */
    function render($data)
    {
        $base_url = "?c=admin&amp;a=manageClassifiers&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN]."&amp;arg=";
        ?>
        <div class="current-activity">
        <h2><?php e(tl('manageclassifiers_manage_classifiers')) ?></h2>
        <form id="classifiersForm" method="get" action=''>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageClassifiers" />
        <input type="hidden" name="arg" value="createclassifier" />
        <div class="top-margin"><label for="class-label"><?php
            e(tl('manageclassifiers_classifier_name')) ?></label>
            <input type="text" id="class-label" name="class_label"
                value="" maxlength="80"
                    class="wide-field"/>
            <button class="button-box"  type="submit"><?php
                e(tl('manageclassifiers_create_button')) ?></button>
        </div>
        </form>
        <?php if (!empty($data['classifiers'])) { ?>
        <h3><?php e(tl('manageclassifiers_available_classifiers')) ?></h3>
        <table class="classifiers-table">
            <tr>
                <th><?php e(tl('manageclassifiers_label_col')) ?></th>
                <th><?php e(tl('manageclassifiers_positive_col')) ?></th>
                <th><?php e(tl('manageclassifiers_negative_col')) ?></th>
                <th colspan="3"><?php
                    e(tl('manageclassifiers_actions_col')) ?></th>
            </tr>
        <?php foreach ($data['classifiers'] as $label => $classifier) { ?>
            <tr>
                <td><b><?php e($label) ?></b><br />
                    <small><?php e(date("d M Y H:i:s",
                        $classifier->timestamp)) ?></small>
                </td>
                <td><?php e($classifier->positive) ?></td>
                <td><?php e($classifier->negative) ?></td>
                <td><a href="<?php e($base_url)
                    ?>editclassifier&amp;class_label=<?php
                    e($label) ?>"><?php
                        e(tl('manageclassifiers_edit')) ?></a></td>
                <td><?php
                if ($classifier->finalized == Classifier::FINALIZED) {
                    e(tl('manageclassifiers_finalized'));
                } else if ($classifier->finalized == Classifier::UNFINALIZED) {
                    if ($classifier->total > 0) {
                        ?><a href="<?php e($base_url)
                        ?>finalizeclassifier&amp;class_label=<?php
                        e($label) ?>"><?php
                            e(tl('manageclassifiers_finalize')) ?></a><?php
                    } else {
                        e(tl('manageclassifiers_finalize'));
                    }
                } else if ($classifier->finalized == Classifier::FINALIZING) {
                    e(tl('manageclassifiers_finalizing'));
                }
                ?></td>
                <td><a href="<?php e($base_url)
                    ?>deleteclassifier&amp;class_label=<?php
                    e($label) ?>"><?php
                        e(tl('manageclassifiers_delete')) ?></a></td>
            </tr>
        <?php } // end foreach over classifiers ?>
        </table>
        <?php } // endif for available classifiers ?>
        </div>
    <?php
    }
}
?>