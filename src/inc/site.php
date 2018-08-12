<?php

const create_script_path = scripts_path . "/create.sh";
const delete_script_path = scripts_path . "/delete.sh";
const themes_relative = "/wp-content/themes/";

include_once('theme.php');

class Site {

  var $domain;
  var $username;
  var $email;
  var $sourceDir;
  var $password;
  var $path;
  var $theme;
  var $pages;

  private $media;

  function __construct($domain, $username, $email, $sourceDir) {
    $this->domain = $domain;
    $this->username = $username;
    $this->email = $email;
    $this->sourceDir = $sourceDir;
    $this->pages = [];
    $this->media = [];
  }

  function create() {
    $out;
    $retval = 0;
    //Bash script to initalize a WordPress site, database.
    $result = exec(create_script_path . " " . escapeshellarg($this->domain) . " " . escapeshellarg($this->username) . " " . escapeshellarg($this->email), $out, $retval);
    if($retval == 1) {
      $results = explode(" ", $result);
      $this->path = $results[0];
      $this->password = $results[1];
      $this->clean();
      return 1;
    } else {
      return $result;
    }
  }

  function delete() {
    $out;
    $retval;
    $result = exec(delete_script_path . " " . escapeshellarg($this->domain), $out, $retval);
    if($retval == 0) {
      return $result;
    } else {
      return $retval;
    }
  }

  function createTheme() {
    $themesDir = $this->path . themes_relative;
    //Copy base theme
    exec('cp -R ' . base_theme . " " . escapeshellarg($themesDir));
    //Create child theme
    $themeName = $this->domainToName($this->domain);
    printLine("Creating theme: " . colorString($themeName, primary_color));
    $this->theme = new Theme($themeName, $themesDir, $this);
    $this->theme->create();
  }

  function buildContent() {
    printLine("Building content...");
    foreach($this->theme->pages as $page) { //Convert each file in input directory to template
      $this->buildPage($page);
    }
  }

  function printCredentials() {
    printLine("Username: " . colorString($this->username, secondary_color));
    printLine("Password: " . colorString($this->password, secondary_color));
    exec("echo $this->password | pbcopy");
    printLine(colorString("Success: ", success_color) . "Password has been added to clipboard.");
  }

  function wpCLI($command, $passthru = false) {
    chdir($this->path);
    if($passthru) {
      passthru($command);
    } else {
      return exec($command);
    }
  }

  function importMedia($path) {
    if(array_key_exists($path, $this->media)) {
      return $this->media[$path];
    } else {
      $id = $this->wpCLI('wp media import ' . escapeshellarg($this->sourceDir . $path) . ' --porcelain');
      $this->media[$path] = $id;
      return $id;
    }
  }

  function importImage($relativePath) {
    return $this->theme->importImage($this->sourceDir . $relativePath);
  }

  function addMenu($location, $element) {
    $this->theme->addMenu($location, $element);
  }

  /* ----------
  * Private Functions
  ---------- */

  private function clean() {
    printLine("Cleaning...");
    $this->wpCLI('wp post delete $(wp post list --post_type=page --format=ids) --force', true);
    $this->wpCLI('wp post delete $(wp post list --post_type=post --format=ids) --force', true);
    //TODO: this doesnt work b/c local nginx setup doesnt link things correctly. Probably fine on remote server
    //$this->wpCLI('wp rewrite structure ' . escapeshellarg('/%postname%/'), true);
  }

  private function domainToName($domain) {
    $split = explode(".", $domain);
    return toWords($split[count($split)-2]);
  }

  private function buildPage($page) {
    //Create page
    $id = $this->wpCLI("wp post create --post_title=" . escapeshellarg($page->getName()) . " --post_type=page --post_status=publish --porcelain");
    //Set page template
    if(isset($id)) {
      $this->wpCLI("wp post meta add $id _wp_page_template " .  escapeshellarg("page-templates/" . basename($page->template->path)));
      if($page->getName() == "Index") {
        $this->wpCLI("wp option update show_on_front page");
        $this->wpCLI("wp option update page_on_front " . $id);
      }
      //Set content meta
      foreach($page->template->sections as $section) {
        foreach($section->meta as $meta) {
          if(isset($meta['key']) && isset($meta['value'])) {
            $this->wpCLI("wp post meta add $id " . escapeshellarg($meta['key']) . " " . escapeshellarg($meta['value']));
          }
        }
      }
      printLine(colorString(checkmark, success_color) . " " . $page->getName());
    } else {
      printError("Page Creation Failed");
    }
  }
}
