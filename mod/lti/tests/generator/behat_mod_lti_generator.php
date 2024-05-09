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

defined('MOODLE_INTERNAL') || die();

/**
 * Define behat generator for mod_lti.
 *
 * @package    mod_lti
 * @category   test
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2020 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_lti_generator extends behat_generator_base {

    /**
     * Get list of entities for mod_lti behat tests.
     *
     * @return array[] List of entity definitions.
     */
    protected function get_creatable_entities(): array {
        return [
            'tool instances' => [
                'singular' => 'instance',
                'datagenerator' => 'instance',
                'required' => ['course', 'tool'],
                'switchids' => ['course' => 'course', 'tool' => 'typeid']
            ],
        ];
    }

    /**
     * Handles the switchid ['tool' => 'typeid'] for finding a tool by name.
     *
     * @param string $name the name of the tool.
     * @return int the id of the tool type identified by the name $name.
     */
    protected function get_tool_id(string $name): int {
        global $DB;

        if (!$id = $DB->get_field('lti_types', 'id', ['name' => $name])) {
            throw new coding_exception('The specified tool with name "' . $name . '" does not exist');
        }
        return (int) $id;
    }
}
