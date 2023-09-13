<?php
/**
 * This file contains the class for the ltix_helper class
 *
 * @package    core
 * @subpackage ltix
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types = 1);

namespace core_ltix;

use context_course;
use \core_ltix\output\external_content;

defined('MOODLE_INTERNAL') || die();


define('LTI_URL_DOMAIN_REGEX', '/(?:https?:\/\/)?(?:www\.)?([^\/]+)(?:\/|$)/i');

define('LTI_LAUNCH_CONTAINER_DEFAULT', 1);
define('LTI_LAUNCH_CONTAINER_EMBED', 2);
define('LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS', 3);
define('LTI_LAUNCH_CONTAINER_WINDOW', 4);
define('LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW', 5);

define('LTI_TOOL_STATE_ANY', 0);
define('LTI_TOOL_STATE_CONFIGURED', 1);
define('LTI_TOOL_STATE_PENDING', 2);
define('LTI_TOOL_STATE_REJECTED', 3);
define('LTI_TOOL_PROXY_TAB', 4);

define('LTI_TOOL_PROXY_STATE_CONFIGURED', 1);
define('LTI_TOOL_PROXY_STATE_PENDING', 2);
define('LTI_TOOL_PROXY_STATE_ACCEPTED', 3);
define('LTI_TOOL_PROXY_STATE_REJECTED', 4);

define('LTI_SETTING_NEVER', 0);
define('LTI_SETTING_ALWAYS', 1);
define('LTI_SETTING_DELEGATE', 2);

define('LTI_COURSEVISIBLE_NO', 0);
define('LTI_COURSEVISIBLE_PRECONFIGURED', 1);
define('LTI_COURSEVISIBLE_ACTIVITYCHOOSER', 2);

define('LTI_VERSION_1', 'LTI-1p0');
define('LTI_VERSION_2', 'LTI-2p0');
define('LTI_VERSION_1P3', '1.3.0');
define('LTI_RSA_KEY', 'RSA_KEY');
define('LTI_JWK_KEYSET', 'JWK_KEYSET');

define('LTI_DEFAULT_ORGID_SITEID', 'SITEID');
define('LTI_DEFAULT_ORGID_SITEHOST', 'SITEHOST');

define('LTI_ACCESS_TOKEN_LIFE', 3600);

// Standard prefix for JWT claims.
define('LTI_JWT_CLAIM_PREFIX', 'https://purl.imsglobal.org/spec/lti');


class ltix_helper
{

    /**
     * Returns tool types for lti add instance and edit page
     *
     * @return array Array of lti types
     */
    public static function lti_get_types_for_add_instance() {
        global $COURSE;
        $admintypes = self::lti_get_lti_types_by_course($COURSE->id);

        $types = array();
        if (has_capability('moodle/ltix:addmanualinstance', context_course::instance($COURSE->id))) {
            $types[0] = (object)array('name' => get_string('automatic', 'ltix'), 'course' => 0, 'toolproxyid' => null);
        }

        foreach ($admintypes as $type) {
            $types[$type->id] = $type;
        }

        return $types;
    }

    /**
     * Returns all lti types visible in this course
     *
     * @param int $courseid The id of the course to retieve types for
     * @param array $coursevisible options for 'coursevisible' field,
     *        default [LTI_COURSEVISIBLE_PRECONFIGURED, LTI_COURSEVISIBLE_ACTIVITYCHOOSER]
     * @return stdClass[] All the lti types visible in the given course
     */
    public static function lti_get_lti_types_by_course($courseid, $coursevisible = null) {
        global $DB, $SITE;

        if ($coursevisible === null) {
            $coursevisible = [LTI_COURSEVISIBLE_PRECONFIGURED, LTI_COURSEVISIBLE_ACTIVITYCHOOSER];
        }

        list($coursevisiblesql, $coursevisparams) = $DB->get_in_or_equal($coursevisible, SQL_PARAMS_NAMED, 'coursevisible');
        $courseconds = [];
        if (has_capability('moodle/ltix:addmanualinstance', context_course::instance($courseid))) {
            $courseconds[] = "course = :courseid";
        }
        if (has_capability('moodle/ltix:addpreconfiguredinstance', context_course::instance($courseid))) {
            $courseconds[] = "course = :siteid";
        }
        if (!$courseconds) {
            return [];
        }
        $coursecond = implode(" OR ", $courseconds);
        $query = "SELECT *
                FROM {lti_types}
               WHERE coursevisible $coursevisiblesql
                 AND ($coursecond)
                 AND state = :active
            ORDER BY name ASC";

        return $DB->get_records_sql($query,
            array('siteid' => $SITE->id, 'courseid' => $courseid, 'active' => LTI_TOOL_STATE_CONFIGURED) + $coursevisparams);
    }

    /**
     * Returns configuration details for the tool
     *
     * @param int $typeid   Basic LTI tool typeid
     *
     * @return array        Tool Configuration
     */
   public static function lti_get_type_config($typeid) {
        global $DB;

        $query = "SELECT name, value
                FROM {lti_types_config}
               WHERE typeid = :typeid1
           UNION ALL
              SELECT 'toolurl' AS name, baseurl AS value
                FROM {lti_types}
               WHERE id = :typeid2
           UNION ALL
              SELECT 'icon' AS name, icon AS value
                FROM {lti_types}
               WHERE id = :typeid3
           UNION ALL
              SELECT 'secureicon' AS name, secureicon AS value
                FROM {lti_types}
               WHERE id = :typeid4";

        $typeconfig = array();
        $configs = $DB->get_records_sql($query,
            array('typeid1' => $typeid, 'typeid2' => $typeid, 'typeid3' => $typeid, 'typeid4' => $typeid));

        if (!empty($configs)) {
            foreach ($configs as $config) {
                $typeconfig[$config->name] = $config->value;
            }
        }

        return $typeconfig;
   }

   /**
    * This handles display and rendering of external tool.
    */
   public static function lti_display_tool($instance, &$o, $module) {
       $external_content = new external_content($instance);
       if(!empty($external_content->get_lti())) {
           $o .= $module->get_renderer()->render(new external_content($instance));
       }
       return $o;
   }

   /**
    * Renders lti attachment on module view page.
    * Called from mod/(module)/classes/output/renderer.php
    */
   public static function lti_render_attachment($renderer, $external_content) {
       if($external_content->get_lti() != null) {
           $cm = $external_content->get_course_module();
       }
       else {
           $cm = $renderer->page->cm;
       }
       $launchurl = new \moodle_url('/mod/lti/launch.php', ['id' => $cm->id, 'triggerview' => 0]);

       $content = '';
       // Build the allowed URL, since we know what it will be from $lti->toolurl,
       // If the specified toolurl is invalid the iframe won't load, but we still want to avoid parse related errors here.
       // So we set an empty default allowed url, and only build a real one if the parse is successful.
       $ltiallow = '';
       $urlparts = parse_url($external_content->get_lti()->toolurl);
       if ($urlparts && array_key_exists('scheme', $urlparts) && array_key_exists('host', $urlparts)) {
           $ltiallow = $urlparts['scheme'] . '://' . $urlparts['host'];
           // If a port has been specified we append that too.
           if (array_key_exists('port', $urlparts)) {
               $ltiallow .= ':' . $urlparts['port'];
           }
       }

       // Request the launch content with an iframe tag.
       $attributes = '';
       $attributes .= 'allow="microphone ' . $ltiallow . '; ' .
           'camera '. $ltiallow . '; ' .
           'geolocation ' . $ltiallow . '; ' .
           'midi '. $ltiallow . '; ' .
           'encrypted-media ' . $ltiallow . '; ' .
           'autoplay ' . $ltiallow . '" ';
       $attributes .= 'allowfullscreen = "1" ';
       $params = [
           'src' => $launchurl,
           'iframeid' => 'contentframe',
           'attributes' => $attributes,
       ];
       //die(print_r($attributes, true));
       return $renderer->render_from_template('core/mod_external_content_banner', $params);
   }


}