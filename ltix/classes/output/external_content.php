<?php

namespace core_ltix\output;

class external_content implements \renderable {
    /** @var \stdClass The assign record.  */
    private $lti;

    public function __construct(\stdClass $course_module) {
        global $DB;
        $module_lti_map = $DB->get_record('module_lti_mapping', array('coursemoduleid' => $course_module->id), '*', IGNORE_MISSING);
        if(empty($module_lti_map)) {
            $this->lti = null;
        }
        else {
            $this->lti = $DB->get_record('lti', array('id' => $module_lti_map->ltiid), '*', MUST_EXIST);
        }
    }

    /**
     * @return $this->lti which is an lti object
     */
    public function get_lti() {
        return $this->lti;
    }

    /**
     * @return $lti_module which is a module object representing lti. It's originally module with id 15.
     */
    public function get_lti_module()
    {
        global $DB;
        $lti_module = $DB->get_record('modules', array('name' => 'lti'), '*', MUST_EXIST);
        return $lti_module;
    }

    /**
     * @return $course_module for the current lti object.
     */
    public function get_course_module() {
        global $DB;
        $lti_module = $this->get_lti_module();
        $course_module = $DB->get_record('course_modules', array('module' => $lti_module->id, 'instance' => $this->lti->id), '*', MUST_EXIST);
        return $course_module;
    }

}