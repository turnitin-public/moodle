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

/**
 * This file contains a class definition for the Asset Processor service
 *
 * @package    ltixservice_assetprocessor
 * @copyright  2023 Turnitin https://www.turnitin.com/
 * @author     Ismael Texidor-Rodriguez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace ltixservice_assetprocessor\local\service;

defined('MOODLE_INTERNAL') || die();

/**
 * A service implementing Asset Processor.
 *
 * @package    ltixservice_assetprocessor
 * @since      Moodle 4.5
 * @copyright  2023 Turnitin https://www.turnitin.com/
 * @author     Ismael Texidor-Rodriguez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assetprocessor extends \core_ltix\local\ltiservice\service_base {

    /** Scope for access to Score service */
    const SCOPE_ASSETPROCESSOR_ASSETREPORTS = 'https://purl.imsglobal.org/spec/lti-ap/scope/report';
    /**
     * Class constructor.
     */
    public function __construct() {

        parent::__construct();
        $this->id = 'assetprocessor';
        $this->name = 'Asset Processor';

    }

    /**
     * Get the resources for this service.
     *
     * @return array
     */
    public function get_resources() {

        if (empty($this->resources)) {
            $this->resources = array();
            $this->resources = new \ltixservice_assetprocessor\local\resources\assetreports($this);
        }

        return $this->resources;

    }

    /**
     * Get the scope(s) permitted for the tool relevant to this service.
     *
     * @return array
     */
    public function get_permitted_scopes() {

        $scopes = array();
        $scopes[] = self::SCOPE_ASSETPROCESSOR_ASSETREPORTS;
        return $scopes;

    }

    /**
     * Get the scope(s) permitted for the tool relevant to this service.
     *
     * @return array
     */
    public function get_scopes() {
        return [self::SCOPE_ASSETPROCESSOR_ASSETREPORTS];
    }

    /**
     * Return an array of key/claim mapping allowing LTI 1.1 custom parameters
     * to be transformed to LTI 1.3 claims.
     *
     * @return array Key/value pairs of params to claim mapping.
     */
    public function get_jwt_claim_mappings(): array {
        return [
            
        ];
    }
}
