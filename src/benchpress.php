<?php

const root_path = __DIR__ . "/../";
const profile_path = root_path . "profile";
const inc_path = __DIR__ . "/inc/";
const scripts_path = __DIR__ . "/scripts/";
const base_theme = __DIR__ . "/base/";

include_once(inc_path . "cli.php");
include_once(inc_path . "site.php");

if($argc > 1) {
  $sourceDir = $argv[1];
  if($argc > 4) {
    $domain = $argv[2];
    $username = $argv[3];
    $email = $argv[4];
    saveProfile($username, $email);
    createSite($domain, $username, $email, $sourceDir);
  } else if(is_dir($sourceDir)) {
    createSite('test.com', 'Connor', 'connor.bcw@gmail.com', $sourceDir);
    //createTheme($input, output_path, $domain);
  } else if(pathToFiletype($sourceDir) == "html") {
    createTemplate($sourceDir, root_path . "output.php", false);
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
    $site->activateTheme();
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
