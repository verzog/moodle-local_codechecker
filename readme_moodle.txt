Since version 5.0 of this plugin we have stopped
copying all the tools needed manually and, instead,
we are installing them via `composer`.

This fork supports Moodle 5.0 to 5.3 LTS, so the
lowest PHP version supported is PHP 8.2 (Moodle 5.0
and 5.1). The `config.platform.php` setting in
`composer.json` is pinned to 8.2 so Composer only
picks package versions that work there.

The tools needed for this to run are (you can also
see the `composer.json` file for details):

- moodlehq/moodle-cs, that installs:
  - squizlabs/php_codesniffer
  - phpcsstandards/phpcsextra
  - phpcsstandards/phpcsutils
  - dealerdirect/phpcodesniffer-composer-installer

PHPCompatibility is no longer bundled: moodle-cs
does not require or enable it any more.

To update any component:

1. Remove the .lock file, the vendor directory.
2. Run `composer clearcache` (to clear composer caches).
3. Switch to the lowest PHP version supported (PHP 8.2).
4. Run `composer install` (to install everything).
5. Update `thirdpartylibs.xml` and the "Third-party
   libraries" section of README.md with the new versions.
6. Commit changes with details about the tools updated.
7. Test, test, test.
8. Optionally, release.

At some point we may want to make the process above automated, so every time
that a new moodle-cs package is released, everything above (1-8) happens automatically.
See (last point of) https://github.com/moodlehq/moodle-local_codechecker/issues/114
