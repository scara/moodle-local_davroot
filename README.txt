This Moodle local plugin uses SabreDAV, a WebDAV framework for PHP,
to map the Moodle Files API into DAV Resources and Collections.

To install using git, type this command in the root of your Moodle installation:
    git clone git://github.com/scara/moodle-local_davroot.git ./local/davroot
Then add '/local/davroot' to your git ignore or local exclude, e.g.:
    echo '/local/davroot' >> .git/info/exclude

Alternatively, download the tar.gz/zip from:
    https://github.com/scara/moodle-local_davroot/tarball/master
    https://github.com/scara/moodle-local_davroot/zipball/master
and uncompress it into the 'local' folder. Then rename the new folder
into 'davroot'.

Log into your Moodle instance as admin: the installation process will start.

After you have installed this local plugin , you'll need to configure it under
Site administration -> Plugins -> Local plugins -> DAVRoot in the 'Settings' block.

LICENSE
DAVRoot is licenced under the GNU GPL v3 or later.
SabreDAV is licenced under the modified BSD, which is compatible with GPL:
details in http://www.gnu.org/licenses/license-list.html#ModifiedBSD.

SECURITY
Requires the 'local/davroot:canconnect' capability at system context level.

PERFORMANCES
WebDAV browsing is limited by, mostly, the amount of queries required
to browse this virtual file system, especially when using
Microsoft Windows' WebDAV implementation and you have some shell extensions.
There will probably be the need for improving the code too.

KNOWN ISSUES
- DAVRoot uses the Moodle Files API EXCEPT when folders/files will be renamed:
  using DAVRoot "should" be as safe as manipulating files using Moodle.
  Browsing is the safest option.
- Microsoft Windows' WebDAV implementation requires some configurations:
  read more at http://code.google.com/p/sabredav/wiki/Windows.
  Shortly, on Vista:
  1. Create a Virtual Host to directly expose the local/davroot folder
  2. Change Virtual Host settings of the DAVRoot plugin
  3. Software Update for Web Folders (KB907306)
  4. [HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Services\WebClient\Parameters]
    "UseBasicAuth"=dword:00000001
    "BasicAuthLevel"=dword:00000002
  5. Restart
  Shortly, on Apache: create a Virtual Host pointing to local/davroot
- Authentication with MNet accounts is not supported
- DAVRoot has been tested NOT in a production environment, using
  Moodle 2.0/MySQL 5.0/PHP 5.2.17 with the following WebDAV clients:
  . davfs, under CentOS 5.7
  . cadaver, under CentOS 5.7
  . Cyberduck, under Windows Vista Home Premium SP2
  . Microsoft Windows' WebDAV implementation, Microsoft-WebDAV-MiniRedir/6.0.6002
- Pay attention when using concurrent clients: Lock Manager should be enabled

TODO
- Temporary file patterns configuration
- Let required folders be configured as part of the plugin settings
- Log who change what
- AOB: see the notes in the code
