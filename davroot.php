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
 * WebDAV server
 * 
 * @package    local
 * @subpackage davroot
 * @author     Matteo Scaramuccia <moodle@matteoscaramuccia.com>
 * @copyright  Copyright (C) 2011-2012 Matteo Scaramuccia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$dirdavroot = dirname( __FILE__ );
// Load Moodle configuration file
require_once(dirname(dirname($dirdavroot)) . '/config.php');
// Load Moodle Files API libraries
require_once("$CFG->libdir/filestorage/file_exceptions.php");
require_once("$CFG->libdir/filestorage/file_storage.php");
require_once("$CFG->libdir/filestorage/zip_packer.php");
require_once("$CFG->libdir/filebrowser/file_browser.php");
// Load Moodle core libraries
require_once("$CFG->dirroot/user/lib.php");
// Load SabreDAV library
require_once("$dirdavroot/lib/sabredav/lib/Sabre/autoload.php");
// Load Plugin classes implementing the required SabreDAV interfaces
require_once("$dirdavroot/lib/BrowseNode.php");
require_once("$dirdavroot/lib/BrowseFile.php");
require_once("$dirdavroot/lib/BrowseDirectory.php");
require_once("$dirdavroot/lib/PoolNode.php");
require_once("$dirdavroot/lib/PoolFile.php");
require_once("$dirdavroot/lib/PoolDirectory.php");

// New Moodle 2.2 parameters
// 1. MDL-28701: $CFG->tempdir
$tempDir = "$CFG->dataroot/temp";
if (isset($CFG->tempdir)) {
    $tempDir = $CFG->tempdir;
}

// Load the plugin configuration settings
$cfgDAVRoot = get_config('local_davroot');
if (!$cfgDAVRoot->allowconns) {
    redirect($CFG->wwwroot);
}

// Check if IP whitelisting has been configured
if (!empty($cfgDAVRoot->allowediplist)) {
    if (!remoteip_in_list($cfgDAVRoot->allowediplist)) {
        die(get_string('ipblocked', 'admin'));
    }
}

// Set up the HTTP BASIC Authentication
$user = false;
$site = get_site();
$realm = $site->fullname;
$auth = new Sabre_HTTP_BasicAuth();
// TODO: encode the fullname, at least remove double quotes
$auth->setRealm(
    implode('', explode('"', $realm))
);
$result = $auth->getUserPass();

if (!$result) {
    // Authentication required
    $auth->requireLogin();
    die("Authentication required\n");
} else {
    $username = $result[0];
    $password = $result[1];

    // Remove the Windows Domain, if any
    $username = array_pop(explode('\\', $username));

    // Note: skip Moodle authentication if already logged in
    $alreadyLoggedIn = (isloggedin() && ($USER->username === $username));
    if (!$alreadyLoggedIn) {
        // Try to authenticate against Moodle
        if (!$user = authenticate_user_login($username, $password)) {
            // Authentication required
            $auth->requireLogin();
            die("Authentication required\n");
        }

        // Create a valid Moodle Session, $USER included
        complete_user_login($user, false);
    }
}

// Can the user connect to the WebDAV server?
if (has_capability('local/davroot:canconnect', get_context_instance(CONTEXT_SYSTEM))) {
    // Start the Virtual File System on top of the Moodle hierarchy
    $server = new Sabre_DAV_Server(
        new DAVRootBrowseDirectory()
    );

    $wwwfullpath = "$CFG->wwwroot/local/davroot/" . ($cfgDAVRoot->urlrewrite ? '' : 'davroot.php');
    $basepath = parse_url($wwwfullpath, PHP_URL_PATH);
    // Do you have exposed the local/davroot folder using a dedicated Virtual Host?
    if ($cfgDAVRoot->vhostenabled_adv && !empty($cfgDAVRoot->vhostenabled)) {
        $basepath = $cfgDAVRoot->vhostenabled;
    }
    $server->setBaseUri($basepath);

    // Support for LOCK and UNLOCK
    if ($cfgDAVRoot->lockmanager) {
        $folder = "$tempDir/davroot";
        $canAddLockMngr = true;
        if (!is_dir($folder)) {
            if (!mkdir($folder, $CFG->directorypermissions, true)) {
                $canAddLockMngr = false;
                debugging("Unable to create '$folder': Lock Manager disabled");
            }
        }
        if ($canAddLockMngr) {
            $server->addPlugin(
                new Sabre_DAV_Locks_Plugin(
                    new Sabre_DAV_Locks_Backend_File("$folder/locks.db")
                )
            );
        }
    }

    // Add the Browser Plugin
    if ($cfgDAVRoot->pluginbrowser) {
        $server->addPlugin(
            new Sabre_DAV_Browser_Plugin()
        );
    }

    // Add the DavMount Plugin
    if ($cfgDAVRoot->pluginmount) {
        $server->addPlugin(
            new Sabre_DAV_Mount_Plugin()
        );
    }

    // Temporary File Filter Plugin
    if ($cfgDAVRoot->plugintempfilefilter) {
        $folder = "$tempDir/davroot/tmp";
        $canAddTMPFilter = true;
        if (!is_dir($folder)) {
            if (!mkdir($folder, $CFG->directorypermissions, true)) {
                $canAddTMPFilter = false;
                debugging("Unable to create '$folder': Temporary File Filter Plugin disabled");
            }
        }
        if ($canAddTMPFilter) {
            $server->addPlugin(
                new Sabre_DAV_TemporaryFileFilterPlugin($folder)
            );
        }
    }

    // Finally, handle the request
    $server->exec();
} else {
    redirect($CFG->wwwroot);
}
