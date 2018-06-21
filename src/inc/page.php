<?php

include_once('template.php');

class Page {

  var $inputPath;
  var $templateDir;
  var $sectionDir;
  var $site;
  var $template;

  function __construct($inputPath, $templateDir, $sectionDir, $site) {
    $this->inputPath = $inputPath;
    $this->templateDir = $templateDir;
    $this->sectionDir = $sectionDir;
    $this->site = $site;
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

}
