<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace core_ltix;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/ltix/OAuth.php');
require_once($CFG->dirroot . '/ltix/TrivialStore.php');

use moodle\ltix as lti;
use moodle_exception;
use stdClass;

/**
 * Helper class specifically dealing with LTI OAuth.
 *
 * @package    core_ltix
 * @author     Alex Morris <alex.morris@catalyst.net.nz>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth_helper {

    /**
     * Signs the petition to launch the external tool using OAuth
     *
     * @param array  $oldparms     Parameters to be passed for signing
     * @param string $endpoint     url of the external tool
     * @param string $method       Method for sending the parameters (e.g. POST)
     * @param string $oauthconsumerkey
     * @param string $oauthconsumersecret
     * @return array|null
     */
    public static function sign_parameters($oldparms, $endpoint, $method, $oauthconsumerkey, $oauthconsumersecret) {

        $parms = $oldparms;

        $testtoken = '';

        // TODO: Switch to core oauthlib once implemented - MDL-30149.
        $hmacmethod = new lti\OAuthSignatureMethod_HMAC_SHA1();
        $testconsumer = new lti\OAuthConsumer($oauthconsumerkey, $oauthconsumersecret, null);
        $accreq = lti\OAuthRequest::from_consumer_and_token($testconsumer, $testtoken, $method, $endpoint, $parms);
        $accreq->sign_request($hmacmethod, $testconsumer, $testtoken);

        $newparms = $accreq->get_parameters();

        return $newparms;
    }

    /**
     * Verifies the OAuth signature of an incoming message.
     *
     * @param int $typeid The tool type ID.
     * @param string $consumerkey The consumer key.
     * @return stdClass Tool type
     * @throws moodle_exception
     * @throws lti\OAuthException
     */
    public static function verify_oauth_signature($typeid, $consumerkey) {
        $tool = types_helper::get_type($typeid);
        // Validate parameters.
        if (!$tool) {
            throw new moodle_exception('errortooltypenotfound', 'mod_lti');
        }
        $typeconfig = types_helper::get_type_config($typeid);

        if (isset($tool->toolproxyid)) {
            $toolproxy = tool_helper::get_tool_proxy($tool->toolproxyid);
            $key = $toolproxy->guid;
            $secret = $toolproxy->secret;
        } else {
            $toolproxy = null;
            if (!empty($typeconfig['resourcekey'])) {
                $key = $typeconfig['resourcekey'];
            } else {
                $key = '';
            }
            if (!empty($typeconfig['password'])) {
                $secret = $typeconfig['password'];
            } else {
                $secret = '';
            }
        }

        if ($consumerkey !== $key) {
            throw new moodle_exception('errorincorrectconsumerkey', 'mod_lti');
        }

        $store = new lti\TrivialOAuthDataStore();
        $store->add_consumer($key, $secret);
        $server = new lti\OAuthServer($store);
        $method = new lti\OAuthSignatureMethod_HMAC_SHA1();
        $server->add_signature_method($method);
        $request = lti\OAuthRequest::from_request();
        try {
            $server->verify_request($request);
        } catch (lti\OAuthException $e) {
            throw new lti\OAuthException("OAuth signature failed: " . $e->getMessage());
        }

        return $tool;
    }

}