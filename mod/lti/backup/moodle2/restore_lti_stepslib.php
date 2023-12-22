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
//
// This file is part of BasicLTI4Moodle
//
// BasicLTI4Moodle is an IMS BasicLTI (Basic Learning Tools for Interoperability)
// consumer for Moodle 1.9 and Moodle 2.0. BasicLTI is a IMS Standard that allows web
// based learning tools to be easily integrated in LMS as native ones. The IMS BasicLTI
// specification is part of the IMS standard Common Cartridge 1.1 Sakai and other main LMS
// are already supporting or going to support BasicLTI. This project Implements the consumer
// for Moodle. Moodle is a Free Open source Learning Management System by Martin Dougiamas.
// BasicLTI4Moodle is a project iniciated and leaded by Ludo(Marc Alier) and Jordi Piguillem
// at the GESSI research group at UPC.
// SimpleLTI consumer for Moodle is an implementation of the early specification of LTI
// by Charles Severance (Dr Chuck) htp://dr-chuck.com , developed by Jordi Piguillem in a
// Google Summer of Code 2008 project co-mentored by Charles Severance and Marc Alier.
//
// BasicLTI4Moodle is copyright 2009 by Marc Alier Forment, Jordi Piguillem and Nikolas Galanis
// of the Universitat Politecnica de Catalunya http://www.upc.edu
// Contact info: Marc Alier Forment granludo @ gmail.com or marc.alier @ upc.edu.

/**
 * This file contains all the restore steps that will be used
 * by the restore_lti_activity_task
 *
 * @package mod_lti
 * @copyright  2009 Marc Alier, Jordi Piguillem, Nikolas Galanis
 *  marc.alier@upc.edu
 * @copyright  2009 Universitat Politecnica de Catalunya http://www.upc.edu
 * @author     Marc Alier
 * @author     Jordi Piguillem
 * @author     Nikolas Galanis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Structure step to restore one lti activity
 */
class restore_lti_activity_structure_step extends restore_activity_structure_step {

    /** @var bool */
    protected $newltitype = false;

    protected function define_structure() {

        $paths = array();
        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        $lti = new restore_path_element('lti', '/activity/lti');
        $paths[] = $lti;
        $paths[] = new restore_path_element('ltitype', '/activity/lti/ltitype');
        $paths[] = new restore_path_element('ltitypesconfig', '/activity/lti/ltitype/ltitypesconfigs/ltitypesconfig');
        $paths[] = new restore_path_element('ltitypesconfigencrypted',
            '/activity/lti/ltitype/ltitypesconfigs/ltitypesconfigencrypted');
        $paths[] = new restore_path_element('ltitoolproxy', '/activity/lti/ltitype/ltitoolproxy');
        $paths[] = new restore_path_element('ltitoolsetting', '/activity/lti/ltitype/ltitoolproxy/ltitoolsettings/ltitoolsetting');

        if ($userinfo) {
            $submission = new restore_path_element('ltisubmission', '/activity/lti/ltisubmissions/ltisubmission');
            $paths[] = $submission;
        }

        $paths[] = new restore_path_element('lticoursevisible', '/activity/lti/lticoursevisible');

        // Add support for subplugin structures.
        $this->add_subplugin_structure('ltisource', $lti);
        $this->add_subplugin_structure('ltiservice', $lti);

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_lti($data) {
        $newitemid = (new restore_lti_structure_step('lti', 'lti'))->process_lti($data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    protected function process_ltitypesconfig($data) {
        (new restore_lti_structure_step('lti', 'lti'))->process_ltitypesconfig($data);
    }

    protected function process_ltitypesconfigencrypted($data) {
        (new restore_lti_structure_step('lti', 'lti'))->process_ltitypesconfigencrypted($data);
    }

    protected function process_ltitoolproxy($data) {
        (new restore_lti_structure_step('lti', 'lti'))->process_ltitoolproxy($data);
    }

    protected function after_execute() {
        // Add lti related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_lti', 'intro', null);
    }
}
