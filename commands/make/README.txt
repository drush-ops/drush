### About:

Drush_make is an extension to drush that can create a ready-to-use drupal site,
pulling sources from various locations. It does this by parsing a flat text
file (similar to a drupal .info file) and downloading the sources it describes.
In practical terms, this means that it is possible to distribute a complicated
Drupal distribution (such as Development Seed's Managing News) as a single text
file.

Among drush_make's capabilities are:
* Downloading Drupal core, as well as contrib modules from drupal.org.
* Checking code out from CVS, SVN, git, bzr, and hg repositories.
* Getting plain .tar.gz and .zip files (particularly useful for libraries
  that can not be distributed directly with drupal core or modules).
* Fetching and applying patches.
* Fetching modules, themes, and installation profiles, but also external
  libraries.

### Usage

drush make {filename.make}

### .make file format

The make file always begins by specifying the core version of Drupal, such as
the following:
====
core = 6.x
====

Then, it goes on to specify a list of projects.
For example, this code would download the latest release of Drupal.
====
core = 6.x
projects[] = drupal
====

More projects may be downloaded in that fashion. This code downloads Drupal
core along with the contributed modules CCK and Views.
====
core = 6.x
projects[] = drupal
projects[] = cck
projects[] = views
====

You can also specify specific versions of each module:
====
projects[cck] = 2.6
projects[views] = 2.7
====

NOTE: The first and second form of specifying projects are mutually exclusive.
If you use the second (including the variations described below) form with the
project name in []'s, DO NOT specify the same project as projects[] = <project>

You can also specify more instructions about what to do with the module. For
example, use the following syntax to apply a patch to a module:
====
projects[adminrole][patch][] = "http://drupal.org/files/issues/adminrole_exceptions.patch"
====

To place a module in a subdirectory of sites/all/modules, the following code
can be used:
====
projects[cck][subdir] = "contrib"
====

This code puts CCK in a subdirectory and specifies a specific version:
====
projects[cck][subdir] = "contrib"
projects[cck][version] = 2.6
====

Drush_make can also download projects from other servers besides drupal.org, so
long as they output the proper XML format. For example:
====
projects[tao][location] = "http://code.developmentseed.com/fserver"
====

While drush_make can automatically detect the type of project (i.e. module,
theme, installation profile) when downloading from drupal.org or another
update XML server, it must be told what type of project it is downloading
if the project is hosted somewhere else.

To download a custom theme from an arbitrary location:
====
projects[mytheme][type] = "theme"
projects[mytheme][download][type] = "svn"
projects[mytheme][download][url] = "http://example.com/svnrepo/cool-theme/"
projects[mytheme][download][branch] = "production"
====

To export from Drupal CVS
====
projects[cck][download][type] = "cvs"
projects[cck][download][module] = "contributions/modules/cck"
projects[cck][download][revision] = "DRUPAL-6--1"
====

To download an external library (this will be placed in sites/all/libraries by default):
====
libraries[tinymce][download][type] = "get"
libraries[tinymce][download][url] = "http://downloads.sourceforge.net/project/tinymce/TinyMCE/3.2.7/tinymce_3_2_7.zip"
libraries[tinymce][directory_name] = "tinymce"
====

To download an external library and place it in an alternate location (this
code downloads jQuery UI and places it in modules/jquery_ui/jquery.ui):
====
libraries[jquery_ui][download][type] = "get"
libraries[jquery_ui][download][url] = "http://jquery-ui.googlecode.com/files/jquery.ui-1.6.zip"
libraries[jquery_ui][directory_name] = jquery.ui
libraries[jquery_ui][destination] = modules/jquery_ui
====

### Recursion

If a profile downloaded by drush_make includes a .make file, drush_make will automatically
parse it. This means that it is possible to create a makefile that is simply a pointer to
the repository for your install profile.
====
core = 6.x
projects[] = drupal
projects[] = managingnews
====
