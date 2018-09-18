<?php
class PluginGenerator {
    private $plugin_name;
    private $class_plugin;
    private $class_controller;
    private $class_model;

    private $class_admin_manage_controller;

    private $config;
    private $zip_save_dir;

    /**
     * Class Constructor
     * 
     * @param   string  $plugin_name    Plugin name
     */
    public function __construct($plugin_name, $config = [], $out_dir = ".") {
        $this->plugin_name = $plugin_name;

        $this->plugin_name_camel_case = $this->toCamelCase($this->formatName($plugin_name));
        $this->plugin_name_underscored = $this->toUnderScoreCase($this->plugin_name_camel_case);

        $this->config = $config;
        $this->zip_save_dir = $out_dir;

        $this->class_plugin = new Nette\PhpGenerator\ClassType($this->plugin_name_camel_case . "Plugin");
        $this->class_plugin->setExtends("Plugin");

        $this->class_controller = new Nette\PhpGenerator\ClassType($this->plugin_name_camel_case . "Controller");
        $this->class_controller->setExtends("AppController");

        $this->class_admin_manage_controller = new Nette\PhpGenerator\ClassType("AdminManagePlugin");
        $this->class_admin_manage_controller->setExtends("AppController"); 
    }

    private function formatName($name) {
        $new_name = "";
        $name[0] = strtoupper($name[0]);
        $characters = str_split($name);

        $next_to_upper = FALSE;
        foreach($characters as $c) {
            if($c == " ") {
                $next_to_upper = TRUE;
            }
            else {
                if($next_to_upper == TRUE) {
                    $new_name .= strtoupper($c);
                    $next_to_upper = FALSE;
                }
                else {
                    $new_name .= $c;
                }
            }
        }
        return $new_name;
    }

    /**
     * Translates a string with underscores into camel case (e.g. first_name -&gt; firstName)
     * @param    string   $str                     String in underscore format
     * @return   string                              $str translated into camel caps
     */
    private function toCamelCase($str) {
        $str = preg_replace("/[^ \w]+/", "", $str);

        //capitalize first char


        $func = create_function('$c', 'return strtoupper($c[1]);');
        return preg_replace_callback('/_([a-z])/', $func, $str);
    }

    private function toUnderScoreCase($str) {
        $characters = str_split($str);
        $first_char = TRUE;
        $underscore_name = "";

        foreach($characters as $character) {
            if($character == strtoupper($character)) {
                //character is in uppercase
                if(!$first_char) {
                    $underscore_name .= "_" . strtolower($character);
                }
                else {
                    $underscore_name .= strtolower($character);
                    $first_char = FALSE;
                }
            }
            else {
                $underscore_name .= $character;
            }
        }
        return $underscore_name;
    }

    private function generateConfig() {
        $config_json = array(
            'version' => "1.0.0",
            'name' => $this->plugin_name,
            'description' => '',
            'authors' => [],
        );

        foreach($config_json as $key => $value) {
            if($key == 'authors' && isset($this->config['authors'])) {
                foreach($this->config["authors"] as $config_author) {
                    $config_entry = array(
                        "name" => "",
                        "url" => ""
                    );
    
                    if(isset($config_author['name'])) {
                        $config_entry["name"] = $config_author['name'];
    
                        if(isset($config_author["url"])) {
                            $config_entry["url"] = $config_author['url'];
                        }
                        $config_json["authors"][] = $config_entry;
                    }
                }
            }
            else {
                if(isset($this->config[$key])) {
                    $config_json[$key] = $this->config[$key];
                }
            }
        }
        return json_encode($config_json);
    }

    private function generatePluginConstructor() {
        $method = $this->class_plugin->addMethod("__construct")
            ->setVisibility("public")
            ->addComment("Class Constructor");

        $lines = array(
            'Language::loadLang("'. $this->plugin_name_underscored .'", null, dirname(__FILE__) . DS . "language" . DS);',
            '$this->loadConfig(dirname(__FILE__) . DS . "config.json");'
        );

        foreach($lines as $l) {
            $method->addBody($l);
        }
    }

    private function generatePluginInstall() {
        $method = $this->class_plugin->addMethod("install")
            ->setVisibility("public")
            ->addComment("Performs the installation of the plugin")
            ->addComment('@param    int $plugin_id  The ID of the plugin being installed');

        $method->addParameter("plugin_id");
    }

    private function generatePluginUnInstall() {
        $method = $this->class_plugin->addMethod("uninstall")
            ->setVisibility("public")
            ->addComment('Performs the uninstallation of the plugin')
            ->addComment('@param    int     $plugin_id  The ID of the plugin being uninstalled')
            ->addComment('@param    bool    $last_instance  True if $plugin_id is the last instance across all companies for this plugin, false otherwise');

        $method->addParameter("plugin_id");
        $method->addParameter("last_instance");
    }

    private function generatePluginUpgrade() {
        $method = $this->class_plugin->addMethod("upgrade")
            ->setVisibility("public")
            ->addComment('Performs migration of data from $current_version (the current installed version) to the given file set version')
            ->addComment('@param    string  $current_version    The current installed version of this plugin')
            ->addComment('@param    int     $plugin_id          The ID of plugin being upgraded');

        $method->addParameter("current_version");
        $method->addParameter("plugin_id");

        $lines = array(
            'if (version_compare($this->version, $current_version) < 0) {',
            "\t" .'$this->Input->setErrors(array(',  
            "\t\t" .'"version" => array(',
            "\t\t\t" . '"invalid" => "Downgrades are not allowed."',
            "\t\t" . ')',
            "\t" . '));',
            "\t" . 'return;',
            '}'
        );

        foreach($lines as $l) {
            $method->addBody($l);
        }
    }

    private function generatePlugin() {
        $this->generatePluginConstructor();
        $this->generatePluginInstall();
        $this->generatePluginUnInstall();
        $this->generatePluginUpgrade();
    }


    public function getOutputPlugin() {
        $this->generatePlugin();
        return $this->class_plugin;
    }

    private function generateControllerPreAction() {
        $method = $this->class_controller->addMethod("preAction")
            ->setVisibility("public");
        
        $lines = array(
            'parent::preAction();',
            '// Override default view directory',
            '$this->view->view = "default";',
            '$this->structure->view = "default";',
        );

        foreach($lines as $l) {
            $method->addBody($l);
        }
    }

    private function generateController() {
        $this->generateControllerPreAction();
    }


    public function getOutputController() {
        $this->generateController();
        return $this->class_controller;
    }

    private function generateAdminManagePluginIndex() {
        $method = $this->class_admin_manage_controller->addMethod("index")
            ->setVisibility("public");

        $lines = array(
            '$var1 = "hello";',
            '$var2 = "world";',
            'return $this->partial("admin_manage_plugin", compact("var1", "var2"));'
        );

        foreach($lines as $l) {
            $method->addBody($l);
        }
    }

    private function generateAdminManagePlugin() {
        $this->generateAdminManagePluginIndex();
    }

    public function getOutputAdminManagePlugin() {
        $this->generateAdminManagePlugin();
        return $this->class_admin_manage_controller;
    }

    private function generateModel() {

    }

    public function getOutputModel() {
        $this->generateModel();
        return $this->class_model;
    }

    public function genereateZipFileName() {
        return "$this->plugin_name_underscored.zip";
    }

    public function generateZip() {
        $languages = ["en_us", "de_de"];

        $zip = new ZipArchive;
        $zip_file_name = $this->genereateZipFileName();
        if($zip->open(realpath($this->zip_save_dir) . DIRECTORY_SEPARATOR  . $zip_file_name, ZipArchive::CREATE) === TRUE) {
            $zip->addEmptyDir($this->plugin_name_underscored);
            $zip->addEmptyDir("$this->plugin_name_underscored/views/default");
            $zip->addEmptyDir("$this->plugin_name_underscored/language");
           
            foreach($languages as $l) {
                $zip->addEmptyDir("$this->plugin_name_underscored/language/$l");
                $zip->addFromString("$this->plugin_name_underscored/language/$l/$this->plugin_name_underscored.php", "<?php\n");
            }

            $zip->addEmptyDir("$this->plugin_name_underscored/controllers");
            $zip->addEmptyDir("$this->plugin_name_underscored/models");

            $zip->addFromString($this->plugin_name_underscored . "/" . $this->plugin_name_underscored . "_plugin.php", "<?php\n" . $this->getOutputPlugin());
            $zip->addFromString($this->plugin_name_underscored . "/" . $this->plugin_name_underscored . "_controller.php", "<?php\n" . $this->getOutputController());
            $zip->addFromString($this->plugin_name_underscored . "/" . $this->plugin_name_underscored . "_model.php", "<?php\n" . $this->getOutputModel());
            $zip->addFromString($this->plugin_name_underscored . "/" . "controllers/admin_manage_plugin.php", "<?php\n" . $this->getOutputAdminManagePlugin());
            $zip->addFromString($this->plugin_name_underscored . "/" . "config.json", $this->generateConfig());

            $zip->addFromString($this->plugin_name_underscored . "/" . "views/default/admin_manage_plugin.pdt", "this is the settings view");
            $zip->close();
        }
    }

}