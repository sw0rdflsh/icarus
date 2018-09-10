<?php
class ModuleGenerator {

    private $class;

    private $module_name;

    private $config;

    private $zip_save_dir;

    public function __construct($module_name, $config = [], $out_dir = ".") {
        $this->module_name = $module_name;

        $this->module_name_camel_case = $this->toCamelCase($this->formatName($module_name));
        $this->module_name_underscored = $this->toUnderScoreCase($this->module_name_camel_case);

        $this->config = $config;
        $this->zip_save_dir = $out_dir;

        $this->class = new Nette\PhpGenerator\ClassType($this->module_name_camel_case);
        $this->class->setExtends("Module");
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
     * Translates a string with underscores into camel case
     * @param   string     $str String in underscore format
     * @return  string     $str translated into camel caps
     */
    function toCamelCase($str) {
        $str = preg_replace("/[^ \w]+/", "", $str);
       
        //capitalize first char
        $str[0] = strtoupper($str[0]);
        
        $func = create_function('$c', 'return strtoupper($c[1]);');
        return preg_replace_callback('/_([a-z])/', $func, $str);
    }

    function toUnderScoreCase($str) {
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
            'name' => $this->module_name,
            'description' => '',
            'authors' => [],
            'service' => array(
                "name_key" => $this->module_name_underscored . ".service",
            ),
            'module' => array(
                'row' => $this->module_name_underscored . ".row.singular",
                'rows' => $this->module_name_underscored . ".row.plural",
                'group' => $this->module_name_underscored . ".group",
                'group' => $this->module_name_underscored . ".row_key"
            )
        );

        foreach($config_json as $key => $value) {
            if($key == 'service' || $key == 'module') {
                foreach($config_json[$key] as $config_sub_key => $config_sub_value) {
                    if(isset($this->config[$key][$config_sub_key])) {
                        $config_json[$key][$config_sub_key] = $this->config[$key][$config_sub_key];
                    }
                }
            } 
            elseif($key == 'authors' && isset($this->config['authors'])) {
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

    private function generateConstructor() {
        $method = $this->class->addMethod("__construct")
            ->setVisibility("public")
            ->addComment("Class Constructor");

        $lines = array(
            'Language::loadLang("'. $this->module_name_underscored .'", null, dirname(__FILE__) . DS . "language" . DS);',
            '$this->loadConfig(dirname(__FILE__) . DS . "config.json");',
            'Loader::loadComponents($this, array("Input"));',
        );

        foreach($lines as $l) {
            $method->addBody($l);
        }
    }

    private function generateInstall() {
        $method = $this->class->addMethod("install")
            ->setVisibility("public")
            ->addComment("Performs the installation of the module.");
    }

    private function generateUninstall() {
        $method = $this->class->addMethod("uninstall") 
            ->setVisibility("public")
            ->addComment("Performs uninstallation of the module")
            ->addComment('@param    int     $module_id      The Id of the module that is going to be uninstalled')
            ->addComment('@param    bool    $last_instance  If true, $module_id is the last instance of the module installed on the system');

        $method->addParameter("module_id");
        $method->addParameter("last_instance");
    }

    private function generateUpgrade() {
        $method = $this->class->addMethod("upgrade")
            ->setVisibility("public")
            ->addComment('Performs an upgrade from $current_version to the given file version of the module')
            ->addComment('@param    string  $current_version    Currently installed version of the module');

        $method->addParameter("current_version");

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

    private function generatePackageFields() {
        $method = $this->class->addMethod("getPackageFields")
            ->setVisibility("public")
            ->addComment("Returns all fields used when adding/editing a package, including any javascript to execute when the page is rendered with these fields.")
            ->addComment('@param    mixed           $vars   A stdClass object representing a set of post fields')
            ->addComment('@return   ModuleFields    A ModuleFields object, containg the fields to render as well as any additional HTML markup to include');

        $method->addParameter("vars", null);
    }

    private function generateAdminAddFields() {
        $method = $this->class->addMethod("getAdminAddFields")
            ->setVisibility("public")
            ->addComment("Returns all fields to display to an admin attempting to add a service with the module")
            ->addComment('@param    stdClass        $package    A stdClass object representing the selected package')
            ->addComment('@param    mixed           $vars       A stdClass object representing a set of post fields')
            ->addComment('@return   ModuleFields    A ModuleFields object, containg the fields to render as well as any additional HTML markup to include');

        $method->addParameter("vars", null);
    }

    private function generateAdminEditFields() {
        $method = $this->class->addMethod("getAdminEditFields")
            ->setVisibility("public")
            ->addComment("Returns all fields to display to an admin attempting to edit a service with the module")
            ->addComment('@param    stdClass        $package    A stdClass object representing the selected package')
            ->addComment('@param    mixed           $vars       A stdClass object representing a set of post fields')
            ->addComment('@return   ModuleFields    A ModuleFields object, containg the fields to render as well as any additional HTML markup to include');

        $method->addParameter("vars", null);
    }

    private function generateClientAddFields() {
        $method = $this->class->addMethod("getClientAddFields")
            ->setVisibility("public")
            ->addComment("Returns all fields to display to a client attempting to add a service with the module")
            ->addComment('@param    stdClass        $package    A stdClass object representing the selected package')
            ->addComment('@param    mixed           $vars       A stdClass object representing a set of post fields')
            ->addComment('@return   ModuleFields    A ModuleFields object, containg the fields to render as well as any additional HTML markup to include');

        $method->addParameter("vars", null);
    }

    private function generateManageModule() {
        $method = $this->class->addMethod("manageModule")
            ->setVisibility("public")
            ->addComment("Returns the rendered view of the manage module page")
            ->addComment('@param    mixed   $module A stdClass object representing the module and its rows')
            ->addComment('@param    array   $vars   An array of post data submitted to or on the manage module page (used to repopulate fields after an error)')
            ->addComment('@return   string  HTML content containing information to display when viewing the manager module page');

        $method->addParameter("module");
        $method->addParameter("vars")
            ->setReference()
            ->setTypeHint("array");

        $lines = array(
            '// Load the view into this object, so helpers can be automatically added to the view',
            '$this->view = new View("manage", "default");',
            '$this->view->base_uri = $this->base_uri;',
            '$this->view->setDefaultView("components" . DS . "modules" . DS . "' . $this->module_name_underscored . '" . DS);'  . "\n",
            'if (empty($vars)) {',
            "\t" . '$vars = $module_row->meta;',
            '}' . "\n",
            '// Load the helpers required for this view',
            'Loader::loadHelpers($this, array("Form", "Html", "Widget"));',
            '$view->set("module", $module);',
            '$this->view->set("vars", (object)$vars);',
            'return $this->view->fetch();'
        );

        foreach($lines as $l) {
            $method->addBody($l);
        }
    }

    private function generateManageAddRow() {
        $method = $this->class->addMethod("manageAddRow")
            ->setVisibility("public")
            ->addComment('Returns the rendered view of the add module row page')
            ->addComment('@param    array   $vars   An array of post data submitted to or on the add module row page (used to repopulate fields after an error)')
            ->addComment('@return   string  HTML content containing information to display when viewing the add module row page');

        $method->addParameter("vars")
            ->setReference()
            ->setTypeHint("array");

        $lines = array(
            '// Load the view into this object, so helpers can be automatically added to the view',
            '$this->view = new View("add_row", "default");',
            '$this->view->base_uri = $this->base_uri;',
            '$this->view->setDefaultView("components" . DS . "modules" . DS . "' . $this->module_name_underscored . '" . DS);'  . "\n",
            'if (empty($vars)) {',
            "\t" . '$vars = $module_row->meta;',
            '}' . "\n",
            '// Load the helpers required for this view',
            'Loader::loadHelpers($this, array("Form", "Html", "Widget"));',
            '$this->view->set("vars", (object)$vars);',
            'return $this->view->fetch();'
        );

        foreach($lines as $l) {
            $method->addBody($l);
        }
    }

    private function generateManageEditRow() {
        $method = $this->class->addMethod("manageEditRow")
            ->setVisibility("public")
            ->addComment('Returns the rendered view of the edit module row page')
            ->addComment('@param    stdClass    $module_row The stdClass representation of the existing module row')
            ->addComment('@param    array       $vars       An array of post data submitted to or on the edit module row page (used to repopulate fields after an error)')
            ->addComment('@return   string      HTML content containing information to display when viewing the edit module row page');

        $method->addParameter("module_row");
        $method->addParameter("vars")
            ->setReference()
            ->setTypeHint("array");

        $lines = array(
            '// Load the view into this object, so helpers can be automatically added to the view',
            '$this->view = new View("edit_row", "default");',
            '$this->view->base_uri = $this->base_uri;',
            '$this->view->setDefaultView("components" . DS . "modules" . DS . "' . $this->module_name_underscored . '" . DS);'  . "\n",
            'if (empty($vars)) {',
            "\t" . '$vars = $module_row->meta;',
            '}' . "\n",
            '// Load the helpers required for this view',
            'Loader::loadHelpers($this, array("Form", "Html", "Widget"));',
            '$this->view->set("vars", (object)$vars);',
            'return $this->view->fetch();'
        );

        foreach($lines as $l) {
            $method->addBody($l);
        }
    }

    private function generateAddService() {
        $method = $this->class->addMethod("addService")
            ->setVisibility("public")
            ->addComment("Adds the service to the remote server. Sets Input errors on failure, preventing the service from being added.")
            ->addComment('@param    stdClass    $package        A stdClass object representing the selected package')
            ->addComment('@param    array       $vars           An array of user supplied info to satisfy the request')
            ->addComment('@param    stdClass    $parent_package A stdClass object representing the parent service\'s selected package (if the current service is an addon service)')
            ->addComment('@param    stdClass    $parent_service A stdClass object representing the parent service of the service being added (if the current service is an addon service and parent service has already been provisioned)')
            ->addComment('@param    string      $status         The status of the service being added. These include: - active - canceled - pending - suspended')
            ->addComment('@return   array       A numerically indexed array of meta fields to be stored for this service containing: - key The key for this meta field - value The value for this key - encrypted Whether or not this field should be encrypted (default 0, not encrypted)');

        $method->addParameter("package");
        $method->addParameter("vars", null)
            ->setTypeHint("array");
        $method->addParameter("parent_package", null);
        $method->addParameter("parent_service", null);
        $method->addParameter("status", "pending");    
    }

    private function generateEditService() {
        $method = $this->class->addMethod("editService")
            ->setVisibility("public")
            ->addComment("Edits the service on the remote server. Sets Input errors on failure, preventing the service from being edited.")
            ->addComment('@param    stdClass    $package        A stdClass object representing the current package')
            ->addComment('@param    stdClass    $service        A stdClass object representing the current service')
            ->addComment('@param    array       $vars           An array of user supplied info to satisfy the request')
            ->addComment('@param    stdClass    $parent_package A stdClass object representing the parent service\'s selected package (if the current service is an addon service)')
            ->addComment('@param    stdClass    $parent_service A stdClass object representing the parent service of the service being edited (if the current service is an addon service)')
            ->addComment('@return   array       A numerically indexed array of meta fields to be stored for this service containing: - key The key for this meta field - value The value for this key - encrypted Whether or not this field should be encrypted (default 0, not encrypted)');

        $method->addParameter("package");
        $method->addParameter("service");
        $method->addParameter("vars", [])
            ->setTypeHint("array");
        $method->addParameter("parent_package", null);
        $method->addParameter("parent_service", null);
    }

    private function generateCancelService() {
        $method = $this->class->addMethod("cancelService")
            ->setVisibility("public")
            ->addComment("Cancels the service on the remote server. Sets Input errors on failure, preventing the service from being canceled.")
            ->addComment('@param    stdClass    $package        A stdClass object representing the current package')
            ->addComment('@param    stdClass    $service        A stdClass object representing the current service')
            ->addComment('@param    stdClass    $parent_package A stdClass object representing the parent service\'s selected package (if the current service is an addon service)')
            ->addComment('@param    stdClass    $parent_service A stdClass object representing the parent service of the service being canceled (if the current service is an addon service)')
            ->addComment('@return   mixed       null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing: - key The key for this meta field - value The value for this key - encrypted Whether or not this field should be encrypted (default 0, not encrypted)'); 
        
        $method->addParameter("package");
        $method->addParameter("service");
        $method->addParameter("parent_package", null);
        $method->addParameter("parent_service", null);
    }

    private function generateSuspendService() {
        $method = $this->class->addMethod("suspendService")
            ->setVisibility("public")
            ->addComment("Suspends the service on the remote server. Sets Input errors on failure, preventing the service from being suspended.")
            ->addComment('@param    stdClass    $package        A stdClass object representing the current package')
            ->addComment('@param    stdClass    $service        A stdClass object representing the current service')
            ->addComment('@param    stdClass    $parent_package A stdClass object representing the parent service\'s selected package (if the current service is an addon service)')
            ->addComment('@param    stdClass    $parent_service A stdClass object representing the parent service of the service being suspended (if the current service is an addon service)')
            ->addComment('@return   mixed       null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing: - key The key for this meta field - value The value for this key - encrypted Whether or not this field should be encrypted (default 0, not encrypted)'); 
    
        $method->addParameter("package");
        $method->addParameter("service");
        $method->addParameter("parent_package", null);
        $method->addParameter("parent_service", null);
    }

    private function generateUnsuspendService() {
        $method = $this->class->addMethod("unsuspendService")
            ->setVisibility("public")
            ->addComment("Unsuspends the service on the remote server. Sets Input errors on failure, preventing the service from being unsuspended.")
            ->addComment('@param    stdClass    $package        A stdClass object representing the current package')
            ->addComment('@param    stdClass    $service        A stdClass object representing the current service')
            ->addComment('@param    stdClass    $parent_package A stdClass object representing the parent service\'s selected package (if the current service is an addon service)')
            ->addComment('@param    stdClass    $parent_service A stdClass object representing the parent service of the service being unsuspended (if the current service is an addon service)')
            ->addComment('@return   mixed       null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing: - key The key for this meta field - value The value for this key - encrypted Whether or not this field should be encrypted (default 0, not encrypted)'); 

        $method->addParameter("package");
        $method->addParameter("service");
        $method->addParameter("parent_package", null);
        $method->addParameter("parent_service", null);
    }

    private function generateRenewService() {
        $method = $this->class->addMethod("renewService")
            ->setVisibility("public")
            ->addComment("Allows the module to perform an action when the service is ready to renew. Sets Input errors on failure, preventing the service from renewing.")
            ->addComment('@param    stdClass    $package        A stdClass object representing the current package')
            ->addComment('@param    stdClass    $service        A stdClass object representing the current service')
            ->addComment('@param    stdClass    $parent_package A stdClass object representing the parent service\'s selected package (if the current service is an addon service)')
            ->addComment('@param    stdClass    $parent_service A stdClass object representing the parent service of the service being renewed (if the current service is an addon service)')
            ->addComment('@return   mixed       null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing: - key The key for this meta field - value The value for this key - encrypted Whether or not this field should be encrypted (default 0, not encrypted)'); 

        $method->addParameter("package");
        $method->addParameter("service");
        $method->addParameter("parent_package", null);
        $method->addParameter("parent_service", null);
    }

    private function generateAddModuleRow() {
        $method = $this->class->addMethod("addModuleRow")
            ->setVisibility("public")
            ->addComment("Adds the module row on the remote server. Sets Input errors on failure, preventing the row from being added.")
            ->addComment('@param    array   $vars   An array of module info to add')
            ->addComment('@return   array   A numerically indexed array of meta fields for the module row containing: - key The key for this meta field - value The value for this key - encrypted Whether or not this field should be encrypted (default 0, not encrypted)');

        $method->addParameter("vars")
            ->setReference()
            ->setTypeHint("array");
    }

    private function generateEditModuleRow() {
        $method = $this->class->addMethod("editModuleRow")
            ->setVisibility("public")
            ->addComment("Adds the module row on the remote server. Sets Input errors on failure, preventing the row from being added.")
            ->addComment('@param    stdClass    $module_row The stdClass representation of the existing module row')
            ->addComment('@param    array       $vars       An array of module info to update')
            ->addComment('@return   array       A numerically indexed array of meta fields for the module row containing: - key The key for this meta field - value The value for this key - encrypted Whether or not this field should be encrypted (default 0, not encrypted)');

        $method->addParameter("module_row");
        $method->addParameter("vars")
            ->setReference()
            ->setTypeHint("array");
    }

    private function generateDeleteModuleRow() {
        $method = $this->class->addMethod("deleteModuleRow")
            ->setVisibility("public")
            ->addComment("Deletes the module row on the remote server. Sets Input errors on failure, preventing the row from being deleted.")
            ->addComment('@param    stdClass    $module_row The stdClass representation of the existing module row');

        $method->addParameter("module_row");
    }

    private function generateClientServiceInfo() {
        $method = $this->class->addMethod("getClientServiceInfo")
            ->setVisibility("public")
            ->addComment("Fetches the HTML content to display when viewing the service info in the client interface.")
            ->addComment('@param    stdClass    $service    A stdClass object representing the service')
            ->addComment('@param    stdClass    $package    A stdClass object representing the service\'s package')
            ->addComment('@return   string      HTML content containing information to display when viewing the service info');

        $method->addParameter("service");
        $method->addParameter("package");

        $lines = array(
            '$row = $this->getModuleRow($package->module_row);' . "\n",
            '// Load the view into this object, so helpers can be automatically added to the view',
            '$this->view = new View("client_service_info", "default");',
            '$this->view->base_uri = $this->base_uri;',
            '$this->view->setDefaultView("components" . DS . "modules" . DS . "' . $this->module_name_underscored . '" . DS);' . "\n",
            '// Load the helpers required for this view',
            'Loader::loadHelpers($this, array("Form", "Html"));' . "\n",
            '$this->view->set("module_row", $row);',
            '$this->view->set("package", $package);',
            '$this->view->set("service", $service);',
            '$this->view->set("service_fields", $this->serviceFieldsToObject($service->fields));' . "\n",
            'return $this->view->fetch();'
        );

        foreach($lines as $l) {
            $method->addBody($l);
        }
    }

    private function generateAdminServiceInfo() {
        $method = $this->class->addMethod("getAdminServiceInfo")
            ->setVisibility("public")
            ->addComment("Fetches the HTML content to display when viewing the service info in the admin interface.")
            ->addComment('@param    stdClass    $service    A stdClass object representing the service')
            ->addComment('@param    stdClass    $package    A stdClass object representing the service\'s package')
            ->addComment('@return   string      HTML content containing information to display when viewing the service info');

        $method->addParameter("service");
        $method->addParameter("package");

        $lines = array(
            '$row = $this->getModuleRow($package->module_row);' . "\n",
            '// Load the view into this object, so helpers can be automatically added to the view',
            '$this->view = new View("admin_service_info", "default");',
            '$this->view->base_uri = $this->base_uri;',
            '$this->view->setDefaultView("components" . DS . "modules" . DS . "' . $this->module_name_underscored . '" . DS);' . "\n",
            '// Load the helpers required for this view',
            'Loader::loadHelpers($this, array("Form", "Html"));' . "\n",
            '$this->view->set("module_row", $row);',
            '$this->view->set("package", $package);',
            '$this->view->set("service", $service);',
            '$this->view->set("service_fields", $this->serviceFieldsToObject($service->fields));' . "\n",
            'return $this->view->fetch();'
        );

        foreach($lines as $l) {
            $method->addBody($l);
        }
    }

    private function generateGetAdminTabs() {
        $method = $this->class->addMethod("getAdminTabs")
            ->setVisibility("public")
            ->addComment("Returns all tabs to display to an admin when managing a service whose package uses this module")
            ->addComment('@param    stdClass    $package    A stdClass object representing the selected package')
            ->addComment('@return   array       An array of tabs in the format of method => title. Example: array(\'methodName\' => "Title", \'methodName2\' => "Title2")');

        $method->addParameter("package");
    }

    private function generateGetClientTabs() {
        $method = $this->class->addMethod("getClientTabs")
            ->setVisibility("public")
            ->addComment("Returns all tabs to display to a client when managing a service whose package uses this module")
            ->addComment('@param    stdClass    $package    A stdClass object representing the selected package')
            ->addComment('@return   array       An array of tabs in the format of method => title, or method => array where array contains: - name (required) The name of the link - icon (optional) use to display a custom icon - href (optional) use to link to a different URL Example: array(\'methodName\' => "Title", \'methodName2\' => "Title2") array(\'methodName\' => array(\'name\' => "Title", \'icon\' => "icon"))');

        $method->addParameter("package");
    }

    private function generate() {
        $this->generateConstructor();
        $this->generateInstall();
        $this->generateUninstall();
        $this->generateUpgrade();

        //fields
        $this->generatePackageFields();
        $this->generateAdminAddFields();
        $this->generateAdminEditFields();
        $this->generateClientAddFields();

        //settings
        $this->generateManageModule();
        $this->generateManageAddRow();
        $this->generateManageEditRow();

        //services
        $this->generateAddService();
        $this->generateEditService();
        $this->generateCancelService();
        $this->generateSuspendService();
        $this->generateUnsuspendService();
        $this->generateRenewService();

        //module rows
        $this->generateAddModuleRow();
        $this->generateEditModuleRow();
        $this->generateDeleteModuleRow();

        //info
        $this->generateClientServiceInfo();
        $this->generateAdminServiceInfo();

        //tabs
        $this->generateGetClientTabs();
        $this->generateGetAdminTabs();
    }

    public function getOutput() {
        $this->generate();
        return $this->class;
    }

    public function genereateZipFileName() {
        return "$this->module_name_underscored.zip";
    }

    private function getLanguageStrings() {
        return array(
            $this->module_name_camel_case . '.add_row.box_title' => $this->module_name,
            $this->module_name_camel_case . '.add_row.basic_title' => $this->module_name . " Basic Title Add Row",
            $this->module_name_camel_case . '.add_row.add_btn' => "Add",
            $this->module_name_camel_case . '.edit_row.box_title' => $this->module_name,
            $this->module_name_camel_case . '.edit_row.basic_title' => $this->module_name . " Basic Title Edit Row",
            $this->module_name_camel_case . '.edit_row.add_btn' => "Edit",
            $this->module_name_camel_case . '.add_module_row' => "Add Module Row",
            $this->module_name_camel_case . '.manage.boxtitle' => "Manage " . $this->module_name,
            $this->module_name_camel_case . '.manage.module_row_title' => "Manage Entries",
            $this->module_name_camel_case . '.heading_options.name' => "Options",
            $this->module_name_camel_case . '.option_edit' => "Edit",
            $this->module_name_camel_case . '.manage.module_row.confirm_delete' => "Confirm Deletion",
            $this->module_name_camel_case . '.manage.module_row.delete' => "Delete",
            $this->module_name_camel_case . '.empty_result' => "No entries found",
        );
    }

    public function generateZip() {
        $languages = ["en_us", "de_de"];

        $zip = new ZipArchive;
        $zip_file_name = $this->genereateZipFileName();
        if($zip->open(realpath($this->zip_save_dir) . DIRECTORY_SEPARATOR  . $zip_file_name, ZipArchive::CREATE) === TRUE) {
            $zip->addEmptyDir($this->module_name_underscored);
            $zip->addEmptyDir("$this->module_name_underscored/views/default");
            $zip->addEmptyDir("$this->module_name_underscored/language");

            $lang = new LanguageGenerator();
            foreach($languages as $l) {
                $zip->addEmptyDir("$this->module_name_underscored/language/$l");
                $zip->addFromString("$this->module_name_underscored/language/$l/$this->module_name_underscored.php", $lang->generate($this->getLanguageStrings()));
            }
            $tempGen = new ModuleTemplateGenerator($this->module_name_camel_case, $this->module_name_underscored);

            $zip->addFromString("$this->module_name_underscored/views/default/manage.pdt", $tempGen->generateManage());
            $zip->addFromString("$this->module_name_underscored/views/default/add_row.pdt", $tempGen->generateAddRow());
            $zip->addFromString("$this->module_name_underscored/views/default/edit_row.pdt", $tempGen->generateEditRow());

            //service info
            $zip->addFromString("$this->module_name_underscored/views/default/client_service_info.pdt", "this is the client service info view");
            $zip->addFromString("$this->module_name_underscored/views/default/admin_service_info.pdt", "this is the admin service info view");

            $zip->addFromString("$this->module_name_underscored/config.json", $this->generateConfig());

            $zip->addFromString("$this->module_name_underscored/" . $this->module_name_underscored . ".php", "<?php\n" . $this->getOutput());
            $zip->close();
        }
    }

}