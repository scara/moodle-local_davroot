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

defined('MOODLE_INTERNAL') || die;

/**
 * Base BrowseNode class
 *
 * The node class implements the method used by
 * VirtualFile and VirtualDirectory classes 
 * 
 * @package    local
 * @subpackage davroot
 * @author     Matteo Scaramuccia <moodle@matteoscaramuccia.com>
 * @copyright  Copyright (C) 2011-2012 Matteo Scaramuccia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class DAVRootBrowseNode implements Sabre_DAV_IProperties
{
    /**
     * @var file_info
     */
    public $fileInfo = null;

    /**
     * @var file_browser
     */
    public $fileBrowser = null;

    /**
     * Sets up the node by passing $fileInfo property or passing up to 6 parameters
     */
    public function __construct()
    {
        $argv = func_get_args();
        $this->fileBrowser = get_file_browser();

        // Two constructor patterns are available
        $argn = func_num_args();
        switch($argn)
        {
            case 0:
                self::ctorGetFromBrowser(null, null, null, null, null, null);
                break;
            case 1:
                if ($argv[0] instanceof file_info) {
                    self::ctorFileInfo($argv[0]);
                } else {
                    self::ctorGetFromBrowser($argv[0], null, null, null, null, null);
                }
                break;
            case 2:
                $argv[2] = $argv[3] = $argv[4] = $argv[5] = null;
            case 3:
                $argv[3] = $argv[4] = $argv[5] = null;
            case 4:
                $argv[4] = $argv[5] = null;
            case 5:
                $argv[5] = null;
            case 6:
                self::ctorGetFromBrowser(
                    $argv[0], $argv[1], $argv[2],
                    $argv[3], $argv[4], $argv[5]
                );
                break;
            default:
                throw new Sabre_DAV_Exception_NotImplemented(
                    "Wrong constructor parameters number: $argn"
                );
        }
    }

    /**
     * Sets up the node, expects 1 parameter being potentially
     * the stored file in the Moodle Files pool
     *
     * @access private
     * @param fileInfo $fileInfo
     * @return void
     */
    private function ctorFileInfo(file_info $fileInfo)
    {
        $this->fileInfo = $fileInfo;
    }

    /**
     * Sets up the node, expects 6 parameters defining potentially
     * a stored file in the Moodle Files pool
     *
     * @access private
     * @param stdClass $context
     * @param string $component
     * @param string $filearea
     * @param int $itemid
     * @param string $filepath
     * @param string $filename
     * @return void
     */
    private function ctorGetFromBrowser(
        $context = null, $component = null, $filearea = null,
        $itemid = null, $filepath = null, $filename = null
    )
    {
        $this->fileInfo = $this->fileBrowser->get_file_info(
            $context,
            $component,
            $filearea,
            $itemid,
            $filepath,
            $filename
        );
    }

    /**
     * Deleted the current node
     *
     * @return void 
     */
    public function delete()
    {
        // Implemented in DAVRootPoolFile
        if ($poolNode = $this->getDAVRootPoolObject()) {
            $poolNode->delete();
        }
    }

    /**
     * Returns the last modification time, as a unix timestamp 
     * 
     * @return int 
     */
    public function getLastModified()
    {
        return $this->fileInfo->get_timemodified();
    }

    /**
     * Returns the name of the node 
     * 
     * @return string 
     */
    public function getName()
    {
        return str_replace('/', get_string('slashrep', 'local_davroot'), $this->fileInfo->get_visible_name());
    }

    /**
     * Renames the node
     *
     * @param string $name The new name
     * @return void
     */
    public function setName($name)
    {
        // Implemented in DAVRootPoolObject
        if ($poolNode = $this->getDAVRootPoolObject()) {
            $poolNode->setName($name);
        }
    }

    /**
     * Updates properties on this node,
     *
     * The properties array uses the propertyName in clark-notation as key,
     * and the array value for the property value. In the case a property
     * should be deleted, the property value will be null.
     *
     * This method must be atomic. If one property cannot be changed, the
     * entire operation must fail.
     *
     * If the operation was successful, true can be returned.
     * If the operation failed, false can be returned.
     *
     * Deletion of a non-existant property is always succesful.
     *
     * Lastly, it is optional to return detailed information about any
     * failures. In this case an array should be returned with the following
     * structure:
     *
     * array(
     *   403 => array(
     *      '{DAV:}displayname' => null,
     *   ),
     *   424 => array(
     *      '{DAV:}owner' => null,
     *   )
     * )
     *
     * In this example it was forbidden to update {DAV:}displayname. 
     * (403 Forbidden), which in turn also caused {DAV:}owner to fail
     * (424 Failed Dependency) because the request needs to be atomic.
     *
     * @param array $mutations 
     * @return bool|array 
     */
    public function updateProperties($mutations)
    {
        return false;
    }

    /**
     * Returns a list of properties for this nodes.
     *
     * The properties list is a list of propertynames the client requested,
     * encoded in clark-notation {xmlnamespace}tagname
     *
     * If the array is empty, it means 'all properties' were requested.
     *
     * @param array $properties 
     * @return void
     */
    public function getProperties($properties)
    {
        $myProperties = array(
        // Common properties
            '{DAV:}creationdate' => new Sabre_DAV_Property_GetLastModified(
                    $this->fileInfo->get_timecreated()
                ),
            '{DAV:}displayname' => $this->getName(),
            '{DAV:}getlastmodified' => new Sabre_DAV_Property_GetLastModified(
                    $this->fileInfo->get_timemodified()
                ),
            // Size: directories in the Moodle pool has size equal to 0
            '{DAV:}getcontentlength' => $this->fileInfo->get_filesize()
        );

        // Some properties of file_info_stored are OOP-protected...
        if ($this->fileInfo instanceof file_info_stored) {
            if ($poolNode = $this->getDAVRootPoolObject()) {
                $props = $poolNode->getProperties($properties);
                // Content Hash
                $myProperties['{DAV:}getetag'] = $props['{DAV:}getetag'];
                // Owner
                if (isset($props['{DAV:}owner'])) {
                    $myProperties['{DAV:}owner'] = $props['{DAV:}owner'];
                }
                unset($props);
                unset($poolNode);
            }
        }

        // Get all properties back, regardless what the client has requested ($properties)
        return $myProperties;
    }

    /**
     * Returns the node in the DAVRootPoolDirectory|DAVRootPoolFile object scope.
     * False if there's no related stored_file
     *
     * #access protected     
     * @return DAVRootPoolFile|DAVRootPoolDirectory|false
     */
    protected function getDAVRootPoolObject()
    {
        $params = $this->fileInfo->get_params();

        $className = 'DAVRootPool' . ($this->fileInfo->is_directory() ? 'Directory' : 'File');
        $poolNode = new $className(
            $params['contextid'],
            $params['component'],
            $params['filearea'],
            $params['itemid'],
            $params['filepath'],
            $params['filename']
        );
        unset($params);

        // Check if the node is virtual (e.g. a Category) or a file in the pool
        if (isset($poolNode->storedFile) && ($poolNode->storedFile instanceof stored_file)) {
            return $poolNode;
        }

        return false;
    }
}
