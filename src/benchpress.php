<?php

const root_path = __DIR__ . "/../";
const profile_path = root_path . "profile";
const inc_path = __DIR__ . "/inc/";
const scripts_path = __DIR__ . "/scripts/";
const essence_template = "wp-essence";
const essence_theme = __DIR__ . "/themes/" . essence_template . "/";
const child_theme = __DIR__ . "/themes/child/";

include_once(inc_path . "cli.php");
include_once(inc_path . "site.php");

if($argc > 1) {
  $sourceDir = $argv[1];
  if(substr($sourceDir, 0, 1) !== "/" && substr($sourceDir, 0, 1) !== "~") {
    $sourceDir = getcwd() . "/" . $sourceDir;
  }
  if(substr($sourceDir, strlen($sourceDir)-1, 1) !== '/') {
    $sourceDir = $sourceDir . "/";
  }
  if($argc > 4) {
    $domain = $argv[2];
    $username = $argv[3];
    $email = $argv[4];
    saveProfile($username, $email);
    createSite($domain, $username, $email, $sourceDir);
  } else if($argc > 2) {
    createSite($argv[2], 'Connor', 'connor.bcw@gmail.com', $sourceDir);
  } else {
    printLine('Format: ' . colorString('php benchpress $source $domain', prompt_color));
  }
}

function saveProfile($username, $email) {
  file_put_contents(profile_path, $username . "\n" . $email);
}

function createSite($domain, $username, $email, $sourceDir) {
  printLine("Creating site: " . colorString($domain, primary_color));
  $site = new Site($domain, $username, $email, $sourceDir);
  $createResult = $site->create();
  if($createResult == 1) {
    $site->createTheme();
    $site->buildContent();
    $site->printCredentials();
    return 1;
  } else {
    printLine(colorString($createResult, failure_color));
    $overwrite = readline(colorString("Overwrite (y/n)? ", prompt_color));
    if(strtolower($overwrite) == 'y') {
      $deleteResult = $site->delete();
      if($deleteResult == 1) {
        return createSite($domain, $username, $email, $sourceDir);
      } else {
        printLine($deleteResult);
        return 0;
      }
    } else {
      return 0;
    }
  }
}
