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

defined('MOODLE_INTERNAL') || die();

require_once('../../config.php');
require_once($CFG->dirroot . '/ltix/constants.php');

/**
 * Helper class specifically dealing with LTI service handling.
 *
 * @package    core_ltix
 * @author     Godson Ahamba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service_helper
{
    /**
     * Transforms a basic LTI object to an array
     *
     * @param object $ltiobject    Basic LTI object
     *
     * @return array Basic LTI configuration details
     */
    public static function lti_get_config($ltiobject) {
        $typeconfig = (array)$ltiobject;
        $additionalconfig = \core_ltix\types_helper::get_type_config($ltiobject->typeid);
        $typeconfig = array_merge($typeconfig, $additionalconfig);
        return $typeconfig;
    }

    /**
     * Build source ID
     *
     * @param int $instanceid
     * @param int $userid
     * @param string $servicesalt
     * @param null|int $typeid
     * @param null|int $launchid
     * @return stdClass
     */
    public static function lti_build_sourcedid($instanceid, $userid, $servicesalt, $typeid = null, $launchid = null) {
        $data = new \stdClass();

        $data->instanceid = $instanceid;
        $data->userid = $userid;
        $data->typeid = $typeid;
        if (!empty($launchid)) {
            $data->launchid = $launchid;
        } else {
            $data->launchid = mt_rand();
        }

        $json = json_encode($data);

        $hash = hash('sha256', $json . $servicesalt, false);

        $container = new \stdClass();
        $container->data = $data;
        $container->hash = $hash;

        return $container;
    }
}