<?php

const header = "/header.php";

include_once('template.php');
include_once('parser.php');

class Page {

  var $inputPath;
  var $templateDir;
  var $sectionDir;
  var $site;
  var $template;
  var $themePath;

  function __construct($inputPath, $templateDir, $site) {
    $this->inputPath = $inputPath;
    $this->templateDir = $templateDir;
    $this->site = $site;
    $this->template = new Template($this->inputPath, $this->templateDir, $site);
  }

  function getName() {
    return toWords(basename($this->inputPath, ".html"));
  }

  function createTemplate() {
    if(isset($this->template)) {
      $this->template->create();
    } else {
      printError("Missing template");
    }
  }

  function createHeader($themePath) {
    $this->themePath = $themePath;
    $parser = new Parser(1);
    $start = "<!DOCTYPE html>\n<html lang='en'>";
    $content = $parser->parse($parser->getElementByTagName($this->inputPath, 'head'));
    file_put_contents($themePath . header, $start . $content);
  }

}
