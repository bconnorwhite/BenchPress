<?php

const valid_wp = ['content', 'title'];

include_once('section.php');

class Template {

  var $inputPath;
  var $path;
  var $sectionDir;
  var $tab;
  var $sections;

  function __construct() {
    $this->tab = 0;
    $this->sections = [];
  }

  function setPath($inputPath, $outputDir) {
    $this->inputPath = $inputPath;
    $dom = $this->getDOM($inputPath);
    $id = $this->getTemplateID($dom);
    $this->path = $outputDir . getSuffix($id) . ".php";
  }

  function create($sectionDir) {
    $this->sectionDir = $sectionDir;
    if(isset($this->path) && isset($this->inputPath)) {
      $start = "<?php\n/**\n * Template Name: " . $this->getName() . " Page Template\n */\nget_header(); ?>";
      $content = $this->getTemplate($this->inputPath);
      $end = "<?php get_footer(); ?>\n";
      file_put_contents($this->path, $start . $content . $end);
    }
  }

  function getName() {
    return toWords(basename($this->path, ".php"));
  }

  function getFileName() {
    return basename($this->path);
  }

  function setName($name) {
    $this->name = $name;
  }

  private function getTemplate($inputPath) {
    $dom = $this->getDOM($inputPath);
    $id = $this->getTemplateID($dom);
    return $this->parse($dom->getElementById($id)) . "\n";
  }

  private function getDOM($inputPath) {
    $dom = new DOMDocument;
    $dom->loadHTMLFile($inputPath);
    $this->cleanDOM($dom);
    return $dom;
  }

  //Clean of empty text nodes
  private function cleanDOM($dom) {
    $xpath = new DOMXPath($dom);
    foreach($xpath->query('//text()') as $node) {
      if(ctype_space($node->wholeText)) {
        $node->parentNode->removeChild($node);
      }
    }
  }

  private function getTemplateID($dom) {
    $body = $dom->getElementsByTagName('body');
    if($body && $body->length > 0) {
      $body = $body->item(0);
      foreach($body->attributes as $attribute) {
        if($attribute->name == 'id') {
          $prefix = getPrefix($attribute->value);
          if($prefix == 'template') {
            return $attribute->value;
          }
        }
      }
    }
  }

  private function parse($element) {
    if(isset($element->tagName)) {
      foreach($element->attributes as $attribute) {
        $prefix = getPrefix($attribute->value);
        if($attribute->name == 'id' && $prefix == 'section') {
          $suffix = getSuffix($attribute->value);
          $this->createSection($element);
          return "\n" . $this->tabs() . '<?php include(get_template_directory() . "/section-templates/' . $suffix . '.php"); ?>';
        }
      }
      return $this->openTag($element) . $this->parseChildren($element) . $this->closeTag($element);
    } else if(isset($element->wholeText)) { //Text
      return $element->wholeText;
    } else {
      return "";
    }
  }

  private function createSection($element) {
    $section = new Section($element);
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

  private function openTag($element) {
    $content = "\n" . $this->tabs() . "<" . $element->tagName;
    foreach($element->attributes as $attribute) {
      $content .= " " . $attribute->name . "='" . $attribute->value . "'";
    }
    $this->tab++;
    return $content . ">";
  }

  private function parseChildren($element) {
    $content = "";
    foreach($element->childNodes as $child) {
      $content .= $this->parse($child);
    }
    return $content;
  }

  private function closeTag($element) {
    $this->tab--;
    if($element->tagName == 'body') { //Don't close body tag, this will happen in footer.php
      return "";
    } else {
      return "\n" . $this->tabs() . "</" . $element->tagName . ">";
    }
  }

  private function tabs() {
    $ret = "";
    for($t=0; $t<$this->tab; $t++) {
      $ret .= "\t";
    }
    return $ret;
  }

}
