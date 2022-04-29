
# Composer Linker Plugin

![Packagist Version](https://img.shields.io/packagist/v/jparkinson1991/composer-linker-plugin?label=version) ![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/jparkinson1991/composer-linker-plugin) [![Build & Test](https://github.com/JParkinson1991/composer-linker-plugin/actions/workflows/test.yml/badge.svg)](https://github.com/JParkinson1991/composer-linker-plugin/actions/workflows/test.yml) [![Coverage Status](https://coveralls.io/repos/github/JParkinson1991/composer-linker-plugin/badge.svg)](https://coveralls.io/github/JParkinson1991/composer-linker-plugin) ![Packagist License](https://img.shields.io/packagist/l/jparkinson1991/composer-linker-plugin)

This plugin enables the movement of package files outside of the standard composer vendor directory via symlink or copying.

This plugin enables the following:

 - Linking of entire package directory to a custom installation folder
 - Linking of specific files from within a package to a custom install folder
	 - Linked files can be renamed as required

Packages (or their specific files) will be linked as per configuration when it is either installed or updated and unlinked when uninstalled.

Any linked package will still exist within the composer vendor directory as well as any configured custom installation path.

## Getting Started

### Requirements

 - A composer based PHP project.
 - Composer `^2`
 - PHP `^7.3 || ^8.0`

#### Composer 2.3.0 deprecations

This plugin knowingly consumes deprecated functionality within composer core to ensure its compatibility with all
current versions of composer 2.x. Update to the latest version of composer to ensure that when this deprecated code
is removed from the plugin your installation will not be effected.

### Installing

```
$ composer require jparkinson1991/composer-linker-plugin
```

## Usage

### Usage as a Plugin

This plugin will invoke automatically after a package is _installed_, _updated_ or _uninstalled_.
To have that package handled by this plugin simply provide [configuration](#configuration) for it. Packages will
be `linked` as per configuration on _install_ and _update_. Packages will be `unlinked` as per configuration on _uninstall_.

All defined [configuration](#configuration) will be processed when this plugin is _installed_ and _uninstalled_. On
_install_ all configuration will be `linked`. On _uninstall_ all configuration will be `unlinked`.

### Usage via Commands

Commands can be used manually trigger a `link`/`unlink`. Commands will only be able to process that which exists in [configuration](#configuration).

#### Link Command

Link all packages as defined in configuration.

```
$ composer composer-linker-plugin:link
$ composer clp-link
```

Link a single package from configuration. _Package must be installed and configuration must exist for it._

```
$ composer composer-linker-plugin:link package/name
$ composer clp-link package/name
```

Link a multiple packages from configuration. _Packages must be installed and configuration must exist for them._

```
$ composer composer-linker-plugin:link package/name second/package third/package ...
$ composer clp-link package/name second/package third/package ...
```

#### Unlink Command

Unlink all packages as defined in configuration.

```
$ composer composer-linker-plugin:unlink
$ composer clp-unlink
```

Unlink a single package from configuration. _Package must be installed and configuration must exist for it._

```
$ composer composer-linker-plugin:unlink package/name
$ composer clp-unlink package/name
```

Unlink a multiple packages from configuration. _Packages must be installed and configuration must exist for them._

```
$ composer composer-linker-plugin:unlink package/name second/package third/package ...
$ composer clp-unlink package/name second/package third/package ...
```

## Configuration

All plugin configuration exists within the `extra` section of the project's `composer.json` file under the `linker-plugin` key.

 - [Simple Links](#simple-links)
 - [Complex Links](#complex-links)
	 - [Defining file mappings](#defining-file-mappings)
		 - [Defining file mappings as an array](#defining-file-mappings-as-an-array)
		 - [Defining file mappings as an object](#defining-file-mappings-as-an-object)
	 - [Defining link level options](#defining-link-level-options)
 - [Options](#options)
	 - [Copying files](#copying-files)
	 - [Deleting orphan directories](#deleting-orphan-directories)
	 - [Default Implied Options](#default-implied-options)

### Simple Links

In it's simplest form, this plugin can link a package directory to a given installation path.

```
{
    "extra": {
        "linker-plugin": {
            "links": {
                "vendor/package": "custom/install/dir"
            }
        }
    }
}
```
*Relative custom installation directories will be resolved from the project root. I.e. The directory containing the composer.json file. Absolute paths will be treat as is.*

### Complex Links

For more granular control of links, complex link objects can be defined using an object with the structure below.

```
{
    "extra": {
        "linker-plugin": {
            "links": {
                "vendor/package": {
                    "dir": "custom/install/dir",
                    "files": ...,
                    "options": ...
                }
            }
        }
    }
}
```
When defining complex link objects the `dir` element is required. Both the `files` and `options` elements are optional. Empty `files` or `options` objects will be treat as an error. Do not include them if they are not needed.

#### Defining file mappings

A selection of files from within the package directory can be handpicked to be linked only. If the `files` element of a complex link exists, **only those files will be linked**.

When definining file mappings:

 - `Source files` are always treat as relative to the package's install directory.
 - `File destinations`
	 - If *relative*, are resolved from the link's custom installation directory.
	 - If *absolute*, are treat as is.

**Important:** The same source file may be mapped to multiple destinations however each destination must be unique within the link definition.

File mappings can be defined as both objects and arrays.

##### Defining file mappings as an array

```
{
    "extra": {
        "linker-plugin": {
            "links": {
                "vendor/package": {
                    "dir": "custom/install/dir",
                    "files": [
                        "PackageFile1.php",
                        {
                            "PackageFile2.php": "includes/PackageFile2.php",
                            "PackageFile3.php": {
                                "PackageFile3-Dest1.php",
                                "PackageFile3-Dest2.php"
                            }
                        }
                    ]
                }
            }
        }
    }
}
```

When using an array to define file mappings:

 - *Flat strings* (`PackageFile1.php`)
	 - Will be treat as having the source and destination
 - *Key Value pairs* (`PackageFile2.php`)
	 - Key will be treat as the source file
	 - Value will be treat as the destination
 - *Key to Multi Value objects* (`PackageFile3.php`)
	 - Key will be treat as the source file
	 - Each value of the object will be treat as a separate destination for that source file.

##### Defining file mappings as an object

```
{
    "extra": {
        "linker-plugin": {
            "links": {
                "vendor/package": {
                    "dir": "custom/install/dir",
                    "files": {
                        "PackageFile1.php": "includes/PackageFile1.php",
                        "PackageFile2.php": {
                            "PackageFile2-Dest1.php",
                            "PackageFile2-Dest2.php"
                        }
                    }
                }
            }
        }
    }
}
```
When defining file mappings as an object, all mappings **must** define a `source file`.

 - *Key Value pairs* (`PackageFile1.php`)
	 - Key will be treat as the source file
	 - Value will be treat as the destination
 - *Key to Multi Value objects* (`PackageFile2.php`)
	 - Key will be treat as the source file
	 - Each value of the object will be treat as a separate destination for the source file.

#### Defining link level options

To define link level options, include the `options` object within a complex link configuration.


```
{
    "extra": {
        "linker-plugin": {
            "links": {
                "vendor/package": {
                    "dir": "custom/install/dir",
                    "options": {}
                }
            }
        }
    }
}
```

Any of the [plugin options](#options) can be placed within a complex link configuration's `options` object.

Link level options will override any global options set for the plugin.

### Options

Plugin level options can be defined in the `options` object.

The `options` object is optional and if not included the [default implied options](#default-implied-options) will be used.


```
{
    "extra": {
        "linker-plugin": {
            "links": {},
            "options": {}
        }
    }
}
```

Any defined plugin option will be applied to all link configurations unless [overridden](#defining-link-level-options).

#### Copying files

To enable the copying of files when linking, define the `copy` option with a `boolean` value.

```
{
    "extra": {
        "linker-plugin": {
            "links": {},
            "options": {
                "copy": true
            }
        }
    }
}
```

#### Deleting orphan directories

If linking package's or their files to nested directories within a project, it may be useful to have the plugin delete the orphan directories of the link when it's associated package is uninstalled.

Directories are only treat as orphans if they are empty.

Orphan directories **will not** be cleaned outside of the composer project's root directory.

As an example

 - Project root: `/var/www/composer-project`
 - Linked directory: `/var/www/composer-project/nested/link/directory`
 - Orphan directories after uninstall
	 - `/var/www/composer-project/nested/link`
	 - `/var/www/composer-project/nested`

To enable deletion of a link configurations's orphan directories after it's associated package is uninstalled, define the `delete-orphans` option with a `boolean` value.

```
{
    "extra": {
        "linker-plugin": {
            "links": {},
            "options": {
                "delete-orphans": true
            }
        }
    }
}
```

#### Default Implied Options

The default implied options are as follows

```
{
    "extra": {
        "linker-plugin": {
            "links": {},
            "options": {
                "copy": false
                "delete-orphans": false
            }
        }
    }
}
```

## Versioning

[SemVer](http://semver.org/) for versioning. For the versions available,  see the [tags on this repository](https://github.com/JParkinson1991/composer-linker-plugin/tags).

## License

This project is licensed under the *GNU GPLv3 License* - see the [LICENSE](LICENSE)  file for details
