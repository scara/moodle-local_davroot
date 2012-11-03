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
 * BrowseFile class
 * 
 * @package    local
 * @subpackage davroot
 * @author     Matteo Scaramuccia <moodle@matteoscaramuccia.com>
 * @copyright  Copyright (C) 2011-2012 Matteo Scaramuccia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class DAVRootBrowseFile extends DAVRootBrowseNode implements Sabre_DAV_IFile
{

    /**
     * Updates the data 
     *
     * @param resource $data Readable stream resource
     * @return void 
     */
    public function put($data)
    {
        // Implemented in DAVRootPoolFile
        if ($poolNode = $this->getDAVRootPoolObject()) {
            if ($poolNode instanceof DAVRootPoolFile) {
                $poolNode->put($data);
                // Update the instance
                // TODO: code below not really useful, the object will be always recreated in real life
                $params = $this->fileInfo->get_params();
                // Context access backward compatibility (2.1-): http://docs.moodle.org/dev/Access_API
                $context = class_exists('context', false) ? context::instance_by_id($params['contextid']) : get_context_instance_by_id($params['contextid']);
                $this->fileInfo = $this->fileBrowser->get_file_info(
                    $context,
                    $params['component'],
                    $params['filearea'],
                    $params['itemid'],
                    $params['filepath'],
                    $params['filename']
                );
                unset($params);
            } else {
                throw new Sabre_DAV_Exception_Forbidden('Invalid pool node: expected File');
            }
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
        // Some properties of file_info_stored are OOP-protected.
        // Access them through stored_file i.e. DAVRootPoolFile
        if ($poolNode = $this->getDAVRootPoolObject()) {
            if ($poolNode instanceof DAVRootPoolFile) {
                return $poolNode->get();
            } else {
                throw new Sabre_DAV_Exception_Forbidden('Invalid pool node: expected File');
            }
        }

        throw new Sabre_DAV_Exception_FileNotFound(
            'Unable to find \''. $this->getName() . '\' in the pool'
        );
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
        return $this->fileInfo->get_mimetype();
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
        // Content Hash is not directly exposed by file_info_stored,
        // DAVRootBrowseNode::getProperties() gets it from DAVRootPoolNode
        $props = $this->getProperties(array());

        return '"' . $props['{DAV:}getetag'] . '"';
    }

    /**
     * Returns the size of the node, in bytes 
     * 
     * @return int 
     */
    public function getSize()
    {
        return $this->fileInfo->get_filesize();
    }
}
