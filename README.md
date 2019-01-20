# Icarus #

Icarus is a command line tool for generating component skeletons for the billing system blesta.

I wrote this tool because i was tired of always consulting the blesta documentation and copy and pasting the code from other modules or plugins for the start of developing my own component.

## Requirements ##

* PHP 7.1
* php-zip
* composer

## Installation ##

After cloning this repository check if composer is installed. If it is not please install it beforehand.

When composer is installed, run `composer install` in the directory this repository was cloned to.

## Features ##

* Generating Modules (this is finished the most)
* Generating Plugins (Still in development)

## Usage ##

For generating a compoent there are 2 Parameters (-n and -t) that are required.

#### -n/--name ####

The name of the compoenent to generate. This name will be used for the title of the component, for naming the files. generating the classnamens and setting the component prefix in the language files.

#### -t/--type ####

Type of the component to generate. Available types are:

* module
* plugin

#### -o/--out (Optional) ####

This parameter sets the destination directory to save the generated zip-file to.

## Example ##

```bash
php icarus.php -n "my test" -t module
```

This will generate a zip-file containing all the files for starting a module in the same directory the icarus.php file is. For setting the destination path of the file use the parameter -o. 

```bash
php icarus.php -n "my test" -t module -o=/home/myuser/
```

This will generate a zip-file containing all the files for starting a module in the directory /home/myuser/.



