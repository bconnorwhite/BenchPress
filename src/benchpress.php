<?php

const valid_wp = ['content', 'title'];
const base_path = __DIR__ . "/base/";
const root_path = __DIR__ . "/../../";
$groups = [];
$fields = [];
$tab;
$parentKeys;
if($argc > 1) {
  $input = $argv[1];
  if(is_dir($input)) {
    createTheme($input);
  } else if(pathToFiletype($input) == "html") {
    $template = createTemplate($input);
    file_put_contents(root_path . "output.php", $template);
  }
}

function createTheme($dirpath) {
  global $groups, $fields;
  $themePath = root_path . "output";
  exec('cp -R ' . base_path . " " . escapeshellarg($themePath));
  $files = scandir($dirpath);
  foreach($files as $file) {
    if(pathToFiletype($file) == "html") {
      $template = createTemplate($dirpath . "/" . $file);
      file_put_contents($themePath . "/page-templates/" . basename($file, ".html") . ".php", $template);
    }
  }
  file_put_contents($themePath."/acf.php", acf($groups, $fields));
}

function createTemplate($filepath) {
  $header = "<?php\n/**\n * Template Name: " . toWords(pathToFilename($filepath)) . " Page Template\n */\nget_header(); ?>";
  $main = getMain($filepath);
  $footer = "<?php get_footer(); ?>\n";
  return $header . $main . $footer;
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

function getMain($filepath) {
  global $tab, $parentKeys;
  $tab = 0;
  $parentKeys = [];
  $dom = new DOMDocument;
  $dom->loadHTMLFile($filepath);
  cleanDOM($dom);
  return parse($dom->getElementById('main')) . "\n";
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
        if($prefix == 'wp' && in_array($prefix, valid_wp)) {
          $suffix = getSuffix($attribute);
          return openTag($element, $suffix) . wpField($suffix) . closeTag($element, true);
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
    return openTag($element, $suffix) . parseChildren($element) . closeTag($element, false);
  } else if(isset($element->wholeText)) { //Text
    return $element->wholeText;
  } else {
    return "";
  }
}

function newGroup($name) {
  global $groups, $parentKeys;
  $key = 'group_' . $name;
  var_dump($name);
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

function acfField($field, $tag) {
  global $tab;
  addField($field, $tag);
  return "\n" . tabs($tab) . "<?php if(get_field('" . $field . "') !== '') { the_field('" . $field . "')['title']; } ?>";
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
  global $tab, $fields, $parentKeys;
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
      $content .= " href=\"<?php if(get_field('" . $field . "') !== '') { echo get_field('" . $field . "')['url']}?>\"";
      $content .= " target=\"<?php if(get_field('" . $field ."') !== '') { echo get_field('" . $field . "')['target']}?>\"";
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
  global $tab;
  $content = "\n" . tabs($tab) . "<" . $element->tagName;
  foreach($element->attributes as $attribute) {
    if($field && $attribute->name == 'src') {
      $content .= " src=\"<?php if(get_field('" . $field . "') !== '') { echo the_field('" . $field . "')}?>\"";
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
