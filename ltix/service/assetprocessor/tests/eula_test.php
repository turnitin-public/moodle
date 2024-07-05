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

use ltixservice_assetprocessor\local\resources\eula;
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
class eula_test extends \advanced_testcase {

    /**
     * Test the post request
     */
    public function test_execute_post() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->create_type();
        $data = new \stdClass();
        $eula = new eula(new assetprocessor());
        $instanceid = 1;
        $data->instanceid = $instanceid;
        $data->userId = "59ed2101-0302-406c-b53f-9705ae1cb357";
        $data->accepted = true;
        $data->timestamp = "2022-04-16T18:54:36.736+00:00";

        $eula->instanceid = 1;
        $eula->userid = "59ed2101-0302-406c-b53f-9705ae1cb357";
        $eula->accepted = false;
        $eula->timestamp = "2022-04-16T18:54:36.736+00:00";
        $apservice = new assetprocessor();
        $apservice->set_type(\core_ltix\helper::get_type($typeid));

        $eularesource = new eula($apservice);

        $server = $this->set_server_for_post($course, $typeid, $data);

        $response = new \core_ltix\local\ltiservice\response();

        $response->set_request_data(json_encode($eula));
        $eularesource->execute($response);
        $this->assertEquals($server['REQUEST_METHOD'], 'POST');
        $this->assertEquals($eula->userid, $data->userId);
        $this->assertNotEquals($eula->accepted, $data->accepted);

    }

    /**
     * Test the delete request
     */
    public function test_execute_delete() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->create_type();
        $data = new \stdClass();
        $eula = new eula(new assetprocessor());
        $instanceid = 1;
        $data->instanceid = $instanceid;
        $data->accepted = true;

        $eula->instanceid = 1;
        $eula->accepted = false;
        $apservice = new assetprocessor();
        $apservice->set_type(\core_ltix\helper::get_type($typeid));

        $eularesource = new eula($apservice);

        $server = $this->set_server_for_delete($course, $typeid, $data);

        $response = new \core_ltix\local\ltiservice\response();

        $response->set_request_data(json_encode($eula));
        $eularesource->execute($response);
        $this->assertEquals($server['REQUEST_METHOD'], 'DELETE');
        $this->assertEquals($eula->instanceid, $data->instanceid);
        $this->assertNotEquals($eula->accepted, $data->accepted);

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
     * @param int $instanceid
     */
    private function set_server_for_post(object $course, int $typeid, object $eula) {
        $_SERVER['REQUEST_METHOD'] = \core_ltix\local\ltiservice\resource_base::HTTP_POST;
        $_SERVER['PATH_INFO'] = "/$course->id/api/lti/$eula->instanceid/eula";

        $token = \core_ltix\helper::new_access_token($typeid, ['https://purl.imsglobal.org/spec/lti/scope/eula']);
        $_SERVER['HTTP_Authorization'] = 'Bearer '.$token->token;
        $_GET['type_id'] = (string)$typeid;
        return $_SERVER;
    }

    /**
     * Sets the server info and get to be configured for a POST operation,
     * including having a proper auth token attached.
     *
     * @param object $course course where to add the lti instance.
     * @param int $typeid
     * @param int $instanceid
     */
    private function set_server_for_delete(object $course, int $typeid, object $eula) {
        $_SERVER['REQUEST_METHOD'] = \core_ltix\local\ltiservice\resource_base::HTTP_DELETE;
        $_SERVER['PATH_INFO'] = "/$course->id/api/lti/$eula->instanceid/eula";

        $token = \core_ltix\helper::new_access_token($typeid, ['https://purl.imsglobal.org/spec/lti/scope/eula']);
        $_SERVER['HTTP_Authorization'] = 'Bearer '.$token->token;
        $_GET['type_id'] = (string)$typeid;
        return $_SERVER;
    }

}