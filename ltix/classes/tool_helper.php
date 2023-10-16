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

use core_component;

/**
 * Helper class specifically dealing with LTI tools.
 *
 * @package    core_ltix
 * @author     Alex Morris <alex.morris@catalyst.net.nz>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_helper {

    public static function get_tools_by_domain($domain, $state = null, $courseid = null) {
        global $DB, $SITE;

        $statefilter = '';
        $coursefilter = '';

        if ($state) {
            $statefilter = 'AND t.state = :state';
        }

        if ($courseid && $courseid != $SITE->id) {
            $coursefilter = 'OR t.course = :courseid';
        }

        $coursecategory = $DB->get_field('course', 'category', ['id' => $courseid]);
        $query = "SELECT t.*
                FROM {lti_types} t
           LEFT JOIN {lti_types_categories} tc on t.id = tc.typeid
               WHERE t.tooldomain = :tooldomain
                 AND (t.course = :siteid $coursefilter)
                 $statefilter
                 AND (tc.id IS NULL OR tc.categoryid = :categoryid)";

        return $DB->get_records_sql($query, [
            'courseid' => $courseid,
            'siteid' => $SITE->id,
            'tooldomain' => $domain,
            'state' => $state,
            'categoryid' => $coursecategory
        ]);
    }

    public static function get_domain_from_url($url) {
        $matches = array();

        if (preg_match(LTI_URL_DOMAIN_REGEX, $url ?? '', $matches)) {
            return $matches[1];
        }
    }

    public static function get_tools_by_url($url, $state, $courseid = null) {
        $domain = self::get_domain_from_url($url);

        return self::get_tools_by_domain($domain, $state, $courseid);
    }

    public static function get_tool_by_url_match($url, $courseid = null, $state = LTI_TOOL_STATE_CONFIGURED) {
        $possibletools = self::get_tools_by_url($url, $state, $courseid);

        return self::get_best_tool_by_url($url, $possibletools, $courseid);
    }

    public static function get_best_tool_by_url($url, $tools, $courseid = null) {
        if (count($tools) === 0) {
            return null;
        }

        $urllower = self::get_url_thumbprint($url);

        foreach ($tools as $tool) {
            $tool->_matchscore = 0;

            $toolbaseurllower = self::get_url_thumbprint($tool->baseurl);

            if ($urllower === $toolbaseurllower) {
                // 100 points for exact thumbprint match.
                $tool->_matchscore += 100;
            } else if (substr($urllower, 0, strlen($toolbaseurllower)) === $toolbaseurllower) {
                // 50 points if tool thumbprint starts with the base URL thumbprint.
                $tool->_matchscore += 50;
            }

            // Prefer course tools over site tools.
            if (!empty($courseid)) {
                // Minus 10 points for not matching the course id (global tools).
                if ($tool->course != $courseid) {
                    $tool->_matchscore -= 10;
                }
            }
        }

        $bestmatch = array_reduce($tools, function($value, $tool) {
            if ($tool->_matchscore > $value->_matchscore) {
                return $tool;
            } else {
                return $value;
            }

        }, (object) array('_matchscore' => -1));

        // None of the tools are suitable for this URL.
        if ($bestmatch->_matchscore <= 0) {
            return null;
        }

        return $bestmatch;
    }

    public static function get_url_thumbprint($url) {
        // Parse URL requires a schema otherwise everything goes into 'path'.  Fixed 5.4.7 or later.
        if (preg_match('/https?:\/\//', $url) !== 1) {
            $url = 'http://' . $url;
        }
        $urlparts = parse_url(strtolower($url));
        if (!isset($urlparts['path'])) {
            $urlparts['path'] = '';
        }

        if (!isset($urlparts['query'])) {
            $urlparts['query'] = '';
        }

        if (!isset($urlparts['host'])) {
            $urlparts['host'] = '';
        }

        if (substr($urlparts['host'], 0, 4) === 'www.') {
            $urlparts['host'] = substr($urlparts['host'], 4);
        }

        $urllower = $urlparts['host'] . '/' . $urlparts['path'];

        if ($urlparts['query'] != '') {
            $urllower .= '?' . $urlparts['query'];
        }

        return $urllower;
    }

    /**
     * Update the database with a tool proxy instance
     *
     * @param object $config Tool proxy definition
     *
     * @return int  Record id number
     */
    public static function add_tool_proxy($config) {
        global $USER, $DB;

        $toolproxy = new \stdClass();
        if (isset($config->lti_registrationname)) {
            $toolproxy->name = trim($config->lti_registrationname);
        }
        if (isset($config->lti_registrationurl)) {
            $toolproxy->regurl = trim($config->lti_registrationurl);
        }
        if (isset($config->lti_capabilities)) {
            $toolproxy->capabilityoffered = implode("\n", $config->lti_capabilities);
        } else {
            $toolproxy->capabilityoffered = implode("\n", array_keys(lti_get_capabilities()));
        }
        if (isset($config->lti_services)) {
            $toolproxy->serviceoffered = implode("\n", $config->lti_services);
        } else {
            $func = function($s) {
                return $s->get_id();
            };
            $servicenames = array_map($func, self::get_services());
            $toolproxy->serviceoffered = implode("\n", $servicenames);
        }
        if (isset($config->toolproxyid) && !empty($config->toolproxyid)) {
            $toolproxy->id = $config->toolproxyid;
            if (!isset($toolproxy->state) || ($toolproxy->state != LTI_TOOL_PROXY_STATE_ACCEPTED)) {
                $toolproxy->state = LTI_TOOL_PROXY_STATE_CONFIGURED;
                $toolproxy->guid = random_string();
                $toolproxy->secret = random_string();
            }
            $id = self::update_tool_proxy($toolproxy);
        } else {
            $toolproxy->state = LTI_TOOL_PROXY_STATE_CONFIGURED;
            $toolproxy->timemodified = time();
            $toolproxy->timecreated = $toolproxy->timemodified;
            if (!isset($toolproxy->createdby)) {
                $toolproxy->createdby = $USER->id;
            }
            $toolproxy->guid = random_string();
            $toolproxy->secret = random_string();
            $id = $DB->insert_record('lti_tool_proxies', $toolproxy);
        }

        return $id;
    }

    /**
     * Initializes an array with the services supported by the LTI module
     *
     * @return array List of services
     */
    public static function get_services() {
        $services = array();
        $definedservices = core_component::get_plugin_list('ltiservice');
        foreach ($definedservices as $name => $location) {
            $classname = "\\ltiservice_{$name}\\local\\service\\{$name}";
            $services[] = new $classname();
        }

        return $services;
    }

    /**
     * Updates a tool proxy in the database
     *
     * @param object $toolproxy Tool proxy
     *
     * @return int    Record id number
     */
    public static function update_tool_proxy($toolproxy) {
        global $DB;

        $toolproxy->timemodified = time();
        $id = $DB->update_record('lti_tool_proxies', $toolproxy);

        return $id;
    }

    /**
     * Delete a Tool Proxy
     *
     * @param int $id   Tool Proxy id
     */
    public static function delete_tool_proxy($id) {
        global $DB;
        $DB->delete_records('lti_tool_settings', array('toolproxyid' => $id));
        $tools = $DB->get_records('lti_types', array('toolproxyid' => $id));
        foreach ($tools as $tool) {
            types_helper::delete_type($tool->id);
        }
        $DB->delete_records('lti_tool_proxies', array('id' => $id));
    }

}
