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
 * BrowseDirectory class
 * 
 * @package    local
 * @subpackage davroot
 * @author     Matteo Scaramuccia <moodle@matteoscaramuccia.com>
 * @copyright  Copyright (C) 2011-2012 Matteo Scaramuccia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class DAVRootBrowseDirectory extends DAVRootBrowseNode implements Sabre_DAV_ICollection
{
    /**
     * Sabre_DAV_INode[]
     * 
     * @see DAVRootBrowseDirectory::getChildren()
     * @access private
     * @var array
     */
    private $children = array();

    /**
     * Creates a new file in the directory 
     *
     * @param string $name Name of the file 
     * @param resource $data Readable stream resource
     * @return void
     */
    public function createFile($name, $data = null)
    {
        // Implemented in DAVRootPoolDirectory
        if ($poolNode = $this->getDAVRootPoolObject()) {
            if ($poolNode instanceof DAVRootPoolDirectory) {
                $poolNode->createFile($name, $data);
                // Invalidate the current children list
                $this->children = null;
            } else {
                throw new Sabre_DAV_Exception_Forbidden('Invalid pool node: expected Directory');
            }
        }
    }

    /**
     * Creates a new subdirectory 
     * 
     * @param string $name 
     * @return void
     */
    public function createDirectory($name)
    {
        // Implemented in DAVRootPoolDirectory
        if ($poolNode = $this->getDAVRootPoolObject()) {
            if ($poolNode instanceof DAVRootPoolDirectory) {
                $poolNode->createDirectory($name);
            } else {
                throw new Sabre_DAV_Exception_Forbidden('Invalid pool node: expected Directory');
            }
        }
    }

    /**
     * Returns a specific child node, referenced by its name 
     * 
     * @param string $name 
     * @return Sabre_DAV_INode 
     */
    public function getChild($name)
    {
        $children = $this->getChildren();
        foreach ($children as $child) {
            if ($name === $child->getName()) {
                return $child;
            }
        }

        throw new Sabre_DAV_Exception_FileNotFound(
            "Node with name '$name' cannot be found during browsing"
        );
    }

    /**
     * Returns an array with all the child nodes 
     * 
     * @return Sabre_DAV_INode[] 
     */
    public function getChildren()
    {
        // Singleton: both getChild and getChildren are called
        //            by the Sabre_DAV_Browser_Plugin
        if (empty($this->children)) {
            $fileInfos = $this->fileInfo->get_children();
            foreach ($fileInfos as $fileInfo) {
                if ($fileInfo->is_directory()) {
                    $this->children[] = new DAVRootBrowseDirectory($fileInfo);
                } else {
                    $this->children[] = new DAVRootBrowseFile($fileInfo);
                }
            }
        }

        return $this->children;
    }

    /**
     * Checks if a child-node with the specified name exists 
     * 
     * @return bool 
     */
    public function childExists($name)
    {
        try {
            $this->getChild($name);
            return true;
        } catch (Sabre_DAV_Exception_FileNotFound $enf) {
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
}
