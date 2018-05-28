<?php

const valid_wp = ['content', 'title'];

const default_color = "\e[39m";
const primary_color = "\e[96m";
const secondary_color = "\e[95m";
const prompt_color = "\e[93m";
const success_color = "\e[92m";
const failure_color = "\e[91m";

const checkmark = "\xE2\x9C\x94";

const base_path = __DIR__ . "/base/";
const root_path = __DIR__ . "/../";
const create_path = __DIR__ . "/create.sh";
const delete_path = __DIR__ . "/delete.sh";
const output_path = root_path . "../output/";
const profile_path = root_path . "profile";
const header = "header.php";
const footer = "footer.php";
const page_templates = "page-templates/";
const inc = "inc/";
const acf = inc . "acf.php";
const style = "style.css";
const theme_relative = "/wp-content/themes/";

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

function createSite($dirpath, $domain, $username, $email) {
  printLine("Creating site: " . colorString($domain, primary_color));
  $out;
  $retval = 0;
  $result = exec(create_path . " " . escapeshellarg($domain) . " " . escapeshellarg($username) . " " . escapeshellarg($email), $out, $retval);
  if($retval == 1) {
    $result = explode(" ", $result);
    $site = $result[0];
    $pages = createTheme($dirpath, $site . theme_relative . $domain . "/", $domain);
    activateTheme($site, $domain);
    createPages($pages, $site);
    printLine("Username: " . colorString($username, secondary_color));
    printLine("Password: " . colorString($result[1], secondary_color));
  } else {
    printLine(colorString($result, failure_color));
    $line = readline(colorString("Overwrite (y/n)? ", prompt_color));
    if($line == 'y' || $line == 'Y') {
      $deleted = deleteSite($domain);
      if($deleted == 1) {
        createSite($dirpath, $domain, $username, $email);
      }
    }
  }
}

function deleteSite($domain) {
  $out;
  $retval = 0;
  $result = exec(delete_path . " " . escapeshellarg($domain), $out, $retval);
  if($retval == 0) {
    echo $result;
  }
  return $retval;
}

function activateTheme($sitePath, $themeName) {
  printLine("Activating theme: " . colorString($themeName, primary_color));
  chdir($sitePath);
  passthru("wp theme activate " . $themeName);
}

function createTheme($dirpath, $output, $themeName) {
  global $groups, $fields;
  printLine("Creating theme: " . colorString($themeName, primary_color));
  exec('cp -R ' . base_path . " " . escapeshellarg($output));
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
  file_put_contents($output . style, "/*\nTheme Name: " . $themeName . "\n*/");
  return $pages;
}

function colorString($string, $color) {
  return $color . $string . "\e[39m";
}

function printLine($string) {
  echo $string . "\n";
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

function createTemplate($filepath, $output) {
  $postTitle = toWords(pathToFilename($filepath));
  $start = "<?php\n/**\n * Template Name: " . $postTitle . " Page Template\n */\nget_header(); ?>";
  $main = getMain($filepath);
  $end = "<?php get_footer(); ?>\n";
  file_put_contents($output, $start . $main . $end);
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

function getMain($filepath) {
  global $tab, $parentKeys;
  $tab = 0;
  $parentKeys = [];
  return parseDOMById($filepath, 'main');
}

function parseDOMById($filepath, $id) {
  $dom = new DOMDocument;
  $dom->loadHTMLFile($filepath);
  cleanDOM($dom);
  return parse($dom->getElementById($id)) . "\n";
}

function cleanDOM($dom) {
  //Clean of empty text nodes
  $xpath = new DOMXPath($dom);
  foreach($xpath->query('//text()') as $node) {
    if(ctype_space($node->wholeText)) {
      $node->parentNode->removeChild($node);
    }
  }
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

function parse($element) {
  if(isset($element->tagName)) { //Tag
    foreach($element->attributes as $attribute) {
      $prefix = getPrefix($attribute);
      $suffix = NULL;
      if($attribute->name == 'id') {
        if($prefix == 'acf') {
          newGroup(getSuffix($attribute));
        }
      } else if($attribute->name == 'class') {
        if($prefix == 'wp') {
          $suffix = getSuffix($attribute);
          if(in_array($suffix, valid_wp)) {
            return openTag($element, $suffix) . wpField($suffix) . closeTag($element, true);
          } else if($element->tagName == "ul") { //Menu
            return wpMenu($suffix);
          }
        } else if($prefix == 'acf') {
          $suffix = getSuffix($attribute);
          if($element->tagName == 'img') {
            return singleTag($element, $suffix);
          } if($element->tagName !== 'div') {
            return openTag($element, $suffix) . acfField($suffix, $element->tagName) . closeTag($element, true);
          } else if(isFirstRepeater($element, $suffix)) { //First acf repeater div
              return acfRepeater($suffix) . openTag($element, $suffix) . parseChildren($element) . closeTag($element, false) . acfRepeaterClose();
          } else { //Not first acf repeater divs
            return "";
          }
        }
      }
    }
    return openTag($element, NULL) . parseChildren($element) . closeTag($element, false);
  } else if(isset($element->wholeText)) { //Text
    return $element->wholeText;
  } else {
    return "";
  }
}

function newGroup($name) {
  global $groups, $parentKeys;
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

function isFirstRepeater($element) {
  if(!isset($element->previousSibling) || $element->previousSibling->tagName !== 'div') {
    return true;
  } else {
    $repeater; //If previous sibling has same repeater field, return false
    foreach($element->previousSibling->attributes as $previousAttribute) {
      if($previousAttribute->name == 'class' && getPrefix($previousAttribute) == 'acf') {
        foreach($element->attributes as $attribute) {
          if($attribute->name == 'class' && getPrefix($attribute) == 'acf') {
            if(getSuffix($previousAttribute) == getSuffix($attribute)) {
              return false;
            }
            break;
          }
        }
        break;
      }
    }
    return true; //No matching repeater field
  }
}

function parseChildren($element) {
  $content = "";
  foreach($element->childNodes as $child) {
    $content .= parse($child);
  }
  return $content;
}

function getPrefix($attribute) {
  return explode('-', $attribute->value)[0];
}

function getSuffix($attribute) {
  $split = explode('-', $attribute->value);
  return count($split) > 1 ? $split[1] : "";
}

function wpField($field) {
  global $tab;
  return "\n" . tabs($tab) . "<?php the_" . $field . "(); ?>";
}

function wpMenu($location) {
  global $tab;
  return "\n" . tabs($tab) . "<?php wp_nav_menu(array('theme_location' => '" . $location . "')); ?>";
}

function acfField($field, $tag) {
  global $tab;
  addField($field, $tag);
  return "\n" . tabs($tab) . ifACFExists($field, "echo the_" . getSub() . "field('" . $field . "')['title']");
}

function addField($field, $tag) {
  global $fields, $parentKeys;
  $settings = array(
    'key' => 'field_' . $field,
    'label' => toWords($field),
    'name' => $field,
    'type' => 'text',
    'parent' => end($parentKeys),
  );
  if($tag == 'a') {
    $settings['type'] = 'link';
    $settings['return_format'] = 'array';
  } else if($tag == 'img') {
    $settings['type'] = 'image';
    $settings['return_format'] = 'url';
  }
  array_push($fields, $settings);
}

function acfRepeater($field) {
  global $tab, $fields, $parentKeys, $inRepeater;
  $inRepeater = true;
  $key = 'field_' . $field;
  array_push($fields, array(
    'key' => $key,
    'label' => toWords($field),
    'name' => $field,
    'type' => 'repeater',
    'parent' => end($parentKeys),
  ));
  array_push($parentKeys, $key);
  $tab+=2;
  return "\n" . tabs($tab-2) . "<?php if(get_field('" . $field . "') !== '') {" .
  "\n" . tabs($tab-1) . "while(have_rows('" . $field . "')) { the_row(); ?>";
}

function acfRepeaterClose() {
  global $tab, $parentKeys;
  array_pop($parentKeys);
  $tab-=2;
  return "\n" . tabs($tab+1) . "<?php }" .
  "\n" . tabs($tab) . "} ?>";
}

function openTag($element, $field) {
  global $tab;
  $content = "\n" . tabs($tab) . "<" . $element->tagName;
  foreach($element->attributes as $attribute) {
    if($field && $attribute->name == 'href') {
      $content .= " href=\"" . ifACFExists($field, "echo the_" . getSub() . "field('" . $field . "')['url']") . "\"";
      $content .= " target=\"" . ifACFExists($field, "echo the_" . getSub() . "field('" . $field . "')['target']") . "\"";
    } else {
      $content .= " " . $attribute->name . "='" . $attribute->value . "'";
    }
  }
  $tab++;
  return $content . ">";
}

function closeTag($element, $newline) {
  global $tab;
  $tab--;
  $newline |= ($element->childNodes->length > 1 || ($element->childNodes->length == 1 && !isset($element->childNodes[0]->wholeText)));
  return ($newline ? "\n" . tabs($tab) : "") . "</" . $element->tagName . ">";
}

//For single tags, i.e. <img />
function singleTag($element, $field) {
  global $tab, $inRepeater;
  $content = "\n" . tabs($tab) . "<" . $element->tagName;
  foreach($element->attributes as $attribute) {
    if($field && $attribute->name == 'src') {
      $content .= " src=\"" . ifACFExists($field, "echo the_" . getSub() . "field('" . $field . "')") . "\"";
      addField($field, $element->tagName);
    } else {
      $content .= " " . $attribute->name . "='" . $attribute->value . "'";
    }
  }
  return $content . "/>";
}

function tabs($n) {
  $ret = "";
  for($t=0; $t<$n; $t++) {
    $ret .= "\t";
  }
  return $ret;
}

function ifACFExists($field, $content) {
  global $inRepeater;
  $sub = $inRepeater ? "sub_" : "";
 return  "<?php if(get_" . getSub() . "field('" . $field . "') !== '') { " . $content . "}?>";
}

function getSub() {
  global $inRepeater;
  return $inRepeater ? "sub_" : "";
}
