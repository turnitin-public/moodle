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
class euladeployment extends resource_base{

    /**
     * Class constructor.
     *
     * @param \ltixservice_assetprocessor\local\service\assetprocessor $service Service instance
     */
    public function __construct($service) {

        parent::__construct($service);
        $this->id = 'EULA DEPLOYMENT';
        $this->template = '/{deployment_id}/api/lti/eula/deployment';
        $this->variables[] = 'euladeployment.url';
        $this->formats[] = 'application/vnd.ims.lis.v2.euladeployment+json';
        $this->formats[] = 'application/json';
        $this->formats[] = 'text/plain';
        $this->methods[] = self::HTTP_PUT;

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
            if (empty($contextid) || !($container ^ ($response->get_request_method() === self::HTTP_PUT)) ||
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

            try {
                if(!$this->is_valid_request($request_data)) {
                    $json->reason = "Invalid Request Data.";
                    $json->text = "Invalid Request Data";
                    $response->set_content_type($this->formats[1]);
                    throw new \Exception("Invalid request data.", 400);
                }
                $eula_deployment = $this->get_eula_deployment($contextid);
                if(!empty($eula_deployment)) {
                    if(!$this->update_eula_deployment($eula_deployment, $request_data->eulaRequired)){
                        $json->reason = "Failed to update EULA DEPLOYMENT.";
                        $json->text = "Failed to update EULA DEPLOYMENT.";
                        $response->set_content_type($this->formats[1]);
                        throw new \Exception("Failed to update EULA DEPLOYMENT.", 400);
                    }
                    $json->reason = "EULA deployment updated.";
                    $json->text = "EULA deployment updated.";
                    $response->set_content_type($this->formats[1]);
                    $response->set_code(201);
                } else {
                    $json->reason = "EULA deployment does not exist in this context.";
                    $json->text = "EULA deployment does not exist in this context.";
                    $response->set_content_type($this->formats[1]);
                    throw new \Exception("EULA deployment does not exist in this context.", 400);
                }
            } catch (\Exception $e) {
                $response->set_code($e->getCode());
                $response->set_reason($e->getMessage());
            }

            $response->set_body(json_encode($json));
        } catch (\Exception $e) {
            $response->set_code($e->getCode());
            $response->set_reason($e->getMessage());
        }

    }

    /**
     * Check if the request is a valid put request.
     *
     * return bool
     */
    private function is_valid_request($data)
    {
        return isset($data->eulaRequired);
    }

    /**
     * Update eula deployment record.
     *
     * @param $eula_deployment
     */
    private function update_eula_deployment($eula_deployment, $eula_required) {
        global $DB;
        $eula_deployment->eularequired = $eula_required;
        return $DB->update_record('ltixservice_assetprocessor_eula_deployment', $eula_deployment);
    }

    /**
     * Get the eula deployment for contextid.
     * @param $contextid
     *
     * return object
     */
    private function get_eula_deployment($contextid){
        global $DB;
        return $DB->get_record('ltixservice_assetprocessor_eula_deployment', array('contextid' => $contextid));
    }
}