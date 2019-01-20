<?php
require "generate-module.php";
require "generate-plugin.php";
require "generate-nmgateway.php";
require "vendor/autoload.php";
require "file-generators/language-generator.php";
require "file-generators/module-template-generator.php";
require "file-generators/plugin-template-generator.php";


$args = getopt("n:t:o::", array("name:", "type:", "out::"));

$name = null;
if(isset($args["n"]) || isset($args["name"])) {
    $name = isset($args["n"]) ? $args["n"] : $args['name'];
}
$selected_type = null;
$allowed_types = array("module", "plugin");
$csv_type = null;
if(isset($args["t"]) || isset($args["type"])) {
    $selected_type = isset($args["t"]) ? $args["t"] : $args['type'];
    if(!in_array($selected_type, $allowed_types)) {
        echo "Type is not allowed. Please choose one of the allowed types (" . implode(",",$allowed_types) . ")\n";
    }
}

if($name != null && $selected_type != null) {
    $g = null;
    $out_path = ".";
    if(isset($args["o"]) || isset($args['out'])) {
        $out_path = isset($args["o"]) ? $args["o"] : $args['out'];

        if($out_path === FALSE) {
            echo "ERROR(FATAL): Outpath is not a valid value (set with --out={DIR})\n";
            exit();
        }
        else {
            $out_path = realpath($out_path);
        }
    }
    else {
        $out_path = realpath(".");
    }

    if($selected_type == "module") {
        $g = new ModuleGenerator($name, [], $out_path);
    }
    elseif($selected_type == "plugin") {
        $g = new PluginGenerator($name, [], $out_path);
    }
    else if($selected_type == "nm-gateway") {
        $g = new NonMerchantGatewayGenerator($name, [], $out_path);
    }
    

    if($g != null) {
        $g->generateZip();
        echo "SUCCESS: Zip File written to " . $out_path . DIRECTORY_SEPARATOR . $g->genereateZipFileName() . "\n";
    }
}
else if($name == null && $selected_type == null) {
    //no parameters given, show banner and description
    $banner = array(' _____                              ',
                    '|_   _|                             ',
                    '  | |   ___  __ _  _ __  _   _  ___ ',
                    '  | |  / __|/ _` || \'__|| | | |/ __|',
                    ' _| |_| (__| (_| || |   | |_| |\__ \\',
                    ' \___/ \___|\__,_||_|    \__,_||___/',
                    '                                    ');

    foreach($banner as $line) {
        echo $line . "\n";
    }
                                        
    $desc = array(
        'Welcome to ICARUS, the blesta component generator',
        'There are currently 2 (two) types of components that can be generated: plugins and modules',
        '',
        'Parameters (REQUIRED):',
        "\t" . '-n/--name: name of the component',
        "\t" . '-t/--type: type of the component (currently supported are plugin and module)',
        '',
        'Parameters (OPTIONAL):',
        "\t" . '-o/--out: directory to save the created zip file to (specify like this --out="/home/")'
    );

    foreach($desc as $line) {
        echo $line . "\n";
    }
}
else {
    if($name == null) {
        echo "ERROR(FATAL): Please specify the name of the compontent with --name/-n\n";
    }

    if($selected_type == null) {
        echo "ERROR(FATAL): Please specify the type of the component with --type/-t (currently supported are plugin and module)\n";
    }
}

