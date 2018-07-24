<?php

const header = "/header.php";

include_once('template.php');

class Page {

  var $inputPath;
  var $templateDir;
  var $sectionDir;
  var $site;
  var $template;
  var $tab;
  var $themePath;

  function __construct($inputPath, $templateDir, $sectionDir, $site) {
    $this->inputPath = $inputPath;
    $this->templateDir = $templateDir;
    $this->sectionDir = $sectionDir;
    $this->site = $site;
    $this->tab = 2;
    $this->template = new Template($this->inputPath, $this->templateDir, $site);
  }

  function getName() {
    return toWords(basename($this->inputPath, ".html"));
  }

  function createTemplate() {
    if(isset($this->template)) {
      $this->template->create($this->sectionDir);
    } else {
      printError("Missing template");
    }
  }

  function createHeader($themePath) {
    $this->themePath = $themePath;
    $dom = $this->getDOM($this->inputPath);
    $head = $dom->getElementsByTagName('head');
    if($head && $head->length > 0) {
      $head = $head->item(0);
      $start = "<!DOCTYPE html>\n<html lang='en'>\n\t<head>";
      $content = $this->parse($head);
      $end = "\n\t\t<?php wp_head(); ?>\n\t</head>";
      file_put_contents($themePath . header, $start . $content . $end);
    }
  }

  private function parse($element) {
    $content = "";
    if($element->childNodes) {
      foreach($element->childNodes as $child) {
        if(isset($child->tagName)) {
          $content .= $this->openTag($child);
          if($child->tagName !== "meta" && $child->tagName !== "link") {
            $content .= $this->parse($child) . $this->closeTag($child);
          }
        }
      }
    } else if(isset($element->wholeText)) {
      $content .= $element->wholeText;
    }
    return $content;
  }

  private function openTag($element) {
    $content = "\n" . $this->tabs() . "<" . $element->tagName;
    foreach($element->attributes as $attribute) {
      if($element->tagName == "link" && $attribute->name == "href" && substr($attribute->value, 0, 4) !== "http") {
        $content .= " " . $attribute->name . "='<?php echo get_template_directory_uri()?>/" . $attribute->value . "'";
      } else {
        $content .= " " . $attribute->name . "='" . $attribute->value . "'";
      }
    }
    if($element->tagName == "meta" || $element->tagName == "link") {
      return $content . "/>";
    } else {
      $this->tab++;
      return $content . ">";
    }
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
    return "\n" . $this->tabs() . "</" . $element->tagName . ">";
  }

  private function tabs() {
    $ret = "";
    for($t=0; $t<$this->tab; $t++) {
      $ret .= "\t";
    }
    return $ret;
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

}
