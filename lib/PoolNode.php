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
 * @copyright  Copyright (C) 2011-2012 Matteo Scaramuccia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class DAVRootPoolNode implements Sabre_DAV_IProperties
{
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
        $children = array();

        // Prevent to delete a virtual root file
        if ($this->storedFile->is_directory() && ($this->storedFile->get_filepath() === '/')) {
            throw new Sabre_DAV_Exception_Forbidden("You can't delete a virtual root file");
        }

        // Load the configuration settings
        $cfgDAVRoot = get_config('local_davroot');
        if ($cfgDAVRoot->readonly) {
            throw new Sabre_DAV_Exception_Forbidden('Read-only access configured');
        }

        // Notes:
        // 1. stored_file::delete() always returns true
        // 2. stored_file::delete() will not delete the file from the file system
        //    if there will be more than a record with the same content hash!
        // 3. {module}_get_file_areas probably misses to inform about peculiar permissions:
        //    everything is delegated in pluginfile.php through {module}_pluginfile. Maybe
        //    Moodle Files API should be improved to decouple permissions on areas from
        //    {module}_pluginfile

        // TODO Check DAV specs to inspect any Client responsibility
        // in collecting every Resource under Collections e.g. using Depth Header
        // with value "infinity" in DELETE method on a Collection
        // If every Client will use it, we could remove the foreach below
        // since the Client is charged of the traversal and cleanup.
        // But... for pool consistency reasons, it is better to safely assure
        // a correct recursive deletion by ourselves w/o relying on the Client
        // See also: Sabre_DAV_Server::getHTTPDepth()

        // Get recursive children. Optimized deletion: the reason for DAVRootPoolNode::delete()
        // instead of specialized DAVRootPoolDirectory::delete() and DAVRootPoolFile::delete()
        if ($this->storedFile->is_directory()) {
            $children = $this->fileStorage->get_directory_files(
                $this->storedFile->get_contextid(),
                $this->storedFile->get_component(),
                $this->storedFile->get_filearea(),
                $this->storedFile->get_itemid(),
                $this->storedFile->get_filepath(),
                true,                          // recursive
                true,                         // includedirs
                'filepath ASC, filename ASC' // sort
            );
        }

        // Perform the deletion of each child item in the pool
        foreach ($children as $child) {
            $child->delete();
        }

        // Delete the node in the pool
        $this->storedFile->delete();
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
     * Renames an item in the Moodle files pool
     *
     * IMPORTANT! Low level functionality: it should be implemented
     * by the Moodle Files API, e.g. stored_file::rename()
     *
     * @throws file_exception
     * @param stored_file $file_stored The stored_file instance to be renamed
     * @param $new_name The new name
     * @return stored_file The updated stored_file instance
     */
    protected function stored_file_rename(stored_file $file_stored, $new_name)
    {
        global $DB;

        $new_name = clean_param($new_name, PARAM_FILE);
        if ($new_name === '') {
            throw new file_exception('storedfileproblem', 'Invalid node name');
        }

        // Update the contents by creating a new record in the pool
        $updrecord = new stdClass();
        $updrecord->id           = $file_stored->get_id();
        $updrecord->contextid    = $file_stored->get_contextid();
        $updrecord->component    = $file_stored->get_component();
        $updrecord->filearea     = $file_stored->get_filearea();
        $updrecord->itemid       = $file_stored->get_itemid();
        if ($file_stored->is_directory()) {
            // dirname() under Windows is not safe for Moodle Files API: '/' becomes '\' (PHP 4.3.0+)
            $filepath = $file_stored->get_filepath();
            $filepath = trim($filepath, '/');
            $dirs = explode('/', $filepath);
            array_pop($dirs);
            $filepath = implode('/', $dirs);

            $updrecord->filepath = ($filepath === '') ? "/$new_name/" : "/$filepath/$new_name/";
            $updrecord->filename = '.';
        } else {
            $updrecord->filepath = $file_stored->get_filepath();
            $updrecord->filename = $new_name;
        }
        $updrecord->userid       = $file_stored->get_userid();
        $updrecord->filesize     = $file_stored->get_filesize();
        $updrecord->mimetype     = $file_stored->get_mimetype();
        if (!$file_stored->is_directory()) {
            $updrecord->mimetype = mimeinfo('type', $updrecord->filename);
        }
        $updrecord->status       = $file_stored->get_status();
        $updrecord->source       = $file_stored->get_source();
        $updrecord->author       = $file_stored->get_author();
        $updrecord->license      = $file_stored->get_license();
        $updrecord->sortorder    = $file_stored->get_sortorder();
        $updrecord->contenthash  = $file_stored->get_contenthash();
        // Evaluate the new pathname hash
        $updrecord->pathnamehash = $this->fileStorage->get_pathname_hash(
            $updrecord->contextid,
            $updrecord->component,
            $updrecord->filearea,
            $updrecord->itemid,
            $updrecord->filepath,
            $updrecord->filename
        );
        $updrecord->timecreated  = $file_stored->get_timecreated();
        $updrecord->timemodified = $file_stored->get_timemodified();

        if ($DB->record_exists('files', array('pathnamehash' => $updrecord->pathnamehash))) {
            throw new Sabre_DAV_Exception(
                "PoolNode::stored_file_rename() - Already exists: $updrecord->filepath$updrecord->filename");
        }

        $DB->update_record('files', $updrecord);

        return $this->fileStorage->get_file_instance($updrecord);
    }

    /**
     * Move an item in the Moodle files pool from its root path to a given one.
     * Be careful in moving root items: they should be never moved!
     *
     * IMPORTANT! Low level functionality: it should be implemented
     * by the Moodle Files API, e.g. file_storage::move_to_new_folder()
     *
     * @throws file_exception
     * @param stored_file $file_stored The stored_file instance to be renamed
     * @param string $old_root_path The old root path
     * @param string $new_root_path The new root path
     * @return stored_file The updated stored_file instance
     */
    protected function file_storage_move_to_new_root_path(
        stored_file $file_stored,
        $old_root_path,
        $new_root_path
    )
    {
        global $DB;

        // Validate old_root_path
        $old_root_path = clean_param($old_root_path, PARAM_PATH);
        if (strpos($file_stored->get_filepath(), $old_root_path) !== 0) {
            throw new file_exception('storedfileproblem', 'Found no matching for the given old root path');
        }
        if (strrpos($old_root_path, '/') !== strlen($old_root_path)-1) {
            // Path must end with '/'
            throw new file_exception('storedfileproblem', 'Invalid old root path');
        }
        // Validate new_root_path
        $new_root_path = clean_param($new_root_path, PARAM_PATH);
        if (strpos($new_root_path, '/') !== 0 or strrpos($new_root_path, '/') !== strlen($new_root_path)-1) {
            throw new file_exception('storedfileproblem', 'Invalid new root path');
        }

        // Update the contents by creating a new record in the pool
        $updrecord = new stdClass();
        $updrecord->id           = $file_stored->get_id();
        $updrecord->contextid    = $file_stored->get_contextid();
        $updrecord->component    = $file_stored->get_component();
        $updrecord->filearea     = $file_stored->get_filearea();
        $updrecord->itemid       = $file_stored->get_itemid();
        // Evaluate the new root path: str_replace() should be byte safe for our needs
        $updrecord->filepath     = str_replace(
            $old_root_path,
            $new_root_path,
            $file_stored->get_filepath()
        );
        $updrecord->filename     = $file_stored->get_filename();
        $updrecord->userid       = $file_stored->get_userid();
        $updrecord->filesize     = $file_stored->get_filesize();
        $updrecord->mimetype     = $file_stored->get_mimetype();
        $updrecord->status       = $file_stored->get_status();
        $updrecord->source       = $file_stored->get_source();
        $updrecord->author       = $file_stored->get_author();
        $updrecord->license      = $file_stored->get_license();
        $updrecord->sortorder    = $file_stored->get_sortorder();
        $updrecord->contenthash  = $file_stored->get_contenthash();
        // Evaluate the new pathname hash
        $updrecord->pathnamehash = $this->fileStorage->get_pathname_hash(
            $updrecord->contextid,
            $updrecord->component,
            $updrecord->filearea,
            $updrecord->itemid,
            $updrecord->filepath,
            $updrecord->filename
        );
        $updrecord->timecreated  = $file_stored->get_timecreated();
        $updrecord->timemodified = $file_stored->get_timemodified();

        $DB->update_record('files', $updrecord);

        return $this->fileStorage->get_file_instance($updrecord);
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
        $children = array();

        if ($this->storedFile->get_filename() === $name) {
            return;
        }
        // Prevent to rename a virtual root file
        if ($this->storedFile->is_directory() && ($this->storedFile->get_filepath() === '/')) {
            throw new Sabre_DAV_Exception_Forbidden("You can't rename a virtual root file");
        }

        // Load the configuration settings
        $cfgDAVRoot = get_config('local_davroot');
        if ($cfgDAVRoot->readonly) {
            throw new Sabre_DAV_Exception_Forbidden('Read-only access configured');
        }

        $transaction = $DB->start_delegated_transaction();

        $oldRootPath = $this->storedFile->get_filepath();
        // Get recursive children. Optimized renaming: the reason for DAVRootPoolNode::setName()
        // instead of specialized DAVRootPoolDirectory::setName() and DAVRootPoolFile::setName()
        if ($this->storedFile->is_directory()) {
            $children = $this->fileStorage->get_directory_files(
            $this->storedFile->get_contextid(),
            $this->storedFile->get_component(),
            $this->storedFile->get_filearea(),
            $this->storedFile->get_itemid(),
            $this->storedFile->get_filepath(),
            true,                          // recursive
            true,                         // includedirs
            'filepath ASC, filename ASC' // sort
            );
        }

        // Rename the node in the pool
        try {
            $this->storedFile = $this->stored_file_rename($this->storedFile, $name);
        } catch (Exception $e) {
            $transaction->rollback(
                new Sabre_DAV_Exception('PoolNode::setName() - ' . $e->getMessage())
            );
        }
        $newRootPath = $this->storedFile->get_filepath();

        // Perform the renaming of the root path of each child item in the pool
        foreach ($children as $child) {
            try {
                // Move the child item under the new root
                $this->file_storage_move_to_new_root_path($child, $oldRootPath, $newRootPath);
            } catch (Exception $e) {
                $transaction->rollback(
                    new Sabre_DAV_Exception('PoolNode::setName() on children - ' . $e->getMessage())
                );
            }
        }

        $transaction->allow_commit();
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
