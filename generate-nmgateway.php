<?php
class NonMerchantGatewayGenerator {
    private $gateway_name;
    private $gateway_name_camel_case;
    private $gateway_name_underscored;

    private $class;

    private $zip_save_dir;

    public function __construct($gateway_name, $config = [], $out_dir = ".") {
        $this->gateway_name = $gateway_name;
        $this->gateway_name_camel_case = $this->toCamelCase($this->formatName($gateway_name));
        $this->gateway_name_underscored = $this->toUnderScoreCase($this->gateway_name_camel_case);

        $this->config = $config;
        $this->zip_save_dir = $out_dir;

        $this->class = new Nette\PhpGenerator\ClassType($this->gateway_name_underscored);
        $this->class->setExtends("NonmerchantGateway");
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
        $str[0] = strtoupper($str[0]);

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
        //config json file with default values
        $config_json = array(
            'version' => '1.0.0',
            'name' => $this->gateway_name,
            'description' => '',
            'authors' => [],
            'currencies' => ['USD'],
            'signup_url' => 'https://google.com'
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

    private function generateInstall() {
        $method = $this->class->addMethod("install")
            ->setVisibility("public")
            ->addComment("Performs installation of the gateway");
    }

    private function generateUninstall() {
        $method = $this->class->addMethod("uninstall")
            ->setVisibility("public")
            ->addComment("Performs uninstallation of the gateway")
            ->addComment('@param    int     $gateway_id     The ID of the gateway being uninstalled')
            ->addComment('@param    bool    $last_instance  True if $gateway_id is the last instance across all companies for this gateway, false otherwise');

        $method->addParameter("gateway_id");
        $method->addParameter("last_instance");
    }

    private function generateUpgrade() {
        $method = $this->class->addMethod("upgrade")
            ->setVisibility("public")
            ->addComment('Performs migration of data from $current_version (the current installed version) to the given file set version')
            ->addComment('@param    string  $current_version    The current installed version of this gateway');

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

    private function generateBuildProcess() {
        $method = $this->class->addMethod("buildProcess")
            ->setVisibility("public")
            ->addComment('Returns all HTML markup required to render an authorization and capture payment form')
            ->addComment("@param    array   \$contact_info       An array of contact info including: \n- id The contact ID \n- client_id The ID of the client this contact belongs to \n- user_id The user ID this contact belongs to (if any) \n- contact_type The type of contact \n- contact_type_id The ID of the contact type \n- first_name The first name on the contact \n- last_name The last name on the contact \n- title The title of the contact \n- company The company name of the contact \n- address1 The address 1 line of the contact \n- address2 The address 2 line of the contact \n- city The city of the contact \n- state An array of state info including: \n- code The 2 or 3-character state code \n- name The local name of the country \n- country An array of country info including: \n- alpha2 The 2-character country code \n- alpha3 The 3-cahracter country code \n- name The english name of the country \n- alt_name The local name of the country \n- zip The zip/postal code of the contact")
            ->addComment('@param    float   $amount             The amount to charge this contact')
            ->addComment("@param    array   \$invoice_amounts    An array of invoices, each containing: \n- id The ID of the invoice being processed \n- amount The amount being processed for this invoice (which is included in \$amount)")
            ->addComment("@param    array   \$options            An array of options including: \n- description The Description of the charge \n- recur An array of recurring info including: \n- amount The amount to recur \n- term The term to recur \n- period The recurring period (day, week, month, year, onetime) used in conjunction with term in order to determine the next recurring payment")
            ->addComment('@return   mixed   A string of HTML markup required to render an authorization and capture payment form, or an array of HTML markup');

        $method->addParameter("contact_info")
            ->setTypeHint("array");
        $method->addParameter("amount")
            ->setTypeHint("float");
        $method->addParameter("invoice_amounts", null)
            ->setTypeHint("array");
        $method->addParameter("options", null)
            ->setTypeHint("array");
    }

    private function generateBuildAuthorize() {
        $method = $this->class->addMethod("buildAuthorize")
            ->setVisibility("public")
            ->addComment('Returns all HTML markup required to render an authorization only payment form')
            ->addComment("@param    array   \$contact_info       An array of contact info including: \n- id The contact ID \n- client_id The ID of the client this contact belongs to \n- user_id The user ID this contact belongs to (if any) \n- contact_type The type of contact \n- contact_type_id The ID of the contact type \n- first_name The first name on the contact \n- last_name The last name on the contact \n- title The title of the contact \n- company The company name of the contact \n- address1 The address 1 line of the contact \n- address2 The address 2 line of the contact \n- city The city of the contact \n- state An array of state info including: \n- code The 2 or 3-character state code \n- name The local name of the country \n- country An array of country info including: \n- alpha2 The 2-character country code \n- alpha3 The 3-cahracter country code \n- name The english name of the country \n- alt_name The local name of the country \n- zip The zip/postal code of the contact")
            ->addComment('@param    float   $amount             The amount to charge this contact')
            ->addComment("@param    array   \$invoice_amounts    An array of invoices, each containing: \n- id The ID of the invoice being processed \n- amount The amount being processed for this invoice (which is included in \$amount)")
            ->addComment("@param    array   \$options            An array of options including: \n- description The Description of the charge \n- recur An array of recurring info including: \n- amount The amount to recur \n- term The term to recur \n- period The recurring period (day, week, month, year, onetime) used in conjunction with term in order to determine the next recurring payment")
            ->addComment('@return   mixed   A string HTML markup required to render an authorization payment form, or an array of HTML markup');

        $method->addParameter("contact_info")
            ->setTypeHint("array");
        $method->addParameter("amount")
            ->setTypeHint("float");
        $method->addParameter("invoice_amounts", null)
            ->setTypeHint("array");
        $method->addParameter("options", null)
            ->setTypeHint("array");
    }

    private function generateCapture() {
        $method = $this->class->addMethod("capture")
            ->setVisibility("public")
            ->addComment('Captures a previously authorized payment')
            ->addComment('@param    string  $reference_id   The reference ID for the previously authorized transaction')
            ->addComment('@param    string  $transaction_id The transaction ID for the previously authorized transaction')
            ->addComment('@param    mixed   $amount')
            ->addComment('@param    array   $invoice_amounts')
            ->addComment("@return   array   An array of transaction data including: \n- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned) \n- reference_id The reference ID for gateway-only use with this transaction (optional) \n- transaction_id The ID returned by the remote gateway to identify this transaction \n- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)");

        $method->addParameter("reference_id")
            ->setTypeHint("string");
        $method->addParameter("transaction_id")
            ->setTypeHint("string");
        $method->addParameter("amount");
        $method->addParameter("invoice_amounts", null);
    }

    private function generateVoid() {
        $method = $this->class->addMethod("void")
            ->setVisibility("public")
            ->addComment('Void a payment or authorization')
            ->addComment('@param    string  $reference_id   The reference ID for the previously submitted transaction')
            ->addComment('@param    string  $transaction_id The transaction ID for the previously submitted transaction')
            ->addComment('@param    string  $notes          Notes about the void that may be sent to the client by the gateway')
            ->addComment("@return   array   An array of transaction data including: \n- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned) \n- reference_id The reference ID for gateway-only use with this transaction (optional) \n- transaction_id The ID returned by the remote gateway to identify this transaction \n- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)");

        $method->addParameter('reference_id')
            ->setTypeHint('string');
        $method->addParameter('transaction_id')
            ->setTypeHint('string');
        $method->addParameter('notes', null)
            ->setTypeHint('string');
    }

    private function generateRefund() {
        $method = $this->class->addMethod('refund')
            ->setVisibility('public')
            ->addComment('Refund a payment')
            ->addComment('@param    string  $reference_id   The reference ID for the previously submitted transaction')
            ->addComment('@param    string  $transaction_id The transaction ID for the previously submitted transaction')
            ->addComment('@param    float   $amount         The amount to refund this transaction')
            ->addComment('@param    string  $notes          Notes about the refund that may be sent to the client by the gateway')
            ->addComment("@return   array   An array of transaction data including: \n- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned) \n- reference_id The reference ID for gateway-only use with this transaction (optional) \n- transaction_id The ID returned by the remote gateway to identify this transaction \n- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)");

        $method->addParameter('reference_id')
            ->setTypeHint('string');
        $method->addParameter('transaction_id')
            ->setTypeHint('string');
        $method->addParameter('amount')
            ->setTypeHint('float');
        $method->addParameter('notes', null)
            ->setTypeHint('string');
    }

    private function generateValidate() {
        $method = $this->class->addMethod('validate')
            ->setVisibility('public')
            ->addComment('Validates the incoming POST/GET response from the gateway to ensure it is legitimate and can be trusted.')
            ->addComment('@param    array   $get    The GET data for this request')
            ->addComment('@param    array   $post   The POST data for this request')
            ->addComment("@return   array   An array of transaction data, sets any errors using Input if the data fails to validate \n- client_id The ID of the client that attempted the payment \n- amount The amount of the payment \n- currency The currency of the payment \n- invoices An array of invoices and the amount the payment should be applied to (if any) including: \n- id The ID of the invoice to apply to \n- amount The amount to apply to the invoice \n- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned) \n- reference_id The reference ID for gateway-only use with this transaction (optional) \n- transaction_id The ID returned by the gateway to identify this transaction \n- parent_transaction_id The ID returned by the gateway to identify this transaction\'s original transaction (in the case of refunds)");

        $method->addParameter('get')
            ->setTypeHint('array');
        $method->addParameter('post')
            ->setTypeHint('array');
        
    }

    private function generateSuccess() {
        $method = $this->class->addMethod('success')
            ->setVisibility('public')
            ->addComment('Returns data regarding a success transaction. This method is invoked when a client returns from the non-merchant gateway\'s web site back to Blesta.')
            ->addComment('@param    array   $get    The GET data for this request')
            ->addComment('@param    array   $post   The POST data for this request')
            ->addComment("@return   array   An array of transaction data, may set errors using Input if the data appears invalid \n- client_id The ID of the client that attempted the payment \n- amount The amount of the payment \n- currency The currency of the payment \n- invoices An array of invoices and the amount the payment should be applied to (if any) including: \n- id The ID of the invoice to apply to \n- amount The amount to apply to the invoice \n- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned) \n- transaction_id The ID returned by the gateway to identify this transaction \n- parent_transaction_id The ID returned by the gateway to identify this transaction\'s original transaction");

        $method->addParameter('get')
            ->setTypeHint('array');
        $method->addParameter('post')
            ->setTypeHint('array');
    }

    private function generateGetSettings() {
        $method = $this->class->addMethod('getSettings')
            ->setVisibility('public')
            ->addComment('Create and return the view content required to modify the settings of this gateway')
            ->addComment('@param    array   $meta   An array of meta (settings) data belonging to this gateway')
            ->addComment('@return   string  HTML content containing the fields to update the meta data for this gateway');

        $method->addParameter('meta', null)
            ->setTypeHint('array');

        $lines = array(
            '$this->view = $this->makeView(\'settings\', \'default\', str_replace(ROOTWEBDIR, \'\', dirname(__FILE__) . DS));' . "\n",
            '// Load the helpers required for this view',
            'Loader::loadHelpers($this, [\'Form\', \'Html\']);',
            '$this->view->set(\'meta\', $meta);',
            'return $this->view->fetch();'
        );

        foreach($lines as $l) {
            $method->addBody($l);
        }
    }

    private function generateEditSettings() {
        $method = $this->class->addMethod('editSettings')
            ->setVisibility('public')
            ->addComment('Validates the given meta (settings) data to be updated for this gateway')
            ->addComment('@param    array   $meta   An array of meta (settings) data to be updated for this gateway')
            ->addComment('@return   array   The meta data to be updated in the database for this gateway, or reset into the form on failure');

        $method->addParameter('meta')
            ->setTypeHint('array');
    }

    private function generateEncryptableFields() {
        $method = $this->class->addMethod('encryptableFields')
            ->setVisibility('public')
            ->addComment('Returns an array of all fields to encrypt when storing in the database')
            ->addComment('@return   array   An array of the field names to encrypt when storing in the database');

        $lines = array(
            'return [];'
        );

        foreach($lines as $l) {
            $method->addBody($l);
        }
    }

    private function generateSetMeta() {
        $method = $this->class->addMethod('setMeta')
            ->setVisibility('public')
            ->addComment('Sets the meta data for this particular gateway')
            ->addComment('@param    array   $meta   An array of meta data to set for this gateway');

        $method->addParameter('meta', null)
            ->setTypeHint('array');

        $lines = array(
            '$this->meta = $meta;'
        );

        foreach($lines as $l) {
            $method->addBody($l);
        }
    }

    private function generateConstructor() {
        $method = $this->class->addMethod("__construct")
            ->setVisibility("public")
            ->addComment('Class constructor');

        $lines = array(
            'Loader::loadComponents($this, array("Input"));'
        );

        foreach($lines as $l) {
            $method->addBody($l);
        }
    }

    private function generate() {
        $this->generateConstructor();

        //install/uninstall/upgrade
        $this->generateInstall();
        $this->generateUninstall();
        $this->generateUpgrade();

        //process
        $this->generateBuildProcess();
        $this->generateBuildAuthorize();

        //capture/void/refund/validate
        $this->generateCapture();
        $this->generateVoid();
        $this->generateRefund();
        $this->generateValidate();

        $this->generateSuccess();

        //settings
        $this->generateGetSettings();
        $this->generateEditSettings();

        $this->generateEncryptableFields();
        $this->generateSetMeta();
    }

    public function getOutput() {
        $this->generate();
        return $this->class;
    }

    public function genereateZipFileName() {
        return "$this->gateway_name_underscored.zip";
    }

    private function getLanguageStrings() {
        return array();
    }


    public function generateZip() {
        $languages = ["en_us", "de_de"];
        $zip = new ZipArchive;
        $zip_file_name = $this->genereateZipFileName();
        if($zip->open(realpath($this->zip_save_dir) . DIRECTORY_SEPARATOR  . $zip_file_name, ZipArchive::CREATE) === TRUE) {
            $zip->addEmptyDir($this->gateway_name_underscored);
            $zip->addEmptyDir("$this->gateway_name_underscored/views/default");
            $zip->addEmptyDir("$this->gateway_name_underscored/language");

            $lang = new LanguageGenerator();
            foreach($languages as $l) {
                $zip->addEmptyDir("$this->gateway_name_underscored/language/$l");
                $zip->addFromString("$this->gateway_name_underscored/language/$l/$this->gateway_name_underscored.php", $lang->generate($this->getLanguageStrings()));
            }

            $zip->addFromString("$this->gateway_name_underscored/views/default/process.pdt", "this is the process view");
            $zip->addFromString("$this->gateway_name_underscored/views/default/settings.pdt", "this is the settings view");
            $zip->addFromString("$this->gateway_name_underscored/views/default/authorize.pdt", "this is the authorize view");

            $zip->addFromString("$this->gateway_name_underscored/config.json", $this->generateConfig());
            $zip->addFromString("$this->gateway_name_underscored/" . $this->gateway_name_underscored . ".php", "<?php\n" . $this->getOutput());
            $zip->close();
        }
    }
}