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
require_once($CFG->dirroot . '/ltix/ltixconstants.php');

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
        if (has_capability('moodle/ltix:addpreconfiguredinstance', context_course::instance($COURSE->id))) {
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
        if (has_capability('moodle/ltix:addpreconfiguredinstance', context_course::instance($courseid))) {
            $courseconds[] = "course = :courseid";
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

    public static function lti_get_type($typeid) {
        global $DB;

        return $DB->get_record('lti_types', array('id' => $typeid));
    }

}