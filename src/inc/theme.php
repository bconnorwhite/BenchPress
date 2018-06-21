<?php

const page_templates = "/page-templates/";
const section_templates = "/section-templates/";
const img = "/img/";

include_once('template.php');
include_once('page.php');

class Theme {

  var $name;
  var $path;
  var $site;
  var $templates;
  var $pages;

  private $img;

  function __construct($name, $themesDir, $site) {
    $this->name = $name;
    $this->path = $themesDir . $name;
    $this->site = $site;
    $this->templates = [];
    $this->pages = [];
    $this->img = [];
  }

  function create() {
    //Copy base theme to site themes directory
    exec('cp -R ' . base_theme . " " . escapeshellarg($this->path));
    $files = scandir($this->site->sourceDir);
    foreach($files as $file) { //Convert each file in input directory to template
      $inputPath = $this->site->sourceDir . $file;
      if(pathinfo($file, PATHINFO_EXTENSION) == "html") { //Check that file is valid
        $templateDir = $this->path . page_templates;
        $sectionDir = $this->path . section_templates;
        $page = new Page($inputPath, $templateDir, $sectionDir, $this->site);
        if(!$this->isDuplicate($page->template)) {
          $page->createTemplate();
          array_push($this->templates, $page->template);
        }
        array_push($this->pages, $page);
      }
    }
    $this->buildACFMapping();
  }

  function importImage($path) {
    if(array_key_exists($path, $this->img)) {
      return $this->img[$path];
    } else if(file_exists($path)) {
      $import = $this->path . img . basename($path);
      copy($path, $import);
      $img[$path] = $import;
      return $import;
    }
  }

  /* ----------
  * Private Functions
  ---------- */

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
    file_put_contents($this->path . "/inc/acf.php",
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
