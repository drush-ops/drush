!! README.md is a work in progress !!

# Dependencies

- d3.js: Webprofiler module requires D3 library 3.x (not 4.x) to render data.

- highlight.js: Webprofiler module requires highlight 9.7.x library to syntax highlight collected queries.

## Install using Composer (recommended)

If you use Composer to manage dependencies, edit `/composer.json` as follows.

1\. Run `composer require --prefer-dist composer/installers` to ensure that you have the `composer/installers` package installed. This package facilitates the installation of packages into directories other than `/vendor` (e.g. `/libraries`) using Composer.

2\. Add the following to the "installer-paths" section of `composer.json`:

```
"libraries/{$name}": ["type:drupal-library"],
```

3\. Add the following to the "repositories" section of `composer.json`:

```
"d3": {
    "type": "package",
    "package": {
        "name": "d3/d3",
        "version": "v3.5.17",
        "type": "drupal-library",
        "dist": {
            "url": "https://github.com/d3/d3/archive/v3.5.17.zip",
            "type": "zip"
        }
    }
},
"highlightjs": {
    "type": "package",
    "package": {
        "name": "components/highlightjs",
        "version": "9.7.0",
        "type": "drupal-library",
        "dist": {
            "url": "https://github.com/components/highlightjs/archive/9.7.0.zip",
            "type": "zip"
        }
    }
}
```
4\. Run `composer require --prefer-dist d3/d3:3.5.* components/highlightjs:9.7.*` - you should find that new directories have been created
under `/libraries`

## Install manually

- d3.js:

  - Create a `/libraries/d3/` directory below your Drupal root directory
  - Download https://d3js.org/d3.v3.min.js
  - Rename it to `/libraries/d3/d3.min.js`

  For further details on how to obtain D3.js, see https://github.com/d3/d3/

- highlight.js:

  - Create `/libraries/highlightjs/` directory below your Drupal root directory
  - Download the library and CSS from http://highlightjs.org into it

# IDE link

Each class name discovered while profiling (controller class, event class) is specially linked to open the class in
an IDE. You can configure the URLs for these links to work for your IDE.

## Sublime text (2 and 3) - macOS
See https://github.com/dhoulb/subl

## Textmate
Use txmt://open?url=file://@file&line=@line

## PhpStorm 8+
Use phpstorm://open?file=@file&line=@line

# Timeline

It is also possible to collect the time needed to instantiate every single service used in a request.

Add the following two lines to `settings.php` (or, even better, to `settings.local.php`):

```
$class_loader->addPsr4('Drupal\\webprofiler\\', [ __DIR__ . '/../../modules/contrib/devel/webprofiler/src']);
$settings['container_base_class'] = '\Drupal\webprofiler\DependencyInjection\TraceableContainer';
```

Check if the path from the Webprofiler module in your `settings.php` file matches the location of the installed Webprofiler module in your project.
