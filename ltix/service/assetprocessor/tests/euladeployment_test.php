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

namespace ltixservice_assetprocessor;

use ltixservice_assetprocessor\local\resources\euladeployment;
use ltixservice_assetprocessor\local\service\assetprocessor;

/**
 * Unit tests for ltix assetprocessor.
 *
 * @package    ltixservice_assetprocessor
 * @category   test
 * @copyright  2024 Godson Ahamba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_ltix\service\assetprocessor\local\assetprocessor
 */

class euladeployment_test extends \advanced_testcase {

    /**
     * Test the post request
     */
    public function test_execute_put() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->create_type();
        $apservice = new assetprocessor();
        $apservice->set_type(\core_ltix\helper::get_type($typeid));
        $data = new \stdClass();
        $euladeployment = new euladeployment($apservice);
        $instanceid = 1;
        $data->instanceid = $instanceid;
        $data->eularequired = true;

        $euladeployment->instanceid = 1;
        $euladeployment->eularequired = false;


        $server = $this->set_server_for_put($course, $typeid, $data);

        $response = new \core_ltix\local\ltiservice\response();

        $response->set_request_data(json_encode($euladeployment));
        $euladeployment->execute($response);
        $this->assertEquals($server['REQUEST_METHOD'], 'PUT');
        $this->assertNotEquals($euladeployment->eularequired, $data->eularequired);

    }

    /**
     * Creates a new LTI Tool Type.
     */
    private function create_type() {
        global $CFG;
        require_once($CFG->dirroot . '/ltix/constants.php');
        $type = new \stdClass();
        $type->state = LTI_TOOL_STATE_CONFIGURED;
        $type->name = "Test tool";
        $type->description = "Example description";
        $type->clientid = "Test client ID";
        $type->baseurl = $this->getExternalTestFileUrl('/test.html');

        $config = new \stdClass();
        $config->ltixservice_assetprocessor = 2;
        return \core_ltix\helper::add_type($type, $config);
    }

    /**
     * Sets the server info and get to be configured for a POST operation,
     * including having a proper auth token attached.
     *
     * @param object $course course where to add the lti instance.
     * @param int $typeid
     * @param object $euladeployment
     */
    private function set_server_for_put(object $course, int $typeid, object $euladeployment) {
        $_SERVER['REQUEST_METHOD'] = \core_ltix\local\ltiservice\resource_base::HTTP_PUT;
        $_SERVER['PATH_INFO'] = "/$course->id/api/lti/$euladeployment->instanceid/eula/deployment";

        $token = \core_ltix\helper::new_access_token($typeid, ['https://purl.imsglobal.org/spec/lti/scope/eula']);
        $_SERVER['HTTP_Authorization'] = 'Bearer '.$token->token;
        $_GET['type_id'] = (string)$typeid;
        return $_SERVER;
    }
}