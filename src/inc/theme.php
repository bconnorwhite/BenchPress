<?php

const base_path = __DIR__ . "/../base";
const page_dir = "/page-templates/";
const section_dir = "/section-templates/";

class Theme {

  var $name;
  var $outputPath;
  var $inputPath;
  var $templates;
  var $pages;

  function __construct($name, $inputPath, $outputPath) {
    $this->name = $name;
    $this->inputPath = $inputPath;
    $this->outputPath = $outputPath;
    $this->templates = [];
    $this->pages = [];
  }

  function create() {
    exec('cp -R ' . base_path . " " . escapeshellarg($this->outputPath));
    include_once('template.php');
    $files = scandir($this->inputPath);
    foreach($files as $file) {
      $filePath = $this->inputPath . $file;
      if(pathinfo($file, PATHINFO_EXTENSION) == "html") { //file is valid
        $template = new Template();
        $template->setPath($filePath, $this->outputPath . page_dir);
        $duplicate = false;
        foreach($this->templates as $t) {
          if($t->path == $template->path) {
            $duplicate = true;
          }
        }
        if(!$duplicate) {
          $template->create($this->outputPath . section_dir);
          array_push($this->templates, $template);
        }
        //TODO: build pages
      }
    }
    //TODO: build acf
  }

}
