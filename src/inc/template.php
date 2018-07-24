<?php

const valid_wp = ['title'];

include_once('section.php');

class Template {

  var $inputPath;
  var $path;
  var $site;
  var $sectionDir;
  var $tab;
  var $sections;

  function __construct($inputPath, $outputDir, $site) {
    $this->inputPath = $inputPath;
    $this->path = $this->getPath($inputPath, $outputDir);
    $this->site = $site;
    $this->tab = 0;
    $this->sections = [];
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

  /* ----------
  * Private Functions
  ---------- */

  private function getPath($inputPath, $outputDir) {
    $dom = $this->getDOM($inputPath);
    $name = $this->getTemplateName($dom);
    return $outputDir . $name . ".php";
  }

  private function getTemplateName($dom) {
    $body = $this->getTemplateBody($dom);
    foreach($body->attributes as $attribute) {
      if($attribute->name == 'id') {
        $prefix = getPrefix($attribute->value);
        if($prefix == 'template') {
          return $attribute->value;
        }
      }
    }
    return basename($this->inputPath, ".html");
  }

  private function getTemplate($inputPath) {
    $dom = $this->getDOM($inputPath);
    $body = $this->getTemplateBody($dom);
    return $this->parse($body) . "\n";
  }

  private function getDOM($inputPath) {
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTMLFile($inputPath);
    libxml_use_internal_errors(false);
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

  private function getTemplateBody($dom) {
    $body = $dom->getElementsByTagName('body');
    if($body && $body->length > 0) {
      $body = $body->item(0);
      return $body;
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
    } else { //Comment
      return "";
    }
  }

  private function createSection($element) {
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
