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
 * Allows LTI Tool to download a file when it knows the pathnamehash.
 *
 * @package    ltixservice_assetprocessor
 * @copyright  Catalyst IT
 * @author     Dan Marsden <dan@danmarsden.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use ltixservice_assetprocessor\local\service\assetprocessor;

require('../../../config.php');

$consumerkey = \core_ltix\oauth_helper::get_oauth_key_from_headers(null, [assetprocessor::SCOPE_ASSET_READ]);
if ($consumerkey === false) {
    throw new \Exception('Missing or invalid consumer key or access token.');
}

$pathnamehash = required_param('pathnamehash', PARAM_ALPHANUM);

$fs = get_file_storage();
$file = $fs->get_file_by_hash($pathnamehash);
if (!$file) {
    send_header_404();
    die;
}

send_stored_file($file);
