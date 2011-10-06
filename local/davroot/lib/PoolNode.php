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
 * Base PoolNode class
 *
 * The node class implements the method used by
 * PoolFile and PoolDirectory classes
 * 
 * @package    local
 * @subpackage davroot
 * @author     Matteo Scaramuccia <moodle@matteoscaramuccia.com>
 * @copyright  Copyright (C) 2011 Matteo Scaramuccia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class DAVRootPoolNode implements Sabre_DAV_IProperties
{
    /**
     * Moodle file record itemid. Default value: 0
     *
     * @var int
     */
    public $itemId = 0;

    /**
     * @var stored_file
     */
    public $storedFile = null;

    /**
     * @var file_storage
     */
    public $fileStorage = null;

    /**
     * Sets up the node by passing $storedFile property or passing 6 parameters
     */
    public function __construct()
    {
        $argv = func_get_args();
        $this->fileStorage = get_file_storage();

        // Only 2 constructors are available: 1 or 6 parameters
        switch(func_num_args())
        {
            case 1:
                self::ctorStoredFile($argv[0]);
                break;
            case 6:
                self::ctorGetFromStorage(
                    $argv[0], $argv[1], $argv[2],
                    $argv[3], $argv[4], $argv[5]
                );
                break;
        }
    }

    /**
     * Sets up the node, expects 1 parameter being the stored file in the Moodle Files pool 
     *
     * @access private
     * @param storedFile $storedFile
     * @return void
     */
    private function ctorStoredFile(stored_file $storedFile)
    {
        $this->storedFile = $storedFile;
    }

    /**
     * Sets up the node, expects 6 parameters defining a stored file in the Moodle Files pool 
     *
     * @access private
     * @param int $contextid
     * @param string $component
     * @param string $filearea
     * @param int $itemid
     * @param string $filepath
     * @param string $filename
     * @return void
     */
    public function ctorGetFromStorage(
        $contextid, $component, $filearea,
        $itemid, $filepath, $filename
    )
    {
        $this->storedFile = $this->fileStorage->get_file(
            $contextid,
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
        // Notes:
        // 1. stored_file::delete() always returns true
        // 2. stored_file::delete() will not delete the file from the file system
        //    if there will be more than a record with the same content hash!
        if ($this->storedFile->is_directory() && (count($this->getChildren()) > 0)) {
            // TODO: implement recursive deletion
            throw new Sabre_DAV_Exception_NotImplemented('Recursive deletion required');
        } else {
            $this->storedFile->delete();
        }
    }

    /**
     * Returns the last modification time, as a unix timestamp 
     * 
     * @return int 
     */
    public function getLastModified()
    {
        return $this->storedFile->get_timemodified();
    }

    /**
     * Returns the name of the node 
     * 
     * @return string 
     */
    public function getName()
    {
        $filename = $this->storedFile->get_filepath() . $this->storedFile->get_filename();

        if ($this->storedFile->is_directory()) {
            return basename($this->storedFile->get_filepath());
        } else {
            return basename($filename);
        }
    }

    /**
     * Renames the node
     *
     * @param string $name The new name
     * @return void
     */
    public function setName($name)
    {
        global $DB;

        $name = clean_param($name, PARAM_FILE);
        if ($name === '') {
            throw new Sabre_DAV_Exception('Invalid node name');
        }

        // File or Directory?
        $isDir = $this->storedFile->is_directory();
        if ($isDir && (count($this->getChildren()) > 0)) {
            // TODO: implement recursive renaming
            throw new Sabre_DAV_Exception_NotImplemented('Recursive renaming required');
        } else {
            // IMPORTANT! Low level functionality: it should be implemented
            //            by the Moodle Files API, e.g. stored_file::rename()
            $now = time();
            // Update the contents by creating a new record in the pool
            $updRecord = new stdClass();
            $updRecord->id           = $this->storedFile->get_id();
            $updRecord->contextid    = $this->storedFile->get_contextid();
            $updRecord->component    = $this->storedFile->get_component();
            $updRecord->filearea     = $this->storedFile->get_filearea();
            $updRecord->itemid       = $this->storedFile->get_itemid();
            if ($isDir) {
                // dirname() under Windows is not safe for Moodle Files API: '/' becomes '\' (PHP 4.3.0+)
                $filepath = $this->file_record->filepath;
                $filepath = trim($filepath, '/');
                $dirs = explode('/', $filepath);
                array_pop($dirs);
                $filepath = implode('/', $dirs);
                $updRecord->filepath = ($filepath === '') ? "/$name/" : "/$filepath/$name/";
                $updRecord->filename = '.';
            } else {
                $updRecord->filepath = $this->storedFile->get_filepath();
                $updRecord->filename = $name;
            }
            $updRecord->timecreated  = $this->storedFile->get_timecreated();
            $updRecord->timemodified = $now;
            $updRecord->mimetype     = null;
            if (!$isDir) {
                $updRecord->mimetype = mimeinfo('type', $updRecord->filename);
            }
            $updRecord->userid       = $this->storedFile->get_userid();
            $updRecord->source       = $this->storedFile->get_source();
            $updRecord->author       = $this->storedFile->get_author();
            $updRecord->license      = $this->storedFile->get_license();
            $updRecord->sortorder    = $this->storedFile->get_sortorder();
            // Evaluate the new pathname hash
            $updRecord->pathnamehash = $this->fileStorage->get_pathname_hash(
                $updRecord->contextid,
                $updRecord->component,
                $updRecord->filearea,
                $updRecord->itemid,
                $updRecord->filepath,
                $updRecord->filename
            );

            $transaction = $DB->start_delegated_transaction();
            try {
                $DB->update_record('files', $updRecord);
            } catch (Exception $e) {
                $transaction->rollback(
                    new Sabre_DAV_Exception('PoolNode::setName() - ' . $e->getMessage())
                );
            }
            $transaction->allow_commit();
            $this->storedFile = $this->fileStorage->get_file_instance($updRecord);
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
                    $this->storedFile->get_timecreated()
                ),
            '{DAV:}displayname' => $this->getName(),
            '{DAV:}getetag' => $this->storedFile->get_contenthash(),
            '{DAV:}getlastmodified' => new Sabre_DAV_Property_GetLastModified(
                    $this->storedFile->get_timemodified()
                )
        );
        // Size: directories in Moodle pool usually have size equal to 0
        $myProperties['{DAV:}getcontentlength'] = $this->storedFile->get_filesize();
        // Owner
        try {
            if ($userid = $this->storedFile->get_userid()) {
                if ($user = user_get_users_by_id(array($userid))) {
                    $user = $user[$userid];
                    $myProperties['{DAV:}owner'] = "$user->lastname, $user->firstname (id: $user->id)";
                }
            }
        } catch (Exception $e) {
            // Do nothing
        }

        // Get all properties back, regardless what the client has requested ($properties)
        return $myProperties;
    }
}
