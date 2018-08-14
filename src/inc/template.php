<?php

include_once('parser.php');

class Template {

  var $inputPath;
  var $path;
  var $site;
  var $fields;
  var $meta;

  function __construct($inputPath, $outputDir, $site) {
    $this->inputPath = $inputPath;
    $this->path = $this->getPath($inputPath, $outputDir);
    $this->site = $site;
    $this->fields = [];
    $this->meta = [];
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
    return "group-" . str_replace(" ", "_", basename($this->path, ".php"));
  }

  function getFieldName($field) {
    return $this->getGroup() . "-" . $field;
  }

  function getFieldType($fieldId) {
    return $this->fields[$fieldId-1]['type'];
  }

  function addField($element, $type) {
    if(isset($element->tagName)) {
      $fieldId = count($this->fields) + 1;
      $settings = array(
        'key' => $this->getFieldName($fieldId),
        'label' => $fieldId,
        'name' => $this->getFieldName($fieldId),
        'parent' => $this->getGroup(),
        'type' => $type,
      );
      if($type == 'link') {
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
        foreach($element->attributes as $attribute) {
          if($attribute->name == 'src') {
            $wpId = $this->site->importMedia($attribute->value);
            if(isset($wpId)) {
              $this->addMeta($fieldId, $wpId);
            }
          }
        }
      } else if($type == 'text') {
        $this->addMeta($fieldId, $element->textContent);
      } else if($type == 'textarea') {
        $settings['new_lines'] = 'br';
        $this->addMeta($fieldId, $this->br2nl($this->innerHTML($element)));
      } else if($type == 'wysiwyg') {
        $settings['media_upload'] = 0;
        $this->addMeta($fieldId, $this->innerHTML($element));
      }
      array_push($this->fields, $settings);
      return $fieldId;
    }
    return NULL;
  }

  private function addMeta($fieldId, $value) {
    array_push($this->meta, array("key"=>$this->getFieldName($fieldId), "value"=>$value));
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
