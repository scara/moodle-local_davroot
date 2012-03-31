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
 * PoolFile class 
 * 
 * @package    local
 * @subpackage davroot
 * @author     Matteo Scaramuccia <moodle@matteoscaramuccia.com>
 * @copyright  Copyright (C) 2011-2012 Matteo Scaramuccia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class DAVRootPoolFile extends DAVRootPoolNode implements Sabre_DAV_IFile
{

    /**
     * Updates the data
     *
     * @param resource $data Readable stream resource
     * @return void 
     */
    public function put($data)
    {
        global $DB, $USER;

        // Load the configuration settings
        $cfgDAVRoot = get_config('local_davroot');
        if ($cfgDAVRoot->readonly) {
            throw new Sabre_DAV_Exception_Forbidden('Read-only access configured');
        }

        if ($type = get_resource_type($data)) {
            // Get the contents
            $contents = '';
            while (!feof($data)) {
                $contents .= fread($data, 8192);
            }

            // Update the file in the pool
            $old = $this->storedFile;
            $transaction = $DB->start_delegated_transaction();
            try {
                $newFileRecord = new stdClass();
                $newFileRecord->contextid = $this->storedFile->get_contextid();
                $newFileRecord->component = $this->storedFile->get_component();
                $newFileRecord->filearea  = $this->storedFile->get_filearea();
                $newFileRecord->itemid    = $this->storedFile->get_itemid();
                $newFileRecord->filepath  = $this->storedFile->get_filepath();
                $newFileRecord->filename  = $this->storedFile->get_filename();
                $newFileRecord->userid    = $USER->id;
                $newFileRecord->sortorder = $this->storedFile->get_sortorder();
                // source, author, license inherited from the old file? Yes, I suppose
                $source                   = $this->storedFile->get_source();
                $newFileRecord->source    = empty($source) ? null : $source;
                $author                   = $this->storedFile->get_author();
                $newFileRecord->author    = empty($author) ? null : $author;
                $license                  = $this->storedFile->get_license();
                $newFileRecord->license   = empty($license) ? null : $this->storedFile->get_license();
                // pathnamehash has a unique index in the DB => Delete the old file
                // with the old contents (i.e. Moodle Files API will move into trashdir)
                // before adding the one with the new contents
                // TODO: trashdir cleanup cannot be transactional with the DB rollback!
                $old->delete();
                // Update the contents by creating the same file with the new contents
                $this->storedFile = $this->fileStorage->create_file_from_string(
                    $newFileRecord,
                    $contents
                );
            } catch (Exception $e) {
                $transaction->rollback(
                    new Sabre_DAV_Exception('PoolFile::put() - ' . $e->getMessage())
                );
            }
            $transaction->allow_commit();
        } else {
            throw new Sabre_DAV_Exception_InvalidResourceType(
                'File data is not a readable file stream resource'
            );
        }
    }

    /**
     * Returns the data 
     * 
     * This method may either return a string or a readable stream resource
     *
     * @return mixed 
     */
    public function get()
    {
        return $this->storedFile->get_content();
    }

    /**
     * Returns the mime-type for a file
     *
     * If null is returned, we'll assume application/octet-stream
     * 
     * @return void
     */
    public function getContentType()
    {
        return $this->storedFile->get_mimetype();
    }

    /**
     * Returns the ETag for a file
     *
     * An ETag is a unique identifier representing the current version of the file.
     * If the file changes, the ETag MUST change.
     * The ETag is an arbitrary string, but MUST be surrounded by double-quotes
     *
     * Return null if the ETag can not effectively be determined
     * 
     * @return void
     */
    public function getETag()
    {
        return '"' . $this->storedFile->get_contenthash() . '"';
    }

    /**
     * Returns the size of the node, in bytes 
     * 
     * @return int 
     */
    public function getSize()
    {
        return $this->storedFile->get_filesize();
    }
}
