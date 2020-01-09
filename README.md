# Composer Linker Plugin

This plugin allows developers to define where they would like to install a 
package, or if required where to install only some of the packages files.

Using this plugin does not change the actual installation path of the package.
Instead it links the package files to the requested locations via symlink by
default (copying files where the operating system does not support symlinks). 
Copying of files can be enabled by default using plugin options if required.

### The Backstory

This plugin was born from the need to use front end asset packages in a Drupal 
project. More specifically using the [slick carousel module](https://www.drupal.org/project/slick) 
on a Drupal site. 

For the slick library to work with this module a developer
must:
- Change the library package directory name from it's default
- Install the library package at a non standard location

Also reading through the Drupal modules documentation, it required only two of
the library files, in this scenario linking the entire package seemed overkill.

Thus, the package was born, capable of
- Renaming a composer package parent directory name
- Defining a non standard installation path for a composer package
- Defining only the needed files to link to this new install path.

## Installing

`composer require jparkinson1991/composer-linker-plugin`

## Configuration

Configuration for this plugin should be placed in the `extra` section of the projects
`composer.json`


### Configuration Structure

Within the `extra` object, all plugin configuration must be stored within a 
`linker-plugin` object.

The configuration consists of two parts:
- `links` (required)
    - Contains all package mappings/links
- `options` (optional)
    - Set plugin wide options
    - These can be overriden per package
    - [Available options](#options)

```
...
{
    "extra": {
        "linker-plugin": {
            "links": {},
            "options": {
                "copy": false
            }
        }
    }
}
...
```

### Simple Links

In it's simplest form, simply provide a package name and the directory in which you want it to be installed to.

All custom install paths will be resolved from path of the composer.json file.

```
...
{
    "extra": {
        "linker-plugin": {
            "links": {
                "vendor/package-name": "custom/install/path/package-name",
                "another/package: "new-location/new-name"   
            }
        }
    }
}
...
```

### Complex Links

Use complex links to define per package:
- Specific files to be mapped only
- Override plugin wide options 

The complex link options structure is as follows
- `dir` (required) 
    - The name of the custom install directory
    - This path is resolved from the composer.json file.
- `files` (optional)
    - The paths to the files within the package to link
    - Only these paths will be linked
    - Paths resolved from the package root
    - If a file is not found it will be skipped
- `options` (optional)
    - Override the plugin options for this specific package
    - [Available options](#options)

```
...
{
    "extra": {
        "linker-plugin": {
            "links": {
                "vendor/theme-package: {
                    "dir": "themes/theme-name",
                    "files": [
                        "./theme/style.css",
                        "theme/scripts.min.js"
                    ],
                    "options": {
                        "copy": true
                    }
                }
            }
        }
    }
}
...
```

### Options

The following plugin/package options are available, all are optional
- `copy` (boolean)
    - Should linking be done via copy rather than symlink?
    - Note: Files are copied for systems that dont support symlinks regardless of this value.
    - Default: `false`
    
The default implied configuration object 

```
...
{
    "extra": {
        "linker-plugin": {
            "options": {
                "copy": false
            }
        }
    }
}
...
```

## Versioning

[SemVer](http://semver.org/) for versioning. For the versions available,
see the [tags on this repository](https://github.com/JParkinson1991/common-frontend/tags).

## License

This project is licensed under the GNU GPLv3 License - see the [LICENSE](LICENSE)
file for details
