<?php

const base_path = __DIR__ . "/../base";
const page_dir = "/page-templates/";
const section_dir = "/section-templates/";
const acf_path = "/inc/acf.php";

include_once('template.php');
include_once('page.php');

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
    $this->create();
  }

  private function create() {
    //Copy base theme to site themes directory
    exec('cp -R ' . base_path . " " . escapeshellarg($this->outputPath));
    $files = scandir($this->inputPath);
    foreach($files as $file) { //Convert each file in input directory to template
      $filePath = $this->inputPath . $file;
      if(pathinfo($file, PATHINFO_EXTENSION) == "html") { //Check that file is valid
        $templateDir = $this->outputPath . page_dir;
        $sectionDir = $this->outputPath . section_dir;
        $page = new Page($filePath, $templateDir, $sectionDir);
        if(!$this->isDuplicate($page->template)) {
          $page->createTemplate();
          array_push($this->templates, $page->template);
        }
        array_push($this->pages, $page);
      }
    }
    $this->buildACFMapping();
  }

  private function isDuplicate($template) {
    //Check that output path doesn't conflict with any exisiting template
    foreach($this->templates as $existingTemplate) {
      if($existingTemplate->path == $template->path) {
        return true;
      }
    }
    return false;
  }

  private function buildACFMapping() {
    $groups = [];
    $fields = [];
    foreach($this->templates as $template) {
      foreach($template->sections as $section) {
        $created = false;
        foreach($groups as $group) {
          $newKey = $section->getGroup();
          if($group['key'] == $newKey) {
            $created = true;
            array_push($group['location'][0], array(
              'param' => 'page_template',
              'operator' => '==',
              'value' => "page-templates/" . $template->getFileName()
            ));
          }
        }
        if(!$created) {
          array_push($groups, array(
            'key' => $section->getGroup(),
            'title' => $section->getName(),
            'location'=> array(
              array(
                array(
                  'param' => 'page_template',
                  'operator' => '==',
                  'value'=> "page-templates/" . $template->getFileName()
                )
              )
            )
          ));
        }
        foreach($section->fields as $field) {
          array_push($fields, $field);
        }
      }
    }
    file_put_contents($this->outputPath . acf_path,
      "<?php\n\n" .
      '$groups = ' . var_export($groups, true) . ";\n\n" .
      '$fields = ' . var_export($fields, true) . ";\n\n" .
      "if(function_exists('acf_add_local_field_group')) {\n" .
      "\t" . 'for($g=0; $g<count($groups); $g++) {' . "\n" .
      "\t\t" . 'acf_add_local_field_group($groups[$g]);' . "\n" .
      "\t}\n" .
      "\t" . 'for($f=0; $f<count($fields); $f++) {' . "\n" .
      "\t\t" . 'acf_add_local_field($fields[$f]);' . "\n" .
      "\t}\n" .
      "}\n"
    );
  }

}
