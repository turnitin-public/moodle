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
use Firebase\JWT\JWT;

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
        require_once(__DIR__ . '/../../config.php');
        require_once($CFG->dirroot . '/mod/lti/locallib.php');
        global $_POST, $_SERVER;

        if (!isloggedin() && empty($_POST['repost'])) {
            header_remove("Set-Cookie");
            $PAGE->set_pagelayout('popup');
            $PAGE->set_context(context_system::instance());
            $output = $PAGE->get_renderer('mod_lti');
            $page = new \mod_lti\output\repost_crosssite_page($_SERVER['REQUEST_URI'], $_POST);
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
                $returnurl = new \moodle_url('/mod/lti/contentitem_return.php', $returnurlparams);
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

        define('NO_DEBUG_DISPLAY', true);
        define('NO_MOODLE_COOKIES', true);
        require_once(__DIR__ . '/../../config.php');

        @header('Content-Type: application/json; charset=utf-8');

        echo json_encode(jwks_helper::get_jwks(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * Replaces the mod/lti/token.php file.
     *
     */
    public static function get_token_endpoint() {

        define('NO_DEBUG_DISPLAY', true);
        define('NO_MOODLE_COOKIES', true);
        require_once(__DIR__ . '/../../config.php');
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        $response = new \mod_lti\local\ltiservice\response();

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
            $typeconfig = lti_get_type_config($tool->id);
            $permittedscopes = lti_get_permitted_service_scopes($tool, $typeconfig);
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


}
