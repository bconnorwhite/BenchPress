<?php

include_once('template.php');

class Page {

  var $inputPath;
  var $templateDir;
  var $sectionDir;
  var $template;

  function __construct($inputPath, $templateDir, $sectionDir) {
    $this->inputPath = $inputPath;
    $this->templateDir = $templateDir;
    $this->sectionDir = $sectionDir;
    $this->template = new Template($this->inputPath, $this->templateDir);
  }

  function getName() {
    return toWords(basename($this->inputPath, ".html"));
  }

  function createTemplate() {
    if(isset($this->template)) {
      $this->template->create($this->sectionDir);
    } else {
      echo("Missing template\n");
    }
  }

}
