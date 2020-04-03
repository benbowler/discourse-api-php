<?php

/**
 * Discourse API client for PHP
 *
 * This is the Discourse API client for PHP
 * This is a very experimental API implementation.
 *
 * @category  DiscourseAPI
 * @package   DiscourseAPI
 * @author    Original author DiscourseHosting <richard@discoursehosting.com>
 * @copyright 2013, DiscourseHosting.com
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GPLv2 
 * @link      https://github.com/discoursehosting/discourse-api-php
 */

class DiscourseAPI
{
    private $_protocol = 'http';
    private $_apiKey = null;
    private $_dcHostname = null;
    private $_httpAuthName = '';
    private $_httpAuthPass = '';

    function __construct($dcHostname, $apiKey = null, $protocol = 'http', $httpAuthName = '', $httpAuthPass = '')
    {
        $this->_dcHostname = $dcHostname;
        $this->_apiKey = $apiKey;
        $this->_protocol = $protocol;
        $this->_httpAuthName = $httpAuthName;
        $this->_httpAuthPass = $httpAuthPass;
    }

    private function _getRequest($reqString, $paramArray = null, $apiUser = false)
    {
        if ($paramArray == null) {
            $paramArray = array();
        }
        $ch = curl_init();
        $url = sprintf(
            '%s://%s%s?%s',
            $this->_protocol,
            $this->_dcHostname,
            $reqString,
            http_build_query($paramArray)
        );

        // Use the username specified in the init function of the overide if present
        $authUser = ($apiUser) ? $apiUser : $this->_httpAuthName;

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Api-Key: " . $this->_apiKey,
            "Api-Username: $authUser"
        ]);

        // Remove old authentication method depricated 1 April 2020
        // if (!empty($this->_httpAuthName) && !empty($this->_httpAuthPass)) {
        //     curl_setopt($ch, CURLOPT_USERPWD, $this->_httpAuthName . ":" . $this->_httpAuthPass);
        //     curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $body = curl_exec($ch);
        $rc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resObj = new \stdClass();
        $resObj->http_code = $rc;
        $resObj->apiresult = json_decode($body);
        return $resObj;
    }

    private function _putRequest($reqString, $paramArray, $apiUser = false)
    {
        return $this->_alternateMethodRequest($reqString, $paramArray, $apiUser, "PUT");
    }

    private function _deleteRequest($reqString, $paramArray, $apiUser = false)
    {
        return $this->_alternateMethodRequest($reqString, $paramArray, $apiUser, "DELETE");
    }

    private function _postRequest($reqString, $paramArray, $apiUser = false)
    {

        return $this->_alternateMethodRequest($reqString, $paramArray, $apiUser, false);
    }

    private function _alternateMethodRequest($reqString, $paramArray, $apiUser = false, $method = false)
    {

        $ch = curl_init();
        $url = sprintf(
            '%s://%s%s?api_key=%s&api_username=%s',
            $this->_protocol,
            $this->_dcHostname,
            $reqString,
            $this->_apiKey,
            $apiUser
        );

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($paramArray));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($method) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        // Use the username specified in the init function of the overide if present
        $authUser = ($apiUser) ? $apiUser : $this->_httpAuthName;

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Api-Key: " . $this->_apiKey,
            "Api-Username: $authUser"
        ]);

        // Remove old authentication method depricated 1 April 2020
        // if (!empty($this->_httpAuthName) && !empty($this->_httpAuthPass)) {
        //     curl_setopt($ch, CURLOPT_USERPWD, $this->_httpAuthName . ":" . $this->_httpAuthPass);
        //     curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // }

        $body = curl_exec($ch);
        $rc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resObj = new \stdClass();
        $resObj->http_code = $rc;
        $resObj->apiresult = json_decode($body);
        return $resObj;
    }

    /**
     * group
     *
     * @param string $groupname         name of group
     * @param string $usernames     users to add to group
     *
     * @return mixed HTTP return code and API return object
     */

    function group($groupname, $usernames = array())
    {
        $obj = $this->_getRequest("/admin/groups.json");
        if ($obj->http_code != 200) {
            return false;
        }

        foreach ($obj->apiresult as $group) {
            if ($group->name === $groupname) {
                $groupId = $group->id;
                break;
            }
            $groupId = false;
        }

        $params = array(
            'group' => array(
                'name' => $groupname,
                'usernames' => implode(',', $usernames)
            )
        );

        if ($groupId) {
            return $this->_putRequest('/admin/groups/' . $groupId, $params);
        } else {
            return $this->_postRequest('/admin/groups', $params);
        }
    }

    /**
     * getGroups
     *
     * @return mixed HTTP return code and API return object
     */

    function getGroups()
    {
        return $this->_getRequest("/admin/groups.json");
    }

    /**
     * getGroupMembers
     *
     * @param string $group         name of group
     * @return mixed HTTP return code and API return object
     */

    function getGroupMembers($group)
    {
        return $this->_getRequest("/groups/{$group}/members.json");
    }

    /**
     * createUser
     *
     * @param string $name         name of new user
     * @param string $userName     username of new user
     * @param string $emailAddress email address of new user
     * @param string $password     password of new user
     *
     * @return mixed HTTP return code and API return object
     */

    function createUser($name, $userName, $emailAddress, $password)
    {
        $obj = $this->_getRequest('/users/hp.json');
        if ($obj->http_code != 200) {
            return false;
        }

        $params = array(
            'name' => $name,
            'username' => $userName,
            'email' => $emailAddress,
            'password' => $password,
            'challenge' => strrev($obj->apiresult->challenge),
            'password_confirmation' => $obj->apiresult->value
        );

        return $this->_postRequest('/users', $params);
    }

    /**
     * activateUser
     *
     * @param integer $userId      id of user to activate
     *
     * @return mixed HTTP return code 
     */

    function activateUser($userId)
    {
        return $this->_putRequest("/admin/users/{$userId}/activate", array());
    }

    /**
     * suspendUser
     *
     * @param integer $userId      id of user to suspend
     *
     * @return mixed HTTP return code
     */

    function suspendUser($userId)
    {
        return $this->_putRequest("/admin/users/{$userId}/suspend", array());
    }

    /**
     * getUsernameByEmail
     *
     * @param string $email     email of user
     *
     * @return mixed HTTP return code and API return object
     */

    function getUsernameByEmail($email)
    {
        $users = $this->_getRequest(
            '/admin/users/list/active.json',
            ['filter' => $email, 'show_emails' => 'true']
        );
        foreach ($users->apiresult as $user) {
            if ($user->email === $email) {
                return $user->username;
            }
        }

        return false;
    }

    /**
     * getUserByUsername
     *
     * @param string $userName     username of user
     *
     * @return mixed HTTP return code and API return object
     */

    function getUserByUsername($userName)
    {
        return $this->_getRequest("/users/{$userName}.json");
    }

    /**
     * getUserByExternalID
     *
     * @param string $externalID     external id of sso user
     *
     * @return mixed HTTP return code and API return object
     */
    function getUserByExternalID($externalID)
    {
        return $this->_getRequest("/users/by-external/{$externalID}.json");
    }

    /**
     * createCategory
     *
     * @param string $categoryName name of new category
     * @param string $color        color code of new category (six hex chars, no #)
     * @param string $textColor    optional color code of text for new category
     * @param string $userName     optional user to create category as
     *
     * @return mixed HTTP return code and API return object
     */

    function createCategory($categoryName, $color, $textColor = '000000', $userName = 'system')
    {
        $params = array(
            'name' => $categoryName,
            'color' => $color,
            'text_color' => $textColor
        );
        return $this->_postRequest('/categories', $params, $userName);
    }

    /**
     * getTopic
     *
     * @param string $topicId   id of topic
     *
     * @return mixed HTTP return code and API return object
     */
    function getTopic($topicID)
    {
        return $this->_getRequest('/t/' . $topicID . '.json');
    }

    /**
     * createTopic
     *
     * @param string $topicTitle   title of topic
     * @param string $bodyText     body text of topic post
     * @param string $categoryName category to create topic in
     * @param string $userName     user to create topic as
     * @param string $replyToId    post id to reply as
     *
     * @return mixed HTTP return code and API return object
     */
    function createTopic($topicTitle, $bodyText, $categoryId, $userName, $replyToId = 0)
    {
        $params = array(
            'title' => $topicTitle,
            'raw' => $bodyText,
            'category' => $categoryId,
            'archetype' => 'regular',
            'reply_to_post_number' => $replyToId,
        );
        return $this->_postRequest('/posts', $params, $userName);
    }

    /**
     * watchTopic
     *
     * watch Topic. If username is given, API-Key must be
     * general API key. Otherwise it will fail.
     * If no username is given, topic will be watched with
     * the system API username
     */
    function watchTopic($topicId, $userName = 'system')
    {
        $params = array(
            'notification_level' => '3'
        );
        return $this->_postRequest("/t/{$topicId}/notifications.json", $params, $userName);
    }

    /**
     * createPost
     *
     * @param string $bodyText     body text of topic post
     * @param string $topicId      topic id - must me a string not array
     * @param string $userName     user to create topic as
     * @param string $replyToId    (optional) id of post replying to
     *
     * @return mixed HTTP return code and API return object
     */
    function createPost($bodyText, $topicId, $userName, $replyToId = false)
    {
        $params = array(
            'raw' => $bodyText,
            'topic_id' => $topicId
        );
        if ($replyToId) {
            $params['reply_to_post_number'] = $replyToId;
        }

        return $this->_postRequest('/posts', $params, $userName);
    }

    /**
     * likePost
     */
    // id: 19
    // post_action_type_id: 2
    // flag_topic: false
    function likePost($postId, $userName = false)
    {
        $params = array(
            'post_action_type_id' => '2',
            'id' => $postId,
            'flag_topic' => false
        );

        return $this->_postRequest('/post_actions', $params, $userName);
    }

    /**
     * unLike
     */
    // /19
    //  DELETE
    //  post_action_type_id: 2
    // post_actions/31
    function unlikePost($postId, $userName = false)
    {
        $params = array(
            'post_action_type_id' => '2'
        );

        return $this->_deleteRequest('/post_actions/' . $postId, $params, $userName);
    }

    function inviteUser($email, $topicId, $userName = 'system')
    {
        $params = array(
            'email' => $email,
            'topic_id' => $topicId
        );
        return $this->_postRequest('/t/' . intval($topicId) . '/invite.json', $params, $userName);
    }

    function changeSiteSetting($siteSetting, $value)
    {
        $params = array($siteSetting => $value);
        return $this->_putRequest('/admin/site_settings/' . $siteSetting, $params);
    }

    function getIDByEmail($email)
    {
        $username = $this->getUsernameByEmail($email);
        if ($username) {
            return $this->_getRequest('/users/' . $username . '/activity.json')->apiresult->user->id;
        } else {
            return false;
        }
    }

    function logoutByEmail($email)
    {
        $user_id = $this->getIDByEmail($email);
        $params  = array('username_or_email' => $email);
        return $this->_postRequest('/admin/users/' . $user_id . '/log_out', $params);
    }

    function getUserinfoByName($username)
    {
        return $this->_getRequest("/users/{$username}.json");
    }
}
