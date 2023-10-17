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

declare(strict_types=1);

namespace core_ltix;

use context_course;
use core\context\course;
use mod_lti\local\ltiopenid\registration_helper;

/**
 * Helper class specifically dealing with LTI types (preconfigured tools).
 *
 * @package    core_ltix
 * @author     Godson Ahamba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class types_helper {

    /**
     * Returns tool types for lti add instance and edit page
     *
     * @return array Array of lti types
     */
    public static function get_types_for_add_instance() {
        global $COURSE, $USER;
        $admintypes = self::get_lti_types_by_course($COURSE->id, $USER->id);

        $types = array();
        if (has_capability('moodle/ltix:addmanualinstance', context_course::instance($COURSE->id))) {
            $types[0] = (object) array('name' => get_string('automatic', 'ltix'), 'course' => 0, 'toolproxyid' => null);
        }

        foreach ($admintypes as $type) {
            $types[$type->id] = $type;
        }

        return $types;
    }

    /**
     * Returns all LTI tool types (preconfigured tools) visible in the given course and for the given user.
     *
     * This list will contain both site level tools and course-level tools.
     *
     * @param int $courseid the id of the course.
     * @param int $userid the id of the user.
     * @param array $coursevisible options for 'coursevisible' field, which will default to
     *        [LTI_COURSEVISIBLE_PRECONFIGURED, LTI_COURSEVISIBLE_ACTIVITYCHOOSER] if omitted.
     * @return \stdClass[] the array of tool type objects.
     */
    public static function get_lti_types_by_course(int $courseid, int $userid, array $coursevisible = []): array {
        global $DB, $SITE;

        if (!has_capability('moodle/ltix:addpreconfiguredinstance', course::instance($courseid), $userid)) {
            return [];
        }

        if (empty($coursevisible)) {
            $coursevisible = [LTI_COURSEVISIBLE_PRECONFIGURED, LTI_COURSEVISIBLE_ACTIVITYCHOOSER];
        }
        [$coursevisiblesql, $coursevisparams] = $DB->get_in_or_equal($coursevisible, SQL_PARAMS_NAMED, 'coursevisible');
        [$coursevisiblesql1, $coursevisparams1] = $DB->get_in_or_equal($coursevisible, SQL_PARAMS_NAMED, 'coursevisible');
        [$coursevisibleoverriddensql, $coursevisoverriddenparams] = $DB->get_in_or_equal(
            $coursevisible,
            SQL_PARAMS_NAMED,
            'coursevisibleoverridden');

        $coursecond = implode(" OR ", ["t.course = :courseid", "t.course = :siteid"]);
        $coursecategory = $DB->get_field('course', 'category', ['id' => $courseid]);
        $query = "SELECT *
                    FROM (SELECT t.*, c.coursevisible as coursevisibleoverridden
                            FROM {lti_types} t
                       LEFT JOIN {lti_types_categories} tc ON t.id = tc.typeid
                       LEFT JOIN {lti_coursevisible} c ON c.typeid = t.id AND c.courseid = $courseid
                           WHERE (t.coursevisible $coursevisiblesql
                                 OR (c.coursevisible $coursevisiblesql1 AND t.coursevisible NOT IN (:lticoursevisibleno)))
                             AND ($coursecond)
                             AND t.state = :active
                             AND (tc.id IS NULL OR tc.categoryid = :categoryid)) tt
                   WHERE tt.coursevisibleoverridden IS NULL
                      OR tt.coursevisibleoverridden $coursevisibleoverriddensql";

        return $DB->get_records_sql(
            $query,
            [
                'siteid' => $SITE->id,
                'courseid' => $courseid,
                'active' => LTI_TOOL_STATE_CONFIGURED,
                'categoryid' => $coursecategory,
                'coursevisible' => LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
                'lticoursevisibleno' => LTI_COURSEVISIBLE_NO,
            ] + $coursevisparams + $coursevisparams1 + $coursevisoverriddenparams
        );
    }

    /**
     * Override coursevisible for a given tool on course level.
     *
     * @param int $tooltypeid Type ID
     * @param int $courseid Course ID
     * @param \core\context\course $context Course context
     * @param bool $showinactivitychooser Show or not show in activity chooser
     * @return bool True if the coursevisible was changed, false otherwise.
     */
    public static function override_type_showinactivitychooser(int $tooltypeid, int $courseid, \core\context\course $context,
        bool $showinactivitychooser): bool {
        global $DB;

        require_capability('moodle/ltix:addcoursetool', $context);

        $ltitype = self::get_type($tooltypeid);
        if ($ltitype && ($ltitype->coursevisible != LTI_COURSEVISIBLE_NO)) {
            $coursevisible = $showinactivitychooser ? LTI_COURSEVISIBLE_ACTIVITYCHOOSER : LTI_COURSEVISIBLE_PRECONFIGURED;
            $ltitype->coursevisible = $coursevisible;

            $config = new \stdClass();
            $config->lti_coursevisible = $coursevisible;

            if (intval($ltitype->course) != intval(get_site()->id)) {
                // It is course tool - just update it.
                self::update_type($ltitype, $config);
            } else {
                $coursecategory = $DB->get_field('course', 'category', ['id' => $courseid]);
                $sql = "SELECT COUNT(*) AS count
                      FROM {lti_types_categories} tc
                     WHERE tc.typeid = :typeid";
                $restrictedtool = $DB->count_records_sql($sql, ['typeid' => $tooltypeid]);
                if ($restrictedtool) {
                    $record = $DB->get_record('lti_types_categories', ['typeid' => $tooltypeid, 'categoryid' => $coursecategory]);
                    if (!$record) {
                        throw new \moodle_exception('You are not allowed to change this setting for this tool.');
                    }
                }

                // This is site tool, but we would like to have course level setting for it.
                $lticoursevisible = $DB->get_record('lti_coursevisible', ['typeid' => $tooltypeid, 'courseid' => $courseid]);
                if (!$lticoursevisible) {
                    $lticoursevisible = new \stdClass();
                    $lticoursevisible->typeid = $tooltypeid;
                    $lticoursevisible->courseid = $courseid;
                    $lticoursevisible->coursevisible = $coursevisible;
                    $DB->insert_record('lti_coursevisible', $lticoursevisible);
                } else {
                    $lticoursevisible->coursevisible = $coursevisible;
                    $DB->update_record('lti_coursevisible', $lticoursevisible);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Returns configuration details for the tool
     *
     * @param int $typeid Basic LTI tool typeid
     *
     * @return array        Tool Configuration
     */
    public static function get_type_config($typeid) {
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

    public static function get_type($typeid) {
        global $DB;

        return $DB->get_record('lti_types', array('id' => $typeid));
    }

    /**
     * Returns all basicLTI tools configured by the administrator
     *
     * @param int $course
     *
     * @return array
     */
    public static function filter_get_types($course) {
        global $DB;

        if (!empty($course)) {
            $where = "WHERE t.course = :course";
            $params = array('course' => $course);
        } else {
            $where = '';
            $params = array();
        }
        $query = "SELECT t.id, t.name, t.baseurl, t.state, t.toolproxyid, t.timecreated, tp.name tpname
                FROM {lti_types} t LEFT OUTER JOIN {lti_tool_proxies} tp ON t.toolproxyid = tp.id
                {$where}";
        return $DB->get_records_sql($query, $params);
    }

    /**
     * Delete a Basic LTI configuration
     *
     * @param int $id Configuration id
     */
    public static function delete_type($id) {
        global $DB;

        // We should probably just copy the launch URL to the tool instances in this case... using a single query.
        /*
        $instances = $DB->get_records('lti', array('typeid' => $id));
        foreach ($instances as $instance) {
            $instance->typeid = 0;
            $DB->update_record('lti', $instance);
        }*/

        $DB->delete_records('lti_types', array('id' => $id));
        $DB->delete_records('lti_types_config', array('typeid' => $id));
        $DB->delete_records('lti_types_categories', array('typeid' => $id));
    }

    public static function set_state_for_type($id, $state) {
        global $DB;

        $DB->update_record('lti_types', (object) array('id' => $id, 'state' => $state));
    }

    public static function request_is_using_ssl() {
        global $CFG;
        return (stripos($CFG->wwwroot, 'https://') === 0);
    }

    public static function update_type($type, $config) {
        global $DB, $CFG;

        self::prepare_type_for_save($type, $config);

        if (self::request_is_using_ssl() && !empty($type->secureicon)) {
            $clearcache = !isset($config->oldicon) || ($config->oldicon !== $type->secureicon);
        } else {
            $clearcache = isset($type->icon) && (!isset($config->oldicon) || ($config->oldicon !== $type->icon));
        }
        unset($config->oldicon);

        if ($DB->update_record('lti_types', $type)) {
            foreach ($config as $key => $value) {
                if (substr($key, 0, 4) == 'lti_' && !is_null($value)) {
                    $record = new \StdClass();
                    $record->typeid = $type->id;
                    $record->name = substr($key, 4);
                    $record->value = $value;
                    self::update_config($record);
                }
                if (substr($key, 0, 11) == 'ltiservice_' && !is_null($value)) {
                    $record = new \StdClass();
                    $record->typeid = $type->id;
                    $record->name = $key;
                    $record->value = $value;
                    self::update_config($record);
                }
            }
            if (isset($type->toolproxyid) && $type->ltiversion === LTI_VERSION_1P3) {
                // We need to remove the tool proxy for this tool to function under 1.3.
                $toolproxyid = $type->toolproxyid;
                $DB->delete_records('lti_tool_settings', array('toolproxyid' => $toolproxyid));
                $DB->delete_records('lti_tool_proxies', array('id' => $toolproxyid));
                $type->toolproxyid = null;
                $DB->update_record('lti_types', $type);
            }
            $DB->delete_records('lti_types_categories', ['typeid' => $type->id]);
            if (isset($config->lti_coursecategories) && !empty($config->lti_coursecategories)) {
                self::type_add_categories($type->id, $config->lti_coursecategories);
            }
            require_once($CFG->libdir . '/modinfolib.php');
            if ($clearcache) {
                $sql = "SELECT cm.id, cm.course
                      FROM {course_modules} cm
                      JOIN {modules} m ON cm.module = m.id
                      JOIN {lti} l ON l.course = cm.course
                     WHERE m.name = :name AND l.typeid = :typeid";

                $rs = $DB->get_recordset_sql($sql, ['name' => 'lti', 'typeid' => $type->id]);

                $courseids = [];
                foreach ($rs as $record) {
                    $courseids[] = $record->course;
                    \course_modinfo::purge_course_module_cache($record->course, $record->id);
                }
                $rs->close();
                $courseids = array_unique($courseids);
                foreach ($courseids as $courseid) {
                    rebuild_course_cache($courseid, false, true);
                }
            }
        }
    }

    public static function prepare_type_for_save($type, $config) {
        if (isset($config->lti_toolurl)) {
            $type->baseurl = $config->lti_toolurl;
            if (isset($config->lti_tooldomain)) {
                $type->tooldomain = $config->lti_tooldomain;
            } else {
                $type->tooldomain = tool_helper::get_domain_from_url($config->lti_toolurl);
            }
        }
        if (isset($config->lti_description)) {
            $type->description = $config->lti_description;
        }
        if (isset($config->lti_typename)) {
            $type->name = $config->lti_typename;
        }
        if (isset($config->lti_ltiversion)) {
            $type->ltiversion = $config->lti_ltiversion;
        }
        if (isset($config->lti_clientid)) {
            $type->clientid = $config->lti_clientid;
        }
        if ((!empty($type->ltiversion) && $type->ltiversion === LTI_VERSION_1P3) && empty($type->clientid)) {
            $type->clientid = registration_helper::get()->new_clientid();
        } else if (empty($type->clientid)) {
            $type->clientid = null;
        }
        if (isset($config->lti_coursevisible)) {
            $type->coursevisible = $config->lti_coursevisible;
        }

        if (isset($config->lti_icon)) {
            $type->icon = $config->lti_icon;
        }
        if (isset($config->lti_secureicon)) {
            $type->secureicon = $config->lti_secureicon;
        }

        $type->forcessl = !empty($config->lti_forcessl) ? $config->lti_forcessl : 0;
        $config->lti_forcessl = $type->forcessl;
        if (isset($config->lti_contentitem)) {
            $type->contentitem = !empty($config->lti_contentitem) ? $config->lti_contentitem : 0;
            $config->lti_contentitem = $type->contentitem;
        }
        if (isset($config->lti_toolurl_ContentItemSelectionRequest)) {
            if (!empty($config->lti_toolurl_ContentItemSelectionRequest)) {
                $type->toolurl_ContentItemSelectionRequest = $config->lti_toolurl_ContentItemSelectionRequest;
            } else {
                $type->toolurl_ContentItemSelectionRequest = '';
            }
            $config->lti_toolurl_ContentItemSelectionRequest = $type->toolurl_ContentItemSelectionRequest;
        }

        $type->timemodified = time();

        unset ($config->lti_typename);
        unset ($config->lti_toolurl);
        unset ($config->lti_description);
        unset ($config->lti_ltiversion);
        unset ($config->lti_clientid);
        unset ($config->lti_icon);
        unset ($config->lti_secureicon);
    }

    /**
     * Updates a tool configuration in the database
     *
     * @param object  $config   Tool configuration
     *
     * @return mixed Record id number
     */
    public static function update_config($config) {
        global $DB;

        $old = $DB->get_record('lti_types_config', array('typeid' => $config->typeid, 'name' => $config->name));

        if ($old) {
            $config->id = $old->id;
            $return = $DB->update_record('lti_types_config', $config);
        } else {
            $return = $DB->insert_record('lti_types_config', $config);
        }
        return $return;
    }

    /**
     * Add LTI Type course category.
     *
     * @param int $typeid
     * @param string $lticoursecategories Comma separated list of course categories.
     * @return void
     */
    public static function type_add_categories(int $typeid, string $lticoursecategories = '') : void {
        global $DB;
        $coursecategories = explode(',', $lticoursecategories);
        foreach ($coursecategories as $coursecategory) {
            $DB->insert_record('lti_types_categories', ['typeid' => $typeid, 'categoryid' => $coursecategory]);
        }
    }

    public static function add_type($type, $config) {
        global $USER, $SITE, $DB;

        self::prepare_type_for_save($type, $config);

        if (!isset($type->state)) {
            $type->state = LTI_TOOL_STATE_PENDING;
        }

        if (!isset($type->ltiversion)) {
            $type->ltiversion = LTI_VERSION_1;
        }

        if (!isset($type->timecreated)) {
            $type->timecreated = time();
        }

        if (!isset($type->createdby)) {
            $type->createdby = $USER->id;
        }

        if (!isset($type->course)) {
            $type->course = $SITE->id;
        }

        // Create a salt value to be used for signing passed data to extension services
        // The outcome service uses the service salt on the instance. This can be used
        // for communication with services not related to a specific LTI instance.
        $config->lti_servicesalt = uniqid('', true);

        $id = $DB->insert_record('lti_types', $type);

        if ($id) {
            foreach ($config as $key => $value) {
                if (!is_null($value)) {
                    if (substr($key, 0, 4) === 'lti_') {
                        $fieldname = substr($key, 4);
                    } else if (substr($key, 0, 11) !== 'ltiservice_') {
                        continue;
                    } else {
                        $fieldname = $key;
                    }

                    $record = new \StdClass();
                    $record->typeid = $id;
                    $record->name = $fieldname;
                    $record->value = $value;

                    self::add_config($record);
                }
            }
            if (isset($config->lti_coursecategories) && !empty($config->lti_coursecategories)) {
                self::type_add_categories($id, $config->lti_coursecategories);
            }
        }

        return $id;
    }

    /**
     * Add a tool configuration in the database
     *
     * @param object $config   Tool configuration
     *
     * @return int Record id number
     */
    public static function add_config($config) {
        global $DB;

        return $DB->insert_record('lti_types_config', $config);
    }

}
