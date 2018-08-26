<?php

include_once('parser.php');

class Template {

  var $inputPath;
  var $path;
  var $site;
  var $fields;
  var $meta;
  var $repeaterStack;

  function __construct($inputPath, $outputDir, $site) {
    $this->inputPath = $inputPath;
    $this->path = $this->getPath($inputPath, $outputDir);
    $this->site = $site;
    $this->fields = [];
    $this->meta = [];
    $this->repeaterStack = array();
  }

  function create() {
    if(isset($this->path) && isset($this->inputPath)) {
      $parser = new Parser(0, $this);
      $start = "<?php\n/**\n * Template Name: " . $this->getName() . " Page Template\n */\nget_header(); ?>";
      $content = $parser->parse($parser->getElementByTagName($this->inputPath, 'body'));
      $end = "\n<?php get_footer(); ?>\n";
      file_put_contents($this->path, $start . $content . $end);
    }
  }

  function getFileName() {
    return str_replace(" ", "_", $this->getName());
  }

  function getName() {
    return toWords(basename($this->path, ".php"));
  }

  function getGroup() {
    return str_replace(" ", "_", basename($this->path, ".php"));
  }

  function getFieldName($fieldId) {
    if(count($this->repeaterStack) > 0) {
      return end($this->repeaterStack)['key'] . "-" . $fieldId;
    } else {
      return $this->getGroup() . "-" . $fieldId;
    }
  }

  function getFieldId($fieldName) {
    $tmp = explode("-", $fieldName);
    return end($tmp);
  }

  function getFieldType($fieldName) {
    if(count($this->repeaterStack) > 0) {
      return end($this->repeaterStack)['sub_fields'][$this->getFieldId($fieldName)]['type'];
    } else {
      return $this->fields[$this->getFieldId($fieldName)-1]['type'];
    }
  }

  function addField($element, $type, $bgURL=false) { //$bg only used if $type == 'image'
    $fieldId = null;
    if(count($this->repeaterStack) > 0) {
      $fieldId = end($this->repeaterStack)['temp']['field'];
    } else {
      $fieldId = count($this->fields) + 1;
    }
    $settings = array(
      'key' => $this->getFieldName($fieldId),
      'label' => $fieldId,
      'name' => $this->getFieldName($fieldId),
      'type' => $type,
    );
    if($type == 'repeater') {
      $settings['temp'] = array("cycle"=>0, "field"=>0);
      $settings['sub_fields'] = array();
    } else if($type == 'link') {
      $settings['return_format'] = 'array';
      $attributes = array("title"=>$element->textContent);
      foreach($element->attributes as $attribute) {
        if($attribute->name == 'href') {
          $attributes['url'] = $attribute->value;
        } else if($attribute->name == 'target') {
          $attributes['target'] = $attribute->value;
        } else if($attribute->name == 'alt') {
          $attributes['alt'] = $attribute->alt;
        }
      }
      $this->addMeta($fieldId, serialize($attributes));
    } else if($type == 'image') {
      $settings['return_format'] = 'id';
      $wpId;
      if($bgURL) {
        $wpId = $this->site->importMedia($bgURL);
      } else {
        foreach($element->attributes as $attribute) {
          if($attribute->name == 'src') {
            $wpId = $this->site->importMedia($attribute->value);
          }
        }
      }
      if(isset($wpId)) {
        $this->addMeta($fieldId, $wpId);
      }
    } else if($type == 'text') {
      $this->addMeta($fieldId, trim($element->textContent));
    } else if($type == 'textarea') {
      $settings['new_lines'] = 'br';
      $this->addMeta($fieldId, trim($this->br2nl($this->innerHTML($element))));
    } else if($type == 'wysiwyg') {
      $settings['media_upload'] = 0;
      $this->addMeta($fieldId, trim($this->br2nl($this->innerHTML($element))));
    }
    if(count($this->repeaterStack) > 0) {
      $this->repeaterStack[count($this->repeaterStack)-1]['temp']['field'] += 1;
      if($type == 'repeater') {
        array_push($this->repeaterStack, $settings);
      } else if(end($this->repeaterStack)['temp']['cycle'] == 0) { //First cycle
        array_push($this->repeaterStack[count($this->repeaterStack)-1]['sub_fields'], $settings);
      }
    } else {
      $settings['parent'] = $this->getGroup();
      if($type == 'repeater') {
        array_push($this->repeaterStack, $settings);
      } else {
        array_push($this->fields, $settings);
      }
    }
    return $settings['key'];
  }

  function loopRepeater() {
    if(count($this->repeaterStack) > 0) {
      $this->repeaterStack[count($this->repeaterStack)-1]['temp']['cycle'] += 1;
      $this->repeaterStack[count($this->repeaterStack)-1]['temp']['field'] = 0;
    }
  }

  function closeRepeater() {
    if(count($this->repeaterStack) > 0) {
      array_push($this->meta, array("key"=>$this->getRepeaterKey(), "value"=>(end($this->repeaterStack)['temp']['cycle'])));
      unset($this->repeaterStack[count($this->repeaterStack)-1]['temp']);
      if(count($this->repeaterStack) == 1) {
        array_push($this->fields, $this->repeaterStack[count($this->repeaterStack)-1]);
      } else if($this->repeaterStack[count($this->repeaterStack)-2]['temp']['cycle'] == 0) {
        array_push($this->repeaterStack[count($this->repeaterStack)-2]['sub_fields'], $this->repeaterStack[count($this->repeaterStack)-1]);
      }
      array_pop($this->repeaterStack);
    } else {
      printError("ERROR: template.php: closeRepeater()");
    }
  }

  private function getRepeaterKey($fieldId=false) {
    $key = "";
    for($r=0; $r<count($this->repeaterStack); $r++) {
      $key .= $this->repeaterStack[$r]['key'];
      if(!($fieldId===false) || $r<count($this->repeaterStack)-1) {
        $key .= "_" . $this->repeaterStack[$r]['temp']['cycle'] . "_";
      }
    }
    if(!($fieldId===false)) {
      $key .= $this->getFieldName($fieldId);
    }
    return $key;
  }

  private function addMeta($fieldId, $value) {
    if(count($this->repeaterStack) > 0) {
      array_push($this->meta, array("key"=> $this->getRepeaterKey($fieldId), "value"=>$value));
    } else {
      array_push($this->meta, array("key"=>$this->getFieldName($fieldId), "value"=>$value));
    }
  }

  private function innerHTML($element) {
    return implode(array_map([$element->ownerDocument,"saveHTML"], iterator_to_array($element->childNodes)));
  }

  private function br2nl($input) {
    return preg_replace('/<br\s?\/?>/ius', "\n", str_replace("\n","",str_replace("\r","", htmlspecialchars_decode($input))));
  }

  /* ----------
  * Private Functions
  ---------- */

  private function getPath($inputPath, $outputDir) {
    $name = $this->getTemplateName();
    return $outputDir . $name . ".php";
  }

  private function getTemplateName() {
    return basename($this->inputPath, ".html");
  }

}
