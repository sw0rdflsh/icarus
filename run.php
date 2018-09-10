<?php
require "generate-module.php";
require "generate-plugin.php";
require "generate-nmgateway.php";
require "file-generators/module-template-generator.php";
require "file-generators/language-generator.php";
require "vendor/autoload.php";


$g = new NonMerchantGatewayGenerator("test Gateway", array(
    "version" => "1.5.0",
    "description" => "test",
    "authors" => array(
        array(
            'name' => "Alexander Sommer",
            'url' => "https://dock26.de"
        )
    )
));

$g->generateZip();
