<?php

const page_templates = "/page-templates/";
const img = "/img/";
const inc = "/inc/";
const acf = inc . "acf.php";
const menus = inc . "menus.php";

include_once('template.php');
include_once('page.php');

class Theme {

  var $name;
  var $themesDir;
  var $site;
  var $templates;
  var $pages;

  private $img;
  private $menus;

  function __construct($name, $themesDir, $site) {
    $this->name = $name;
    $this->themesDir = $themesDir;
    $this->site = $site;
    $this->templates = [];
    $this->pages = [];
    $this->img = [];
    $this->menus = [];
  }

  function create() {
    //Copy parent theme to themes directory
    exec('cp -R ' . essence_theme . " " . escapeshellarg($this->themesDir . essence_template));
    //Copy child theme to themes directory
    exec('cp -R ' . child_theme . " " . escapeshellarg($this->path()));
    //Set as child of Essence Theme
    file_put_contents($this->path() . "/style.css", "/*\nTheme Name:\tEssence ". $this->name . "\nTemplate:\t" . essence_template . "\n*/");
    //Copy everything from source dir except .html files to child theme
    chdir($this->site->sourceDir);
    exec('find . -not -name "*.html" -type f | cpio -pdm ' . escapeshellarg($this->path()));
    $this->activate();
    $files = scandir($this->site->sourceDir);
    foreach($files as $file) { //Convert each file in input directory to template
      $inputPath = $this->site->sourceDir . $file;
      if(pathinfo($file, PATHINFO_EXTENSION) == "html") { //Check that file is valid
        $templateDir = $this->path() . page_templates;
        $page = new Page($inputPath, $templateDir, $this->site);
        if(!$this->isDuplicate($page->template)) {
          $page->createTemplate();
          $page->createHeader($this->path());
          array_push($this->templates, $page->template);
        }
        array_push($this->pages, $page);
      }
    }
    $this->buildACFMapping();
  }

  function path() {
    return $this->themesDir . $this->templateName();
  }

  function templateName() {
    return 'wp-essence-' . strtolower(str_replace(" ", "-", $this->name));
  }

  function activate() {
    printLine("Activating theme: " . colorString($this->name, primary_color));
    $this->site->wpCLI("wp theme activate " . escapeshellarg($this->templateName()));
    $this->site->wpCLI('wp theme delete twentyseventeen'); //Don't need this anymore
  }

  function importImage($path) {
    if(array_key_exists($path, $this->img)) {
      return $this->img[$path];
    } else if(file_exists($path)) {
      $import = $this->path() . img . basename($path);
      copy($path, $import);
      $img[$path] = $import;
      return $import;
    }
  }

  function addMenu($location, $element) {
    if(!array_key_exists($location, $this->menus)) {
      $menuTitle = toWords($location);
      $this->menus[$location] = $menuTitle;
      $this->writeMenus(); //Register location
      //Create Menu
      $menuID = $this->site->wpCLI('wp menu create ' . escapeshellarg($menuTitle) . ' --porcelain');
      if(isset($menuID)) {
        //Set menu location
        $this->site->wpCLI('wp menu location assign ' . escapeshellarg($menuID) . ' ' . escapeshellarg($location));
        //Set menu content
        $this->parseMenuItems($element, $menuID, NULL);
      } else {
        printError('Could not create menu ' . $menuTitle);
      }
    }
  }

  /* ----------
  * Private Functions
  ---------- */

  /* Menus should be structured:
  <ul>
    <li>
      <a>
        <ul>...
  */
  private function parseMenuItems($element, $menuID, $parentID) {
    foreach($element->childNodes as $child) {
      if($child->tagName == 'li') {
        $itemID = NULL;
        $parent = $parentID !== NULL ? " --parent-id=$parentID" : "";
        foreach($child->childNodes as $grandchild) {
          if(isset($grandchild->tagName)) {
            if($grandchild->tagName == 'a') {
              $linkURL = '';
              foreach($grandchild->attributes as $attribute) {
                if($attribute->name == 'href') {
                  $linkURL = $attribute->value;
                }
              }
              $itemID = $this->site->wpCLI('wp menu item add-custom ' . escapeshellarg($menuID) . ' ' . escapeshellarg($grandchild->textContent) . ' ' . escapeshellarg($linkURL) .  $parent . ' --porcelain');
            } else if($grandchild->tagName == 'ul') {
              $this->parseMenuItems($grandchild, $menuID, $itemID);
            }
          } else if(isset($grandchild->wholeText)) {
            $itemID = $this->site->wpCLI('wp menu item add-custom ' . escapeshellarg($menuID) . ' ' . escapeshellarg($grandchild->textContent) . ' ' . escapeshellarg('') . $parent . ' --porcelain');
          }
        }
      }
    }
  }

  private function writeMenus() {
    $content = "<?php\n\tfunction register_menus(){\n\t\tregister_nav_menus(";
    $content .= var_export($this->menus, true);
    $content .= ");\n\t}\n\tadd_action('init', 'register_menus');";
    file_put_contents($this->path() . menus, $content);
  }

  //Check that output path doesn't conflict with any exisiting template
  private function isDuplicate($template) {
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
      $created = false;
      foreach($groups as $group) {
        $newKey = $template->getGroup();
        if($group['key'] == $newKey) {
          $created = true;
          array_push($group['location'][0], array(
            'param' => 'page_template',
            'operator' => '==',
            'value' => "page-templates/" . strtolower($template->getFileName()) . '.php'
          ));
        }
      }
      if(!$created) {
        array_push($groups, array(
          'key' => $template->getGroup(),
          'title' => $template->getName(),
          'location'=> array(
            array(
              array(
                'param' => 'page_template',
                'operator' => '==',
                'value'=> "page-templates/" . strtolower($template->getFileName()) . '.php'
              )
            )
          )
        ));
      }
      foreach($template->fields as $field) {
        array_push($fields, $field);
      }
    }
    file_put_contents($this->path() . acf,
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
