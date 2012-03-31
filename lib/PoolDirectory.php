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
 * PoolDirectory class
 * 
 * @package    local
 * @subpackage davroot
 * @author     Matteo Scaramuccia <moodle@matteoscaramuccia.com>
 * @copyright  Copyright (C) 2011-2012 Matteo Scaramuccia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class DAVRootPoolDirectory extends DAVRootPoolNode implements Sabre_DAV_ICollection
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
        global $DB, $USER;

        // Load the configuration settings
        $cfgDAVRoot = get_config('local_davroot');
        if ($cfgDAVRoot->readonly) {
            throw new Sabre_DAV_Exception_Forbidden('Read-only access configured');
        }

        if ($type = get_resource_type($data)) {
            try {
                $contents = '';
                while (!feof($data)) {
                    $contents .= fread($data, 8192);
                }

                $newFileRecord = new stdClass();
                $newFileRecord->contextid = $this->storedFile->get_contextid();
                $newFileRecord->component = $this->storedFile->get_component();
                $newFileRecord->filearea  = $this->storedFile->get_filearea();
                // Get the item id from the parent folder
                $newFileRecord->itemid    = $this->storedFile->get_itemid();
                $newFileRecord->filepath  = $this->storedFile->get_filepath();
                $newFileRecord->filename  = $name;
                // Does the File already exist?
                if (!$this->fileStorage->file_exists(
                    $newFileRecord->contextid,
                    $newFileRecord->component,
                    $newFileRecord->filearea,
                    $newFileRecord->itemid,
                    $newFileRecord->filepath,
                    $newFileRecord->filename)
                ) {
                    $newFileRecord->userid = $USER->id;
                    // source, author, license inherited from Directory? No, I suppose
                    $transaction = $DB->start_delegated_transaction();
                    try {
                        $this->fileStorage->create_file_from_string(
                            $newFileRecord,
                            $contents
                        );
                    } catch (Exception $e) {
                        $transaction->rollback(
                            new Sabre_DAV_Exception('PoolDirectory:createFile() - ' . $e->getMessage())
                        );
                    }
                    $transaction->allow_commit();
                    // Invalidate the current children list
                    $this->children = null;
                } else { // File already exists
                    throw new Sabre_DAV_Exception(
                        "Filename '$newFileRecord->filepath/$newFileRecord->filename' already exists"
                    );
                }
            } catch (Exception $e) {
                throw new Sabre_DAV_Exception($e->getMessage());
            }
        } else {
            throw new Sabre_DAV_Exception_InvalidResourceType(
                'File data is not a readable file stream resource'
            );
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
        global $DB, $USER;

        // Load the configuration settings
        $cfgDAVRoot = get_config('local_davroot');
        if ($cfgDAVRoot->readonly) {
            throw new Sabre_DAV_Exception_Forbidden('Read-only access configured');
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            $this->fileStorage->create_directory(
                $this->storedFile->get_contextid(),
                $this->storedFile->get_component(),
                $this->storedFile->get_filearea(),
                // Get the item id from the parent folder
                $this->storedFile->get_itemid(),
                $this->storedFile->get_filepath() . "/$name/",
                $USER->id
            );
            // Invalidate the current children list
            $this->children = null;
        } catch (Exception $e) {
            $transaction->rollback(
                new Sabre_DAV_Exception('PoolDirectory:createDirectory() - '. $e->getMessage())
            );
        }
        $transaction->allow_commit();
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
            "Node with name '$name' cannot be found in the pool"
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
            $storedFiles = $this->fileStorage->get_directory_files(
                $this->storedFile->get_contextid(),
                $this->storedFile->get_component(),
                $this->storedFile->get_filearea(),
                // Get the item id from the parent folder
                $this->storedFile->get_itemid(),
                $this->storedFile->get_filepath(),
                false,                         // recursive
                true,                         // includedirs
                'filepath ASC, filename ASC' // sort
            );

            foreach ($storedFiles as $storedFile) {
                if ($storedFile->is_directory()) {
                    $this->children[][] = new DAVRootPoolDirectory($storedFile);
                } else {
                    $this->children[][] = new DAVRootPoolFile($storedFile);
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
