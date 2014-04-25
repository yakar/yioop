<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * This Element is responsible for drawing screens in the Admin View related
 * to localization. Namely, the ability to create, delete, and text writing mode
 * for locales as well as the ability to modify translations within a locale.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class ManagelocalesElement extends Element
{

    /**
     * Responsible for drawing the ceate, delete set writing mode screen for
     * locales as well ass the screen for adding modifying translations
     *
     * @param array $data  contains info about the available locales and what
     *      has been translated
     */
    function render($data)
    {
    ?>
        <div class="current-activity">
        <?php
        if($data['FORM_TYPE'] == "search") {
            $this->renderSearchForm($data);
        } else {
            $this->renderLocaleForm($data);
        }
        $data['TABLE_TITLE'] = tl('managelocales_element_locale_list');
        $data['NO_FLOAT_TABLE'] = true;
        $data['ACTIVITY'] = 'manageLocales';
        $data['VIEW'] = $this->view;
        $this->view->helper("pagingtable")->render($data);
        ?>
        <table class="locale-table">
            <tr>
            <th><?php e(tl('managelocales_element_localename')); ?></th>
            <th><?php e(tl('managelocales_element_localetag'));?></th>
            <th><?php e(tl('managelocales_element_writingmode'));
                ?></th>
            <th><?php e(tl('managelocales_element_percenttranslated'));?></th>
            <th><?php e(tl('managelocales_element_actions'));?></th>
            </tr>
        <?php
        $base_url = '?c=admin&amp;a=manageLocales&amp;'.CSRF_TOKEN."=".
            $data[CSRF_TOKEN];
        foreach($data['LOCALES'] as $locale) {
            e("<tr><td><a href='$base_url".
                "&amp;arg=editlocale&amp;selectlocale=".$locale['LOCALE_TAG'].
                "' >". $locale['LOCALE_NAME']."</a></td><td>".
                $locale['LOCALE_TAG']."</td>");
            e("<td>".$locale['WRITING_MODE']."</td><td class='align-right' >".
                $locale['PERCENT_WITH_STRINGS']."</td>");
            e("<td><a href='$base_url"
                ."&amp;arg=deletelocale&amp;selectlocale=".
                $locale['LOCALE_TAG']."' >"
                .tl('managelocales_element_delete')."</a></td></tr>");
        }
        ?>
        </table>
        </div>
    <?php
    }

    function renderLocaleForm($data)
    {
        ?>
        <h2><?php e(tl('managelocales_element_add_locale'))?></h2>
        <form id="addLocaleForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageLocales" />
        <input type="hidden" name="arg" value="addlocale" />

        <table class="name-table">
            <tr><td><label for="locale-name"><?php
                e(tl('managelocales_element_localenamelabel'))?></label></td>
                <td><input type="text" id="locale-name"
                    name="localename" maxlength="80" class="narrow-field"/>
                </td><td></td>
            </tr>
            <tr><td><label for="locale-tag"><?php
                e(tl('managelocales_element_localetaglabel'))?></label></td>
                <td><input type="text" id="locale-tag"
                name="localetag"  maxlength="80" class="narrow-field"/></td>
            </tr>
            <tr><td><?php e(tl('managelocales_element_writingmodelabel'))?></td>
            <td><label for="locale-lr-tb">lr-tb</label><input type="radio"
                id="locale-lr-tb" name="writingmode"
                value="lr-tb" checked="checked" />
            <label for="locale-rl-tb">rl-tb</label><input type="radio"
                id="locale-rl-tb" name="writingmode" value="rl-tb" />
            <label for="locale-tb-rl">tb-rl</label><input type="radio"
                id="locale-tb-rl" name="writingmode" value="tb-rl" />
            <label for="locale-tb-lr">tb-lr</label><input type="radio"
                id="locale-tb-lr" name="writingmode" value="tb-lr" />
            </td>
            </tr>
            <tr><td></td><td class="center"><button class="button-box"
                type="submit"><?php e(tl('managelocales_element_submit'));
                ?></button></td>
            </tr>
        </table>
        </form>
        <?php
    }

    /**
     *
     */
    function renderSearchForm($data)
    {
        $controller = "admin";
        $activity = "manageLocales";
        $view = $this->view;
        $title = tl('managelocales_element_search_locales');
        $return_form_name = tl('managelocales_element_addlocale_form');
        $fields = array(
            tl('managelocales_element_localename') => "name",
            tl('managelocales_element_localetag') => "tag",
            tl('managelocales_element_writingmode') => "mode"
        );
        $dropdowns = array(
            "mode" => array("lr-tb" => "lr-rb", "rl-tb" => "rl-tb",
                "tb-rl" => "tb-rl", "tb-lr" => "tb-lr")
        );
        $view->helper("searchform")->render($data, $controller, $activity,
                $view, $title, $return_form_name, $fields, $dropdowns);
    }
}
?>
