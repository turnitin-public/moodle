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
use context_system;

/**
 * Tests for Asset Processor LTI Service
 *
 * @package    ltixservice_assetprocessor
 * @category   test
 * @copyright  2024 2024 Dan Marsden
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

        $metadata = json_decode(assetservice::get_file_metadata($file->get_pathnamehash()));

        $this->assertEquals($metadata->sha256_checksum, $file->get_contenthash());

        $metadata = assetservice::get_file_metadata("INVALIDHASH");
        $this->assertNull($metadata);
    }
}



