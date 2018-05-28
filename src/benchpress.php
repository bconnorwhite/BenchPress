<?php

const env = 'local';
const valid_wp = ['content', 'title'];

const base_path = __DIR__ . "/base/";
const root_path = __DIR__ . "/../";
const create_path = __DIR__ . "/create.sh";
const output_path = root_path . "../output/";
const profile_path = root_path . "profile";
const header_path = output_path . "header.php";
const footer_path = output_path . "footer.php";
const page_templates_path = output_path . "page-templates/";
const inc_path = output_path . "inc/";
const acf_path = inc_path . "acf.php";

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
    createTheme($input);
  } else if(pathToFiletype($input) == "html") {
    createTemplate($input, root_path . "output.php", false);
  }
}

function saveProfile($username, $email) {
  file_put_contents(profile_path, $username . "\n" . $email);
}

function createSite($dirpath, $domain, $username, $email) {
  createTheme($dirpath);
  if(isset($domain) && isset($username) && isset($email)) {
    exec(create_path . " " . escapeshellarg(env) . " " . escapeshellarg($domain) . " " . escapeshellarg($username) . " " . escapeshellarg($email));
  }
}

function createTheme($dirpath) {
  global $groups, $fields;
  exec('cp -R ' . base_path . " " . escapeshellarg(output_path));
  $files = scandir($dirpath);
  $first = true;
  foreach($files as $file) {
    if(pathToFiletype($file) == "html") {
      createTemplate($dirpath . "/" . $file, page_templates_path . basename($file, ".html") . ".php", $first);
    }
    $first = false;
  }
  file_put_contents(acf_path, acf($groups, $fields));
}

function createTemplate($filepath, $output, $headerFooter) {
  if($headerFooter) {
    createHeader($filepath);
    createFooter($filepath);
  }
  $postTitle = toWords(pathToFilename($filepath));
  $start = "<?php\n/**\n * Template Name: " . $postTitle . " Page Template\n */\nget_header(); ?>";
  $main = getMain($filepath);
  $end = "<?php get_footer(); ?>\n";
  file_put_contents($output, $start . $main . $end);
}

function createHeader($filepath) {
  global $tab;
  $tab = 3;
  $header = parseDOMById($filepath, 'header');
  file_put_contents(header_path, $header, FILE_APPEND);
}

function createFooter($filepath) {
  global $tab;
  $tab = 2;
  $footer = parseDOMById($filepath, 'footer') . file_get_contents(footer_path);
  file_put_contents(footer_path, $footer);
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
