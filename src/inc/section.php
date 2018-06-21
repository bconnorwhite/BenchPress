<?php

class Section {

  var $element;
  var $site;
  var $path;
  var $fields;
  var $meta;

  private $tab;
  private $inRepeater;

  function __construct($element, $site) {
    $this->element = $element;
    $this->site = $site;
    $this->fields = [];
    $this->meta = [];
  }

  function setPath($outputDir) {
    foreach($this->element->attributes as $attribute) {
      if($attribute->name == "id") {
        $prefix = getPrefix($attribute->value);
        if($prefix == "section") {
          $suffix = getSuffix($attribute->value);
          $this->path = $outputDir . $suffix . ".php";
          return true;
        }
      }
    }
    return false;
  }

  function create() {
    if(isset($this->element) && isset($this->path)) {
      $this->fields = [];
      $this->tab = 0;
      $this->inRepeater = false;
      $content = $this->parse($this->element);
      file_put_contents($this->path, $content);
    }
  }

  function getGroup() {
    return "group-" . basename($this->path, ".php");
  }

  function getName() {
    return toWords(basename($this->path, ".php"));
  }

  function getFields() {
    return $this->fields;
  }

  /* ----------
  * Private Functions
  ---------- */

  private function parse($element) {
    if(isset($element->tagName)) { //Tag
      foreach($element->attributes as $attribute) {
        $prefix = getPrefix($attribute->value);
        $suffix = NULL;
        if($attribute->name == 'class') {
          if($prefix == 'wp') {
            $suffix = getSuffix($attribute->value);
            if(in_array($suffix, valid_wp)) {
              return $this->openTag($element, $suffix) . $this->wpField($suffix) . $this->closeTag($element, true);
            } else if($element->tagName == "ul") { //Menu
              return $this->wpMenu($suffix);
            }
          } else if($prefix == 'acf') {
            $suffix = getSuffix($attribute->value);
            if($element->tagName == 'img') {
              return $this->acfImgTag($element, $suffix);
            } if($element->tagName !== 'div') { //Normal acf field
              return $this->openTag($element, $suffix) . $this->acfField($suffix, $element) . $this->closeTag($element, true);
            } else if($this->isFirstRepeater($element, $suffix)) { //First acf repeater div
              return $this->acfRepeater($suffix) . $this->openTag($element, $suffix) . $this->parseChildren($element) . $this->closeTag($element, false) . $this->acfRepeaterClose();
            } else { //Not first acf repeater divs
              return "";
            }
          }
        }
      }
      if($element->tagName == 'img') {
        return $this->imgTag($element);
      } else {
        return $this->openTag($element, NULL) . $this->parseChildren($element) . $this->closeTag($element, false);
      }
    } else if(isset($element->wholeText)) { //Text
      return $element->wholeText;
    } else {
      return "";
    }
  }

  private function parseChildren($element) {
    $content = "";
    foreach($element->childNodes as $child) {
      $content .= $this->parse($child);
    }
    return $content;
  }

  private function isFirstRepeater($element) {
    if(!isset($element->previousSibling) || $element->previousSibling->tagName !== 'div') {
      return true;
    } else {
      $repeater; //If previous sibling has same repeater field, return false
      foreach($element->previousSibling->attributes as $previousAttribute) {
        if($previousAttribute->name == 'class' && getPrefix($previousAttribute->value) == 'acf') {
          foreach($element->attributes as $attribute) {
            if($attribute->name == 'class' && getPrefix($attribute->value) == 'acf') {
              if(getSuffix($previousAttribute->value) == getSuffix($attribute->value)) {
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

  private function wpMenu($location) {
    return "\n" . $this->tabs() . "<?php wp_nav_menu(array('theme_location' => '" . $location . "')); ?>";
  }

  private function getFieldName($field) {
    return $this->getGroup() . "-" . $field;
  }

  private function acfField($field, $element) {
    $this->addField($field, $element->tagName);
    if($element->tagName == "a") {
      $url = "";
      foreach($element->attributes as $attribute) {
        if($attribute->name == 'href') {
          $url = $attribute->value;
        }
      }
      array_push($this->meta, array("key"=>$this->getFieldName($field), "value"=>serialize(array("title"=>$element->textContent, "url"=>$url))));
    } else {
      array_push($this->meta, array("key"=>$this->getFieldName($field), "value"=>$element->textContent));
    }
    return "\n" . $this->tabs() . $this->ifACFExists($field, "echo the_" . $this->getSub() . "field('" . $this->getFieldName($field) . "')['title'];");
  }

  private function openTag($element, $field) {
    $content = "\n" . $this->tabs() . "<" . $element->tagName;
    foreach($element->attributes as $attribute) {
      if($field && $attribute->name == 'href') {
        $content .= " href=\"" . $this->ifACFExists($field, "echo the_" . $this->getSub() . "field('" . $this->getFieldName($field) . "')['url'];") . "\"";
        $content .= " target=\"" . $this->ifACFExists($field, "echo the_" . $this->getSub() . "field('" . $this->getFieldName($field) . "')['target'];") . "\"";
      } else {
        $content .= " " . $attribute->name . "='" . $attribute->value . "'";
      }
    }
    $this->tab++;
    return $content . ">";
  }

  private function ifACFExists($field, $content) {
    return  "<?php if(get_" . $this->getSub() . "field('" . $this->getFieldName($field) . "') !== '') { " . $content . "}?>";
  }

  private function wpField($field) {
    return "\n" . $this->tabs() . "<?php the_" . $field . "(); ?>";
  }

  private function closeTag($element, $newline) {
    $this->tab--;
    $newline |= ($element->childNodes->length > 1 || ($element->childNodes->length == 1 && !isset($element->childNodes[0]->wholeText)));
    return ($newline ? "\n" . $this->tabs() : "") . "</" . $element->tagName . ">";
  }

  private function imgTag($element) {
    global $sourceDir;
    $content = "<img src=";
    foreach($element->attributes as $attribute) {
      if($attribute->name == 'src') {
        /*echo($sourceDir . $attribute->value);
        echo("\n");
        echo file_exists($sourceDir . $attribute->value);
        echo("\n");*/
      }
    }
  }

  private function acfImgTag($element, $field) {
    $content = "\n" . $this->tabs() . "<" . $element->tagName;
    foreach($element->attributes as $attribute) {
      if($field && $attribute->name == 'src') {
        $content .= " src=\"" . $this->ifACFExists($field, "echo the_" . $this->getSub() . "field('" . $this->getFieldName($field) . "');") . "\"";
        $this->addField($field, $element->tagName);
        //TODO: import image into WordPress
        //TODO: add meta for that image
      } else {
        $content .= " " . $attribute->name . "='" . $attribute->value . "'";
      }
    }
    return $content . "/>";
  }

  private function importImage($path) {

  }

  private function getSub() {
    return $this->inRepeater ? "sub_" : "";
  }

  private function addField($field, $tag) {
    $settings = array(
      'key' => $this->getGroup() . "-field-" . $field,
      'label' => toWords($field),
      'name' => $this->getFieldName($field),
      'type' => 'text',
      'parent' => $this->getGroup(),
    );
    if($tag == 'a') {
      $settings['type'] = 'link';
      $settings['return_format'] = 'array';
    } else if($tag == 'img') {
      $settings['type'] = 'image';
      $settings['return_format'] = 'url';
    }
    array_push($this->fields, $settings);
  }

  private function acfRepeater($field) {
    $this->inRepeater = true;
    $key = $this->getGroup() . '-field-' . $field;
    array_push($this->fields, array(
      'key' => $key,
      'label' => toWords($field),
      'name' => $this->getFieldName($field),
      'type' => 'repeater',
      'parent' => $this->getGroup(),
    ));
    $retval = "\n" . $this->tabs() . "<?php if(get_field('" . $this->getFieldName($field) . "') !== '') {" . "\n";
    $this->tab+=1;
    $retval .= $this->tabs() . "while(have_rows('" . $this->getFieldName($field) . "')) { the_row(); ?>";
    $this->tab+=1;
    return $retval;
  }

  private function acfRepeaterClose() {
    $this->tab-=1;
    $retval = "\n" . $this->tabs() . "<?php }";
    $this->tab-=1;
    $retval .= "\n" . $this->tabs() . "} ?>";
    return $retval;
  }

  private function tabs() {
    $ret = "";
    for($t=0; $t<$this->tab; $t++) {
      $ret .= "\t";
    }
    return $ret;
  }

}
