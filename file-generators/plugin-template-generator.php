<?php
class PluginTemplateGenerator {

    private $plugin_name_camel_case;
    private $plugin_name_underscored;

    public function __construct($plugin_name_camel_case, $plugin_name_underscored) {
        $this->plugin_name_camel_case = $plugin_name_camel_case;
        $this->plugin_name_underscored = $plugin_name_underscored;
    }

    private function startsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }

    function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

    private function generateCodeFromLines(array $lines) {
        $openings = ['<?php', '<div', '<ul', '<li', '<table', '<tr', '<script', '<td'];
        $closings = ['?>', '</div>', '</ul>', '</li>', '</table>', '</tr>', '</script>', '</td>'];

        $str = "";
        $counter = 0;
        foreach($lines as $line) {
            //check if line is a closing statement, if true, decrease counter by 1
            foreach($closings as $c) {
                if($this->endsWith($line, $c) == TRUE) {
                    $counter--;
                    break;
                }
            }

            //indent the line
            for($i = 0; $i < $counter; $i++) {
                $str .= "\t";
            }

            //add line to $str
            $str .= $line . "\n";
            
            //check if line start is in openings, if true add 1 to counter to indent the next line
            foreach($openings as $o) {
                if($this->startsWith($line, $o) == TRUE) {
                    $counter++;
                    break;
                }
            }
        }
        return $str;
    }

    public function generateAdminManagePlugin() {
        $lines = array(
            '<?php',
            '$this->Widget->clear();',
            '$this->Widget->create($this->_(\'' . $this->plugin_name_camel_case . '.index.boxtitle_manage\', true));',
            '$this->Form->create();',
            '?>',
            '<div class="inner">',
            '<div class="pad">',
            '<ul>',
            '<li>',
            '<?php $this->Form->label($this->_(\'' . $this->plugin_name_camel_case . '.index.' . $this->plugin_name_underscored . '_test_setting\', true));?>',
            '<?php $this->Form->fieldText(\''.$this->plugin_name_underscored.'_test_setting\', $this->Html->ifSet($settings[\''.$this->plugin_name_underscored.'_test_setting\'])); ?>',
            '</li>',
            '</ul>',
            '</div>',
            '',
            '<div class="button_row">',
            '<?php',
            '$this->Form->fieldSubmit(\'save\', $this->_(\'' . $this->plugin_name_camel_case .  '.index.submit\', true), [\'class\' => \'btn btn-primary pull-right\']);',
            '?>',
            '</div>',
            '</div>',
            '<?php',
            '$this->Form->end();',
            '$this->Widget->end();',
            '?>',
        );

        return $this->generateCodeFromLines($lines);
    }
}