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
 * Privacy provider tests.
 *
 * @package    core_ltix
 * @copyright  2018 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_ltix\privacy;

use core_privacy\local\metadata\collection;
use core_ltix\privacy\provider;

/**
 * Privacy provider tests class.
 *
 * @package    core_ltix
 * @copyright  2018 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_test extends \core_privacy\tests\provider_testcase {

    /**
     * Test for provider::get_metadata().
     * @covers ::get_metadata
     * @return null
     */
    public function test_get_metadata() {
        $collection = new collection('core_ltix');
        $newcollection = provider::get_metadata($collection);
        $itemcollection = $newcollection->get_collection();
        $this->assertCount(2, $itemcollection);

        $ltitoolproxies = array_shift($itemcollection);
        $this->assertEquals('lti_tool_proxies', $ltitoolproxies->get_name());

        $ltitypestable = array_shift($itemcollection);
        $this->assertEquals('lti_types', $ltitypestable->get_name());

        $privacyfields = $ltitoolproxies->get_privacy_fields();
        $this->assertArrayHasKey('name', $privacyfields);
        $this->assertArrayHasKey('createdby', $privacyfields);
        $this->assertArrayHasKey('timecreated', $privacyfields);
        $this->assertArrayHasKey('timemodified', $privacyfields);
        $this->assertEquals('privacy:metadata:lti_tool_proxies', $ltitoolproxies->get_summary());

        $privacyfields = $ltitypestable->get_privacy_fields();
        $this->assertArrayHasKey('name', $privacyfields);
        $this->assertArrayHasKey('createdby', $privacyfields);
        $this->assertArrayHasKey('timecreated', $privacyfields);
        $this->assertArrayHasKey('timemodified', $privacyfields);
        $this->assertEquals('privacy:metadata:lti_types', $ltitypestable->get_summary());
    }

    /**
     * Test for provider::export_user_data().
     * @covers ::export_user_data_lti_types
     * @return null
     */
    public function test_export_for_context_tool_types() {
        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        // Create a user which will make a tool type.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create a user that will not make a tool type.
        $this->getDataGenerator()->create_user();

        $type = new \stdClass();
        $type->baseurl = 'http://moodle.org';
        $type->course = $course1->id;
        \core_ltix\helper::add_type($type, new \stdClass());

        $type = new \stdClass();
        $type->baseurl = 'http://moodle.org';
        $type->course = $course1->id;
        \core_ltix\helper::add_type($type, new \stdClass());

        $type = new \stdClass();
        $type->baseurl = 'http://moodle.org';
        $type->course = $course2->id;
        \core_ltix\helper::add_type($type, new \stdClass());

        // Export all of the data for the context.
        $coursecontext = \context_course::instance($course1->id);
        $this->export_context_data_for_user($user->id, $coursecontext, 'core_ltix');
        $writer = \core_privacy\local\request\writer::with_context($coursecontext);

        $this->assertTrue($writer->has_any_data());

        $data = $writer->get_data();
        $this->assertCount(2, $data->lti_types);

        $coursecontext = \context_course::instance($course2->id);
        $this->export_context_data_for_user($user->id, $coursecontext, 'core_ltix');
        $writer = \core_privacy\local\request\writer::with_context($coursecontext);

        $this->assertTrue($writer->has_any_data());

        $data = $writer->get_data();
        $this->assertCount(1, $data->lti_types);
    }

    /**
     * Test for provider::export_user_data().
     * @covers ::export_user_data_lti_tool_proxies
     * @return null
     */
    public function test_export_for_context_tool_proxies() {
        $this->resetAfterTest();

        // Create a user that will not make a tool proxy.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $toolproxy = new \stdClass();
        $toolproxy->createdby = $user;
        \core_ltix\helper::add_tool_proxy($toolproxy);

        // Export all of the data for the context.
        $systemcontext = \context_system::instance();
        $this->export_context_data_for_user($user->id, $systemcontext, 'core_ltix');
        $writer = \core_privacy\local\request\writer::with_context($systemcontext);

        $this->assertTrue($writer->has_any_data());

        $data = $writer->get_data();
        $this->assertCount(1, $data->lti_tool_proxies);
    }
}
