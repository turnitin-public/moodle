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
<<<<<<<< HEAD:ltix/tests/fixtures/tool_provider.php
 * Testing fixture.
 *
 * @package   core_ltix
 * @copyright 2016 John Okely
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
========
 * This library exposes functions for LTI Dynamic Registration.
 *
 * @package    core_ltix
 * @copyright  2020 Claude Vervoort (Cengage), Carlos Costa, Adrian Hutchinson (Macgraw Hill)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
>>>>>>>> 324e99f7749 (MDL-79113 ltix: Move mod_lti oauth functions into helper class):ltix/classes/ltiopenid/registration_exception.php
 */
namespace core_ltix\ltiopenid;

<<<<<<<< HEAD:ltix/tests/fixtures/tool_provider.php
?>
<html>
  <head>
    <title>Tool provider</title>
  </head>
  <body>
    <p>This represents a tool provider</p>
  </body>
</html>
========
/**
 * Exception when transforming the registration to LTI config.
 *
 * Code is the HTTP Error code.
 */
class registration_exception extends \Exception {
}
>>>>>>>> 324e99f7749 (MDL-79113 ltix: Move mod_lti oauth functions into helper class):ltix/classes/ltiopenid/registration_exception.php
