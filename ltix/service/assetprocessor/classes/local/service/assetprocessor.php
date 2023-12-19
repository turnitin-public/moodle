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
        }

        return $this->resources;

    }
}
