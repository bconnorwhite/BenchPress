<?php

const default_color = "\e[39m";
const primary_color = "\e[96m";
const secondary_color = "\e[95m";
const prompt_color = "\e[93m";
const success_color = "\e[92m";
const failure_color = "\e[91m";

const checkmark = "\xE2\x9C\x94";

const root_path = __DIR__ . "/../";
const scripts_path = __DIR__ . "/scripts/";

const output_path = root_path . "../output/";
const profile_path = root_path . "profile";
const inc = "inc/";
const acf = inc . "acf.php";
const style = "style.css";

$groups = [];
$fields = [];
$tab;
$parentKeys;
$inRepeater = false;

if($argc > 1) {
  $input = $argv[1];
  if($argc > 4) {
    $domain = $argv[2];
    $username = $argv[3];
    $email = $argv[4];
    saveProfile($username, $email);
    createSite($input, $domain, $username, $email);
  } else if(is_dir($input)) {
    createSite($input, 'test.com', 'Connor', 'connor.bcw@gmail.com');
    //createTheme($input, output_path, $domain);
  } else if(pathToFiletype($input) == "html") {
    createTemplate($input, root_path . "output.php", false);
  }
}

function saveProfile($username, $email) {
  file_put_contents(profile_path, $username . "\n" . $email);
}

function createSite($inputPath, $domain, $username, $email) {
  printLine("Creating site: " . colorString($domain, primary_color));
  include_once(__DIR__ . "/inc/site.php");
  $site = new Site($inputPath, $domain, $username, $email);
  $createResult = $site->create();
  if($createResult == 1) {
    $themeName = domainToName($domain);
    printLine("Creating theme: " . colorString($themeName, primary_color));
    $site->createTheme($themeName);
    /*printLine("Activating theme: " . colorString($themeName, primary_color));
    $site->activateTheme();
    $site->createPages();*/
    printLine("Username: " . colorString($site->username, secondary_color));
    printLine("Password: " . colorString($site->password, secondary_color));
    return 1;
  } else {
    printLine(colorString($createResult, failure_color));
    $overwrite = readline(colorString("Overwrite (y/n)? ", prompt_color));
    if($overwrite == 'y' || $overwrite == 'Y') {
      $deleteResult = $site->delete();
      if($deleteResult == 1) {
        return createSite($inputPath, $domain, $username, $email);
      } else {
        printLine($deleteResult);
        return 0;
      }
    } else {
      return 0;
    }
  }
}

function colorString($string, $color) {
  return $color . $string . "\e[39m";
}

function printLine($string) {
  echo $string . "\n";
}

function domainToName($domain) {
  $split = explode(".", $domain);
  return toWords($split[count($split)-2]);
}

function toWords($string) {
  return ucwords(str_replace("_", " ", $string));
}

function getPrefix($string) {
  return explode('-', $string)[0];
}

function getSuffix($string) {
  $split = explode('-', $string);
  return count($split) > 1 ? $split[1] : "";
}

/*
function createTheme($dirpath, $output, $themeName) {
  global $groups, $fields;

  $files = scandir($dirpath);
  $first = true;
  $pages = [];
  foreach($files as $file) {
    $filepath = $dirpath . $file;
    if(pathToFiletype($file) == "html") {
      $page = basename($file, ".html");
      $template = page_templates . $page . ".php";
      createTemplate($filepath, $output . $template);
      array_push($pages, array("name" => toWords($page), "template" => $template));
      if($first) {
        createHeader($filepath, $output . header);
        createFooter($filepath, $output . footer);
        $first = false;
      }
    }
  }
  file_put_contents($output . acf, acf($groups, $fields));
  file_put_contents($output . style, "\nTheme Name: " . $themeName . "\n");
  return $pages;
}

private function newGroup($name) {
  $key = 'group_' . $name;
  $group = array(
    'key' => $key,
    'title' => toWords($name),
    'fields'=> array(),
    'location'=> array(
      array(
        array(
          'param' => 'page_template',
          'operator' => '==',
          'value'=> "page-templates/$name.php"
        )
      )
    )
  );
  array_push($groups, $group);
  array_push($parentKeys, $key);
}



function deletePages($site) {
  chdir($site);
  printLine("Deleting default pages...");
  passthru('wp post delete $(wp post list --post_type=page --format=ids) --force'); //Delete default pages
  passthru('wp post delete $(wp post list --post_type=post --format=ids) --force'); //Delete default posts
}

function createPages($pages, $site) {
  deletePages($site);
  printLine("Creating pages...");
  chdir($site);
  foreach($pages as $page) {
    $id = exec("wp post create --post_title=" . escapeshellarg($page['name']) . " --post_type=page --post_status=publish --porcelain");
    exec("wp post meta add $id _wp_page_template " .  $page['template']);
    printLine(colorString(checkmark, success_color) . " " . $page['name']);
  }
}

function createHeader($filepath, $output) {
  global $tab;
  $tab = 3;
  $header = parseDOMById($filepath, 'header');
  file_put_contents($output, $header, FILE_APPEND);
}

function createFooter($filepath, $output) {
  global $tab;
  $tab = 2;
  $footer = parseDOMById($filepath, 'footer') . file_get_contents($output);
  file_put_contents($output, $footer);
}

function pathToFiletype($path) {
  $split = explode(".", $path);
  return end($split);
}

function pathToFilename($path) {
  $split = explode("/", $path);
  return explode(".", end($split))[0];
}

function toWords($tag) {
  return ucwords(str_replace("_", " ", $tag));
}

function acf($groups, $fields) {
  return "<?php\n\n" .
  '$groups = ' . var_export($groups, true) . ";\n\n" .
  '$fields = ' . var_export($fields, true) . ";\n\n" .
  "if(function_exists('acf_add_local_field_group')) {\n" .
  "\t" . 'for($g=0; $g<count($groups); $g++) {' . "\n" .
  "\t\t" . 'acf_add_local_field_group($groups[$g]);' . "\n" .
  "\t}\n" .
  "\t" . 'for($f=0; $f<count($fields); $f++) {' . "\n" .
  "\t\t" . 'acf_add_local_field($fields[$f]);' . "\n" .
  "\t}\n" .
  "}\n";
}
*/
