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
class assetreports extends resource_base
{
    /**
     * Class constructor.
     *
     * @param \ltixservice_assetprocessor\local\service\assetprocessor $service Service instance
     */
    public function __construct($service) {

        parent::__construct($service);
        $this->id = 'AssetProcessor';
        $this->template = '/{context_id}/resource/{resource_id}/reports';
        $this->variables[] = 'AssetReports.url';
        $this->formats[] = 'application/vnd.ims.lis.v2.assetreports+json';
        $this->formats[] = 'application/json';
        $this->formats[] = 'text/plain';
        $this->methods[] = self::HTTP_POST;

    }

    /**
     * Execute the request for this resource.
     *
     * @param \core_ltix\local\ltiservice\response $response  Response object for this request.
     */
    public function execute($response) {
        global $DB;
        $params = $this->parse_template();
        $contextid = $params['context_id'];
        $resourceid = $params['resource_id'];

        $contenttype = $response->get_content_type();

        $container = empty($contenttype) || ($contenttype === $this->formats[0]);

        // TODO: check type_id
        $typeid = optional_param('type_id', null, PARAM_ALPHANUM);

        $scope = assetprocessor::SCOPE_ASSETPROCESSOR_ASSETREPORT;

        try {
            /*if (!$this->check_tool($typeid, $response->get_request_data(), array($scope))) {
                throw new \Exception(null, 401);
            }
            $typeid = $this->get_service()->get_type()->id;
            if (empty($contextid) || !($container ^ ($response->get_request_method() === self::HTTP_POST)) ||
                (!empty($contenttype) && !in_array($contenttype, $this->formats))) {
                throw new \Exception('No context or unsupported content type', 400);
            }
            if (!($course = $DB->get_record('course', array('id' => $contextid), 'id', IGNORE_MISSING))) {
                throw new \Exception("Not Found: Course {$contextid} doesn't exist", 404);
            }
            if (!$this->get_service()->is_allowed_in_context($typeid, $course->id)) {
                throw new \Exception('Not allowed in context', 403);
            }
            if (!$DB->record_exists('lti', array('id' => $resourceid))) {
                throw new \Exception("Not Found: LTI Resource {$resourceid} doesn't exist", 404);
            }*/

            // Create a result object to store the text to be returned by response body
            $result = new \stdClass();
            try {
                $request_data = json_decode($response->get_request_data());
                if($this->params_exist($request_data)) {
                    $record = $this->record_exists($resourceid, $request_data->type, $request_data->userId, $request_data->assetId);
                    if(!empty($record)) {
                        // Asset already exists for the type, then override the existing record if scores are valid
                        if($this->is_valid_score_values($request_data)){
                            $this->update_asset_report($request_data, $record, $resourceid);
                            $response->set_code(201);
                            $result->text = "Successfully Updated Asset";
                        } else {
                            $response->set_code(400);
                            $response->set_reason("Invalid Request Data. Check the score values");
                            $result->text = "Invalid Request Data";
                        }

                    } else {
                        if($this->is_single_asset($request_data)) {
                            if($this->is_valid_score_values($request_data)){
                                $this->add_asset_report($request_data, $resourceid);
                                $response->set_code(201);
                                $result->text = "Successfully Added Single Asset";
                            } else {
                                $response->set_code(400);
                                $response->set_reason("Invalid Request Data. Check the score values");
                                $result->text = "Invalid Request Data";
                            }
                        } else {
                            if($this->is_failed_status($request_data)) {
                                $this->add_asset_report($request_data, $resourceid);
                                $response->set_code(201);
                                $result->text = "Added Asset with Failed Status";
                            } else {
                                $response->set_code(400);
                                $response->set_reason("Invalid Request Data");
                                $result->text = "Invalid Request Data";
                            }
                        }
                    }

                    $response->set_content_type($this->formats[1]);
                } else {
                    throw new \Exception('Invalid request data', 400);
                }

            } catch (\Exception $e) {
                $response->set_code($e->getCode());
                $response->set_reason($e->getMessage());
            }
            $response->set_body(json_encode($result));
        } catch (\Exception $e) {
            $response->set_code($e->getCode());
            $response->set_reason($e->getMessage());
        }


    }

    /**
     * Check if the required parameters exist
     * @param $decoded_data
     * @return bool
     */
    private function params_exist($decoded_data) {
        return isset($decoded_data->assetId) &&
            isset($decoded_data->type) &&
            isset($decoded_data->timestamp) &&
            isset($decoded_data->title) &&
            isset($decoded_data->processingProgress) &&
            isset($decoded_data->userId);
    }

    /**
     * Check if the request data is a single asset
     * @param $decoded_data
     * @return bool
     */
    private function is_single_asset($decoded_data) {
        return isset($decoded_data->scoreGiven) &&
            isset($decoded_data->scoreMaximum) &&
            isset($decoded_data->indicationColor) &&
            isset($decoded_data->indicationAlt) &&
            isset($decoded_data->priority);
    }

    /**
     * Check if the request data is a single asset
     * @param $decoded_data
     * @return bool
     */
    private function is_failed_status($decoded_data) {
        return isset($decoded_data->comment);
    }

    /**
     * Check if type already exists for user and asset
     */
    private function record_exists($resourceid, $type, $userid, $assetid) {
        global $DB;
        $binary_user_id = $this->convert_uuid_to_binary($userid);
        $binary_asset_id = $this->convert_uuid_to_binary($assetid);
        return $DB->get_record('ltixservice_assetprocessor_assetreport', array('resource_id' => $resourceid, 'type' => $type, 'user_id' => $binary_user_id, 'asset_id' => $binary_asset_id));
        return false;
    }

    /**
     * Check if scores are valid
     */
    private function is_valid_score_values($request_data) {
        if($request_data->scoreGiven >= 0) {
            if(is_null($request_data->scoreMaximum) || $request_data->scoreMaximum < 0) {
                return false;
            }
        }
        if($request_data->scoreGiven < 0) {
            return false;
        }
        return true;
    }

    /**
     * Add asset report
     */
    private function add_asset_report($request_data, $resourceid) {
        global $DB;
        $record = new \stdClass();
        $record->resource_id = $resourceid;
        $record->type = $request_data->type;
        $record->user_id = $this->convert_uuid_to_binary($request_data->userId);
        $record->asset_id = $this->convert_uuid_to_binary($request_data->assetId);
        $record->timestamp = $request_data->timestamp;
        $record->title = $request_data->title;
        $record->processing_progress = $request_data->processingProgress;
        $record->score_given = $request_data->scoreGiven;
        $record->score_maximum = $request_data->scoreMaximum;
        $record->indication_color = $request_data->indicationColor;
        $record->indication_alt = $request_data->indicationAlt;
        $record->priority = $request_data->priority;
        $record->comment = $request_data->comment;
        $DB->insert_record('ltixservice_assetprocessor_assetreport', $record);
        return true;
    }

    /**
     * Update asset report
     */
    private function update_asset_report($request_data, $record, $resourceid) {
        global $DB;
        $record->resource_id = intval($resourceid);
        $record->type = $request_data->type;
        $record->user_id = $this->convert_uuid_to_binary($request_data->userId);
        $record->asset_id = $this->convert_uuid_to_binary($request_data->assetId);
        $record->timestamp = $request_data->timestamp;
        $record->title = $request_data->title;
        $record->processing_progress = $request_data->processingProgress;
        $record->score_given = $request_data->scoreGiven;
        $record->score_maximum = $request_data->scoreMaximum;
        $record->indication_color = $request_data->indicationColor;
        $record->indication_alt = $request_data->indicationAlt;
        $record->priority = $request_data->priority;
        $record->comment = $request_data->comment;
        $DB->update_record('ltixservice_assetprocessor_assetreport', $record);
        return true;
    }

    /**
     * Replace '-' and Convert UUID to BINARY for DB
     */
    private function convert_uuid_to_binary($uuid) {
        return hex2bin(str_replace('-', '', $uuid));
    }
}