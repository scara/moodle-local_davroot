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
 * Language strings
 *
 * @package    local
 * @subpackage davroot
 * @author     Matteo Scaramuccia <moodle@matteoscaramuccia.com>
 * @copyright  Copyright (C) 2011 Matteo Scaramuccia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['davroot:canconnect'] = 'Permette all\'utente di collegarsi al server WebDAV che espone i files in Moodle';
$string['allowconns'] = 'Permette di connettersi';
$string['allowconnsdescr'] = 'Permette agli utenti privilegiati di accedere ai files in Moodle attraverso WebDAV';
$string['credits'] = 'Crediti';
$string['creditsdescr'] = '<div style="text-align:center;">DAVRoot utilizza <a href="http://code.google.com/p/sabredav/" target="_blank">SabreDAV, un framework WebDAV per PHP</a>, per mappare l\'API dei Files in Moodle alle Risorse e Collezioni in DAV</div>';
$string['lockmanager'] = 'Gestore dei Lock';
$string['lockmanagerdescr'] = 'Permette la gestione centralizzata dei Lock in {$a->lockmngrfolder}';
$string['pluginbrowser'] = 'Plugin Browser';
$string['pluginbrowserdescr'] = 'Produce indici simile a quelli di Apache per il File System virtuale di Moodle';
$string['pluginmount'] = 'Plugin DavMount';
$string['pluginmountdescr'] = 'Aggiunge il supporto per l\'RFC 4709. Questa specifica definisce un documento che possa dire al client come montare un WebDAV server';
$string['pluginname'] = 'DAVRoot';
$string['pluginnamedescr'] = 'Espone i files in Moodle attraverso WebDAV';
$string['plugintempfilefilter'] = 'Plugin Temporary File Filter';
$string['plugintempfilefilterdescr'] = 'Intercetta i pi&ugrave; comuni file temporanei conosciuti, creati dal Sistema Operativo e dagli Applicativi, e li posiziona in una cartella temporanea, {$a->tmpfilefilterfolder}';
// Slashes in a Virtual Node name (e.g. a Category) break browsing, replace them with safe characters
$string['slashrep'] = '[barra]';
$string['urlrewrite'] = 'Abilita l\'<i>URL rewrite</i>';
$string['urlrewritedescr'] = 'Permette alle URL DAV di essere scritte senza la pagina PHP finale: {$a->wwwpath}';
$string['vhostenabled'] = 'Abilita il <i>Virtual Host</i>';
$string['vhostenableddescr'] = 'Permette a WebDAV di essere esposto alla radice di un <i>Virtual Host</i> opportunamente configurato per mappare {$a->dirpath}';
