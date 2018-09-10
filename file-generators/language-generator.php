<?php 
class LanguageGenerator {
    public function generate(array $entries) {
        $str = '<?php' . "\n";

        foreach($entries as $key => $value) {
            $str .= '$lang[\'' . $key . '\'] = \'' . $value . '\'' . ";\n";
        }

        return $str;
    }
}