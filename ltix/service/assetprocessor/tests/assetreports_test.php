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

use ltixservice_assetprocessor\local\resources\assetreports;
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
class assetreports_test extends \advanced_testcase {

    /**
     * Test the post request
     */
    public function test_execute_post() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->create_type();
        $apservice = new assetprocessor();
        $apservice->set_type(\core_ltix\helper::get_type($typeid));

        $data = new \stdClass();
        $assetreports = new assetreports($apservice);

        $resource_id = 1;
        $data->resource_id = $resource_id;
        $data->assetId = "59ed2101-0302-406c-b53f-9705ae1cb351";
        $data->timestamp = "2022-04-16T18:54:36.736+00:00";
        $data->title = "Turnitin Originality";
        $data->processingProgress = "Processed";
        $data->userId = "59ed2101-0302-406c-b53f-9705ae1cb357";

        $assetreports->resource_id = $resource_id;
        $assetreports->assetId = "59ed2101-0302-406c-b53f-9705ae1cb351";
        $assetreports->timestamp = "2022-04-16T18:54:36.736+00:00";
        $assetreports->title = "Turnitin Originality";
        $assetreports->processingProgress = "Failed";
        $assetreports->userid = "59ed2101-0302-406c-b53f-9705ae1cb357";

        $server = $this->set_server_for_post($course, $typeid, $data);

        $response = new \core_ltix\local\ltiservice\response();

        $response->set_request_data(json_encode($assetreports));
        $assetreports->execute($response);
        $this->assertEquals($server['REQUEST_METHOD'], 'POST');
        $this->assertEquals($assetreports->userid, $data->userId);
        $this->assertNotEquals($assetreports->processingProgress, $data->processingProgress);

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
     * @param int $assetreports
     */
    private function set_server_for_post(object $course, int $typeid, object $assetreports) {
        $_SERVER['REQUEST_METHOD'] = \core_ltix\local\ltiservice\resource_base::HTTP_POST;
        $_SERVER['PATH_INFO'] = "/$course->id/resource/$assetreports->resource_id/reports";

        $token = \core_ltix\helper::new_access_token($typeid, ['https://purl.imsglobal.org/spec/lti-ap/scope/report']);
        $_SERVER['HTTP_Authorization'] = 'Bearer '.$token->token;
        $_GET['type_id'] = (string)$typeid;
        return $_SERVER;
    }

}