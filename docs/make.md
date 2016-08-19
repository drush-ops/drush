
Drush make
----------
Drush make is an extension to drush that can create a ready-to-use drupal site,
pulling sources from various locations. It does this by parsing a flat text file
(similar to a drupal `.info` file) and downloading the sources it describes. In
practical terms, this means that it is possible to distribute a complicated
Drupal distribution as a single text file.

Among Drush make's capabilities are:

- Downloading Drupal core, as well as contrib modules from drupal.org.
- Checking code out from SVN, git, and bzr repositories.
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

The `.make` file format
-----------------------
Each makefile is a plain text file that adheres to YAML syntax. See
the included `examples/example.make.yml` for an example of a working
makefile.

The older Drupal `.info` INI format is also supported. See
`examples/example.make` for a working example.

### Core version

The make file always begins by specifying the core version of Drupal
for which each package must be compatible. Example:

    core: 7.x

### API version

The make file must specify which Drush Make API version it uses. This version
of Drush Make uses API version `2`

    api: 2


### Projects

An array of the projects (e.g. modules, themes, libraries, and drupal) to be
retrieved. Each project name can be specified as a single string value. If
further options need to be provided for a project, the project should be
specified as the key.

**Project with no further options:**

    projects:
      - drupal

**Project using options (see below):**

    projects:
      drupal:
        version: "7.33"

Do not use both types of declarations for a single project in your makefile.


### Project options

- `version`

  Specifies the version of the project to retrieve.
  This can be as loose as the major branch number or
  as specific as a particular point release.

        projects:
          views:
            # Picks the latest release.
            version: ~

        projects:
          views:
            version: "2.8"

        projects:
          views:
            version: "3.0-alpha2"

        # Shorthand syntax for versions if no other options are to be specified
        projects:
          views: "3.0-alpha2"

  Note that version numbers should be enclosed in
  quotes to ensure they are interpreted correctly
  by the YAML parser.

- `patch`

  One or more patches to apply to this project. An array of URLs from which
  each patch should be retrieved.

        projects:
          calendar:
            patch:
              rfc-fixes:
                url: "http://drupal.org/files/issues/cal-760316-rfc-fixes-2.diff"
                md5: "e4876228f449cb0c37ffa0f2142"
          adminrole:
            # shorthand syntax if no md5 checksum is specified
            patch:
              - "http://drupal.org/files/issues/adminrole_exceptions.patch"
              - "http://drupal.org/files/issues/adminrole-213212-01.patch"

- `subdir`

  Place a project within a subdirectory of the `--contrib-destination`
  specified. In the example below, `cck` will be placed in
  `sites/all/modules/contrib` instead of the default `sites/all/modules`.

        projects:
          cck:
            subdir: "contrib"

- `location`

  URL of an alternate project update XML server to use. Allows project XML data
  to be retrieved from sites other than `updates.drupal.org`.

        projects:
          tao:
            location: "http://code.developmentseed.com/fserver"

- `type`

  The project type. Must be provided if an update XML source is not specified
  and/or using version control or direct retrieval for a project. May be one of
  the following values: core, module, profile, theme.

        projects:
          mytheme:
            type: "theme"

- `directory_name`

  Provide an alternative directory name for this project. By default, the
  project name is used.

        projects:
          mytheme:
            directory_name: "yourtheme"

- `l10n_path`

  Specific URL (can include tokens) to a translation. Allows translations to be
  retrieved from l10n servers other than `localize.drupal.org`.

        projects:
          mytheme:
            l10n_path: "http://myl10nserver.com/files/translations/%project-%core-%version-%language.po"

- `l10n_url`

  URL to an l10n server XML info file. Allows translations to be retrieved from
  l10n servers other than `localize.drupal.org`.

        projects:
          mytheme:
            l10n_url: "http://myl10nserver.com/l10n_server.xml"

- `overwrite`

  Allows the project to be installed in a directory that is not empty.
  If not specified this is treated as FALSE, Drush make sets an error when the directory is not empty.
  If specified TRUE, Drush make will continue and use the existing directory.
  Useful when adding extra files and folders to existing folders in libraries or module extensions.

        projects:
          myproject:
            overwrite: TRUE

- `translations`

  Retrieve translations for the specified language, if available, for all projects.

        translations:
          - es
          - fr

- `do_recursion`

  Recursively build an included makefile. Defaults to 'true'. 

        do_recursion: false

- `variant`

  Which type of tarball to download for profiles. Valid options include:
    - 'full': complete distro including Drupal core, e.g. `distro_name-core.tar.gz`
    - 'projects': the fully built profile, projects defined drupal-org.make, etc., e.g. `distro_name-no-core.tar.gz`
    - 'profile-only' (just the bare profile, e.g. `distro_name.tar.gz`).
  Defaults to 'profile-only'. When using 'projects', `do_recursion: false` will be necessary to avoid recursively making any makefiles included in the profile.

        variant: projects



### Project download options

  Use an alternative download method instead of retrieval through update XML.

  If no download type is specified, make defaults the type to
  `git`. Additionally, if no url is specified, make defaults to use
  Drupal.org.

  The following methods are available:

- `download[type] = file`

  Retrieve a project as a direct download. Options:

  `url` - the URL of the file. Required.
          The URL can also be a path to a local file either using the bare path or
          the file:// protocol. The path may be absolute or relative to the makefile.

  `md5`, `sha1`, `sha256`, or `sha512` - one or more checksums for the file. Optional.

  `request_type` - the request type - get or post. post depends on
  http://drupal.org/project/make_post. Optional.

  `data` - The post data to be submitted with the request. Should be a
  valid URL query string. Requires http://drupal.org/project/make_post. Optional.

  `filename` - What to name the file, if it's not an archive. Optional.

  `subtree`  - if the download is an archive, only this subtree within the
  archive will be copied to the target destination. Optional.

- `download[type] = copy`

  Copies a project from a local folder. Options:

  `url` - the URL of the folder. Required.
          The URL must be a path to a local folder either using the bare path or
          the file:// protocol. The path may be absolute or relative to the makefile.

     projects[example][type] = "profile"
     projects[example][download][type] = "copy"
     projects[example][download][url] = "file://./example"

- `download[type] = bzr`

  Use a bazaar repository as the source for this project. Options:

  `url` - the URL of the repository. Required.

  `working-copy` - If true, the checked out source will be kept as a working copy rather than exported as standalone files

- `download[type] = git`

  Use a git repository as the source for this project. Options:

  `url` - the URL of the repository. Required.

  `branch` - the branch to be checked out. Optional.

  `revision` - a specific revision identified by commit to check
    out. Optional. Note that it is recommended on use `branch` in
    combination with `revision` if relying on the .info file rewriting.

  `tag` - the tag to be checked out. Optional.

     projects[mytheme][download][type] = "git"
     projects[mytheme][download][url] = "git://github.com/jane_doe/mytheme.git"

  `refspec` - the git reference to fetch and checkout. Optional.

     If this is set, it will have priority over tag, revision and branch options.

  `working-copy` - If true, the checked out source will be kept as a working copy rather than exported as standalone files

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

     projects:
       mytheme:
         download:
           type: "svn"
           url: "http://example.com/svnrepo/cool-theme/"

  `working-copy` - If true, the checked out source will be kept as a working copy rather than exported as standalone files

  Shorthand for `download[url]` available for all download types:

     projects:
       mytheme:
         download: "git://github.com/jane_doe/mytheme.git"

  is equivalent to:

     projects:
       mytheme:
         download:
           url: "git://github.com/jane_doe/mytheme.git"

### Libraries

An array of non-Drupal-specific libraries to be retrieved (e.g. js, PHP or other
Drupal-agnostic components). Each library should be specified as the key of an
array of options in the libraries array.

**Example:**

    libraries:
      jquery_ui:
        download:
          type: "file"
          url: "http://jquery- ui.googlecode.com/files/jquery.ui-1.6.zip"
          md5: "c177d38bc7af59d696b2efd7dda5c605"


### Library options

Libraries share the `download`, `subdir`, and `directory_name` options with
projects. Additionally, they may specify a destination:

- `destination`

  The target path to which this library should be moved. The path is relative to
  that specified by the `--contrib-destination` option. By default, libraries
  are placed in the `libraries` directory.

        libraries:
          jquery_ui:
            destination: "modules/contrib/jquery_ui"


### Includes

An array of makefiles to include. Each include may be a local relative path to
the include makefile directory, a direct URL to the makefile, or from a git
repository. Includes are appended in order with the source makefile appended
last. As a result, values in the source makefile take precedence over those in
includes. Use `overrides` for the reverse order of precedence.

**Example:**

    includes:
      # Includes a file in the same directory.
      - "example.make"
      # Includes a file with a relative path.
      - "../example_relative/example_relative.make"
      # A remote-hosted file.
      - "http://www.example.com/remote.make"
      # A file on a git repository.
      - makefile: "example_dir/example.make"
        download:
          type: "git"
          url: "git@github.com:organisation/repository.git"
          # Branch could be tag or revision, it relies on the standard Drush git download feature.
          branch: "master"          

The `--includes` option is available for most make commands, and allows
makefiles to be included at build-time.

**Example:**

    # Build from a production makefile, but add development and test projects.
    $ drush make production.make --includes=dev.make,test.make


### Overrides

Similar to `includes`, `overrides` will include content from other makefiles.
However, the order of precedence is reversed. That is, they override the
keys/values of the source makefile.

The `--overrides` option is available for most make commands, and allows
overrides to be included at build-time.

**Example:**

    #production.make.yml:
    api: 2
    core: 8.x
    includes:
      - core.make
      - contrib.make
    projects:
      custom_feature_A:
        type: module
        download:
          branch: production
          type: git
          url: http://github.com/example/custom_feature_A.git
      custom_feature_B:
        type: module
        download:
          branch: production
          type: git
          url: http://github.com/example/custom_feature_B.git

     # Build production code-base.
     $ drush make production.make.yml

     #testing.make
     projects:
       custom_feature_A:
         download:
           branch: dev/bug_fix
       custom_feature_B:
         download:
           branch: feature/new_feature

     # Build production code-base using development/feature branches for custom code.
     $ drush make /path/to/production.make --overrides=http://url/of/testing.make


### Defaults

If all projects or libraries have identical settings for a given
attribute, the `defaults` array can be used to specify these,
rather than specifying the attribute for each project.

**Example:**

    # Specify common subdir of "contrib"
    defaults:
      projects:
        subdir: "contrib"
    # Projects that don't specify subdir will go to the 'contrib' directory.
    projects:
      views:
        version: "3.3"
      # Override a default value.
      devel:
        subdir: "development"

### Overriding properties

Makefiles which include others may override the included makefiles properties.
Properties in the includer takes precedence over the includee.

**Example:**

`base.make`

    core: "6.x"
      views:
        subdir: "contrib"
      cck:
        subdir: "contrib"

`extender.make`

    includes:
      - "base.make"
    projects:
      views:
        # This line overrides the included makefile's 'subdir' option
        subdir: "patched"

        # These lines overrides the included makefile, switching the download type
        # to a git clone.
        type: "module"
        download:
          type: "git"
          url: "http://git.drupal.org/project/views.git"

A project or library entry of an included makefile can be removed entirely by
setting the corresponding key to NULL:

      # This line removes CCK entirely which was defined in base.make
      cck: ~


Recursion
---------

If a project that is part of a build contains a `.make.yml` itself, Drush make will
automatically parse it and recurse into a derivative build.

For example, a full build tree may look something like this:

    Drush make distro.make distro

    distro.make FOUND
    - Drupal core
    - Foo bar install profile
      + foobar.make.yml FOUND
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
embedded within the managingnews profile must be called "managingnews.make". If
no makefile matching the project's name is found, Drush Make will look for a
"drupal-org.make.yml" makefile instead. The file must be in the project's root
directory. Subdirectories will be ignored.

**Build a full Drupal site with the Managing News install profile:**

    core: 6.x
    api: 2
    projects:
      - drupal
      - managingnews

** Use a distribution as core **

    core: 7.x
    api: 2
    projects:
      commerce_kickstart:
        type: "core"
        version: "7.x-1.19"

This behavior can be overridden globally using the `--no-recursion` option, or on a project-by-project basis by setting the `do_recursion` project parameter to 'false' in a makefile:

    core: 7.x
    api: 2
    projects:
      drupal:
        type: core
      hostmaster:
        type: profile
        do_recursion: false


Testing
-------
Drush make also comes with testing capabilities, designed to test Drush make
itself. Writing a new test is extremely simple. The process is as follows:

1. Figure out what you want to test. Write a makefile that will test
   this out.  You can refer to existing test makefiles for
   examples. These are located in `DRUSH/tests/makefiles`.
2. Drush make your makefile, and use the --md5 option. You may also use other
   options, but be sure to take note of which ones for step 4.
3. Verify that the result you got was in fact what you expected. If so,
   continue. If not, tweak it and re-run step 2 until it's what you expected.
4. Using the md5 hash that was spit out from step 2, make a new entry in the
   tests clase (DRUSH/tests/makeTest.php), following the example below.
    'machine-readable-name' => array(
      'name'     => 'Human readable name',
      'makefile' => 'tests/yourtest.make',
      'messages' => array(
          'Build hash: f68e6510-your-hash-e04fbb4ed',
      ),
      'options'  => array('any' => TRUE, 'other' => TRUE, 'options' => TRUE),
    ),
5. Test! Run Drush test suite (see DRUSH/tests/README.md). To just
   run the make tests:

     `./unish.sh --filter=makeMake .`


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

Maintainers
-----------
- Jonathan Hedstrom ([jhedstrom](https://www.drupal.org/u/jhedstrom))
- Christopher Gervais ([ergonlogic](http://drupal.org/u/ergonlogic))
- [The rest of the Drush maintainers](https://github.com/drush-ops/drush/graphs/contributors)

Original Author
---------------
[Dmitri Gaskin (dmitrig01)](https://twitter.com/dmitrig01)
