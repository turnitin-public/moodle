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
        $this->template = '/api/lti/activity/{activity_id}/resource/{resource_id}/reports';
        $this->variables[] = 'AssetReports.url';
        $this->formats[] = 'application/vnd.ims.lis.v2.assetreports+json';
        $this->methods[] = self::HTTP_GET;

    }

    /**
     * Execute the request for this resource.
     *
     * @param \core_ltix\local\ltiservice\response $response  Response object for this request.
     */
    public function execute($response) {
        echo "Hi";
        global $CFG, $DB;
        $params = $this->parse_template();
        $activity_id = $params['activity_id'];
        $resource_id = $params['resource_id'];


        $scope = assetprocessor::SCOPE_ASSETPROCESSOR_ASSETREPORTS;

        //This is where the asset reports logic goes.








    }
}