<?php

const create_script_path = scripts_path . "/create.sh";
const delete_script_path = scripts_path . "/delete.sh";
const theme_relative = "/wp-content/themes/";

include('theme.php');

class Site {

  var $inputPath;
  var $domain;
  var $username;
  var $email;
  var $password;
  var $path;
  var $theme;

  function __construct($inputPath, $domain, $username, $email) {
    $this->inputPath = $inputPath;
    $this->domain = $domain;
    $this->username = $username;
    $this->email = $email;
  }

  function setPath($path) {
    $this->path = $path;
  }

  function setPassword($password) {
    $this->password = $password;
  }

  function create() {
    $out;
    $retval = 0;
    $result = exec(create_script_path . " " . escapeshellarg($this->domain) . " " . escapeshellarg($this->username) . " " . escapeshellarg($this->email), $out, $retval);
    if($retval == 1) {
      $results = explode(" ", $result);
      $this->setPath($results[0]);
      $this->setPassword($results[1]);
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

  function createTheme($themeName) {
    $outputPath = $this->path . theme_relative . $themeName;
    $this->theme = new Theme($themeName, $this->inputPath, $outputPath);
    $this->theme->create();
  }

  function activateTheme() {
    if(isset($this->path)) {
      chdir($this->path);
      passthru("wp theme activate " . $this->theme->name);
    }
  }

  function clean() {
    chdir($this->path);
    passthru('wp post delete $(wp post list --post_type=page --format=ids) --force'); //Delete default pages
    passthru('wp post delete $(wp post list --post_type=post --format=ids) --force'); //Delete default posts
  }

}