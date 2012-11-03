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
 * Add page to admin menu.
 *
 * @package    local
 * @subpackage davroot
 * @author     Matteo Scaramuccia <moodle@matteoscaramuccia.com>
 * @copyright  Copyright (C) 2011-2012 Matteo Scaramuccia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->dirroot/local/davroot/locallib.php");

if ($hassiteconfig) { // needs this condition or there is error on login page

    // DAVRoot plugin settings
    $settings = new admin_settingpage(
        'local_davroot',
        get_string('pluginname', 'local_davroot')
    );
    $settings->add(
        new admin_setting_heading(
            'descr',
            get_string('pluginnamedescr', 'local_davroot'),
            ''
        )
    );

    // 1. Allow connections
    $settings->add(
        new admin_setting_configcheckbox('local_davroot/allowconns',
            get_string('allowconns', 'local_davroot'),
            get_string('allowconnsdescr', 'local_davroot'),
            0
        )
    );
    // 2. Allowed IPs
    $settings->add(
        new admin_setting_configiplist(
            'local_davroot/allowediplist',
            get_string('allowediplist', 'admin'),
            get_string('ipblockersyntax', 'admin'),
            ''
        )
    );
    // 3. Enable URL rewrite
    $wwwpath = "$CFG->wwwroot/local/davroot/";
    $settings->add(
        new admin_setting_configcheckbox('local_davroot/urlrewrite',
            get_string('urlrewrite', 'local_davroot'),
            get_string('urlrewritedescr', 'local_davroot', array('wwwpath' => $wwwpath)),
            0
        )
    );
    // 4. Enable Virtual Host mapping (required for Windows Explorer)
    if (has_mdl35990()) {
        // Warn for MDL-35990, otherwise browsing the virtual file system will fire HTTP 500 errors
        $settings->add(
            new admin_setting_heading(
                'mdl35990',
                get_string('warnmdl35990', 'local_davroot'),
                get_string('warnmdl35990descr', 'local_davroot', array('filepath' => "$CFG->dirroot/repository/lib.php"))
            )
        );
    }
    $settings->add(
        new admin_setting_configtext_with_advanced('local_davroot/vhostenabled',
            get_string('vhostenabled', 'local_davroot'),
            get_string('vhostenableddescr', 'local_davroot', array('dirpath' => "$CFG->dirroot/local/davroot/")),
            array('value' => '/', 'adv' => false),
            PARAM_PATH
        )
    );
    // 5. Enable Lock Manager
    $settings->add(
        new admin_setting_configcheckbox('local_davroot/lockmanager',
            get_string('lockmanager', 'local_davroot'),
            get_string(
                'lockmanagerdescr',
                'local_davroot',
                array('lockmngrfolder' => "$CFG->dataroot/temp/davroot")
            ),
            0
        )
    );
    // 6. Enable Browser Plugin
    $settings->add(
        new admin_setting_configcheckbox('local_davroot/pluginbrowser',
            get_string('pluginbrowser', 'local_davroot'),
            get_string('pluginbrowserdescr', 'local_davroot'),
            1
       )
    );
    // 7. Enable Mount Plugin
    $settings->add(
        new admin_setting_configcheckbox('local_davroot/pluginmount',
            get_string('pluginmount', 'local_davroot'),
            get_string('pluginmountdescr', 'local_davroot'),
            1
        )
    );
    // 8. Enable Temporary Filter Plugin
    $settings->add(
        new admin_setting_configcheckbox('local_davroot/plugintempfilefilter',
            get_string('plugintempfilefilter', 'local_davroot'),
            get_string(
                'plugintempfilefilterdescr',
                'local_davroot',
                array('tmpfilefilterfolder' => "$CFG->dataroot/temp/davroot/tmp")
            ),
            0
        )
    );
    // 9. Allow write methods
    $settings->add(
        new admin_setting_configcheckbox('local_davroot/readonly',
            get_string('readonly', 'local_davroot'),
            get_string(
                'readonlydescr',
                'local_davroot'
            ),
            1
        )
    );

    // Finally, add these DAVRoot Plugin settings
    $ADMIN->add('localplugins', $settings);
}
