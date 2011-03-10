
Drush make
----------
Drush make is an extension to drush that can create a ready-to-use drupal site,
pulling sources from various locations. It does this by parsing a flat text file
(similar to a drupal `.info` file) and downloading the sources it describes. In
practical terms, this means that it is possible to distribute a complicated
Drupal distribution as a single text file.

Among drush make's capabilities are:

- Downloading Drupal core, as well as contrib modules from drupal.org.
- Checking code out from CVS, SVN, git, and bzr repositories.
- Getting plain `.tar.gz` and `.zip` files (particularly useful for libraries
  that can not be distributed directly with drupal core or modules).
- Fetching and applying patches.
- Fetching modules, themes, and installation profiles, but also external
  libraries.


Usage
-----
The `drush make` command can be executed from a path within a Drupal codebase or
independent of any Drupal sites entirely. See the examples below for instances
where `drush make` can be used within an existing Drupal site.

    drush make [-options] [filename.make] [build path]


### Options

    --contrib-destination=path

      Specify a path under which modules and themes should be
      placed. Defaults to sites/all.

    --force-complete

      Force a complete build even if errors occur.

    --ignore-checksums

      Do not verify md5 checksums for downloaded files.

    --md5

      Output an md5 hash of the current build after completion.

    --no-clean

      Leave temporary build directories in place instead of
      cleaning up after completion.

    --no-core

      Do not require a Drupal core project to be specified.

    --no-patch-txt

      Do not write a PATCHES.txt file in the directory of
      each patched project.

    --prepare-install

      Prepare the built site for installation. Generate a
      properly permissioned settings.php and files directory.

    --tar

      Generate a tar archive of the build. The output filename
      will be [build path].tar.gz.

    --test

      Run a temporary test build and clean up.

    --translations=languages

      Retrieve translations for the specified comma-separated list
      of language(s) if available for all projects.

    --working-copy

      Where possible, retrieve a working copy of projects from
      their respective repositories.


### Examples

1. Build a Drupal site at `example/` including Drupal core and any projects
   defined in the makefile:

        drush make example.make example

2. Build a tarball of the platform above as `example.tar.gz`:

        drush make --tar example.make example

3. Build an installation profile within an existing Drupal site:

        drush make --no-core --contrib-destination=. installprofile.make


The `.make` file format
-----------------------
Each makefile is a plain text file that adheres to the Drupal `.info` file
syntax. See the included `example.make` for an example of a working makefile.


### Core version

The make file always begins by specifying the core version of Drupal for which
 each package must be compatible. Example:

    core = 6.x


### API version

The make file must specify which Drush Make API version it uses. This version
of Drush Make uses API version `2`

    api = 2


### Projects

An array of the projects (e.g. modules, themes, libraries, and drupal) to be
retrieved. Each project name can be specified as a single string value. If
further options need to be provided for a project, the project should be
specified as the key.

**Project with no further options:**

    projects[] = drupal

**Project using options (see below):**

    projects[drupal][version] = 6.15

Do not use both types of declarations for a single project in your makefile.


### Project options

- `version`

  Specifies the version of the project to retrieve.
  This can be as loose as the major branch number or
  as specific as a particular point release.

        projects[views][version] = 3
        projects[views][version] = 2.8
        projects[views][version] = 3.0-alpha2

        ; Shorthand syntax for versions if no other options are to be specified
        projects[views] = 3.0-alpha2

- `patch`

  One or more patches to apply to this project. An array of URLs from which
  each patch should be retrieved.

        projects[calendar][patch][rfc-fixes][url] = "http://drupal.org/files/issues/cal-760316-rfc-fixes-2.diff"
        projects[calendar][patch][rfc-fixes][md5] = "e4876228f449cb0c37ffa0f2142"

        ; shorthand syntax if no md5 checksum is specified
        projects[adminrole][patch][] = "http://drupal.org/files/issues/adminrole_exceptions.patch"

- `subdir`

  Place a project within a subdirectory of the `--contrib-destination`
  specified. In the example below, `cck` will be placed in
  `sites/all/modules/contrib` instead of the default `sites/all/modules`.

        projects[cck][subdir] = "contrib"

- `location`

  URL of an alternate project update XML server to use. Allows project XML data
  to be retrieved from sites other than `updates.drupal.org`.

        projects[tao][location] = "http://code.developmentseed.com/fserver"

- `type`

  The project type. Must be provided if an update XML source is not specified
  and/or using version control or direct retrieval for a project. May be one of
  the following values: core, module, profile, theme.

        projects[mytheme][type] = "theme"

- `directory_name`

  Provide an alternative directory name for this project. By default, the
  project name is used.

        projects[mytheme][directory_name] = "yourtheme"

- `l10n_path`

  Specific URL (can include tokens) to a translation. Allows translations to be
  retrieved from l10n servers other than `localize.drupal.org`.

        projects[mytheme][l10n_path] = "http://myl10nserver.com/files/translations/%project-%core-%version-%language.po"

- `l10n_url`

  URL to an l10n server XML info file. Allows translations to be retrieved from
  l10n servers other than `localize.drupal.org`.

        projects[mytheme][l10n_url] = "http://myl10nserver.com/l10n_server.xml"


### Project download options

  Use an alternative download method instead of retrieval through update XML.
  The following methods are available:

- `download[type] = file`

  Retrieve a project as a direct download. Options:

  `url` - the URL of the file. Required.

  `md5`, `sha1`, `sha256`, or `sha512` - one or more checksums for the file. Optional.

  `request_type` - the request type - get or post. Defaults to get. Optional.

  `data` - The post data to be submitted with the request. Should be a
  valid URL query string. Required.

  `filename` - What to name the file, if it's not an archive. Optional.

- `download[type] = bzr`

  Use a bazaar repository as the source for this project. Options:

  `url` - the URL of the repository. Required.

- `download[type] = cvs`

  Use a CVS repository as the source for this project. Options:

  `date` - use the latest revision no later than specified date. See the CVS
  man page for more about how to use the date flag.

  `root` - the CVS repository to use for this project. Optional. If unspecified,
  the `CVSROOT` environment value will first be used and finally Drupal contrib
  CVS is used as a last resort fallback.

  `module` - the CVS module to retrieve. Required.

  `revision` - a specific tag or revision to check out. Optional.

     projects[mytheme][download][type] = "cvs"
     projects[mytheme][download][module] = "mytheme"

- `download[type] = git`

  Use a git repository as the source for this project. Options:

  `url` - the URL of the repository. Required.

  `branch` - the branch to be checked out. Optional.

  `revision` - a specific revision identified by commit to check out. Optional.

  `tag` - the tag to be checked out. Optional.

     projects[mytheme][download][type] = "git"
     projects[mytheme][download][url] = "git://github.com/jane_doe/mytheme.git"

- `download[type] = svn`

  Use an SVN repository as the source for this project. Options:

  `url` - the URL of the repository. Required.

  `interactive` - whether to prompt the user for authentication credentials
  when using a private repository. Allows username and/or password options to
  be omitted. Optional.

  `username` - the username to use when retrieving an SVN project as a working
  copy or from a private repository. Optional.

  `password` - the password to use when retrieving an SVN project as a working
  copy or from a private repository. Optional.

     projects[mytheme][download][type] = "svn"
     projects[mytheme][download][url] = "http://example.com/svnrepo/cool-theme/"


### Libraries

An array of non-Drupal-specific libraries to be retrieved (e.g. js, PHP or other
Drupal-agnostic components). Each library should be specified as the key of an
array of options in the libraries array.

**Example:**

    libraries[jquery_ui][download][type] = "file"
    libraries[jquery_ui][download][url] = "http://jquery- ui.googlecode.com/files/jquery.ui-1.6.zip"
    libraries[jquery_ui][download][md5] = "c177d38bc7af59d696b2efd7dda5c605"


### Library options

Libraries share the `download`, `subdir`, and `directory_name` options with
projects. Additionally, they may specify a destination:

- `destination`

  The target path to which this library should be moved. The path is relative to
  that specified by the `--contrib-destination` option. By default, libraries
  are placed in the `libraries` directory.

        libraries[jquery_ui][destination] = "modules/contrib/jquery_ui


### Includes

An array of makefiles to include. Each include may be a local relative path
to the includer makefile directory or a direct URL to the makefile. Includes
are appended in order with the source makefile appended last, allowing latter
makefiles to override the keys/values of former makefiles.

**Example:**

    includes[example] = "example.make"
    includes[example_relative] = "../example_relative/example_relative.make"
    includes[remote] = "http://www.example.com/remote.make"


### Overriding properties

Makefiles which include others may override the included makefiles properties.
Properties in the includer takes precedence over the includee.

**Example:**

`base.make`

    core = "6.x"
    projects[views][subdir] = "contrib"
    projects[cck][subdir] = "contrib"

`extender.make`

    includes[base] = "base.make"

    ; This line overrides the included makefile's 'subdir' option
    projects[views][subdir] = "patched"

    ; This line overrides the included makefile, switching the download type
    ; to a CVS checkout
    projects[views][type] = "module"
    projects[views][download][type] = "cvs"
    projects[views][download][module] = "contributions/modules/views"
    projects[views][download][revision] = "DRUPAL-6--2"

A project or library entry of an included makefile can be removed entirely by
setting the corresponding key to FALSE:

    ; This line removes CCK entirely which was defined in base.make
    projects[cck] = FALSE


Recursion
---------
If a project that is part of a build contains a `.make` itself, drush make will
automatically parse it and recurse into a derivative build.

For example, a full build tree may look something like this:

    drush make distro.make distro

    distro.make FOUND
    - Drupal core
    - Foo bar install profile
      + foobar.make FOUND
        - CCK
        - Token
        - Module x
          + x.make FOUND
            - External library x.js
        - Views
        - etc.

Recursion can be used to nest an install profile build in a Drupal site, easily
build multiple install profiles on the same site, fetch library dependencies
for a given module, or bundle a set of module and its dependencies together.
For Drush Make to recognize a makefile embedded within a project, the makefile
itself must have the same name as the project. For instance, the makefile
embedded within the managingnews profile must be called "managingnews.make".

**Build a full Drupal site with the Managing News install profile:**

    core = 6.x
    projects[] = drupal
    projects[] = managingnews

Testing
-------
Drush make also comes with testing capabilities, designed to test drush make
itself. Writing a new test is extremely simple. The process is as follows:

1. Figure out what you want to test. Write a makefile that will test this out.
   You can refer to existing test makefiles for examples.
2. Drush make your makefile, and use the --md5 option. You may also use other
   options, but be sure to take note of which ones for step 4.
3. Verify that the result you got was in fact what you expected. If so,
   continue. If not, tweak it and re-run step 2 until it's what you expected.
4. Using the md5 hash that was spit out from step 2, make a new entry in the
   tests array in drush_make.test.inc, following the example below.
    'machine-readable-name' => array(
      'name'     => 'Human readable name',
      'makefile' => 'tests/yourtest.make',
      'messages' => array(
          'Build hash: f68e6510-your-hash-e04fbb4ed',
      ),
      'options'  => array('any' => TRUE, 'other' => TRUE, 'options' => TRUE),
    ),
5. Test! Run drush make-test machine-readable-name to see if the test passes.

You can check for any messages you want in the message array, but the most
basic tests would just check the build hash.

Generate
--------

Drush make has a primitive makefile generation capability. To use it, simply
change your directory to the Drupal installation from which you would like to
generate the file, and run the following command: 

`drush generate-makefile /path/to/make-file.make`

This will generate a basic makefile. If you have code from other repositories,
the makefile will not complete - you'll have to fill in some information before
it is fully functional.

Maintainer
----------
- Dmitri Gaskin (dmitrig01)

Co-maintainers
------------
- Adrian Rossouw (adrian)
- Antoine Beaupré (anarcat)
- Chad Phillips (hunmonk)
- Jeff Miccolis (jmiccolis)
- Young Hahn (yhahn)

