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

namespace ltixservice_assetprocessor\local\resources;

use core_ltix\local\ltiservice\resource_base;
use ltixservice_assetprocessor\local\service\assetprocessor;

defined('MOODLE_INTERNAL') || die();

/**
 * A resource implementing Asset Reports.
 *
 * @package    ltixservice_assetprocessor
 * @since      Moodle 4.x
 * @copyright  2024 Turnitin LLC
 * @author     Godson Ahamba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class eula extends resource_base {
    /**
     * Class constructor.
     *
     * @param \ltixservice_assetprocessor\local\service\assetprocessor $service Service instance
     */
    public function __construct($service) {

        parent::__construct($service);
        $this->id = 'EULA';
        $this->template = '/{deployment_id}/api/lti/eula';
        $this->variables[] = 'eula.url';
        $this->formats[] = 'application/vnd.ims.lis.v2.eula+json';
        $this->formats[] = 'application/json';
        $this->formats[] = 'text/plain';
        $this->methods[] = self::HTTP_POST;
        $this->methods[] = self::HTTP_DELETE;

    }

    /**
     * Execute the request for this resource.
     *
     * @param \core_ltix\local\ltiservice\response $response  Response object for this request.
     */
    public function execute($response) {
        global $DB;
        $params = $this->parse_template();
        $contextid = $params['deployment_id'];

        $contenttype = $response->get_content_type();

        $container = empty($contenttype) || ($contenttype === $this->formats[0]);

        // TODO: check type_id
        $typeid = optional_param('type_id', null, PARAM_ALPHANUM);

        $scope = assetprocessor::SCOPE_ASSETPROCESSOR_EULA;

        try {
            //contextid will be modified to fit requirements subsequently
            if (!$this->check_tool($typeid, $response->get_request_data(), array($scope))) {
                throw new \Exception(null, 401);
            }
            $typeid = $this->get_service()->get_type()->id;
            if (empty($contextid) || !($container ^ ($response->get_request_method() === self::HTTP_POST || self::HTTP_DELETE)) ||
                (!empty($contenttype) && !in_array($contenttype, $this->formats))) {
                throw new \Exception('No context or unsupported content type', 400);
            }
            if (!($course = $DB->get_record('course', array('id' => $contextid), 'id', IGNORE_MISSING))) {
                throw new \Exception("Not Found: Course {$contextid} doesn't exist", 404);
            }
            if (!$this->get_service()->is_allowed_in_context($typeid, $course->id)) {
                throw new \Exception('Not allowed in context', 403);
            }
            if (!$DB->record_exists('ltixservice_assetprocessor_eula_deployment', array('contextid' => $contextid))) {
                throw new \Exception("Not Found: EULA Deployment doesn't exist", 404);
            }

            $json = new \stdClass();
            $request_data = json_decode($response->get_request_data());
            switch ($response->get_request_method()) {
                case 'POST':
                    try {
                        if(!$this->is_valid_post_request($request_data)) {
                            $json->reason = "Invalid Request Data.";
                            $json->text = "Invalid Request Data";
                            $response->set_content_type($this->formats[1]);
                            throw new \Exception("Invalid request data.", 400);
                        }
                        $eula = $this->get_eula($contextid, $request_data->userId);
                        if(!empty($eula)) {
                            if(!$this->update_eula($request_data, $eula, $contextid)){
                                $json->reason = "Failed to update EULA.";
                                $json->text = "Failed to update EULA.";
                                $response->set_content_type($this->formats[1]);
                                throw new \Exception("Failed to update EULA.", 400);
                            }
                            $json->reason = "EULA updated.";
                            $json->text = "EULA updated.";
                            $response->set_content_type($this->formats[1]);
                            $response->set_code(201);
                        } else {
                            if(!$this->insert_eula($request_data, $contextid)){
                                $json->reason = "Failed to insert EULA.";
                                $json->text = "Failed to insert EULA.";
                                $response->set_content_type($this->formats[1]);
                                throw new \Exception("Failed to insert EULA.", 400);
                            }
                            $json->reason = "EULA inserted.";
                            $json->text = "EULA inserted.";
                            $response->set_content_type($this->formats[1]);
                            $response->set_code(201);
                        }
                    } catch (\Exception $e) {
                        $response->set_code($e->getCode());
                        $response->set_reason($e->getMessage());
                    }
                    break;
                case 'DELETE':
                    try {
                        foreach($this->get_eula_for_delete_request($contextid) as $eula) {
                            $eula->accepted = false;
                            $this->delete_eula($eula);
                        }
                        $json->text = "EULA successfully deleted.";
                        $response->set_content_type($this->formats[1]);
                    } catch (\Exception $e) {
                        $response->set_code($e->getCode());
                        $response->set_reason($e->getMessage());
                    }
                    break;
                default:  // Should not be possible.
                    $response->set_code(405);
                    $response->set_reason("Invalid request method specified.");
                    return;
            }
            $response->set_body(json_encode($json));
        } catch (\Exception $e) {
            $response->set_code($e->getCode());
            $response->set_reason($e->getMessage());
        }

    }

    /**
     * Check if the request is a valid post request.
     *
     * return bool
     */
    private function is_valid_post_request($data)
    {
        return isset($data->userId) &&
            isset($data->accepted) &&
            isset($data->timestamp);
    }

    /**
     * Insert into the eula table.
     * @param $data
     * @param $contextid
     * return bool
     */
    private function insert_eula($data, $contextid)
    {
        global $DB;
        $eula = new \stdClass();
        $eula->contextid = $contextid;
        $eula->userid = $this->convert_uuid_to_binary($data->userId);
        $eula->accepted = $data->accepted;
        $eula->timestamp = $data->timestamp;
        if(empty($this->eula_deployment_exists($contextid))) {
            $eula_deployment = new \stdClass();
            $eula_deployment->contextid = $contextid;
            $eula_deployment->eularequired = false;
            $DB->insert_record('ltixservice_assetprocessor_eula_deployment', $eula_deployment);
        }
        return $DB->insert_record('ltixservice_assetprocessor_eula', $eula);
    }

    /**
     * Update the eula table.
     * @param $request_data
     * @param $record
     * @param $contextid
     * return bool
     */
    private function update_eula($request_data, $record, $contextid) {
        global $DB;

        $record->contextid = $contextid;
        $record->userid = $this->convert_uuid_to_binary($request_data->userId);
        $record->accepted = $request_data->accepted;
        $record->timestamp = $request_data->timestamp;
        return $DB->update_record('ltixservice_assetprocessor_eula', $record);
    }

    /**
     * Delete the eula acceptance record.
     * This is a soft delete that only sets the accepted field to false.
     * @param $eula
     */
    private function delete_eula($eula) {
        global $DB;
        $eula->accepted = false;
        $DB->update_record('ltixservice_assetprocessor_eula', $eula);
    }

    /**
     * Get the eula record.
     * @param $contextid
     * @param $userid
     *
     * return object
     */
    private function get_eula($contextid, $userid) {
        global $DB;
        $binary_userid = $this->convert_uuid_to_binary($userid);
        return $DB->get_record('ltixservice_assetprocessor_eula', array('contextid' => $contextid, 'userid' => $binary_userid));
    }

    /**
     * Get the eula record for delete request.
     * @param $contextid
     * @param $userid
     *
     * return object[]
     */
    private function get_eula_for_delete_request($contextid){
        global $DB;
        return $DB->get_records('ltixservice_assetprocessor_eula', array('contextid' => $contextid));
    }

    /**
     * Replace '-' and Convert UUID to BINARY for DB
     */
    private function convert_uuid_to_binary($uuid) {
        return hex2bin(str_replace('-', '', $uuid));
    }

    /**
     * Check if eula deployment exists for context.
     * @param $contextid
     *
     * return boolean
     */
    private function eula_deployment_exists($contextid){
        global $DB;
        return $DB->record_exists('ltixservice_assetprocessor_eula_deployment', array('contextid' => $contextid));
    }
}