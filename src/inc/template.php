<?php

include_once('section.php');
include_once('parser.php');

class Template {

  var $inputPath;
  var $path;
  var $site;
  var $fields;
  var $meta;
  var $sectionDir;
  var $sections;

  function __construct($inputPath, $outputDir, $site) {
    $this->inputPath = $inputPath;
    $this->path = $this->getPath($inputPath, $outputDir);
    $this->site = $site;
    $this->fields = [];
    $this->meta = [];
    $this->sections = [];
  }

  function create($sectionDir) {
    $this->sectionDir = $sectionDir;
    if(isset($this->path) && isset($this->inputPath)) {
      $parser = new Parser(0, $this);
      $start = "<?php\n/**\n * Template Name: " . $this->getName() . " Page Template\n */\nget_header(); ?>";
      $content = $parser->parse($parser->getElementByTagName($this->inputPath, 'body'));
      $end = "<?php get_footer(); ?>\n";
      file_put_contents($this->path, $start . $content . $end);
    }
  }

  function getFileName() {
    return $this->getName();
  }

  function getName() {
    return toWords(basename($this->path, ".php"));
  }

  function createSection($element) {
    $section = new Section($element, $this->site);
    if($section->setPath($this->sectionDir)) {
      foreach($this->sections as $s) {
        if($s->path == $section->path) {
          return;
        }
      }
      $section->create();
      array_push($this->sections, $section);
    }
  }

  function getGroup() {
    return "group-" . basename($this->path, ".php");
  }

  function getFieldName($field) {
    return $this->getGroup() . "-" . $field;
  }

  function addField($field, $tag) {
    if(isset($field) && isset($tag)) {
      $settings = array(
        'key' => $this->getGroup() . "-field-" . $field,
        'label' => toWords($field),
        'name' => $this->getFieldName($field),
        'parent' => $this->getGroup(),
      );
      if($tag == 'a') {
        $settings['type'] = 'link';
        $settings['return_format'] = 'array';
      } else if($tag == 'img') {
        $settings['type'] = 'image';
        $settings['return_format'] = 'id';
      } else if($tag == 'text') {
        $settings['type'] = 'text';
      } else if($tag == 'textarea') {
        $settings['type'] = 'textarea';
        $settings['new_lines'] = 'br';
      } else if($tag == 'p') {
        $settings['type'] = 'wysiwyg';
        $settings['media_upload'] = 0;
      }
      array_push($this->fields, $settings);
    }
  }

  function addMeta($key, $value) {
    array_push($this->meta, array("key"=>$key, "value"=>$value));
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
