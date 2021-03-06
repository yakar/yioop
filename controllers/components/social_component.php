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
 * @subpackage component
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * Used to manage the process of sending emails to users
 */
require_once BASE_DIR."/lib/mail_server.php";
/**
 * Provides activities to AdminController related to creating, updating
 * blogs (and blog entries), static web pages, and crawl mixes.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage component
 */
class SocialComponent extends Component implements CrawlConstants
{
    /**
     * Used to handle the manage group activity.
     *
     * This activity allows new groups to be created out of a set of users.
     * It allows admin rights for the group to be transferred and it allows
     * roles to be added to a group. One can also delete groups and roles from
     * groups.
     *
     * @return array $data information about groups in the system
     */
    function manageGroups()
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $possible_arguments = array("activateuser",
            "addgroup", "banuser", "changeowner",
            "creategroup", "deletegroup", "deleteuser", "editgroup",
            "inviteusers", "joingroup", "memberaccess",
            "registertype", "reinstateuser", "search", "unsubscribe",
            "voteaccess", "postlifetime");

        $data["ELEMENT"] = "managegroups";
        $data['SCRIPT'] = "";
        $data['FORM_TYPE'] = "addgroup";
        $data['MEMBERSHIP_CODES'] = array(
            INACTIVE_STATUS => tl('social_component_request_join'),
            INVITED_STATUS => tl('social_component_invited'),
            ACTIVE_STATUS => tl('social_component_active_status'),
            BANNED_STATUS => tl('social_component_banned_status')
        );
        $data['REGISTER_CODES'] = array(
            NO_JOIN => tl('social_component_no_join'),
            REQUEST_JOIN => tl('social_component_by_request'),
            PUBLIC_BROWSE_REQUEST_JOIN => tl('social_component_public_request'),
            PUBLIC_JOIN => tl('social_component_public_join')
        );
        $data['ACCESS_CODES'] = array(
            GROUP_PRIVATE => tl('social_component_private'),
            GROUP_READ => tl('social_component_read'),
            GROUP_READ_COMMENT => tl('social_component_read_comment'),
            GROUP_READ_WRITE => tl('social_component_read_write'),
            GROUP_READ_WIKI => tl('social_component_read_wiki'),
        );
        $data['VOTING_CODES'] = array(
            NON_VOTING_GROUP => tl('social_component_no_voting'),
            UP_VOTING_GROUP => tl('social_component_up_voting'),
            UP_DOWN_VOTING_GROUP => tl('social_component_up_down_voting')
        );
        $data['POST_LIFETIMES'] = array(
            FOREVER => tl('social_component_forever'),
            ONE_HOUR => tl('social_component_one_hour'),
            ONE_DAY => tl('social_component_one_day'),
            ONE_MONTH => tl('social_component_one_month'),
        );
        $search_array = array();

        $default_group = array("name" => "","id" => "", "owner" =>"",
            "register" => -1, "member_access" => -1, 'vote_access' => -1,
            "post_lifetime" => -1);
        $data['CURRENT_GROUP'] = $default_group;
        $data['PAGING'] = "";
        $name = "";
        $data['visible_users'] = "";
        $is_owner = false;
        /* start owner verify code / get current group
           $group_id is only set in this block (except creategroup) and it
           is only not NULL if $group['OWNER_ID'] == $_SESSION['USER_ID'] where
           this is also the only place group loaded using $group_id
        */
        if(isset($_REQUEST['group_id']) && $_REQUEST['group_id'] != "") {
            $group_id = $parent->clean($_REQUEST['group_id'], "int" );
            $group = $group_model->getGroupById($group_id,
                $_SESSION['USER_ID']);
            if($group && ($group['OWNER_ID'] == $_SESSION['USER_ID'] ||
                ($_SESSION['USER_ID'] == ROOT_ID && $_REQUEST['arg'] ==
                "changeowner"))) {
                $name = $group['GROUP_NAME'];
                $data['CURRENT_GROUP']['name'] = $name;
                $data['CURRENT_GROUP']['id'] = $group['GROUP_ID'];
                $data['CURRENT_GROUP']['owner'] = $group['OWNER'];
                $data['CURRENT_GROUP']['register'] =
                    $group['REGISTER_TYPE'];
                $data['CURRENT_GROUP']['member_access'] =
                    $group['MEMBER_ACCESS'];
                $data['CURRENT_GROUP']['vote_access'] =
                    $group['VOTE_ACCESS'];
                $data['CURRENT_GROUP']['post_lifetime'] =
                    $group['POST_LIFETIME'];
                $is_owner = true;
            } else if(!in_array($_REQUEST['arg'], array("deletegroup",
                "joingroup", "unsubscribe"))) {
                $group_id = NULL;
                $group = NULL;
            }
        } else if(isset($_REQUEST['name'])){
            $name = substr(trim($parent->clean($_REQUEST['name'], "string")), 0,
                SHORT_TITLE_LEN);
            $data['CURRENT_GROUP']['name'] = $name;
            $group_id = NULL;
            $group = NULL;
        } else {
            $group_id = NULL;
            $group = NULL;
        }
        /* end ownership verify */
        $data['USER_FILTER'] = "";
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "activateuser":
                    $_REQUEST['arg'] = "editgroup";
                    $user_id = (isset($_REQUEST['user_id'])) ?
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if($user_id && $group_id && $is_owner &&
                        $group_model->checkUserGroup($user_id,
                            $group_id)) {
                        $group_model->updateStatusUserGroup($user_id,
                            $group_id, ACTIVE_STATUS);
                        $this->getGroupUsersData($data, $group_id);
                        $parent->redirectWithMessage(
                            tl('accountaccess_component_user_activated'),
                            array("arg", "visible_users", 'start_row',
                            'end_row', 'num_show', 'user_filter'));
                    } else {
                        $parent->redirectWithMessage(
                            tl('accountaccess_component_no_user_activated'),
                            array("arg", "visible_users", 'start_row',
                            'end_row', 'num_show', 'user_filter'));
                    }
                break;
                case "addgroup":
                    if(($add_id = $group_model->getGroupId($name)) > 0) {
                        $register =
                            $group_model->getRegisterType($add_id);
                        if($add_id > 0 && $register && $register != NO_JOIN) {
                            $this->addGroup($data, $add_id, $register);
                            if(isset($_REQUEST['browse']) &&
                                $_REQUEST['browse'] == 'true') {
                                $_REQUEST['arg'] = 'search';
                            } else {
                                $_REQUEST['arg'] = 'none';
                            }
                            $parent->redirectWithMessage(
                                tl('social_component_joined'),
                                array('browse', 'arg'));
                        } else {
                            $parent->redirectWithMessage(
                                tl('social_component_groupname_unavailable'));
                        }
                    } else if ($name != ""){
                        $data['FORM_TYPE'] = "creategroup";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_name_available').
                            "</h1>')";
                    }
                break;
                case "banuser":
                    $_REQUEST['arg'] = "editgroup";
                    $user_id = (isset($_REQUEST['user_id'])) ?
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if($user_id && $is_owner &&
                        $group_model->checkUserGroup($user_id,
                            $group_id)) {
                        $group_model->updateStatusUserGroup($user_id,
                            $group_id, BANNED_STATUS);
                        $this->getGroupUsersData($data, $group_id);
                        $parent->redirectWithMessage(
                            tl('social_component_user_banned'),
                            array("arg", "visible_users",'start_row',
                            'end_row', 'num_show', 'user_filter'));
                    } else {
                        $parent->redirectWithMessage(
                            tl('social_component_no_user_banned'),
                            array("arg", "visible_users", 'start_row',
                            'end_row', 'num_show', 'user_filter'));
                    }
                break;
                case "changeowner":
                    $data['FORM_TYPE'] = "changeowner";
                    if(isset($_REQUEST['new_owner']) && $is_owner) {
                        $new_owner_name = substr(
                            $parent->clean($_REQUEST['new_owner'],
                            'string'), 0, NAME_LEN);
                        $new_owner = $parent->model("user")->getUser(
                            $new_owner_name);
                        if(isset($new_owner['USER_ID']) ) {
                            if($group_model->checkUserGroup(
                                $new_owner['USER_ID'], $group_id)) {
                                $group_model->changeOwnerGroup(
                                    $new_owner['USER_ID'], $group_id);
                                $_REQUEST['arg'] = "none";
                                unset($_REQUEST['group_id']);
                                $parent->redirectWithMessage(
                                    tl('social_component_owner_changed'),
                                    array("arg", 'start_row', 'end_row',
                                    'num_show', 'browse'));
                            } else {
                                $parent->redirectWithMessage(
                                    tl('social_component_not_in_group'),
                                    array("arg", 'start_row', 'end_row',
                                    'num_show'));
                            }
                        } else {
                            $parent->redirectWithMessage(
                                tl('social_component_not_a_user'),
                                array("arg", 'start_row', 'end_row',
                                'num_show'));
                        }
                    }
                break;
                case "creategroup":
                    $_REQUEST['arg'] = "addgroup";
                    if($group_model->getGroupId($name) > 0) {
                        $parent->redirectWithMessage(
                            tl('social_component_groupname_exists'));
                    } else {
                        $group_fields = array(
                            "member_access" => array("ACCESS_CODES",
                                GROUP_READ),
                            "register" => array("REGISTER_CODES",
                                REQUEST_JOIN),
                            "vote_access" => array("VOTING_CODES",
                                NON_VOTING_GROUP),
                            "post_lifetime" => array("VOTING_CODES",
                                FOREVER)
                        );
                        foreach($group_fields as $field => $info) {
                            if(!isset($_REQUEST[$field]) ||
                                !in_array($_REQUEST[$field],
                                array_keys($data[$info[0]]))) {
                                $_REQUEST[$field] = $info[1];
                            }
                        }
                        $group_model->addGroup($name,
                            $_SESSION['USER_ID'], $_REQUEST['register'],
                            $_REQUEST['member_access'],
                            $_REQUEST['vote_access'],
                            $_REQUEST['post_lifetime']);
                        //one exception to setting $group_id
                        $group_id = $group_model->getGroupId($name);
                        $parent->redirectWithMessage(
                            tl('social_component_groupname_added'),
                            array("arg", 'start_row', 'end_row', 'num_show'));
                    }
                break;
                case "deletegroup":
                    $_REQUEST['arg'] = "none";
                    $data['CURRENT_GROUP'] = $default_group;
                    if( $group_id <= 0) {
                        $parent->redirectWithMessage(
                          tl('social_component_groupname_doesnt_exists'),
                          array("arg"));
                    } else if(($group &&
                        $group['OWNER_ID'] == $_SESSION['USER_ID']) ||
                        $_SESSION['USER_ID'] == ROOT_ID) {
                        $group_model->deleteGroup($group_id);
                        $parent->redirectWithMessage(
                            tl('social_component_group_deleted'),
                            array("arg"));
                    } else {
                        $parent->redirectWithMessage(
                            tl('social_component_no_delete_group'),
                            array("arg", 'start_row', 'end_row', 'num_show'));
                    }
                break;
                case "deleteuser":
                    $_REQUEST['arg'] = "editgroup";
                    $user_id = (isset($_REQUEST['user_id'])) ?
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if($is_owner && $group_model->deletableUser(
                        $user_id, $group_id)) {
                        $group_model->deleteUserGroup(
                            $user_id, $group_id);
                        $parent->redirectWithMessage(
                            tl('social_component_user_deleted'),
                            array("arg", "visible_users", 'start_row',
                            'end_row', 'num_show', 'user_filter'));
                    } else {
                        $parent->redirectWithMessage(
                            tl('social_component_no_delete_user_group'),
                            array("arg", "visible_users", 'start_row',
                            'end_row', 'num_show', 'user_filter'));
                    }
                    $this->getGroupUsersData($data, $group_id);
                break;
                case "editgroup":
                    if(!$group_id || !$is_owner) { break;}
                    $data['FORM_TYPE'] = "editgroup";
                    $update_fields = array(
                        array('register', 'REGISTER_TYPE','REGISTER_CODES'),
                        array('member_access', 'MEMBER_ACCESS', 'ACCESS_CODES'),
                        array('vote_access', 'VOTE_ACCESS', 'VOTING_CODES'),
                        array('post_lifetime', 'POST_LIFETIME',
                            'POST_LIFETIMES')
                        );
                    $this->updateGroup($data, $group, $update_fields);
                    $data['CURRENT_GROUP']['register'] =
                        $group['REGISTER_TYPE'];
                    $data['CURRENT_GROUP']['member_access'] =
                        $group['MEMBER_ACCESS'];
                    $data['CURRENT_GROUP']['vote_access'] =
                        $group['VOTE_ACCESS'];
                    $data['CURRENT_GROUP']['post_lifetime'] =
                        $group['POST_LIFETIME'];
                    $this->getGroupUsersData($data, $group_id);
                break;
                case "inviteusers":
                    $data['FORM_TYPE'] = "inviteusers";
                    if(isset($_REQUEST['users_names']) && $is_owner) {
                        $users_string = $parent->clean($_REQUEST['users_names'],
                            "string");
                        $pre_user_names = preg_split("/\s+|\,/", $users_string);
                        $users_invited = false;
                        foreach($pre_user_names as $user_name) {
                            $user_name = trim($user_name);
                            $user = $parent->model("user")->getUser($user_name);
                            if($user) {
                                if(!$group_model->checkUserGroup(
                                    $user['USER_ID'], $group_id)) {
                                    $group_model->addUserGroup(
                                        $user['USER_ID'], $group_id,
                                        INVITED_STATUS);
                                    $users_invited = true;
                                }
                            }
                        }
                        $_REQUEST['arg'] = "editgroup";
                        if($users_invited) {
                            $parent->redirectWithMessage(
                                tl('social_component_users_invited'),
                                array("arg", "visible_users", 'start_row',
                                'end_row', 'num_show', 'user_filter'));
                        } else {
                            $parent->redirectWithMessage(
                                tl('social_component_no_users_invited'),
                                array("arg", "visible_users", 'start_row',
                                'end_row', 'num_show', 'user_filter'));
                        }
                    }
                break;
                case "joingroup":
                    $_REQUEST['arg'] = "search";
                    $user_id = (isset($_REQUEST['user_id'])) ?
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if($user_id && $group_id &&
                        $group_model->checkUserGroup($user_id,
                            $group_id, INVITED_STATUS)) {
                        $group_model->updateStatusUserGroup($user_id,
                            $group_id, ACTIVE_STATUS);
                        $parent->redirectWithMessage(
                            tl('social_component_joined'),
                            array('arg'));
                    } else {
                        $parent->redirectWithMessage(
                            tl('social_component_no_unsubscribe'),
                            array('arg', 'browse', 'start_row', 'end_row',
                            'num_show'));
                    }
                break;
                case "memberaccess":
                    $update_fields = array(
                        array('memberaccess', 'MEMBER_ACCESS', 'ACCESS_CODES'));
                    $this->updateGroup($data, $group, $update_fields);
                    $data['CURRENT_GROUP'] = $default_group;
                break;
                case "postlifetime":
                    $update_fields = array(
                        array('postlifetime', 'POST_LIFETIME',
                        'POST_LIFETIMES'));
                    $this->updateGroup($data, $group, $update_fields);
                    $data['CURRENT_GROUP'] = $default_group;
                break;
                case "voteaccess":
                    $update_fields = array(
                        array('voteaccess', 'VOTE_ACCESS', 'VOTING_CODES'));
                    $this->updateGroup($data, $group, $update_fields);
                    $data['CURRENT_GROUP'] = $default_group;
                break;
                case "registertype":
                    $update_fields = array(
                        array('registertype', 'REGISTER_TYPE',
                            'REGISTER_CODES'));
                    $this->updateGroup($data, $group, $update_fields);
                    $data['CURRENT_GROUP'] = $default_group;
                break;
                case "reinstateuser":
                    $_REQUEST['arg'] = "editgroup";
                    $user_id = (isset($_REQUEST['user_id'])) ?
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if($user_id && $group_id && $is_owner &&
                        $group_model->checkUserGroup($user_id,
                        $group_id)) {
                        $group_model->updateStatusUserGroup($user_id,
                            $group_id, ACTIVE_STATUS);
                        $this->getGroupUsersData($data, $group_id);
                        $parent->redirectWithMessage(
                            tl('social_component_user_reinstated'),
                            array("arg", "visible_users", 'start_row',
                            'end_row', 'num_show', 'user_filter'));
                    } else {
                        $parent->redirectWithMessage(
                            tl('social_component_no_user_reinstated'),
                            array("arg", "visible_users", 'start_row',
                            'end_row', 'num_show', 'user_filter'));
                    }
                break;
                case "search":
                    $data['ACCESS_CODES'][INACTIVE_STATUS * 10] =
                        tl('social_component_request_join');
                    $data['ACCESS_CODES'][INVITED_STATUS * 10] =
                        tl('social_component_invited');
                    $data['ACCESS_CODES'][BANNED_STATUS * 10] =
                        tl('social_component_banned_status');
                    $search_array =
                        $parent->tableSearchRequestHandler($data,
                        array('name', 'owner', 'register', 'access','voting',
                            'lifetime'),
                        array('register', 'access', 'voting', 'lifetime'));
                break;
                case "unsubscribe":
                    $_REQUEST['arg'] = "none";
                    $user_id = (isset($_REQUEST['user_id'])) ?
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if($user_id && $group_id &&
                        $group_model->checkUserGroup($user_id,
                        $group_id)) {
                        $group_model->deleteUserGroup($user_id,
                            $group_id);
                        $parent->redirectWithMessage(
                            tl('social_component_unsubscribe'),
                            array('arg','start_row', 'end_row',
                            'num_show'));
                    } else {
                        $parent->redirectWithMessage(
                            tl('social_component_no_unsubscribe'),
                            array('arg',  'start_row', 'end_row',
                            'num_show'));
                    }
                break;
            }
        }
        $current_id = $_SESSION["USER_ID"];
        $browse = false;
        if(isset($_REQUEST['browse']) && $_REQUEST['browse'] == 'true' &&
            $_REQUEST['arg'] == 'search') {
            $browse = true;
            $data['browse'] = 'true';
        }
        if($search_array == array()) {
            $search_array[] = array("name", "", "", "ASC");
        }
        $parent->pagingLogic($data, $group_model,
            "GROUPS", DEFAULT_ADMIN_PAGING_NUM, $search_array, "",
            array($current_id, $browse));
        return $data;
    }

    /**
     * Used to add a group to a user's list of group or to request
     * membership in a group if the group is By Request or Public
     * Request
     *
     * @param array &$data field variables to be drawn to view,
     *      we modify the SCRIPT component of this with a message
     *      regarding success of not of add attempt.
     * @param int $add_id group id to be added
     * @param int $register the registration type of the group
     */
    function addGroup(&$data, $add_id, $register)
    {
        $parent = $this->parent;
        $group_model = $parent->model('group');
        $join_type = (($register == REQUEST_JOIN ||
            $register == PUBLIC_BROWSE_REQUEST_JOIN) &&
            $_SESSION['USER_ID'] != ROOT_ID) ?
            INACTIVE_STATUS : ACTIVE_STATUS;
        $msg = ($join_type == ACTIVE_STATUS) ? "doMessage('<h1 class=\"red\" >".
            tl('social_component_group_joined').
            "</h1>')" : "doMessage('<h1 class=\"red\" >".
            tl('social_component_group_request_join').
            "</h1>')";
        $group_model->addUserGroup(
            $_SESSION['USER_ID'], $add_id, $join_type);
        $data['SCRIPT'] .= $msg;
        if(!in_array($join_type, array(REQUEST_JOIN,
            PUBLIC_BROWSE_REQUEST_JOIN) ) ){ return; }
        // if account needs to be activated email owner
        $group_info = $group_model->getGroupById($add_id,
            ROOT_ID);
        $user_model = $parent->model("user");
        $owner_info = $user_model->getUser(
            $group_info['OWNER']);
        $server = new MailServer(MAIL_SENDER, MAIL_SERVER,
            MAIL_SERVERPORT, MAIL_USERNAME, MAIL_PASSWORD,
            MAIL_SECURITY);
        $subject = tl('social_component_activate_group',
            $group_info['GROUP_NAME']);
        $current_username = $user_model->getUserName(
            $_SESSION['USER_ID']);
        $edit_user_url = NAME_SERVER . "?c=admin&a=manageGroups".
            "&arg=editgroup&group_id=$add_id&visible_users=true".
            "&user_filter=$current_username";
        $body = tl('social_component_activate_body',
            $current_username,
            $group_info['GROUP_NAME'])."\n".
            $edit_user_url . "\n\n".
            tl('social_component_notify_closing')."\n".
            tl('social_component_notify_signature');
        $message = tl(
            'social_component_notify_salutation',
            $owner_info['USER_NAME'])."\n\n";
        $message .= $body;
        $server->send($subject, MAIL_SENDER,
            $owner_info['EMAIL'], $message);
    }
    /**
     * Uses $_REQUEST and $user_id to look up all the users that a group
     * has to subject to $_REQUEST['user_limit'] and
     * $_REQUEST['user_filter']. Information about these roles is added as
     * fields to $data[NUM_USERS_GROUP'] and $data['GROUP_USERS']
     *
     * @param array& $data data for the manageGroups view.
     * @param int $group_id group to look up users for
     */
    function getGroupUsersData(&$data, $group_id)
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $data['visible_users'] = (isset($_REQUEST['visible_users']) &&
            $_REQUEST['visible_users']=='true') ? 'true' : 'false';
        if($data['visible_users'] == 'false') {
            unset($_REQUEST['user_filter']);
            unset($_REQUEST['user_limit']);
        }
        if(isset($_REQUEST['user_filter'])) {
            $user_filter = substr($parent->clean(
                $_REQUEST['user_filter'], 'string'), 0, NAME_LEN);
        } else {
            $user_filter = "";
        }
        $data['USER_FILTER'] = $user_filter;
        $data['NUM_USERS_GROUP'] =
            $group_model->countGroupUsers($group_id, $user_filter);
        if(isset($_REQUEST['group_limit'])) {
            $group_limit = min($parent->clean(
                $_REQUEST['group_limit'], 'int'),
                $data['NUM_USERS_GROUP']);
            $group_limit = max($group_limit, 0);
        } else {
            $group_limit = 0;
        }
        $data['GROUP_LIMIT'] = $group_limit;
        $data['GROUP_USERS'] =
            $group_model->getGroupUsers($group_id, $user_filter,
            $group_limit);
    }

    /**
     * Used by $this->manageGroups to check and clean $_REQUEST variables
     * related to groups, to check that a user has the correct permissions
     * if the current group is to be modfied, and if so, to call model to
     * handle the update
     *
     * @param array& $data used to add any information messages for the view
     *     about changes or non-changes to the model
     * @param array& $group current group which might be altered
     * @param array $update_fields which fields in the current group might be
     *     changed. Elements of this array are triples, the name of the
     *     group field, name of the request field to use for data, and an
     *     array of allowed values for the field
     */
    function updateGroup(&$data, &$group, $update_fields)
    {
        $parent = $this->parent;
        $changed = false;
        if(!isset($group["OWNER_ID"]) ||
            $group["OWNER_ID"] != $_SESSION['USER_ID']) {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('social_component_no_permission')."</h1>');";
            return;
        }
        foreach($update_fields as $row) {
            list($request_field, $group_field, $check_field) = $row;
            if(isset($_REQUEST[$request_field]) &&
                in_array($_REQUEST[$request_field],
                    array_keys($data[$check_field]))) {
                if($group[$group_field] != $_REQUEST[$request_field]) {
                    $group[$group_field] =
                        $_REQUEST[$request_field];
                    if(!isset($_REQUEST['change_filter'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_group_updated').
                            "</h1>');";
                    }
                    $changed = true;
                }
            } else if(isset($_REQUEST[$request_field]) &&
                $_REQUEST[$request_field] != "") {
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('social_component_unknown_access')."</h1>');";
            }
        }
        if($changed) {
            if(!isset($_REQUEST['change_filter'])) {
                $parent->model("group")->updateGroup($group);
            } else {
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('social_component_group_filter_users').
                    "</h1>');";
            }
        }
    }
    /**
     * Used to support requests related to posting, editing, modifying,
     * and deleting group feed items.
     *
     * @return array $data fields to be used by GroupfeedElement
     */
    function groupFeeds()
    {
        $parent = $this->parent;
        $controller_name =
            (get_class($parent) == "AdminController") ? "admin" : "group";
        $data["CONTROLLER"] = $controller_name;
        $other_controller_name = (get_class($parent) == "AdminController")
            ? "group" : "admin";
        $group_model = $parent->model("group");
        $user_model = $parent->model("user");
        $cron_model = $parent->model("cron");
        $cron_time = $cron_model->getCronTime("cull_old_items");
        $delta = time() - $cron_time;
        if($delta > ONE_HOUR) {
            $cron_model->updateCronTime("cull_old_items");
            $group_model->cullExpiredGroupItems();
        } else if ($delta == 0) {
            $cron_model->updateCronTime("cull_old_items");
        }
        $data["ELEMENT"] = "groupfeed";
        $data['SCRIPT'] = "";
        $data["INCLUDE_STYLES"] = array("editor");
        if(isset($_SESSION['USER_ID'])) {
            $user_id = $_SESSION['USER_ID'];
        } else {
            $user_id = PUBLIC_GROUP_ID;

        }
        $username = $user_model->getUsername($user_id);
        if(isset($_REQUEST['num'])) {
            $results_per_page = $parent->clean($_REQUEST['num'], "int");
        } else if(isset($_SESSION['MAX_PAGES_TO_SHOW']) ) {
            $results_per_page = $_SESSION['MAX_PAGES_TO_SHOW'];
        } else {
            $results_per_page = NUM_RESULTS_PER_PAGE;
        }
        if(isset($_REQUEST['limit'])) {
            $limit = $parent->clean($_REQUEST['limit'], "int");
        } else {
            $limit = 0;
        }
        if(isset($_SESSION['OPEN_IN_TABS'])) {
            $data['OPEN_IN_TABS'] = $_SESSION['OPEN_IN_TABS'];
        } else {
            $data['OPEN_IN_TABS'] = false;
        }
        $clean_array = array( "title" => "string", "description" => "string",
            "just_group_id" => "int", "just_thread" => "int",
            "just_user_id" => "int");
        $strings_array = array( "title" => TITLE_LEN, "description" =>
            MAX_GROUP_POST_LEN);
        if($user_id == PUBLIC_GROUP_ID) {
            $_SESSION['LAST_ACTIVITY']['a'] = 'groupFeeds';
        } else {
            unset($_SESSION['LAST_ACTIVITY']);
        }
        foreach($clean_array as $field => $type) {
            $$field = ($type == "string") ? "" : 0;
            if(isset($_REQUEST[$field])) {
                $tmp = $parent->clean($_REQUEST[$field], $type);
                if(isset($strings_array[$field])) {
                    $tmp = substr($tmp, 0, $strings_array[$field]);
                }
                if($user_id == PUBLIC_GROUP_ID) {
                    $_SESSION['LAST_ACTIVITY'][$field] = $tmp;
                }
                $$field = $tmp;
            }
        }
        $possible_arguments = array("addcomment", "deletepost", "addgroup",
            "newthread", "updatepost", "status", "upvote", "downvote");
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "addcomment":
                    if(!isset($_REQUEST['parent_id']) || !$_REQUEST['parent_id']
                        ||!isset($_REQUEST['group_id'])||
                        !$_REQUEST['group_id']){
                        $parent->redirectWithMessage(
                            tl('social_component_comment_error'));
                    }
                    if(!$description) {
                        $parent->redirectWithMessage(
                            tl('social_component_no_comment'));
                    }
                    $parent_id = $parent->clean($_REQUEST['parent_id'], "int");
                    $group_id = $parent->clean($_REQUEST['group_id'], "int");
                    $group = $group_model->getGroupById($group_id,
                        $user_id, true);
                    $read_comment = array(GROUP_READ_COMMENT, GROUP_READ_WRITE,
                        GROUP_READ_WIKI);
                    if(!$group || ($group["OWNER_ID"] != $user_id &&
                        !in_array($group["MEMBER_ACCESS"], $read_comment) &&
                        $user_id != ROOT_ID)) {
                        $parent->redirectWithMessage(
                            tl('social_component_no_post_access'));
                    }
                    if($parent_id >= 0) {
                        $parent_item = $group_model->getGroupItem($parent_id);
                        if(!$parent_item) {
                            $parent->redirectWithMessage(
                                tl('social_component_no_post_access'));
                        }
                    } else {
                        $parent_item = array(
                            'TITLE' => tl('social_component_join_group',
                                $username, $group['GROUP_NAME']),
                            'DESCRIPTION' =>
                                tl('social_component_join_group_detail',
                                    date("r", $group['JOIN_DATE']),
                                    $group['GROUP_NAME']),
                            'ID' => -$group_id,
                            'PARENT_ID' => -$group_id,
                            'GROUP_ID' => $group_id
                        );
                    }
                    $title = "-- ".$parent_item['TITLE'];
                    $id = $group_model->addGroupItem($parent_item["ID"],
                        $group_id, $user_id, $title, $description);
                    $followers = $group_model->getThreadFollowers(
                        $parent_item["ID"], $group['OWNER_ID'], $user_id);
                    $server = new MailServer(MAIL_SENDER, MAIL_SERVER,
                        MAIL_SERVERPORT, MAIL_USERNAME, MAIL_PASSWORD,
                        MAIL_SECURITY);
                    $post_url = "";
                    if(in_array($group['REGISTER_TYPE'], array(
                        PUBLIC_BROWSE_REQUEST_JOIN, PUBLIC_JOIN))) {
                        $post_url = BASE_URL . "?c=group&a=groupFeeds&".
                            "just_thread=" . $parent_item["ID"] . "\n";
                    }
                    $subject = tl('social_component_thread_notification',
                        $parent_item['TITLE']);
                    $body = tl('social_component_notify_body')."\n".
                        $parent_item['TITLE']."\n".
                        $post_url .
                        tl('social_component_notify_closing')."\n".
                        tl('social_component_notify_signature');
                    foreach($followers as $follower) {
                        $message = tl('social_component_notify_salutation',
                            $follower['USER_NAME'])."\n\n";
                        $message .= $body;
                        $server->send($subject, MAIL_SENDER,
                            $follower['EMAIL'], $message);
                    }
                    $parent->redirectWithMessage(
                        tl('social_component_comment_added'));
                break;
                case "addgroup":
                    $register =
                        $group_model->getRegisterType($just_group_id);
                    if($just_group_id > 0 && $register && $register != NO_JOIN){
                        $this->addGroup($data, $just_group_id, $register);
                        unset($data['SUBSCRIBE_LINK']);
                    } else {
                        $parent->redirectWithMessage(
                            tl('social_component_groupname_cant_add'));
                    }
                break;
                case "deletepost":
                    if(!isset($_REQUEST['post_id'])) {
                        $parent->redirectWithMessage(
                            tl('social_component_delete_error'));
                        break;
                    }
                    $post_id = $parent->clean($_REQUEST['post_id'], "int");
                    $success=$group_model->deleteGroupItem($post_id, $user_id);
                    $search_array = array(
                        array("parent_id", "=", $just_thread, ""));
                    $item_count = $group_model->getGroupItemCount($search_array,
                        $user_id, -1);
                    if($success) {
                        if($item_count == 0) {
                            unset($_REQUEST['just_thread']);
                        }
                        $parent->redirectWithMessage(
                            tl('social_component_item_deleted'));
                    } else {
                        $parent->redirectWithMessage(
                            tl('social_component_no_item_deleted'));
                    }
                break;
                case "downvote":
                    if(!isset($_REQUEST['group_id']) || !$_REQUEST['group_id']
                        ||!isset($_REQUEST['post_id']) ||
                        !$_REQUEST['post_id']){
                        $parent->redirectWithMessage(
                            tl('social_component_vote_error'));
                    }
                    $post_id = $parent->clean($_REQUEST['post_id'], "int");
                    $group_id = $parent->clean($_REQUEST['group_id'], "int");
                    $group = $group_model->getGroupById($group_id,
                        $user_id, true);
                    if(!$group || (!in_array($group["VOTE_ACCESS"],
                        array(UP_DOWN_VOTING_GROUP) ) ) ) {
                        $parent->redirectWithMessage(
                            tl('social_component_no_vote_access'));
                    }
                    $post_item = $group_model->getGroupItem($post_id);
                    if(!$post_item || $post_item['GROUP_ID'] != $group_id) {
                        $parent->redirectWithMessage(
                            tl('social_component_no_post_access'));
                    }
                    if($group_model->alreadyVoted($user_id, $post_id)) {
                        $parent->redirectWithMessage(
                            tl('social_component_already_voted'));
                    }
                    $group_model->voteDown($user_id, $post_id);
                    $parent->redirectWithMessage(
                        tl('social_component_vote_recorded'));
                break;
                case "newthread":
                    if(!isset($_REQUEST['group_id']) || !$_REQUEST['group_id']){
                        $parent->redirectWithMessage(
                            tl('social_component_comment_error'));
                    }
                    $group_id = $parent->clean($_REQUEST['group_id'], "int");
                    if(!$description || !$title) {
                        $parent->redirectWithMessage(
                            tl('social_component_need_title_description'));
                    }
                    $group_id = $parent->clean($_REQUEST['group_id'], "int");
                    $group =
                        $group_model->getGroupById($group_id,
                        $user_id, true);
                    $new_thread = array(GROUP_READ_WRITE, GROUP_READ_WIKI);
                    if(!$group || ($group["OWNER_ID"] != $user_id &&
                        !in_array($group["MEMBER_ACCESS"], $new_thread) &&
                        $user_id != ROOT_ID)) {
                        $parent->redirectWithMessage(
                            tl('social_component_no_post_access'));
                    }
                    $thread_id = $group_model->addGroupItem(0,
                        $group_id, $user_id, $title, $description);
                    if($user_id != $group['OWNER_ID']) {
                        $server = new MailServer(MAIL_SENDER, MAIL_SERVER,
                            MAIL_SERVERPORT, MAIL_USERNAME, MAIL_PASSWORD,
                            MAIL_SECURITY);
                        $subject = tl('social_component_new_thread_mail',
                            $group['GROUP_NAME']);
                        $post_url = BASE_URL . "?c=group&a=groupFeeds&".
                            "just_thread=" . $thread_id . "\n";
                        $owner_name = $user_model->getUsername(
                            $group['OWNER_ID']);
                        $owner = $user_model->getUser($owner_name);
                        $body = tl('social_component_new_thread_body',
                            $group['GROUP_NAME'])."\n".
                            "\"".$title."\"\n".
                            $post_url .
                            tl('social_component_notify_closing')."\n".
                            tl('social_component_notify_signature');
                        $message = tl('social_component_notify_salutation',
                            $owner_name)."\n\n";
                        $message .= $body;
                        $server->send($subject, MAIL_SENDER,
                            $owner['EMAIL'], $message);
                    }
                    $parent->redirectWithMessage(
                        tl('social_component_thread_created'));
                break;
                case "status":
                    $data['REFRESH'] = "feedstatus";
                break;
                case "updatepost":
                    if(!isset($_REQUEST['post_id'])) {
                        $parent->redirectWithMessage(
                            tl('social_component_comment_error'));
                    }
                    if(!$description || !$title) {
                        $parent->redirectWithMessage(
                            tl('social_component_need_title_description'));
                    }
                    $post_id =$parent->clean($_REQUEST['post_id'], "int");
                    $action = "updatepost" . $post_id;
                    if(!$parent->checkCSRFTime(CSRF_TOKEN, $action)) {
                        $parent->redirectWithMessage(
                            tl('social_component_post_edited_elsewhere'));
                    }
                    $items = $group_model->getGroupItems(0, 1, array(
                        array("post_id", "=", $post_id, "")), $user_id);
                    if(isset($items[0])) {
                        $item = $items[0];
                    } else {
                        $parent->redirectWithMessage(
                            tl('social_component_no_update_access'));
                    }
                    $group_id = $item['GROUP_ID'];
                    $group = $group_model->getGroupById($group_id, $user_id,
                        true);
                    $update_thread = array(GROUP_READ_WRITE, GROUP_READ_WIKI);
                    if($post_id != $item['PARENT_ID'] && $post_id > 0) {
                        $update_thread[] = GROUP_READ_COMMENT;
                    }
                    if(!$group || ($group["OWNER_ID"] != $user_id &&
                        !in_array($group["MEMBER_ACCESS"], $update_thread) &&
                        $user_id != ROOT_ID)) {
                        $parent->redirectWithMessage(
                            tl('social_component_no_update_access'));
                        break;
                    }
                    $group_model->updateGroupItem($post_id, $title,
                        $description);
                    $parent->redirectWithMessage(
                        tl('social_component_post_updated'));
                break;
                case "upvote":
                    if(!isset($_REQUEST['group_id']) || !$_REQUEST['group_id']
                        ||!isset($_REQUEST['post_id']) ||
                        !$_REQUEST['post_id']){
                        $parent->redirectWithMessage(
                            tl('social_component_vote_error'));
                    }
                    $post_id = $parent->clean($_REQUEST['post_id'], "int");
                    $group_id = $parent->clean($_REQUEST['group_id'], "int");
                    $group = $group_model->getGroupById($group_id, $user_id,
                        true);
                    if(!$group || (!in_array($group["VOTE_ACCESS"],
                        array(UP_VOTING_GROUP, UP_DOWN_VOTING_GROUP) ) ) ) {
                        $parent->redirectWithMessage(
                            tl('social_component_no_vote_access'));
                    }
                    $post_item = $group_model->getGroupItem($post_id);
                    if(!$post_item || $post_item['GROUP_ID'] != $group_id) {
                        $parent->redirectWithMessage(
                            tl('social_component_no_post_access'));
                    }
                    if($group_model->alreadyVoted($user_id, $post_id)) {
                        $parent->redirectWithMessage(
                            tl('social_component_already_voted'));
                    }
                    $group_model->voteUp($user_id, $post_id);
                    $parent->redirectWithMessage(
                        tl('social_component_vote_recorded'));
                break;
            }
        }
        $view_mode = (isset($_REQUEST['v'])) ? 
            $parent->clean($_REQUEST['v'], "string") :
            ((isset($_SESSION['view_mode'])) ? $_SESSION['view_mode'] :
            "ungrouped");
        $_SESSION['view_mode'] = $view_mode;
        $view_mode = (!$just_group_id && !$just_user_id
            && !$just_thread) ? $view_mode : "ungrouped";
        if($view_mode == "grouped") {
            return $this->calculateGroupedFeeds($user_id, $limit,
                $results_per_page, $controller_name, $other_controller_name,
                $data);
        }
        $groups_count = 0;
        $page = array();
        if(!$just_user_id && (!$just_thread || $just_thread < 0)) {
            $search_array = array(
                array("group_id", "=", max(-$just_thread, $just_group_id), ""),
                array("access", "!=", GROUP_PRIVATE, ""),
                array("status", "=", ACTIVE_STATUS, ""),
                array("join_date", "=","", "DESC"));
            $groups = $group_model->getRows(
                0, $limit + $results_per_page, $groups_count,
                $search_array, array($user_id, false));
            $pages = array();
            foreach($groups as $group) {
                $page = array();
                $page['USER_ICON'] = "resources/anonymous.png";
                $page[self::TITLE] = tl('social_component_join_group',
                    $username, $group['GROUP_NAME']);
                $page[self::DESCRIPTION] =
                    tl('social_component_join_group_detail',
                        date("r", $group['JOIN_DATE']), $group['GROUP_NAME']);
                $page['ID'] = -$group['GROUP_ID'];
                $page['PARENT_ID'] = -$group['GROUP_ID'];
                $page['USER_NAME'] = "";
                $page['USER_ID'] = "";
                $page['GROUP_ID'] = $group['GROUP_ID'];
                $page[self::SOURCE_NAME] = $group['GROUP_NAME'];
                $page['MEMBER_ACCESS'] = $group['MEMBER_ACCESS'];
                $page['STATUS'] = $group['STATUS'];
                if($group['OWNER_ID'] == $user_id || $user_id == ROOT_ID) {
                    $page['MEMBER_ACCESS'] = GROUP_READ_WIKI;
                }
                $page['PUBDATE'] = $group['JOIN_DATE'];
                $pages[$group['JOIN_DATE']] = $page;
            }
        }
        $pub_clause = array('pub_date', "=", "", "DESC");
        $sort = "krsort";
        if($just_thread) {
            $thread_parent = $group_model->getGroupItem($just_thread);
            if(isset($thread_parent["TYPE"]) &&
                $thread_parent["TYPE"] == WIKI_GROUP_ITEM) {
                $page_info = $group_model->getPageInfoByThread($just_thread);
                if(isset($page_info["PAGE_NAME"])) {
                    $data["WIKI_PAGE_NAME"] = $page_info["PAGE_NAME"];
                    $data["WIKI_QUERY"] = "?c=$controller_name&amp;".
                        "a=wiki&amp;arg=edit&amp;page_name=".
                        $page_info['PAGE_NAME']."&amp;locale_tag=".
                        $page_info["LOCALE_TAG"]."&amp;group_id=".
                        $page_info["GROUP_ID"];
                }
            }
            if((!isset($_REQUEST['f']) ||
                !in_array($_REQUEST['f'], array("rss", "json", "serial")))) {
                $pub_clause = array('pub_date', "=", "", "ASC");
                $sort = "ksort";
                $group_model->incrementThreadViewCount($just_thread);
            }
        }
        $search_array = array(
            array("parent_id", "=", $just_thread, ""),
            array("group_id", "=", $just_group_id, ""),
            array("user_id", "=", $just_user_id, ""),
            $pub_clause);
        $for_group = ($just_group_id) ? $just_group_id : (($just_thread) ?
            -2 : -1);
        $item_count = $group_model->getGroupItemCount($search_array, $user_id,
            $for_group);
        $group_items = $group_model->getGroupItems(0,
            $limit + $results_per_page, $search_array, $user_id, $for_group);
        $recent_found = false;
        $time = time();
        $j = 0;
        $parser = new WikiParser("", array(), true);
        $locale_tag = getLocaleTag();
        $page = false;
        $pages = array();
        $math = false;
        foreach($group_items as $item) {
            $page = $item;
            $page['USER_ICON'] = $user_model->getUserIconUrl($page['USER_ID']);
            $page[self::TITLE] = $page['TITLE'];
            unset($page['TITLE']);
            $description = $page['DESCRIPTION'];
            //start code for sharing crawl mixes
            preg_match_all("/\[\[([^\:\n]+)\:mix(\d+)\]\]/", $description,
                $matches);
            $num_matches = count($matches[0]);
            for($i = 0; $i < $num_matches; $i++) {
                $match = preg_quote($matches[0][$i]);
                $match = str_replace("@","\@", $match);
                $replace = "<a href='?c=admin&amp;a=mixCrawls".
                    "&amp;arg=importmix&amp;".CSRF_TOKEN."=".
                    $parent->generateCSRFToken($user_id).
                    "&amp;timestamp={$matches[2][$i]}'>".
                    $matches[1][$i]."</a>";
                $description = preg_replace("@".$match."@u", $replace,
                    $description);
                $page["NO_EDIT"] = true;
            }
            //end code for sharing crawl mixes
            $page[self::DESCRIPTION] = $parser->parse($description);
            $page[self::DESCRIPTION] =
                $group_model->insertResourcesParsePage($item['GROUP_ID'], -1,
                $locale_tag, $page[self::DESCRIPTION]);
            if(!$math && strpos($page[self::DESCRIPTION], "`") !== false) {
                $math = true;
                if(!isset($data["INCLUDE_SCRIPTS"])) {
                    $data["INCLUDE_SCRIPTS"] = array();
                }
                $data["INCLUDE_SCRIPTS"][] = "math";
            }
            unset($page['DESCRIPTION']);
            $page['OLD_DESCRIPTION'] = $description;
            $page[self::SOURCE_NAME] = $page['GROUP_NAME'];
            unset($page['GROUP_NAME']);
            if($item['OWNER_ID'] == $user_id || $user_id == ROOT_ID) {
                $page['MEMBER_ACCESS'] = GROUP_READ_WIKI;
            }
            if(!$recent_found && !$math && $time - $item["PUBDATE"] <
                5 * ONE_MINUTE) {
                $recent_found = true;
                $data['SCRIPT'] .= 'doUpdate();';
            }
            $pages[$item["PUBDATE"] . sprintf("%04d", $j)] = $page;
            $j++;
        }
        if($pages) {
            $sort($pages);
        }
        $data['SUBTITLE'] = "";
        if($just_thread != "" && isset($page[self::TITLE])) {
            $title = $page[self::TITLE];
            $data['SUBTITLE'] = trim($title, "\- \t\n\r\0\x0B");
            $data['ADD_PAGING_QUERY'] = "&amp;just_thread=$just_thread";
            $data['JUST_THREAD'] = $just_thread;
            $group = $group_model->getGroupById($page['GROUP_ID'], $user_id);
            $data['GROUP_STATUS'] = $group['STATUS'];
        } else if($just_thread != "" && !isset($page[self::TITLE])) {
            $data['NO_POSTS_IN_THREAD'] = true;
        }
        if(!$just_group_id && !$just_thread) {
           $data['GROUP_STATUS'] = ACTIVE_STATUS;
        }
        if($just_group_id) {
            $group = $group_model->getGroupById($just_group_id, $user_id);
            $data['GROUP_STATUS'] = $group['STATUS'];
            if(!isset($page[self::SOURCE_NAME]) ) {
                $page[self::SOURCE_NAME] = $group['GROUP_NAME'];
                $data['NO_POSTS_YET'] = true;
                if($user_id == $group['OWNER_ID'] || $user_id == ROOT_ID) {
                        // this case happens when a group is no read
                        $data['NO_POSTS_START_THREAD'] = true;
                }
            }
            if($user_id != PUBLIC_USER_ID &&
                !$group_model->checkUserGroup($user_id, $just_group_id)) {
                $data['SUBSCRIBE_LINK'] = $group_model->getRegisterType(
                    $just_group_id);
            }
            $data['SUBTITLE'] = $page[self::SOURCE_NAME];
            $data['ADD_PAGING_QUERY'] = "&amp;just_group_id=$just_group_id";
            $data['JUST_GROUP_ID'] = $just_group_id;
        }
        if($just_user_id && isset($page["USER_NAME"])) {
            $data['SUBTITLE'] = $page["USER_NAME"];
            $data['ADD_PAGING_QUERY'] = "&amp;just_user_id=$just_user_id";
            $data['JUST_USER_ID'] = $just_user_id;
        }
        if($pages) {
            $pages = array_slice($pages, $limit, $results_per_page);
        }
        $data['TOTAL_ROWS'] = $item_count + $groups_count;
        $data['LIMIT'] = $limit;
        $data['RESULTS_PER_PAGE'] = $results_per_page;
        $data['PAGES'] = $pages;
        $data['PAGING_QUERY'] = "./?c=$controller_name&amp;a=groupFeeds";
        $data['OTHER_PAGING_QUERY'] =
            "./?c=$other_controller_name&amp;a=groupFeeds";
        $this->initializeWikiEditor($data, -1);
        return $data;
    }
    /**
     * Used to set up GroupfeedView to draw a users group feeds grouped
     * by group names as opposed to as a linear list of thread and post
     * titles
     *
     * @param int $user_id id of current user
     * @param int $limit lower bound on the groups to display feed data for
     * @param int $results_per_page number of groups to display feed data
     *      for
     * @param string $controller_name name of controller on which this
     *      this component lives (either admin or group). Used by
     *      view to draw expand or collapse link
     * @param string $other_controller_name opposite of controller_name. I.e.,
     *      if $controller_name was admin then group and vice-versa. (could
     *      caluclated but sending as a parameter as calc already done)
     * @param array $data field data for view to draw itself
     */
    function calculateGroupedFeeds($user_id, $limit, $results_per_page,
        $controller_name, $other_controller_name, $data)
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $data['MODE'] = 'grouped';
        $data['GROUPS'] = $group_model->getRows($limit, $results_per_page,
                $data['NUM_GROUPS'], array(), array($user_id, false));
        $num_shown = count($data['GROUPS']);
        for ($i = 0; $i < $num_shown; $i++) {
            $search_array = array(array("group_id", "=",
                $data['GROUPS'][$i]['GROUP_ID'], ""),
                array("pub_date", "", "", "DESC"));
            $item = $group_model->getGroupItems(0, 1, $search_array, $user_id);
            $data['GROUPS'][$i]['NUM_POSTS'] = $group_model->getGroupItemCount(
                $search_array, $user_id);
            $data['GROUPS'][$i]['NUM_THREADS']=$group_model->getGroupItemCount(
                $search_array, $user_id, $data['GROUPS'][$i]['GROUP_ID']);
            $data['GROUPS'][$i]['NUM_PAGES'] = $group_model->getGroupPageCount(
                $data['GROUPS'][$i]['GROUP_ID']);
            if (isset($item[0]['TITLE'])) {
                $data['GROUPS'][$i]["ITEM_TITLE"] = $item[0]['TITLE'];
                $data['GROUPS'][$i]["THREAD_ID"] = $item[0]['PARENT_ID'];
            } else {
                $data['GROUPS'][$i]["ITEM_TITLE"] =
                    tl('accountaccess_component_no_posts_yet');
                $data['GROUPS'][$i]["THREAD_ID"] = -1;
            }
        }
        $data['NUM_SHOWN'] = $num_shown;
        $data['LIMIT'] = $limit;
        $data['RESULTS_PER_PAGE'] = $results_per_page;
        $data['PAGING_QUERY'] = "./?c=$controller_name&amp;a=groupFeeds";
        $data['OTHER_PAGING_QUERY'] =
            "./?c=$other_controller_name&amp;a=groupFeeds";
        return $data;
    }
    /**
     * Handles requests to reading, editing, viewing history, reverting, etc
     * wiki pages
     * @return $data an associative array of form variables used to draw
     *     the appropriate wiki page
     */
    function wiki()
    {
        $parent = $this->parent;
        $controller_name =
            (get_class($parent) == "AdminController") ? "admin" : "group";
        $data = array();
        $data["CONTROLLER"] = $controller_name;
        $other_controller_name = (get_class($parent) == "AdminController")
            ? "group" : "admin";

        $data["ELEMENT"] = "wiki";
        $data["VIEW"] = "wiki";
        $data["SCRIPT"] = "";
        $data["INCLUDE_STYLES"] = array("editor");
        $group_model = $parent->model("group");
        $locale_tag = getLocaleTag();
        $data['CURRENT_LOCALE_TAG'] = $locale_tag;
        if(isset($_SESSION['USER_ID'])) {
            $user_id = $_SESSION['USER_ID'];
            $data['ADMIN'] = 1;
        } else {
            $user_id = PUBLIC_USER_ID;
        }
        $search_translation = tl('social_component_search');
        $search_form = <<<EOD
<form method="get" class="search-box $2-search-box" >
<input type='hidden' name="its" value='$1' />
<input type='text'  name='q'  value="" placeholder='$3'
    title='$3' class='search-input' />
<button type="submit" class='search-button'><img
    src='./resources/search-button.png'  alt='$search_translation'/></button>
</form>
EOD;
        $additional_substitutions[] = array('/{{\s*search\s*:\s*(.+?)\s*\|'.
            '\s*size\s*:\s*(.+?)\s*\|\s*placeholder\s*:\s*(.+?)}}/',
            $search_form);
        $clean_array = array(
            "group_id" => "int",
            "page_name" => "string",
            "page" => "string",
            "edit_reason" => "string",
            "filter" => 'string',
            "limit" => 'int',
            "num" => 'int',
            "page_id" => 'int',
            "show" => 'int',
            "diff" => 'int',
            "diff1" => 'int',
            "diff2" => 'int',
            "revert" => 'int',
        );
        $strings_array = array(
            "page_name" => TITLE_LEN,
            "page" => MAX_GROUP_PAGE_LEN,
            "edit_reason" => SHORT_TITLE_LEN,
            "filter" => SHORT_TITLE_LEN);
        $last_care_missing = 2;
        $missing_fields = false;
        $i = 0;
        if($user_id == PUBLIC_USER_ID) {
            $_SESSION['LAST_ACTIVITY']['a'] = 'wiki';
        } else {
            unset($_SESSION['LAST_ACTIVITY']);
        }
        foreach($clean_array as $field => $type) {
            if(isset($_REQUEST[$field])) {
                $tmp = $parent->clean($_REQUEST[$field], $type);
                if(isset($strings_array[$field])) {
                    $tmp = substr($tmp, 0, $strings_array[$field]);
                }
                if($field == "page_name") {
                    $tmp = str_replace(" ", "_", $tmp);
                }
                $$field = $tmp;
                if($user_id == PUBLIC_USER_ID) {
                    $_SESSION['LAST_ACTIVITY'][$field] = $tmp;
                }
            } else if($i < $last_care_missing) {
                $$field = false;
                $missing_fields = true;
            }
            $i++;
        }
        if(isset($_REQUEST['group_id']) && $_REQUEST['group_id']) {
            $group_id = $parent->clean($_REQUEST['group_id'], "int");
        } else if(isset($page_id)) {
            $page_info = $group_model->getPageInfoByPageId($page_id);
            if(isset($page_info["GROUP_ID"])) {
                $group_id = $page_info["GROUP_ID"];
                unset($page_info);
            } else {
                $group_id = PUBLIC_GROUP_ID;
            }
        } else {
            $group_id = PUBLIC_GROUP_ID;
        }
        $group = $group_model->getGroupById($group_id, $user_id);
        $data["CAN_EDIT"] = false;
        if((isset($_REQUEST['c'])) && $_REQUEST['c'] == "api"){
            $data['MODE'] = 'api';
            $data['VIEW'] = 'api';
        }else {
            $data["MODE"] = "read";
        }
        if(!$group) {
            if($data['MODE'] !== 'api'){
                $parent->redirectWithMessage(
                    tl("group_controller_no_group_access"));
            }else{
                $data['errors'] =  array();
                $data['errors'][] = tl("group_controller_no_group_access");
            }
            $group_id = PUBLIC_GROUP_ID;
            $group = $group_model->getGroupById($group_id, $user_id);
        } else {
            if($group["OWNER_ID"] == $user_id ||
                ($group["STATUS"] == ACTIVE_STATUS &&
                $group["MEMBER_ACCESS"] == GROUP_READ_WIKI)) {
                $data["CAN_EDIT"] = true;
            }
        }
        $page_defaults = array(
            'page_type' => 'standard',
            'page_alias' => '',
            'page_border' => 'solid',
            'toc' => true,
            'title' => '',
            'author' => '',
            'robots' => '',
            'description' => '',
            'page_header' => '',
            'page_footer' => ''
        );
        $data['page_types'] = array(
            "standard" => tl('social_component_standard_page'),
            "page_alias" => tl('social_component_page_alias'),
            "media_list" => tl('social_component_media_list'),
            "presentation" => tl('social_component_presentation')
        );
        $data['page_borders'] = array(
            "solid-border" => tl('social_component_solid'),
            "dashed-border" => tl('social_component_dashed'),
            "none" => tl('social_component_none')
        );
        if($group_id == PUBLIC_GROUP_ID) {
            $read_address = "[{controller_and_page}]";
        } else {
            $read_address = "?c=[{controller}]&amp;a=wiki&amp;".
                "arg=read&amp;group_id=$group_id&amp;page_name=";
        }
        if(isset($_REQUEST["arg"])) {
            switch($_REQUEST["arg"])
            {
                case "edit":
                    if(!$data["CAN_EDIT"]) { continue; }
                    if(isset($_REQUEST['caret']) &&
                       isset($_REQUEST['scroll_top'])
                            && !isset($page)) {
                        $caret = $parent->clean($_REQUEST['caret'],
                            'int');
                        $scroll_top= $parent->clean($_REQUEST['scroll_top'],
                            'int');
                        $data['SCRIPT'] .= "wiki = elt('wiki-page');".
                            "if (wiki.setSelectionRange) { " .
                            "   wiki.focus();" .
                            "   wiki.setSelectionRange($caret, $caret);".
                            "} ".
                            "wiki.scrollTop = $scroll_top;";
                    }
                    if(isset($page)){
                        $data["MODE"] = "read";
                    }else{
                        $data["MODE"] = "edit";
                    }
                    $page_info = $group_model->getPageInfoByName($group_id,
                        $page_name, $locale_tag, 'resources');
                    /* if page not yet created than $page_info will be null
                       so in the below $page_info['ID'] won't be set.
                     */
                    if($missing_fields) {
                        $parent->redirectWithMessage(
                            tl("group_controller_missing_fields"));
                    } else if(!$missing_fields && isset($page)) {
                        $action = "wikiupdate_".
                            "group=".$group_id."&page=".$page_name;
                        if(!$parent->checkCSRFTime(CSRF_TOKEN, $action)) {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('social_component_wiki_edited_elsewhere').
                                "</h1>');";
                            break;
                        }
                        $write_head = false;
                        $head_vars = array();
                        $page_types = array_keys($data['page_types']);
                        $page_borders = array_keys($data['page_borders']);
                        foreach($page_defaults as $key => $default) {
                            $head_vars[$key] = $default;
                            if(isset($_REQUEST[$key])) {
                                $head_vars[$key] =  trim(
                                    $parent->clean($_REQUEST[$key], "string"));
                                if($key == 'page_type') {
                                    if(!in_array($head_vars[$key],
                                        $page_types)) {
                                        $head_vars[$key] = $default;
                                    }
                                } else if($key == 'page_borders') {
                                    if(!in_array($head_vars[$key],
                                        $page_types)) {
                                        $head_vars[$key] = $default;
                                    }
                                } else {
                                    $head_vars[$key] =
                                        trim(preg_replace("/\n+/", "\n",
                                        $head_vars[$key]));
                                }
                                if($head_vars[$key] != $default) {
                                    $write_head = true;
                                }
                            } else if($key == 'toc') {
                                if(isset($_REQUEST['title'])) {
                                    $head_vars[$key] = false;
                                } else {
                                    $head_vars[$key] == true;
                                }
                            }
                        }
                        if($write_head) {
                            $head_string = "";
                            foreach($page_defaults as $key => $default) {
                                $head_string .= $key."=".$head_vars[$key].
                                    "\n\n";
                            }
                            $page = $head_string."END_HEAD_VARS".$page;
                        }
                        $group_model->setPageName($user_id,
                            $group_id, $page_name, $page,
                            $locale_tag, $edit_reason,
                            tl('group_controller_page_created', $page_name),
                            tl('group_controller_page_discuss_here'),
                            $read_address, $additional_substitutions);
                        $parent->redirectWithMessage(
                            tl("group_controller_page_saved"),
                            array('arg', 'page_name', 'settings',
                            'caret', 'scroll_top','back_params'));
                    } else if(!$missing_fields &&
                        isset($_FILES['page_resource']['name']) &&
                        $_FILES['page_resource']['name'] !="") {
                        if(!isset($page_info['ID'])) {
                            $parent->redirectWithMessage(
                                tl('social_component_resource_save_first'),
                                array('arg', 'page_name', 'settings',
                                'caret', 'scroll_top'));
                        } else {
                            $upload_parts = array('name', 'type', 'tmp_name');
                            $file = array();
                            $upload_okay = true;
                            foreach($upload_parts as $part) {
                                if(isset($_FILES['page_resource'][$part])) {
                                    $file[$part] = $parent->clean(
                                        $_FILES['page_resource'][$part],
                                        'string');
                                } else {
                                    $upload_okay = false;
                                    break;
                                }
                            }
                        }
                        if($upload_okay) {
                            $group_model->copyFileToGroupPageResource(
                                $file['tmp_name'], $file['name'], $file['type'],
                                $group_id, $page_info['ID']);
                            $parent->redirectWithMessage(
                                tl('social_component_resource_uploaded'),
                                array('arg', 'page_name', 'settings',
                                'caret', 'scroll_top'));
                        } else {
                            $parent->redirectWithMessage(
                                tl('social_component_upload_error'),
                                array('arg', 'page_name', 'settings',
                                'caret', 'scroll_top'));
                        }
                    } else if(!$missing_fields && isset($_REQUEST['delete'])) {
                        $resource_name = $parent->clean($_REQUEST['delete'],
                            "string");
                        if(isset($page_info['ID']) &&
                            $group_model->deleteResource($resource_name,
                            $group_id, $page_info['ID'])) {
                            $parent->redirectWithMessage(
                                tl('social_component_resource_deleted'),
                                array('arg', 'page_name', 'settings',
                                'caret', 'scroll_top'));
                        } else {
                            $parent->redirectWithMessage(
                                tl('social_component_resource_not_deleted'),
                                array('arg', 'page_name', 'settings',
                                'caret', 'scroll_top'));
                        }
                    } else if(!$missing_fields &&
                        isset($_REQUEST['new_resource_name']) &&
                        isset($_REQUEST['old_resource_name'])) {
                        $old_resource_name = $parent->clean(
                            $_REQUEST['old_resource_name'], "string");
                        $new_resource_name = $parent->clean(
                            $_REQUEST['new_resource_name'], "string");
                        if(isset($page_info['ID']) &&
                            $group_model->renameResource($old_resource_name,
                                $new_resource_name, $group_id, 
                                $page_info['ID'])) {
                            $parent->redirectWithMessage(
                                tl('social_component_resource_renamed'),
                                array('arg', 'page_name', 'settings',
                                'caret', 'scroll_top'));
                        } else {
                            $parent->redirectWithMessage(
                                tl('social_component_resource_not_renamed'),
                                array('arg', 'page_name', 'settings',
                                'caret', 'scroll_top'));
                        }
                    }
                    if(isset($page_info['ID'])) {
                        $data['RESOURCES_INFO'] =
                            $group_model->getGroupPageResourceUrls($group_id,
                            $page_info['ID']);
                    } else {
                        $data['RESOURCES_INFO'] = array();
                    }
                break;
                case "history":
                    if(!$data["CAN_EDIT"] || !isset($page_id) || !$page_id) {
                        continue;
                    }
                    $data["MODE"] = "history";
                    $data["PAGE_NAME"] = "history";
                    $limit = isset($limit) ? $limit : 0;
                    $num = (isset($_SESSION["MAX_PAGES_TO_SHOW"])) ?
                       $_SESSION["MAX_PAGES_TO_SHOW"] :
                       DEFAULT_ADMIN_PAGING_NUM;
                    $default_history = true;
                    if(isset($show)) {
                        $page_info = $group_model->getHistoryPage(
                            $page_id, $show);
                        if($page_info) {
                            $data["MODE"] = "show";
                            $default_history = false;
                            $data["PAGE_NAME"] = $page_info["PAGE_NAME"];
                            $parser = new WikiParser($read_address,
                                $additional_substitutions);
                            $parsed_page = $parser->parse($page_info["PAGE"]);
                            $data["PAGE_ID"] = $page_id;
                            $data[CSRF_TOKEN] =
                                $parent->generateCSRFToken($user_id);
                            $history_link = "?c={$data['CONTROLLER']}&amp;".
                                "a=wiki&amp;". CSRF_TOKEN.'='.$data[CSRF_TOKEN].
                                '&amp;arg=history&amp;page_id='.
                                $data['PAGE_ID'];
                            $data["PAGE"] =
                                "<div>&nbsp;</div>".
                                "<div class='black-box back-dark-gray'>".
                                "<div class='float-opposite'>".
                                "<a href='$history_link'>".
                                tl("group_controller_back") . "</a></div>".
                                tl("group_controller_history_page",
                                $data["PAGE_NAME"], date("c", $show)) .
                                "</div>" . $parsed_page;
                            $data["DISCUSS_THREAD"] =
                                $page_info["DISCUSS_THREAD"];
                        }
                    } else if(isset($diff) && $diff &&
                        isset($diff1) && isset($diff2)) {
                        $page_info1 = $group_model->getHistoryPage(
                            $page_id, $diff1);
                        $page_info2 = $group_model->getHistoryPage(
                            $page_id, $diff2);
                        $data["MODE"] = "diff";
                        $default_history = false;
                        $data["PAGE_NAME"] = $page_info2["PAGE_NAME"];
                        $data["PAGE_ID"] = $page_id;
                        $data[CSRF_TOKEN] =
                            $parent->generateCSRFToken($user_id);
                        $history_link = "?c={$data['CONTROLLER']}".
                            "&amp;a=wiki&amp;".CSRF_TOKEN.'='.$data[CSRF_TOKEN].
                            '&amp;arg=history&amp;page_id='.
                            $data['PAGE_ID'];
                        $out_diff = "<div>--- {$data["PAGE_NAME"]}\t".
                            "''$diff1''\n";
                        $out_diff .= "<div>+++ {$data["PAGE_NAME"]}\t".
                            "''$diff2''\n";
                        $out_diff .= diff($page_info1["PAGE"],
                            $page_info2["PAGE"], true);
                        $data["PAGE"] =
                            "<div>&nbsp;</div>".
                            "<div class='black-box back-dark-gray'>".
                            "<div class='float-opposite'>".
                            "<a href='$history_link'>".
                            tl("group_controller_back") . "</a></div>".
                            tl("group_controller_diff_page",
                            $data["PAGE_NAME"], date("c", $diff1),
                            date("c", $diff2)) .
                            "</div>" . "$out_diff";
                    } else if(isset($revert)) {
                        $page_info = $group_model->getHistoryPage(
                            $page_id, $revert);
                        if($page_info) {
                            $action = "wikiupdate_".
                                "group=".$group_id."&page=" .
                                $page_info["PAGE_NAME"];
                            if(!$parent->checkCSRFTime(CSRF_TOKEN, $action)) {
                                $data['SCRIPT'] .=
                                    "doMessage('<h1 class=\"red\" >".
                                    tl('social_component_wiki_edited_elsewhere')
                                    . "</h1>');";
                                break;
                            }
                            $group_model->setPageName($user_id,
                                $group_id, $page_info["PAGE_NAME"],
                                $page_info["PAGE"],
                                $locale_tag,
                                tl('group_controller_page_revert_to',
                                date('c', $revert)), "", "", $read_address,
                                $additional_substitutions);
                            $parent->redirectWithMessage(
                                tl("group_controller_page_reverted"),
                                array('arg', 'page_name', 'page_id'));
                        } else {
                            $parent->redirectWithMessage(
                                tl("group_controller_revert_error"),
                                array('arg', 'page_name', 'page_id'));
                        }
                    }
                    if($default_history) {
                        $data["LIMIT"] = $limit;
                        $data["RESULTS_PER_PAGE"] = $num;
                        list($data["TOTAL_ROWS"], $data["PAGE_NAME"],
                            $data["HISTORY"]) =
                            $group_model->getPageHistoryList($page_id, $limit,
                            $num);
                        if((!isset($diff1) || !isset($diff2))) {
                            $data['diff1'] = $data["HISTORY"][0]["PUBDATE"];
                            $data['diff2'] = $data["HISTORY"][0]["PUBDATE"];
                            if(count($data["HISTORY"]) > 1) {
                                $data['diff2'] = $data["HISTORY"][1]["PUBDATE"];
                            }
                        }
                    }
                    $data['page_id'] = $page_id;
                break;
                case "media":
                    if(!isset($page_id) || !isset($_REQUEST['n'])) { break; }
                    $media_name = $parent->clean($_REQUEST['n'], "string");
                    $page_info = $group_model->getPageInfoByPageId($page_id);
                    $data['PAGE_NAME'] = $page_info['PAGE_NAME'];
                    $name_parts = pathinfo($media_name);
                    $file_name = $name_parts['filename'];
                    $data['MEDIA_NAME'] = $media_name;
                    $page_string = "((resource:$media_name|$file_name))";
                    $data["PAGE"] = $group_model->insertResourcesParsePage(
                        $group_id, $page_id, $locale_tag, $page_string);
                    $data["PAGE_ID"] = $page_id;
                break;
                case "pages":
                    $data["MODE"] = "pages";
                    $limit =isset($limit) ? $limit : 0;
                    $num = (isset($_SESSION["MAX_PAGES_TO_SHOW"])) ?
                       $_SESSION["MAX_PAGES_TO_SHOW"] :
                       DEFAULT_ADMIN_PAGING_NUM;
                    if(!isset($filter)) {
                        $filter = "";
                    }
                    if(isset($page_name)) {
                        $data['PAGE_NAME'] = $page_name;
                    }
                    $data["LIMIT"] = $limit;
                    $data["RESULTS_PER_PAGE"] = $num;
                    $data["FILTER"] = $filter;
                    $search_page_info = false;
                    if($filter != "") {
                        $search_page_info = $group_model->getPageInfoByName(
                            $group_id, $filter, $locale_tag, "read");
                    }
                    if(!$search_page_info) {
                        list($data["TOTAL_ROWS"], $data["PAGES"]) =
                            $group_model->getPageList(
                            $group_id, $locale_tag, $filter, $limit,
                            $num);
                        if($data["TOTAL_ROWS"] == 0 && $filter != "") {
                            $data["MODE"] = "read";
                            $page_name = $filter;
                        }
                    } else {
                        $data["MODE"] = "read";
                        $page_name = $filter;
                    }
                break;
            }
        }
        if(!$page_name) {
            $page_name = tl('group_controller_main');
        }
        $data["GROUP"] = $group;
        if(in_array($data["MODE"], array("read", "edit", "media","api"))) {
            if(!isset($data["PAGE"]) || !$data['PAGE']) {
                $data["PAGE_NAME"] = $page_name;
                if(isset($search_page_info) && $search_page_info) {
                    $page_info = $search_page_info;
                } else {
                    $page_info = $group_model->getPageInfoByName($group_id,
                        $page_name, $locale_tag, $data["MODE"]);
                }
                $data["PAGE"] = $page_info["PAGE"];
                $data["PAGE_ID"] = $page_info["ID"];
                $data["DISCUSS_THREAD"] = $page_info["DISCUSS_THREAD"];
            }
            if((!isset($data["PAGE"]) || !$data["PAGE"]) &&
                $locale_tag != DEFAULT_LOCALE) {
                //fallback to default locale for translation
                $page_info = $group_model->getPageInfoByName(
                    $group_id, $page_name, DEFAULT_LOCALE, $data["MODE"]);
                $data["PAGE"] = $page_info["PAGE"];
                $data["PAGE_ID"] = $page_info["ID"];
                $data["DISCUSS_THREAD"] = $page_info["DISCUSS_THREAD"];
            }
            $view = $parent->view($data['VIEW']);
            $parent->parsePageHeadVars($view, $data["PAGE_ID"], $data["PAGE"]);
            $data["PAGE"] = $this->dynamicSubstitutions($group_id, $data,
                $view->page_objects[$data["PAGE_ID"]]);
            $data["HEAD"] = $view->head_objects[$data["PAGE_ID"]];
            if(isset($data["HEAD"]['page_type']) &&
                $data["HEAD"]['page_type'] == 'page_alias' &&
                $data["HEAD"]['page_alias'] != '' &&
                $data['MODE'] == "read" && !isset($_REQUEST['noredirect']) ) {
                $_REQUEST['page_name'] = $data["HEAD"]['page_alias'];
                $parent->redirectWithMessage("", array('page_name'));
            }
            if($data['MODE'] == "read" && isset($data["HEAD"]['page_header']) &&
                $data["HEAD"]['page_type'] != 'presentation') {
                $page_header = $group_model->getPageInfoByName($group_id,
                    $data["HEAD"]['page_header'], $locale_tag, $data["MODE"]);
                if(isset($page_header['PAGE'])) {
                    $header_parts =
                        explode("END_HEAD_VARS", $page_header['PAGE']);
                }
                $data["PAGE_HEADER"] = (isset($header_parts[1])) ?
                    $header_parts[1] : "". $page_header['PAGE'];
                $data["PAGE_HEADER"] = $this->dynamicSubstitutions(
                    $group_id, $data, $data["PAGE_HEADER"]);
            }
            if($data['MODE'] == "read" && isset($data["HEAD"]['page_footer']) &&
                $data["HEAD"]['page_type'] != 'presentation') {
                $page_footer = $group_model->getPageInfoByName($group_id,
                    $data["HEAD"]['page_footer'], $locale_tag, $data["MODE"]);
                if(isset($page_footer['PAGE'])) {
                    $footer_parts =
                        explode("END_HEAD_VARS", $page_footer['PAGE']);
                }
                $data['PAGE_FOOTER'] = (isset($footer_parts[1])) ?
                    $footer_parts[1] : "" . $page_footer['PAGE'];
                $data["PAGE_FOOTER"] = $this->dynamicSubstitutions(
                    $group_id, $data, $data["PAGE_FOOTER"]);
            }
            if($data['MODE'] == "read" && strpos($data["PAGE"], "`") !== false){
                if(!isset($data["INCLUDE_SCRIPTS"])) {
                    $data["INCLUDE_SCRIPTS"] = array();
                }
                $data["INCLUDE_SCRIPTS"][] = "math";
            }
            if($data['MODE'] == "read" && isset($data["HEAD"]['page_type'])) {
                if($data["HEAD"]['page_type'] == 'media_list') {
                    $data['RESOURCES_INFO'] =
                        $group_model->getGroupPageResourceUrls($group_id,
                            $data['PAGE_ID']);
                }
                if($data["HEAD"]['page_type'] == 'presentation' &&
                    $data['CONTROLLER'] == 'group') {
                    $data['page_type'] = 'presentation';
                    $data['INCLUDE_SCRIPTS'][] =  "slidy";
                    $data['INCLUDE_STYLES'][] =  "slidy";
                }
            }
            if($data['MODE'] == "edit") {
                foreach($page_defaults as $key => $default) {
                    $data[$key] = $default;
                    if(isset($data["HEAD"][$key])) {
                        $data[$key] = $data["HEAD"][$key];
                    }
                }
                $data['settings'] = "false";
                if(isset($_REQUEST['settings']) &&
                    $_REQUEST['settings']=='true') {
                    $data['settings'] = "true";
                }
                $data['current_page_type'] = $data["page_type"];
                $data['SCRIPT'] .= <<< EOD
                setDisplay('page-settings', {$data['settings']});
                function toggleSettings()
                {
                    var settings = elt('p-settings');
                    settings.value = (settings.value =='true')
                        ? 'false' : 'true';
                    var value = (settings.value == 'true') ? true : false;
                    setDisplay('page-settings', value);
                    var page_type = elt("page-type");
                    var cur_type = page_type.options[
                        page_type.selectedIndex].value;
                    if(cur_type == "media_list") {
                        setDisplay('save-container', value);
                    }
                }
                ptype = document.getElementById("page-type");
                is_media_list = ('media_list'=='{$data['current_page_type']}');
                is_settings = {$data['settings']};
                is_page_alias = ('page_alias'=='{$data['current_page_type']}');
                setDisplay('page-settings', is_settings || is_page_alias);
                setDisplay("media-list-page", is_media_list && !is_page_alias);
                setDisplay("page-container", !is_media_list && !is_page_alias);
                setDisplay("non-alias-type", !is_page_alias);
                setDisplay("alias-type", is_page_alias);
                setDisplay('save-container', !is_media_list || is_settings);
                setDisplay("toggle-settings", !is_page_alias, "inline");
                setDisplay("page-resources", !is_page_alias);
                ptype.onchange = function() {
                    var cur_type = ptype.options[ptype.selectedIndex].value;
                    if(cur_type == "media_list") {
                        setDisplay("media-list-page", true, "inline");
                        setDisplay("page-container", false);
                        setDisplay("toggle-settings", true);
                        setDisplay("non-alias-type", true);
                        setDisplay("alias-type", false);
                        setDisplay("page-resources", true);
                    } else if(cur_type == "page_alias") {
                        setDisplay("toggle-settings", false);
                        setDisplay("media-list-page", false);
                        setDisplay("page-container", false);
                        setDisplay("non-alias-type", false);
                        setDisplay("alias-type", true);
                        setDisplay("page-resources", false);
                    } else {
                        setDisplay("page-container", true);
                        setDisplay("media-list-page", false);
                        setDisplay("toggle-settings", true, "inline");
                        setDisplay("non-alias-type", true);
                        setDisplay("alias-type", false);
                        setDisplay("page-resources", true);
                    }
                }
EOD;
                $this->initializeWikiEditor($data);
            }
        }
       /** Check if back params need to be set. Set them if required.
        * the back params are usually sent when the wiki action is initiated
        * from within an open help article.
        */
        $data["OTHER_BACK_URL"] = "";
            if(isset($_REQUEST['back_params']) &&
                ((isset($_REQUEST['arg']) && in_array(
                    $parent->clean($_REQUEST['arg'],"string"), array('edit',
                    'read'))) || (isset($_REQUEST['page_name'])))
                    ) {
                $back_params_cleaned = $_REQUEST['back_params'];
                array_walk($back_params_cleaned, array($parent, 'clean'));
                foreach($back_params_cleaned as
                        $back_param_key => $back_param_value) {
                    $data['BACK_PARAMS']["back_params[$back_param_key]"]
                        = $back_param_value;
                    $data["OTHER_BACK_URL"] .=
                        "&amp;back_params[$back_param_key]" . "=" .
                        $back_param_value;
                }
                $data['BACK_URL'] = http_build_query($back_params_cleaned);
            }
        return $data;
    }
    /**
     *  The controller used to display a wiki page might vary (could be
     *  admin, group or static). Links within a wiki page need to be updated
     *  to reflect which controller is being used. This method does the
     *  update.
     *
     *  @param int $group_id id of wiki page the passed page belongs to
     *  @param array $data fields etc which will be sent to the view
     *  @param string $pre_page a wiki page where links,etc have not yet
     *      had dynamic substitutions applied
     *  @return string page after subustitutions
     */
    function dynamicSubstitutions($group_id, $data, $pre_page)
    {
        $csrf_token = "";
        $no_right_amp_csrf_token = "";
        if(isset($data['ADMIN']) && $data['ADMIN']) {
            $no_right_amp_csrf_token = 
                "&amp;".CSRF_TOKEN."=".$this->parent->generateCSRFToken(
                $_SESSION['USER_ID']);
            $csrf_token = $no_right_amp_csrf_token . "&amp;";
        }
        if($data['CONTROLLER'] == 'static') {
            $address = "?c=static&amp;{$csrf_token}p=";
        } else {
            $address = "?c={$data['CONTROLLER']}{$csrf_token}a=wiki&amp;".
                "arg=read&amp;group_id=$group_id&amp;page_name=";
        }
        $pre_page = preg_replace('/\[{controller_and_page}\]/', $address,
            $pre_page);
        $pre_page = preg_replace('/\[{controller}\]/', $data['CONTROLLER'].
            $no_right_amp_csrf_token, $pre_page);
        return $pre_page;
    }
    /**
     * Called to include the Javascript Wiki Editor (wiki.js) on a page
     * and to send any localizations needed from PHP to Javascript-land
     *
     * @param array& $data an asscoiative array of data to be used by the
     *     view and layout that the wiki editor will be drawn on
     *     This method tacks on to INCLUDE_SCRIPTS to make the layout load
     *     wiki.js.
     * @param $id if "" then all textareas on page will get editor buttons,
     *     if -1 then sets up translations, but does not add any button,
     *     otherwise, jadd buttons to textarea $id will. (Can call this method
     *     multiple times, if want more than one but not all)
     */
    function initializeWikiEditor(&$data, $id = "")
    {
        if(!isset($data["WIKI_INITIALIZED"]) || !$data["WIKI_INITIALIZED"]) {
            if (!isset($data["INCLUDE_SCRIPTS"])) {
                $data["INCLUDE_SCRIPTS"] = array();
            }

            $data["INCLUDE_SCRIPTS"][] = "wiki";
            $data["WIKI_INITIALIZED"] = true;

            //set up an array of translation for javascript-land
            if(!isset($data['SCRIPT'])) {
                $data['SCRIPT'] = "";
            }
            $data['SCRIPT'] .= "\ntl = {".
                'wiki_js_small :"'. tl('wiki_js_small') .'",' .
                'wiki_js_medium :"'. tl('wiki_js_medium').'",'.
                'wiki_js_large :"'. tl('wiki_js_large').'",'.
                'wiki_js_search_size :"'. tl('wiki_js_search_size').'",'.
                'wiki_js_prompt_heading :"'. tl('wiki_js_prompt_heading').'",'.
                'wiki_js_example :"'. tl('wiki_js_example').'",'.
                'wiki_js_table_title :"'. tl('wiki_js_table_title').'",'.
                'wiki_js_submit :"'. tl('wiki_js_submit').'",'.
                'wiki_js_cancel :"'. tl('wiki_js_cancel').'",'.
                'wiki_js_bold :"'. tl('wiki_js_bold') . '",' .
                'wiki_js_italic :"'. tl('wiki_js_italic').'",'.
                'wiki_js_underline :"'. tl('wiki_js_underline').'",'.
                'wiki_js_strike :"'. tl('wiki_js_strike').'",'.
                'wiki_js_heading :"'. tl('wiki_js_heading').'",'.
                'wiki_js_heading1 :"'. tl('wiki_js_heading1').'",'.
                'wiki_js_heading2 :"'. tl('wiki_js_heading2').'",'.
                'wiki_js_heading3 :"'. tl('wiki_js_heading3').'",'.
                'wiki_js_heading4 :"'. tl('wiki_js_heading4').'",'.
                'wiki_js_bullet :"'. tl('wiki_js_bullet').'",'.
                'wiki_js_enum :"'. tl('wiki_js_enum') .'",'.
                'wiki_js_nowiki :"'. tl('wiki_js_nowiki') .'",'.
                'wiki_js_add_search :"'.tl('wiki_js_add_search') .'",'.
                'wiki_js_search_size :"'. tl('wiki_js_search_size') .'",'.
                'wiki_js_add_wiki_table :"'. tl('wiki_js_add_wiki_table').'",'.
                'wiki_js_for_table_cols :"'. tl('wiki_js_for_table_cols').'",'.
                'wiki_js_for_table_rows :"'. tl('wiki_js_for_table_rows').'",'.
                'wiki_js_add_hyperlink :"'. tl('wiki_js_add_hyperlink').'",'.
                'wiki_js_link_text :"'. tl('wiki_js_link_text').'",'.
                'wiki_js_link_url :"'. tl('wiki_js_link_url').'",'.
                'wiki_js_placeholder :"'. tl('wiki_js_placeholder').'",'.
                'wiki_js_centeraligned :"'. tl('wiki_js_centeraligned').'",'.
                'wiki_js_rightaligned :"'. tl('wiki_js_rightaligned').'",'.
                'wiki_js_leftaligned :"'. tl('wiki_js_leftaligned').'",'.
                'wiki_js_definitionlist_item :"'.
                tl('wiki_js_definitionlist_item').'",'.
                'wiki_js_definitionlist_definition :"'.
                tl('wiki_js_definitionlist_definition').'",'.
                'wiki_js_slide_sample_title :"'.
                    tl('wiki_js_slide_sample_title').'",'.
                'wiki_js_slide_sample_bullet :"'.
                    tl('wiki_js_slide_sample_bullet').'"'.
                '};';
        }
        if($id != -1) {
            if($id == "") {
                $data['SCRIPT'] .= "editorizeAll();\n";
            } else {
                $data['SCRIPT'] .= "editorize('$id');\n";
            }
        }
    }
    /**
     * Handles admin request related to the crawl mix activity
     *
     * The crawl mix activity allows a user to create/edit crawl mixes:
     * weighted combinations of search indexes
     *
     * @return array $data info about available crawl mixes and changes to them
     *     as well as any messages about the success or failure of a
     *     sub activity.
     */
    function mixCrawls()
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $user_model = $parent->model("user");
        $possible_arguments = array(
            "createmix", "deletemix", "editmix", "index", "importmix",
            "search", "sharemix");
        $data["ELEMENT"] = "mixcrawls";
        $user_id = $_SESSION['USER_ID'];

        $data['mix_default'] = 0;
        $machine_urls = $parent->model("machine")->getQueueServerUrls();
        $num_machines = count($machine_urls);
        if($num_machines <  1 || ($num_machines ==  1 &&
            UrlParser::isLocalhostUrl($machine_urls[0]))) {
            $machine_urls = NULL;
        }
        $crawls = $crawl_model->getCrawlList(false, true, $machine_urls);
        $data['available_crawls'][0] = tl('social_component_select_crawl');
        $data['available_crawls'][1] = tl('social_component_default_crawl');
        $data['SCRIPT'] = "c = [];c[0]='".
            tl('social_component_select_crawl')."';";
        $data['SCRIPT'] .= "c[1]='".
            tl('social_component_default_crawl')."';";
        foreach($crawls as $crawl) {
            $data['available_crawls'][$crawl['CRAWL_TIME']] =
                $crawl['DESCRIPTION'];
            $data['SCRIPT'] .= 'c['.$crawl['CRAWL_TIME'].']="'.
                $crawl['DESCRIPTION'].'";';
        }
        $search_array = array();
        $can_manage_crawls = $user_model->isAllowedUserActivity(
                $_SESSION['USER_ID'], "manageCrawls");
        $data['PAGING'] = "";
        $data['FORM_TYPE'] = "";
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "createmix":
                    $mix['TIMESTAMP'] = time();
                    if(isset($_REQUEST['NAME'])) {
                        $mix['NAME'] = substr(trim($parent->clean(
                            $_REQUEST['NAME'], 'string')), 0, NAME_LEN);
                    } else {
                        $mix['NAME'] = "";
                    }
                    if($mix['NAME'] &&
                        !$crawl_model->getCrawlMixTimestamp($mix['NAME'])) {
                        $mix['FRAGMENTS'] = array();
                        $mix['OWNER_ID'] = $user_id;
                        $mix['PARENT'] = -1;
                        $crawl_model->setCrawlMix($mix);
                        $parent->redirectWithMessage(
                            tl('social_component_mix_created'));
                    } else {
                        $parent->redirectWithMessage(
                            tl('social_component_invalid_name'));
                    }
                break;
                case "deletemix":
                    if(!isset($_REQUEST['timestamp'])||
                        !$crawl_model->isMixOwner($_REQUEST['timestamp'],
                            $user_id)) {
                        $parent->redirectWithMessage(
                            tl('social_component_mix_invalid_timestamp'));
                    }
                    $crawl_model->deleteCrawlMix($_REQUEST['timestamp']);
                    $parent->redirectWithMessage(
                        tl('social_component_mix_deleted'));
                break;
                case "editmix":
                    //$data passed by reference
                    $this->editMix($data);
                break;
                case "importmix":
                    $import_success = true;
                    if(!isset($_REQUEST['timestamp'])) {
                        $import_success = false;
                    }
                    $timestamp = substr($parent->clean(
                        $_REQUEST['timestamp'], "int"), 0, TIMESTAMP_LEN);
                    $mix = $crawl_model->getCrawlMix($timestamp);
                    if(!$mix) {
                        $import_success = false;
                    }
                    if(!$import_success) {
                        $parent->redirectWithMessage(
                            tl('social_component_mix_doesnt_exists'));
                        return $data;
                    }
                    $mix['PARENT'] = $mix['TIMESTAMP'];
                    $mix['OWNER_ID'] = $user_id;
                    $mix['TIMESTAMP'] = time();
                    $crawl_model->setCrawlMix($mix);
                    $parent->redirectWithMessage(
                        tl('social_component_mix_imported'));
                break;
                case "index":
                    $timestamp = substr(
                        $parent->clean($_REQUEST['timestamp'], "int"), 0,
                        TIMESTAMP_LEN);
                    if($can_manage_crawls) {
                        $crawl_model->setCurrentIndexDatabaseName(
                            $timestamp);
                    } else {
                        $_SESSION['its'] = $timestamp;
                        $user_model->setUserSession($user_id, $_SESSION);
                    }
                    $parent->redirectWithMessage(
                        tl('social_component_set_index'));
                break;
                case "search":
                    $search_array =
                        $parent->tableSearchRequestHandler($data,
                            array('name'));
                break;
                case "sharemix":
                    if(!isset($_REQUEST['group_name'])) {
                        $parent->redirectWithMessage(
                            tl('social_component_comment_error'));
                    }
                    if(!isset($_REQUEST['timestamp']) ||
                        !$crawl_model->isMixOwner($_REQUEST['timestamp'],
                            $user_id)) {
                        $parent->redirectWithMessage(
                            tl('social_component_invalid_timestamp'));
                    }
                    $timestamp = $parent->clean($_REQUEST['timestamp'], "int");
                    $group_model = $parent->model("group");
                    $group_name = substr(
                        $parent->clean($_REQUEST['group_name'], "string"), 0,
                        SHORT_TITLE_LEN);
                    $group_id = $group_model->getGroupId($group_name);
                    $group = NULL;
                    if($group_id) {
                        $group = $group_model->getGroupById($group_id,
                            $user_id, true);
                    }
                    $share = array(GROUP_READ_WRITE, GROUP_READ_WIKI);
                    if(!$group || ($group["OWNER_ID"] != $user_id &&
                        !in_array($group["MEMBER_ACCESS"], $share) &&
                        $user_id != ROOT_ID)) {
                        $parent->redirectWithMessage(
                            tl('social_component_no_post_access'));
                    }
                    $mix = $crawl_model->getCrawlMix($timestamp);
                    $user_name = $user_model->getUserName($user_id);
                    $title = tl('social_component_share_title',
                        $user_name);
                    $description = tl('social_component_share_description',
                        $user_name,"[[{$mix['NAME']}:mix{$mix['TIMESTAMP']}]]");
                    $group_model->addGroupItem(0,
                        $group_id, $user_id, $title, $description);
                    $parent->redirectWithMessage(
                        tl('social_component_thread_created'));
                break;
            }
        }
        if($search_array == array()) {
            $search_array[] = array("name", "", "", "ASC");
        }
        $search_array[] = array("owner_id", "=", $user_id, "");
        $parent->pagingLogic($data, $crawl_model, "available_mixes",
            DEFAULT_ADMIN_PAGING_NUM, $search_array, "", true);

        if(!$can_manage_crawls && isset($_SESSION['its'])) {
            $crawl_time = $_SESSION['its'];
        } else {
            $crawl_time = $crawl_model->getCurrentIndexDatabaseName();
        }
        if(isset($crawl_time) ) {
            $data['CURRENT_INDEX'] = (int)$crawl_time;
        } else {
            $data['CURRENT_INDEX'] = -1;
        }

        return $data;
    }
    /**
     * Handles admin request related to the editing a crawl mix activity
     *
     * @param array $data info about the fragments and their contents for a
     *     particular crawl mix (changed by this method)
     */
    function editMix(&$data)
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $data["leftorright"] =
            (getLocaleDirection() == 'ltr') ? "right": "left";
        $data["ELEMENT"] = "editmix";
        $user_id = $_SESSION['USER_ID'];

        $mix = array();
        $timestamp = 0;
        if(isset($_REQUEST['timestamp'])) {
            $timestamp = substr($parent->clean($_REQUEST['timestamp'], "int"),
                0, TIMESTAMP_LEN);
        } else if (isset($_REQUEST['mix']['TIMESTAMP'])) {
            $timestamp = substr(
                $parent->clean($_REQUEST['mix']['TIMESTAMP'], "int"),
                0, TIMESTAMP_LEN);
        }
        if(!$crawl_model->isCrawlMix($timestamp)) {
            $_REQUEST['a'] = "mixCrawls";
            $parent->redirectWithMessage(
                tl('social_component_mix_invalid_timestamp'));
        }
        if(!$crawl_model->isMixOwner($timestamp, $user_id)) {
            $_REQUEST['a'] = "mixCrawls";
            $parent->redirectWithMessage(
                tl('social_component_mix_not_owner'));
        }
        $mix = $crawl_model->getCrawlMix($timestamp);
        $owner_id = $mix['OWNER_ID'];
        $parent_id = $mix['PARENT'];
        $data['MIX'] = $mix;
        $data['INCLUDE_SCRIPTS'] = array("mix");
        //set up an array of translation for javascript-land
        $data['SCRIPT'] .= "tl = {".
            'social_component_add_crawls:"'.
                tl('social_component_add_crawls') .
            '",' . 'social_component_num_results:"'.
                tl('social_component_num_results').'",'.
            'social_component_del_frag:"'.
                tl('social_component_del_frag').'",'.
            'social_component_weight:"'.
                tl('social_component_weight').'",'.
            'social_component_name:"'.tl('social_component_name').'",'.
            'social_component_add_keywords:"'.
                tl('social_component_add_keywords').'",'.
            'social_component_actions:"'.
                tl('social_component_actions').'",'.
            'social_component_add_query:"'.
                tl('social_component_add_query').'",'.
            'social_component_delete:"'.tl('social_component_delete').'"'.
            '};';
        //clean and save the crawl mix sent from the browser
        if(isset($_REQUEST['update']) && $_REQUEST['update'] ==
            "update") {
            $mix = $_REQUEST['mix'];
            $mix['TIMESTAMP'] = $timestamp;
            $mix['OWNER_ID']= $owner_id;
            $mix['PARENT'] = $parent_id;
            $mix['NAME'] = $parent->clean($mix['NAME'], "string");
            $comp = array();
            $save_mix = false;
            if(isset($mix['FRAGMENTS'])) {
                if($mix['FRAGMENTS'] != NULL && count($mix['FRAGMENTS']) <
                    MAX_MIX_FRAGMENTS) {
                    foreach($mix['FRAGMENTS'] as $fragment_id=>$fragment_data) {
                        if(isset($fragment_data['RESULT_BOUND'])) {
                            $mix['FRAGMENTS'][$fragment_id]['RESULT_BOUND'] =
                                $parent->clean($fragment_data['RESULT_BOUND'],
                                    "int");
                        } else {
                            $mix['FRAGMENTS']['RESULT_BOUND'] = 0;
                        }
                        if(isset($fragment_data['COMPONENTS'])) {
                            $comp = array();
                            foreach($fragment_data['COMPONENTS'] as $component){
                                $row = array();
                                $row['CRAWL_TIMESTAMP'] =
                                    $parent->clean(
                                        $component['CRAWL_TIMESTAMP'], "int");
                                $row['WEIGHT'] = $parent->clean(
                                    $component['WEIGHT'], "float");
                                $row['KEYWORDS'] = $parent->clean(
                                    $component['KEYWORDS'],
                                    "string");
                                $comp[] =$row;
                            }
                            $mix['FRAGMENTS'][$fragment_id]['COMPONENTS']=$comp;
                        } else {
                            $mix['FRAGMENTS'][$fragment_id]['COMPONENTS'] =
                                array();
                        }
                    }
                    $save_mix = true;
                } else if(count($mix['FRAGMENTS']) >= MAX_MIX_FRAGMENTS) {
                    $mix['FRAGMENTS'] = $data['MIX']['FRAGMENTS'];
                    $parent->redirectWithMessage(
                        tl('social_component_too_many_fragments'));
                } else {
                    $mix['FRAGMENTS'] = $data['MIX']['FRAGMENTS'];
                }
            } else {
                $mix['FRAGMENTS'] = $data['MIX']['FRAGMENTS'];
            }
            if($save_mix) {
                $data['MIX'] = $mix;
                $crawl_model->setCrawlMix($mix);
                $parent->redirectWithMessage(
                    tl('social_component_mix_saved'));
            }
        }
        $data['SCRIPT'] .= 'fragments = [';
        $not_first = "";
        foreach($mix['FRAGMENTS'] as $fragment_id => $fragment_data) {
            $data['SCRIPT'] .= $not_first.'{';
            $not_first= ",";
            if(isset($fragment_data['RESULT_BOUND'])) {
                $data['SCRIPT'] .= "num_results:".
                    $fragment_data['RESULT_BOUND'];
            } else {
                $data['SCRIPT'] .= "num_results:1 ";
            }
            $data['SCRIPT'] .= ", components:[";
            if(isset($fragment_data['COMPONENTS'])) {
                $comma = "";
                foreach($fragment_data['COMPONENTS'] as $component) {
                    $crawl_ts = $component['CRAWL_TIMESTAMP'];
                    $crawl_name = $data['available_crawls'][$crawl_ts];
                    $data['SCRIPT'] .= $comma." [$crawl_ts, '$crawl_name', ".
                        $component['WEIGHT'].", ";
                    $comma = ",";
                    $keywords = (isset($component['KEYWORDS'])) ?
                        $component['KEYWORDS'] : "";
                    $data['SCRIPT'] .= "'$keywords'] ";
                }
            }
            $data['SCRIPT'] .= "] }";
        }
        $data['SCRIPT'] .= ']; drawFragments();';
    }
}
