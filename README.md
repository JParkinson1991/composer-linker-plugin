# Composer Linker Plugin

This plugin provides package alteration, with it the following can be achieved

- Set a per package custom installation folder (copy/symlink)
- Map specific files from a package into a new folder (copy/symlink)
   - Rename these files as required.

Using this plugin does not alter the default installation paths of packages. It
simply symlinks or copies the files to a configured location after it has been 
installed or updated.

This plugin can be used for (and was born the need to) trim down front end asset
packages before placing them into the public web root, as an example.

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
    - Use an object to define source and destination
        - Key => source
        - Value => destination
        - _IMPORTANT: One key value pair per object._
    - If a source file is not found it will be skipped
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
                        "theme/scripts.min.js",
                        {
                            "theme/source/component.js": "dest/component.js"
                        },
                        {
                            "theme/source/component.js": "same-source/multiple-destinations.js"
                        },
                        {
                            "theme/source/component-2.js": "dest/another-component.js"
                        }
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
