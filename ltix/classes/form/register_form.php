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

namespace core_ltix\form;

/**
 * This file defines the main tool registration configuration form
 *
 * @package core_ltix
 * @copyright  2014 Vital Source Technologies http://vitalsource.com
 * @author     Stephen Vickers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php');

/**
 * The core_ltix_register_types_form class.
 *
 * @package    core_ltix
 * @since      Moodle 2.8
 * @copyright  2014 Vital Source Technologies http://vitalsource.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_ltix_register_types_form extends moodleform {

    /**
     * Set up the form definition.
     */
    public function definition() {
        global $CFG;

        $mform    =& $this->_form;

<<<<<<<< HEAD:ltix/classes/form/register_form.php
        $mform->addElement('header', 'setup', get_string('registration_options', 'core_ltix'));
========
        $mform->addElement('header', 'setup', get_string('registration_options', 'ltix'));
>>>>>>>> bd7240cf59e (MDL-79116 ltix: endpoints migration):ltix/register_form.php

        // Tool Provider name.

        $strrequired = get_string('required');
<<<<<<<< HEAD:ltix/classes/form/register_form.php
        $mform->addElement('text', 'lti_registrationname', get_string('registrationname', 'core_ltix'));
        $mform->setType('lti_registrationname', PARAM_TEXT);
        $mform->addHelpButton('lti_registrationname', 'registrationname', 'core_ltix');
========
        $mform->addElement('text', 'lti_registrationname', get_string('registrationname', 'ltix'));
        $mform->setType('lti_registrationname', PARAM_TEXT);
        $mform->addHelpButton('lti_registrationname', 'registrationname', 'ltix'); //need to change reference to ltix
>>>>>>>> bd7240cf59e (MDL-79116 ltix: endpoints migration):ltix/register_form.php
        $mform->addRule('lti_registrationname', $strrequired, 'required', null, 'client');

        // Registration URL.

<<<<<<<< HEAD:ltix/classes/form/register_form.php
        $mform->addElement('text', 'lti_registrationurl', get_string('registrationurl', 'core_ltix'), array('size' => '64'));
        $mform->setType('lti_registrationurl', PARAM_URL);
        $mform->addHelpButton('lti_registrationurl', 'registrationurl', 'core_ltix');
========
        $mform->addElement('text', 'lti_registrationurl', get_string('registrationurl', 'ltix'), array('size' => '64'));
        $mform->setType('lti_registrationurl', PARAM_URL);
        $mform->addHelpButton('lti_registrationurl', 'registrationurl', 'ltix'); //need to change reference to ltix
>>>>>>>> bd7240cf59e (MDL-79116 ltix: endpoints migration):ltix/register_form.php
        $mform->addRule('lti_registrationurl', $strrequired, 'required', null, 'client');

        // LTI Capabilities.

        $options = array_keys(\core_ltix\helper::get_capabilities());
        natcasesort($options);
        $attributes = array( 'multiple' => 1, 'size' => min(count($options), 10) );
<<<<<<<< HEAD:ltix/classes/form/register_form.php
        $mform->addElement('select', 'lti_capabilities', get_string('capabilities', 'core_ltix'),
            array_combine($options, $options), $attributes);
        $mform->setType('lti_capabilities', PARAM_TEXT);
        $mform->addHelpButton('lti_capabilities', 'capabilities', 'core_ltix');
========
        $mform->addElement('select', 'lti_capabilities', get_string('capabilities', 'ltix'),
            array_combine($options, $options), $attributes);
        $mform->setType('lti_capabilities', PARAM_TEXT);
        $mform->addHelpButton('lti_capabilities', 'capabilities', 'ltix');
>>>>>>>> bd7240cf59e (MDL-79116 ltix: endpoints migration):ltix/register_form.php
        $mform->addRule('lti_capabilities', $strrequired, 'required', null, 'client');

        // LTI Services.

        $services = \core_ltix\helper::get_services();
        $options = array();
        foreach ($services as $service) {
            $options[$service->get_id()] = $service->get_name();
        }
        $attributes = array( 'multiple' => 1, 'size' => min(count($options), 10) );
<<<<<<<< HEAD:ltix/classes/form/register_form.php
        $mform->addElement('select', 'lti_services', get_string('services', 'core_ltix'), $options, $attributes);
        $mform->setType('lti_services', PARAM_TEXT);
        $mform->addHelpButton('lti_services', 'services', 'core_ltix');
========
        $mform->addElement('select', 'lti_services', get_string('services', 'ltix'), $options, $attributes);
        $mform->setType('lti_services', PARAM_TEXT);
        $mform->addHelpButton('lti_services', 'services', 'ltix');
>>>>>>>> bd7240cf59e (MDL-79116 ltix: endpoints migration):ltix/register_form.php
        $mform->addRule('lti_services', $strrequired, 'required', null, 'client');

        $mform->addElement('hidden', 'toolproxyid');
        $mform->setType('toolproxyid', PARAM_INT);

        $tab = optional_param('tab', '', PARAM_ALPHAEXT);
        $mform->addElement('hidden', 'tab', $tab);
        $mform->setType('tab', PARAM_ALPHAEXT);

        $courseid = optional_param('course', 1, PARAM_INT);
        $mform->addElement('hidden', 'course', $courseid);
        $mform->setType('course', PARAM_INT);

        // Add standard buttons, common to all modules.

        $this->add_action_buttons();
    }

    /**
     * Set up rules for disabling fields.
     */
    public function disable_fields() {

        $mform    =& $this->_form;

        $mform->disabledIf('lti_registrationurl', null);
        $mform->disabledIf('lti_capabilities', null);
        $mform->disabledIf('lti_services', null);

    }
}
