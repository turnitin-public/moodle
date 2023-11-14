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
<<<<<<<< HEAD:ltix/services.php
 * This file contains a controller for receiving LTI service requests
 *
 * @package    core_ltix
========
 * Strings for component 'ltixservice_toolproxy', language 'en'
 *
 * @package    ltixservice_toolproxy
>>>>>>>> 4d7aacb583d (MDL-79596 core_ltix: promote ltiservice toolproxy to core):ltix/service/toolproxy/lang/en/ltixservice_toolproxy.php
 * @copyright  2014 Vital Source Technologies http://vitalsource.com
 * @author     Stephen Vickers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../config.php');
\core_ltix\helper::get_services_endpoint();

