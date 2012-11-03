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
 * Local library.
 *
 * @package    local
 * @subpackage davroot
 * @author     Matteo Scaramuccia <moodle@matteoscaramuccia.com>
 * @copyright  Copyright (C) 2012 Matteo Scaramuccia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Check if the Moodle instance code is affected by MDL-35990, Moodle 2.3+.
 * More details in the linked tracker issue.
 *
 * @link http://tracker.moodle.org/browse/MDL-35990
 * @return bool
 */
function has_mdl35990()
{
    global $CFG;

    // Only Moodle 2.3 and above: http://docs.moodle.org/dev/Releases#Moodle_2.3
    if ($CFG->version < 2012062500) {
        return false;
    }

    $found = false;
    // Load the first 2KB of the Repository Library code
    $repolib_file_contents = file_get_contents(
        "$CFG->dirroot/repository/lib.php",
        false, null, -1, 2048
    );
    // Find the line, not commented out
    if ($repolib_file_contents) {
        $found = (false !== strpos(
            $repolib_file_contents,
            "require_once(dirname(dirname(__FILE__)) . '/config.php');"
        )) && (false === strpos(
            $repolib_file_contents,
            // Comment out, useful for debugging purposes
            "//require_once(dirname(dirname(__FILE__)) . '/config.php');"
        ));
    }

    return $found;
}