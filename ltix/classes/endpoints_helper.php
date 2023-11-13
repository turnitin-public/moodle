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

use core_ltix\ltiopenid\jwks_helper;
use core_ltix\types_helper;
use Firebase\JWT\JWT;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../constants.php');

/**
 * Helper class specifically dealing with LTI tools.
 *
 * @package    core_ltix
 * @author     Godson Ahamba (Turnitin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class endpoints_helper {
    /**
     * Replaces the mod/lti/auth.php file.
     *
     */
    public static function get_auth_endpoint() {
        global $_POST, $_SERVER;

        if (!isloggedin() && empty($_POST['repost'])) {
            header_remove("Set-Cookie");
            $PAGE->set_pagelayout('popup');
            $PAGE->set_context(context_system::instance());
            $output = $PAGE->get_renderer('mod_lti');
            $page = new \core_ltix\output\repost_crosssite_page($_SERVER['REQUEST_URI'], $_POST);
            echo $output->header();
            echo $output->render($page);
            echo $output->footer();
            return;
        }

        $scope = optional_param('scope', '', PARAM_TEXT);
        $responsetype = optional_param('response_type', '', PARAM_TEXT);
        $clientid = optional_param('client_id', '', PARAM_TEXT);
        $redirecturi = optional_param('redirect_uri', '', PARAM_URL);
        $loginhint = optional_param('login_hint', '', PARAM_TEXT);
        $ltimessagehintenc = optional_param('lti_message_hint', '', PARAM_TEXT);
        $state = optional_param('state', '', PARAM_TEXT);
        $responsemode = optional_param('response_mode', '', PARAM_TEXT);
        $nonce = optional_param('nonce', '', PARAM_TEXT);
        $prompt = optional_param('prompt', '', PARAM_TEXT);

        $ok = !empty($scope) && !empty($responsetype) && !empty($clientid) &&
            !empty($redirecturi) && !empty($loginhint) &&
            !empty($nonce);

        if (!$ok) {
            $error = 'invalid_request';
        }
        $ltimessagehint = json_decode($ltimessagehintenc);
        $ok = $ok && isset($ltimessagehint->launchid);
        if (!$ok) {
            $error = 'invalid_request';
            $desc = 'No launch id in LTI hint';
        }
        if ($ok && ($scope !== 'openid')) {
            $ok = false;
            $error = 'invalid_scope';
        }
        if ($ok && ($responsetype !== 'id_token')) {
            $ok = false;
            $error = 'unsupported_response_type';
        }
        if ($ok) {
            $launchid = $ltimessagehint->launchid;
            list($courseid, $typeid, $id, $messagetype, $foruserid, $titleb64, $textb64) = explode(',', $SESSION->$launchid, 7);
            unset($SESSION->$launchid);
            $config = \core_ltix\types_helper::get_type_type_config($typeid);
            $ok = ($clientid === $config->lti_clientid);
            if (!$ok) {
                $error = 'unauthorized_client';
            }
        }
        if ($ok && ($loginhint !== $USER->id)) {
            $ok = false;
            $error = 'access_denied';
        }

        // If we're unable to load up config; we cannot trust the redirect uri for POSTing to.
        if (empty($config)) {
            throw new moodle_exception('invalidrequest', 'error');
        } else {
            $uris = array_map("trim", explode("\n", $config->lti_redirectionuris));
            if (!in_array($redirecturi, $uris)) {
                throw new moodle_exception('invalidrequest', 'error');
            }
        }
        if ($ok) {
            if (isset($responsemode)) {
                $ok = ($responsemode === 'form_post');
                if (!$ok) {
                    $error = 'invalid_request';
                    $desc = 'Invalid response_mode';
                }
            } else {
                $ok = false;
                $error = 'invalid_request';
                $desc = 'Missing response_mode';
            }
        }
        if ($ok && !empty($prompt) && ($prompt !== 'none')) {
            $ok = false;
            $error = 'invalid_request';
            $desc = 'Invalid prompt';
        }

        if ($ok) {
            $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
            if ($id) {
                $cm = get_coursemodule_from_id('lti', $id, 0, false, MUST_EXIST);
                $context = context_module::instance($cm->id);
                require_login($course, true, $cm);
                require_capability('mod/lti:view', $context);
                $lti = $DB->get_record('lti', array('id' => $cm->instance), '*', MUST_EXIST);
                $lti->cmid = $cm->id;
                list($endpoint, $params) = lti_get_launch_data($lti, $nonce, $messagetype, $foruserid);
            } else {
                require_login($course);
                $context = context_course::instance($courseid);
                require_capability('moodle/course:manageactivities', $context);
                require_capability('mod/lti:addcoursetool', $context);
                // Set the return URL. We send the launch container along to help us avoid frames-within-frames when the user returns.
                $returnurlparams = [
                    'course' => $courseid,
                    'id' => $typeid,
                    'sesskey' => sesskey()
                ];
                $returnurl = new \moodle_url('/ltix/contentitem_return.php', $returnurlparams);
                // Prepare the request.
                $title = base64_decode($titleb64);
                $text = base64_decode($textb64);
                $request = lti_build_content_item_selection_request($typeid, $course, $returnurl, $title, $text,
                    [], [], false, true, false, false, false, $nonce);
                $endpoint = $request->url;
                $params = $request->params;
            }
        } else {
            $params['error'] = $error;
            if (!empty($desc)) {
                $params['error_description'] = $desc;
            }
        }
        if (isset($state)) {
            $params['state'] = $state;
        }
        unset($SESSION->lti_message_hint);
        $r = '<form action="' . $redirecturi . "\" name=\"ltiAuthForm\" id=\"ltiAuthForm\" " .
            "method=\"post\" enctype=\"application/x-www-form-urlencoded\">\n";
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $key = htmlspecialchars($key, ENT_COMPAT);
                $value = htmlspecialchars($value, ENT_COMPAT);
                $r .= "  <input type=\"hidden\" name=\"{$key}\" value=\"{$value}\"/>\n";
            }
        }
        $r .= "</form>\n";
        $r .= "<script type=\"text/javascript\">\n" .
            "//<![CDATA[\n" .
            "document.ltiAuthForm.submit();\n" .
            "//]]>\n" .
            "</script>\n";
        echo $r;
    }

    /**
     * Replaces the mod/lti/certs.php file.
     *
     */
    public static function get_certs_endpoint() {

        @header('Content-Type: application/json; charset=utf-8');

        echo json_encode(jwks_helper::get_jwks(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * Replaces the mod/lti/token.php file.
     *
     */
    public static function get_token_endpoint() {

        require_once(__DIR__ . '/../../config.php');

        $response = new \core_ltix\local\ltiservice\response();

        $contenttype = isset($_SERVER['CONTENT_TYPE']) ? explode(';', $_SERVER['CONTENT_TYPE'], 2)[0] : '';

        $ok = ($_SERVER['REQUEST_METHOD'] === 'POST') && ($contenttype === 'application/x-www-form-urlencoded');
        $error = 'invalid_request';

        $clientassertion = optional_param('client_assertion', '', PARAM_TEXT);
        $clientassertiontype = optional_param('client_assertion_type', '', PARAM_TEXT);
        $granttype = optional_param('grant_type', '', PARAM_TEXT);
        $scope = optional_param('scope', '', PARAM_TEXT);

        if ($ok) {
            $ok = !empty($clientassertion) && !empty($clientassertiontype) &&
                !empty($granttype) && !empty($scope);
        }

        if ($ok) {
            $ok = ($clientassertiontype === 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer') &&
                ($granttype === 'client_credentials');
            $error = 'unsupported_grant_type';
        }

        if ($ok) {
            $parts = explode('.', $clientassertion);
            $ok = (count($parts) === 3);
            if ($ok) {
                $payload = JWT::urlsafeB64Decode($parts[1]);
                $claims = json_decode($payload, true);
                $ok = !is_null($claims) && !empty($claims['sub']);
            }
            $error = 'invalid_request';
        }

        if ($ok) {
            $tool = $DB->get_record('lti_types', array('clientid' => $claims['sub']));
            if ($tool) {
                try {
                    lti_verify_jwt_signature($tool->id, $claims['sub'], $clientassertion);
                    $ok = true;
                } catch (Exception $e) {
                    $error = $e->getMessage();
                    $ok = false;
                }
            } else {
                $error = 'invalid_client';
                $ok = false;
            }
        }

        if ($ok) {
            $scopes = array();
            $requestedscopes = explode(' ', $scope);
            $typeconfig = types_helper::get_type_config($tool->id);
            $permittedscopes = types_helper::get_permitted_service_scopes($tool, $typeconfig);
            $scopes = array_intersect($requestedscopes, $permittedscopes);
            $ok = !empty($scopes);
            $error = 'invalid_scope';
        }

        if ($ok) {
            $token = lti_new_access_token($tool->id, $scopes);
            $expiry = LTI_ACCESS_TOKEN_LIFE;
            $permittedscopes = implode(' ', $scopes);
            $body = <<< EOD
            {
              "access_token" : "{$token->token}",
              "token_type" : "Bearer",
              "expires_in" : {$expiry},
              "scope" : "{$permittedscopes}"
            }
            EOD;
                    } else {
                        $response->set_code(400);
                        $body = <<< EOD
            {
              "error" : "{$error}"
            }
            EOD;
        }

        $response->set_body($body);

        $response->send();
    }

    /**
     * Return the launch data required for opening the external tool.
     *
     * @param  stdClass $instance the external tool activity settings
     * @param  string $nonce  the nonce value to use (applies to LTI 1.3 only)
     * @return array the endpoint URL and parameters (including the signature)
     * @since  Moodle 3.0
     */
    function get_launch_data($instance, $nonce = '', $messagetype = 'basic-lti-launch-request', $foruserid = 0) {
        global $PAGE, $USER;
        $messagetype = $messagetype ? $messagetype : 'basic-lti-launch-request';
        $tool = lti_get_instance_type($instance);
        if ($tool) {
            $typeid = $tool->id;
            $ltiversion = $tool->ltiversion;
        } else {
            $typeid = null;
            $ltiversion = LTI_VERSION_1;
        }

        if ($typeid) {
            $typeconfig = \core_ltix\types_helper::get_type_config($typeid);
        } else {
            // There is no admin configuration for this tool. Use configuration in the lti instance record plus some defaults.
            $typeconfig = (array)$instance;

            $typeconfig['sendname'] = $instance->instructorchoicesendname;
            $typeconfig['sendemailaddr'] = $instance->instructorchoicesendemailaddr;
            $typeconfig['customparameters'] = $instance->instructorcustomparameters;
            $typeconfig['acceptgrades'] = $instance->instructorchoiceacceptgrades;
            $typeconfig['allowroster'] = $instance->instructorchoiceallowroster;
            $typeconfig['forcessl'] = '0';
        }

        if (isset($tool->toolproxyid)) {
            $toolproxy = \core_ltix\tool_helper::get_tool_proxy($tool->toolproxyid);
            $key = $toolproxy->guid;
            $secret = $toolproxy->secret;
        } else {
            $toolproxy = null;
            if (!empty($instance->resourcekey)) {
                $key = $instance->resourcekey;
            } else if ($ltiversion === LTI_VERSION_1P3) {
                $key = $tool->clientid;
            } else if (!empty($typeconfig['resourcekey'])) {
                $key = $typeconfig['resourcekey'];
            } else {
                $key = '';
            }
            if (!empty($instance->password)) {
                $secret = $instance->password;
            } else if (!empty($typeconfig['password'])) {
                $secret = $typeconfig['password'];
            } else {
                $secret = '';
            }
        }

        $endpoint = !empty($instance->toolurl) ? $instance->toolurl : $typeconfig['toolurl'];
        $endpoint = trim($endpoint);

        // If the current request is using SSL and a secure tool URL is specified, use it.
        if (\core_ltix\tool_helper::request_is_using_ssl() && !empty($instance->securetoolurl)) {
            $endpoint = trim($instance->securetoolurl);
        }

        // If SSL is forced, use the secure tool url if specified. Otherwise, make sure https is on the normal launch URL.
        if (isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) {
            if (!empty($instance->securetoolurl)) {
                $endpoint = trim($instance->securetoolurl);
            }

            if ($endpoint !== '') {
                $endpoint = \core_ltix\tool_helper::ensure_url_is_https($endpoint);
            }
        } else if ($endpoint !== '' && !strstr($endpoint, '://')) {
            $endpoint = 'http://' . $endpoint;
        }

        $orgid = \core_ltix\types_helper::get_organizationid($typeconfig);

        $course = $PAGE->course;
        $islti2 = isset($tool->toolproxyid);
        $allparams = lti_build_request($instance, $typeconfig, $course, $typeid, $islti2, $messagetype, $foruserid);
        if ($islti2) {
            $requestparams = \core_ltix\tool_helper::build_request_lti2($tool, $allparams);
        } else {
            $requestparams = $allparams;
        }
        $requestparams = array_merge($requestparams, lti_build_standard_message($instance, $orgid, $ltiversion, $messagetype));
        $customstr = '';
        if (isset($typeconfig['customparameters'])) {
            $customstr = $typeconfig['customparameters'];
        }
        $services = \core_ltix\tool_helper::get_services();
        foreach ($services as $service) {
            [$endpoint, $customstr] = $service->override_endpoint($messagetype,
                $endpoint, $customstr, $instance->course, $instance);
        }
        $requestparams = array_merge($requestparams, lti_build_custom_parameters($toolproxy, $tool, $instance, $allparams, $customstr,
            $instance->instructorcustomparameters, $islti2));

        $launchcontainer = lti_get_launch_container($instance, $typeconfig);
        $returnurlparams = array('course' => $course->id,
            'launch_container' => $launchcontainer,
            'instanceid' => $instance->id,
            'sesskey' => sesskey());

        // Add the return URL. We send the launch container along to help us avoid frames-within-frames when the user returns.
        $url = new \moodle_url('/mod/lti/return.php', $returnurlparams);
        $returnurl = $url->out(false);

        if (isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) {
            $returnurl = \core_ltix\tool_helper::ensure_url_is_https($returnurl);
        }

        $target = '';
        switch($launchcontainer) {
            case LTI_LAUNCH_CONTAINER_EMBED:
            case LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS:
                $target = 'iframe';
                break;
            case LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW:
                $target = 'frame';
                break;
            case LTI_LAUNCH_CONTAINER_WINDOW:
                $target = 'window';
                break;
        }
        if (!empty($target)) {
            $requestparams['launch_presentation_document_target'] = $target;
        }

        $requestparams['launch_presentation_return_url'] = $returnurl;

        // Add the parameters configured by the LTI services.
        if ($typeid && !$islti2) {
            $services = \core_ltix\tool_helper::get_services();
            foreach ($services as $service) {
                $serviceparameters = $service->get_launch_parameters('basic-lti-launch-request',
                    $course->id, $USER->id , $typeid, $instance->id);
                foreach ($serviceparameters as $paramkey => $paramvalue) {
                    $requestparams['custom_' . $paramkey] = \core_ltix\tool_helper::parse_custom_parameter($toolproxy, $tool,
                        $requestparams, $paramvalue, $islti2);
                }
            }
        }

        // Allow request params to be updated by sub-plugins.
        $plugins = core_component::get_plugin_list('ltisource');
        foreach (array_keys($plugins) as $plugin) {
            $pluginparams = component_callback('ltisource_'.$plugin, 'before_launch',
                array($instance, $endpoint, $requestparams), array());

            if (!empty($pluginparams) && is_array($pluginparams)) {
                $requestparams = array_merge($requestparams, $pluginparams);
            }
        }

        if ((!empty($key) && !empty($secret)) || ($ltiversion === LTI_VERSION_1P3)) {
            if ($ltiversion !== LTI_VERSION_1P3) {
                $parms = \core_ltix\oauth_helper::sign_parameters($requestparams, $endpoint, 'POST', $key, $secret);
            } else {
                $parms = \core_ltix\oauth_helper::sign_jwt($requestparams, $endpoint, $key, $typeid, $nonce);
            }

            $endpointurl = new \moodle_url($endpoint);
            $endpointparams = $endpointurl->params();

            // Strip querystring params in endpoint url from $parms to avoid duplication.
            if (!empty($endpointparams) && !empty($parms)) {
                foreach (array_keys($endpointparams) as $paramname) {
                    if (isset($parms[$paramname])) {
                        unset($parms[$paramname]);
                    }
                }
            }

        } else {
            // If no key and secret, do the launch unsigned.
            $returnurlparams['unsigned'] = '1';
            $parms = $requestparams;
        }

        return array($endpoint, $parms);
    }


}
