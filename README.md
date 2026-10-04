Moodle Code Checker
===================

[![Codechecker CI](https://github.com/verzog/moodle-local_codechecker/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/verzog/moodle-local_codechecker/actions/workflows/ci.yml)

Information
-----------

This Moodle plugin uses the [PHP CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) tool to
check that code follows the [Moodle coding style](https://moodledev.io/general/development/policies/codingstyle).
It uses the [Moodle Coding Style](https://github.com/moodlehq/moodle-cs) 'sniffs' that check many aspects of the code.
(PHPCompatibility checks are not included: moodle-cs no longer enables them.)

It was created by developers at the Open University, including Sam Marshall,
Tim Hunt and Jenny Gray. Upstream is maintained by Moodle HQ; this fork
carries additional fixes on top of it.

Requirements and compatibility
------------------------------

This fork supports Moodle 5.0 to 5.3 LTS. The PHP floor follows
upstream: PHP 8.2+ on Moodle 5.0 and 5.1, and PHP 8.3+ on Moodle 5.2
and 5.3. CI tests every supported stable branch (`MOODLE_500_STABLE`
to `MOODLE_503_STABLE`) on PHP 8.2 to 8.4 against PostgreSQL and
MariaDB. Moodle 6.0 (`main`) is not yet supported.

Upstream releases can be downloaded and installed from
<https://moodle.org/plugins/view.php?plugin=local_codechecker>.

Installing via uploaded ZIP file
--------------------------------

1. Download the ZIP from
   <https://github.com/verzog/moodle-local_codechecker/zipball/main>.
2. Log in to your Moodle site as an admin and go to
   _Site administration > Plugins > Install plugins_.
3. Upload the ZIP file with the plugin code. You should only be prompted
   to add extra details if your plugin type is not automatically detected.
4. Check the plugin validation report and finish the installation.

Installing manually
-------------------

The plugin can also be installed by putting the contents of this
repository into the `local/codechecker` folder of your Moodle code. On
Moodle 5.1 and later the code lives in the `public/` folder, so the path
is `public/local/codechecker`:

    # Moodle 5.0
    git clone https://github.com/verzog/moodle-local_codechecker.git local/codechecker
    # Moodle 5.1 and later
    git clone https://github.com/verzog/moodle-local_codechecker.git public/local/codechecker

Then add that folder to your Moodle git ignore, log in as an admin and
go to _Site administration > Notifications_ to complete the installation
(or run `php admin/cli/upgrade.php`).

Using it
--------

After installing, the checker is available at:

> Site administration -> Development -> Code checker

Enter paths relative to the Moodle code root (`$CFG->dirroot`, which is
the `public/` folder on Moodle 5.1 and later), for example
`local/myplugin`.

From the command line, run from the Moodle code root (the `public/`
folder on Moodle 5.1 and later):

    php local/codechecker/run.php local/myplugin

Run `php local/codechecker/run.php --help` for all options.

You can also run the bundled PHP_CodeSniffer directly. It is already
configured to find the Moodle standard, so do **not** run
`phpcs --config-set installed_paths` (that would overwrite the bundled
configuration):

    local/codechecker/vendor/bin/phpcs --standard=moodle local/myplugin

Web server file permissions
---------------------------

The form submits to ``/local/codechecker/index.php`` so the request is
always handled by PHP directly, matching how the page itself is served.
If you still see a bare ``403 Forbidden`` only when pressing "Check"
(while the form page loads fine), check that the ``codechecker``
directory is readable and traversable by your web server user or group,
matching the rest of your Moodle tree. This can happen when the plugin
is deployed as a non-web account (for example ``rsync`` or ``git pull``
run as a deploy user).

Checking proprietary or third-party code
----------------------------------------

The Moodle standard requires every file to start with the Moodle GPL
boilerplate and carry matching ``@copyright`` / ``@license`` tags.
Code that ships under different terms (for example a plugin whose files
declare ``@license Proprietary``) fails those two checks on every file,
which drowns out the genuine style problems. Tick **Skip Moodle licence
and boilerplate checks** on the check form (or pass ``--skiplicence`` to
``run.php``) to exclude just those sniffs for a run; all other checks
still apply.

We hope you find this tool useful. Feel free to enhance it! Also, you can
report any idea or bug using GitHub's issues and pull requests, thanks!


Integrations
------------

Since version v4.0.0 this plugin shouldn't be used as source for any
integration with IDEs or tools, and
[Moodle Coding Style](https://github.com/moodlehq/moodle-cs) (the new source
for the moodle-cs standard) should be used instead.

Please refer to the information available in that repository to know more
about how to install, configure and integrate it with your development
environment.

Third-party libraries
---------------------

The `vendor/` folder bundles these libraries, unmodified, installed with
Composer (see `thirdpartylibs.xml` and `composer.lock`). Each keeps its
own licence file in `vendor/`.

- **PHP_CodeSniffer** 3.13.6 - BSD-3-Clause - Squiz Pty Ltd, PHPCSStandards
  and contributors - <https://github.com/PHPCSStandards/PHP_CodeSniffer>
- **Moodle Coding Style (moodle-cs)** 3.7.0 - GPL-3.0-or-later - Moodle HQ
  and contributors - <https://github.com/moodlehq/moodle-cs>
- **PHPCSExtra** 1.5.0 - LGPL-3.0-or-later - PHPCSStandards and contributors -
  <https://github.com/PHPCSStandards/PHPCSExtra>
- **PHPCSUtils** 1.2.3 - LGPL-3.0-or-later - PHPCSStandards and contributors -
  <https://github.com/PHPCSStandards/PHPCSUtils>
- **PHP_CodeSniffer Standards Composer Installer** 1.2.0 - MIT - Dealerdirect
  B.V., PHPCSStandards and contributors -
  <https://github.com/PHPCSStandards/composer-installer>

License
-------

2011 The Open University and contributors

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program. If not, see <https://www.gnu.org/licenses/>.
