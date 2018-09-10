<?php
class ModuleTemplateGenerator {
    private $module_name_camel_case;
    private $module_name_underscored;

    public function __construct($module_name_camel_case, $module_name_underscored) {
        $this->module_name_camel_case = $module_name_camel_case;
        $this->module_name_underscored = $module_name_underscored;
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

    public function generateAddRow() {
        $lines = array(
            '<?php',
            '$this->Widget->clear();',
            '$this->Widget->create($this->_(\'' . $this->module_name_camel_case . '.add_row.box_title\', true));',
            '?>',
            '',
            '<div class="inner">',
            '<?php',
            '$this->Form->create();',
            '?>',
            "",
            '<div class="title_row first">',
            '<h3><?php $this->_(\'' . $this->module_name_camel_case . '.add_row.basic_title\', true); ?></h3>',
            '</div>',
            '',
            '<!-- Form values here -->',
            '<div class="pad">',
            '<ul>',
            '<li>',
            '<!-- Place for Form fields !-->',
            '</li>',
            '</ul>',
            '</div>',
            "",
            '<div class="button_row">',
            '<?php',
            '$this->Form->fieldSubmit(\'submit\', $this->_(\'' .  $this->module_name_camel_case . '.add_row.add_btn\', true), [\'class\' => \'btn btn-primary pull-right\']);',
            '?>',
            '</div>',
            '</div>',
            "",
            '<?php',
            '$this->Widget->end();',
            '?>',
        );

        return $this->generateCodeFromLines($lines);
    }

    public function generateEditRow() {
        $lines = array(
            '<?php',
            '$this->Widget->clear();',
            '$this->Widget->create($this->_(\'' . $this->module_name_camel_case . '.edit_row.box_title\', true));',
            '?>',
            '',
            '<div class="inner">',
            '<?php',
            '$this->Form->create();',
            '?>',
            "",
            '<div class="title_row first">',
            '<h3><?php $this->_(\'' . $this->module_name_camel_case . '.edit_row.basic_title\', true); ?></h3>',
            '</div>',
            '',
            '<!-- Form values here -->',
            '<div class="pad">',
            '<ul>',
            '<li>',
            '<!-- Place for Form fields !-->',
            '</li>',
            '</ul>',
            '</div>',
            "",
            '<div class="button_row">',
            '<?php',
            '$this->Form->fieldSubmit(\'submit\', $this->_(\'' .  $this->module_name_camel_case . '.edit_row.add_btn\', true), [\'class\' => \'btn btn-primary pull-right\']);',
            '?>',
            '</div>',
            '</div>',
            "",
            '<?php',
            '$this->Widget->end();',
            '?>',
        );

        return $this->generateCodeFromLines($lines);
    }

    public function generateManage() {
        $lines = array(
            '<?php',
            '$link_buttons = array([\'name\'=>$this->_(\'' . $this->module_name_camel_case . '.add_module_row\', true), \'attributes\' => array(\'href\'=> $this->base_uri . \'settings/company/modules/addrow/\' . $module->id)]);',
            '',
            '$this->Widget->clear();',
            '$this->Widget->setLinkButtons($link_buttons);',
            '$this->Widget->create($this->_(\'' . $this->module_name_camel_case  . '.manage.boxtitle\', true, $this->Html->_($module->name, true)), [\'id\' => \'manage_'. $this->module_name_underscored . '\']);',
            '?>',
            '',
            '<div class="title_row first">',
            '<h3><?php $this->_(\'' . $this->module_name_camel_case . '.manage.module_row_title\'); ?></h3>',
            '</div>',
            '',
            '<?php',
            '$num_rows = count($this->Html->ifSet($module->rows));',
            '',
            'if ($num_rows > 0) {',
            '?>',
            '<table class="table">',
            '<tr class="heading_row">',
            '<td>',
            '<!-- More Headers for Rows here -->',
            '</td>',
            '<td><span><?php $this->_(\'' . $this->module_name_camel_case . '.heading_options.name\'); ?></span></td>',
            '</tr>',
            '<?php',
            'for ($i=0; $i < $num_rows; $i++)',
            '{',
            '?>',
            '<tr <?php echo ($i % 2 == 1) ? \' class="odd_row"\' : \'\'; ?>>',
            '<td>',
            '<!-- Fields for Row values here -->',
            '</td>',
            '<td>',
            '<a href="<?php echo $this->Html->safe($this->base_uri . \'settings/company/modules/editrow/\' . $this->Html->ifSet($module->id) . \'/\' . $this->Html->ifSet($module->rows[$i]->id) . \'/\'); ?>"><?php $this->_(\'' . $this->module_name_camel_case . '.option_edit\'); ?></a>',
            '<?php',
            '$this->Form->create($this->base_uri . \'settings/company/modules/deleterow/\');',
            '$this->Form->fieldHidden(\'id\', $this->Html->ifSet($module->id));',
            '$this->Form->fieldHidden(\'row_id\', $this->Html->ifSet($module->rows[$i]->id));',
            '?>',
            '',
            '<a href="<?php echo $this->Html->safe($this->base_uri . \'settings/company/modules/deleterow/\' . $this->Html->ifSet($module->id) . \'/\' . $this->Html->ifSet($module->rows[$i]->id) . \'/\'); ?>" class="manage" rel="<?php echo $this->Html->safe($this->_(\''. $this->module_name_camel_case .'.manage.module_row.confirm_delete\', true)) . $module->rows[$i]->meta->username; ?>"><?php $this->_(\'' . $this->module_name_camel_case . '.manage.module_row.delete\'); ?></a>',                  
            '<?php',
            '$this->Form->end();',
            '?>',
            '',
            '</td>',
            '</tr>',
            '<?php',
	        '}',
            '?>',
            '</table>',
            '',
            '<?php',
	        '} else {',
	        '?>',
	        '<div class="empty_section">',
	        '<div class="empty_box">',
		    '<?php $this->_(\'' . $this->module_name_camel_case . '.empty_result\'); ?>',
		    '</div>',
	        '</div>',
            '',
	        '<?php',
	        '}',
            '?>',
            '',
            '<?php',
	        '$this->Widget->end();',
	        '?>',
            '<script type="text/javascript">',
	        '$(document).ready(function() {',
		    '$(\'a.manage[rel]\').blestaModalConfirm({base_url: \'<?php echo $this->base_uri;?>\', close: \'<?php $this->_("AppController.modal.text_close");?>\', submit: true});',
	        '});',
            '</script>'
        );

        return $this->generateCodeFromLines($lines);
    }
}