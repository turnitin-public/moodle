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
 * This file keeps track of upgrades to the lti module
 *
 * @package ltixassetprocessor
 * @author     Godson Ahamba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * This function is called when Moodle detects a plugin version change.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_ltixservice_assetprocessor_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // Loads database manager and XMLDB classes.

    if ($oldversion < 2024050311) {

        // Define table ltixservice_assetprocessor_eula to be created.
        $table = new xmldb_table('ltixservice_assetprocessor_eula');

        // Adding fields to table ltixservice_assetprocessor_eula.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_BINARY, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('accepted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timestamp', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);

        // Adding keys to table ltixservice_assetprocessor_eula.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table ltixservice_assetprocessor_eula.
        $table->add_index('instanceid', XMLDB_INDEX_NOTUNIQUE, ['instanceid']);

        // Conditionally launch create table for ltixservice_assetprocessor_eula.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Assetprocessor savepoint reached.
        upgrade_plugin_savepoint(true, 2024050311, 'ltixservice', 'assetprocessor');
    }

    if ($oldversion < 2024050310) {

        // Define table ltixservice_assetprocessor_eula_deployment to be created.
        $table = new xmldb_table('ltixservice_assetprocessor_eula_deployment');

        // Adding fields to table ltixservice_assetprocessor_eula_deployment.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('eularequired', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table ltixservice_assetprocessor_eula_deployment.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table ltixservice_assetprocessor_eula_deployment.
        $table->add_index('instanceid', XMLDB_INDEX_UNIQUE, ['instanceid']);

        // Conditionally launch create table for ltixservice_assetprocessor_eula_deployment.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Assetprocessor savepoint reached.
        upgrade_plugin_savepoint(true, 2024050310, 'ltixservice', 'assetprocessor');
    }



    // Add additional upgrade steps as needed.

    return true;
}
