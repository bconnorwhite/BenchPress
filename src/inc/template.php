<?php

include_once('parser.php');

class Template {

  var $inputPath;
  var $path;
  var $site;
  var $fields;
  var $meta;
  var $repeater;

  function __construct($inputPath, $outputDir, $site) {
    $this->inputPath = $inputPath;
    $this->path = $this->getPath($inputPath, $outputDir);
    $this->site = $site;
    $this->fields = [];
    $this->meta = [];
    $this->repeater = null;
  }

  function create() {
    if(isset($this->path) && isset($this->inputPath)) {
      $parser = new Parser(0, $this);
      $start = "<?php\n/**\n * Template Name: " . $this->getName() . " Page Template\n */\nget_header(); ?>";
      $content = $parser->parse($parser->getElementByTagName($this->inputPath, 'body'));
      $end = "<?php get_footer(); ?>\n";
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
    if(isset($this->repeater)) {
      return "sub-" . $fieldId;
    } else {
      return $this->getGroup() . "-" . $fieldId;
    }
  }

  function getFieldId($fieldName) {
    $tmp = explode("-", $fieldName);
    return end($tmp);
  }

  function getFieldType($fieldName) {
    if(isset($this->repeater)) {
      return $this->repeater['sub_fields'][$this->getFieldId($fieldName)]['type'];
    } else {
      return $this->fields[$this->getFieldId($fieldName)-1]['type'];
    }
  }

  function loopRepeater() {
    if(isset($this->repeater)) {
      $this->repeater['temp']['field'] = 0;
      $this->repeater['temp']['counter'] += 1;
    }
  }

  function addField($element, $type, $bgURL=false) { //$bg only used if $type == 'image'
    $fieldId = null;
    if(isset($this->repeater)) {
      $fieldId = $this->repeater['temp']['field'];
      $this->repeater['temp']['field'] += 1;
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
      $settings['sub_fields'] = array();
      $settings['temp'] = array("counter"=>0, "field"=>0, "parent"=>&$this->repeater);
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
    if(isset($this->repeater)) {
      if($this->repeater['temp']['counter'] == 0) {
        array_push($this->repeater['sub_fields'], $settings);
      }
    } else {
      $settings['parent'] = $this->getGroup();
      array_push($this->fields, $settings);
    }
    if($type == 'repeater') {
      $this->repeater = &$this->fields[count($this->fields)-1];
    }
    return $settings['key'];
  }

  function closeRepeater() {
    if(isset($this->repeater)) {
      array_push($this->meta, array("key"=>$this->repeater['key'], "value"=>($this->repeater['temp']['counter']+1)));
      if(isset($this->repeater['parent'])) {
        unset($this->repeater['temp']);
        unset($this->repeater);
      } else {
        $temp = &$this->repeater['temp'];
        $this->repeater = $this->repeater['temp']['parent'];
        unset($temp);
      }
    } else {
      printError("ERROR: template.php: closeRepeater()");
    }
  }

  private function addMeta($fieldId, $value) {
    if(isset($this->repeater)) {
      array_push($this->meta, array("key"=> $this->repeater['key'] . "_" . $this->repeater['temp']['counter'] . "_" . $this->getFieldName($fieldId), "value"=>$value));
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
