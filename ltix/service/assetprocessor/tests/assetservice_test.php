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

use ltixservice_assetprocessor\local\resources\assetservice;
use ltixservice_assetprocessor\local\service\assetprocessor;
use context_system;

/**
 * Tests for Asset Processor LTI Service
 *
 * @package    ltixservice_assetprocessor
 * @category   test
 * @copyright  Catalyst IT
 * @author     Dan Marsden <dan@danmarsden.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assetservice_test extends \advanced_testcase {
    /**
     * @covers assetprocessor::get_filemetadata
     *
     * Test retrieving metadata for an existing file.
     */
    public function test_get_file_metadata() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $file = $this->create_file();

        $metadata = json_decode(assetservice::get_file_metadata($file->get_pathnamehash()));

        $this->assertEquals($metadata->sha256_checksum, $file->get_contenthash());

        $metadata = assetservice::get_file_metadata("INVALIDHASH");
        $this->assertNull($metadata);
    }

    /**
     * @covers assetprocessor::execute
     *
     * Test requesting an asset from assetservice/
     */
    public function test_execute_post() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $typeid = $this->create_type();

        $file = $this->create_file();

        $apservice = new assetprocessor();
        $assetservice = new assetservice($apservice);

        $this->set_server_for_post($file->get_pathnamehash(), $typeid);

        $response = new \core_ltix\local\ltiservice\response();
        $response->set_request_data(json_encode($file->get_pathnamehash()));
        $assetservice->execute($response);

        $responseitem = json_decode($response->get_body());

        $this->assertEquals($file->get_contenthash(), $responseitem->sha256_checksum);
    }

    /**
     * Creates a new LTI Tool Type.
     * @return int
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
        $config->ltixservice_gradesynchronization = 2;
        return \core_ltix\helper::add_type($type, $config);
    }

    /**
     * Create file for use in tests.
     *
     * @return stored_file
     */
    private function create_file() {
        $context = context_system::instance();
        // Create a file to request.
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'core',
            'filearea' => 'unittest',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'example.txt'
        ];
        $fs = get_file_storage();
        $file = $fs->create_file_from_string($filerecord, 'Dummy content ');

        return $file;
    }

    /**
     * Sets the server info and get to be configured for a POST operation,
     * including having a proper auth token attached.
     *
     * @param string $assetid - pathnamehash of file.
     * @param int $typeid
     * @param int $instanceid
     * @return array
     */
    private function set_server_for_post(string $assetid, int $typeid) {
        $_SERVER['REQUEST_METHOD'] = \core_ltix\local\ltiservice\resource_base::HTTP_POST;
        $_SERVER['PATH_INFO'] = "/api/lti/assetservice/$assetid";

        $token = \core_ltix\helper::new_access_token($typeid, ['https://purl.imsglobal.org/spec/lti-ap/scope/asset.readonly']);
        $_SERVER['HTTP_Authorization'] = 'Bearer '.$token->token;
        $_GET['type_id'] = (string)$typeid;
        return $_SERVER;
    }
}
