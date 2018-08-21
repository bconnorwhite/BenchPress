<?php

const single_tags = ['meta', 'link', 'img', 'br'];

class Parser {

  var $template;

  private $tab;

  function __construct($tab, $template=NULL) {
    $this->tab = $tab;
    $this->template = $template;
  }

  function getElementByTagName($inputPath, $tagName) {
    $dom = $this->getDOM($inputPath);
    $elements = $dom->getElementsByTagName($tagName);
    if($elements && $elements->length > 0) {
      return $elements->item(0);
    } else {
      return NULL;
    }
  }

  function parse($element) {
    $content = "";
    if(isset($element)) {
      if(isset($element->wholeText)) {
        $content .= $element->wholeText;
      } else if(isset($element->tagName)) {
        $fieldName = $this->getFieldName($element);
        $firstInRepeater = $this->firstInRepeater($element);
        if($firstInRepeater) {
          $content .= $this->openRepeater();
        }
        $content .= $this->openTag($element, $fieldName);
        if(!$this->isSingleTag($element)) {
          $content .= $this->parseInner($element, $fieldName);
          $content .= $this->closeTag($element);
        }
        if($this->lastInRepeater($element)) {
          $content = $this->closeRepeater(); //If last only return close to the repeater
        } else if($this->inRepeater($element)) {
          $this->template->loopRepeater();
          if(!$firstInRepeater) { //Only return if first or last in repeater
            return "";
          }
        }
      }
    }
    return $content;
  }

  /* ----------
  * Private Functions
  ---------- */

  private function firstInRepeater($element) {
    return isset($this->template) && isset($element->nextSibling) && $this->matchingStructure($element, $element->nextSibling) && (!isset($element->previousSibling) || !$this->matchingStructure($element, $element->previousSibling));
  }

  private function lastInRepeater($element) {
    return isset($this->template) && isset($element->previousSibling) && $this->matchingStructure($element, $element->previousSibling) && (!isset($element->nextSibling) || !$this->matchingStructure($element, $element->nextSibling));
  }

  private function inRepeater($element) {
    return isset($this->template) && (isset($element->previousSibling) && $this->matchingStructure($element, $element->previousSibling)) || (isset($element->nextSibling) && $this->matchingStructure($element, $element->nextSibling));
  }

  private function matchingStructure($element, $sibling) {
    if(isset($element->wholeText) || isset($sibling->wholeText)) {
      return true;
    } else if(isset($element->tagName) && isset($sibling->tagName)) {
      if($element->tagName == $sibling->tagName) {
        if($element->tagName == 'A') {
          return true;
        } else if(count($element->attributes) == count($sibling->attributes) && count($element->childNodes) == count($sibling->childNodes)) {
          foreach($element->attributes as $attribute) {
            if(!isset($sibling->attributes[$attribute->name]) || $sibling->getAttribute($attribute->name) !== $attribute->value) {
              return false;
            }
          }
          if($this->getStructure($element)['hasText'] && $this->getStructure($sibling)['hasText']) {
            return true;
          } else {
            for($c=0; $c<count($element->childNodes); $c++) {
              if(!$this->matchingStructure($element->childNodes[$c], $sibling->childNodes[$c])) {
                return false;
              }
            }
            return true;
          }
        }
      } else {
        return false;
      }
    } else { //Shouldn't happen...
      printError("ERROR: parser.php: matchingStructure()", failure_color);
      var_dump($element);
      var_dump($sibling);
      return false;
    }
  }

  private function openRepeater() {
    $fieldName = $this->template->addField(null, 'repeater');
    $content = "\n" . $this->tabs() . "<?php $" . str_replace("-", "_", $fieldName) . "_counter = -1; while(have_rows('" . $fieldName . "')) { the_row(); $" . str_replace("-", "_", $fieldName) . "_counter++; ?>";
    $this->tab++;
    return $content;
  }

  private function closeRepeater() {
    $this->template->closeRepeater();
    $this->tab--;
    return "\n" . $this->tabs() . "<?php } ?>";
  }

  private function getFieldName($element) {
    if(isset($this->template)) {
      if($element->tagName == 'a') {
        return $this->template->addField($element, 'link');
      } else if($element->tagName == 'img') {
        return $this->template->addField($element, 'image');
      } else {
        $structure = $this->getStructure($element);
        if($structure['hasText']) {
          if($structure['hasTags']) {
            return $this->template->addField($element, 'wysiwyg');
          } else if($structure['hasBR'] || strlen($element->textContent) > 40) {
            return $this->template->addField($element, 'textarea');
          } else {
            return $this->template->addField($element, 'text');
          }
        }
      }
    }
    return NULL;
  }

  private function getStructure($element) {
    $structure =  array("hasText"=>false, "hasBR"=>false, "hasTags"=>false);
    foreach($element->childNodes as $child) {
      if(isset($child->wholeText)) { //Has a text node
        $structure['hasText'] = true;
      } else if($child->tagName == 'br') {
        $structure['hasBR'] = true;
      } else {
        $structure['hasTags'] = true;
      }
    }
    return $structure;
  }

  private function getFieldPrefix() {
    if(isset($this->template->repeater)) {
      return $this->template->repeater['key'] . "_<?php echo $" . str_replace("-", "_", $this->template->repeater['key']) . "_counter; ?>_";
    } else {
      return "";
    }
  }

  private function openTag($element, $fieldName) {
    $content = "\n" . $this->tabs() . "<" . $element->tagName;
    if(isset($fieldName) && isset($this->template)) {
      $content .= " field='" . $this->getFieldPrefix() . $fieldName . "'";
    }
    foreach($element->attributes as $attribute) {
      if($attribute->name == 'src') {
        if($element->tagName == "img" && isset($fieldName)) {
          $content .= " src='" . $this->ifACFExists($fieldName, "echo(wp_get_attachment_image_url(get_" . $this->getSub() . "field('" . $fieldName . "'), 'fullsize'));") . "'";
        } else if($this->urlIsRelative($attribute->value)) {
          $content .= " src='<?php echo get_stylesheet_directory_uri()?>/" . $attribute->value . "'";
        } else {
          $content .= " " . $attribute->name . "='" . $attribute->value . "'";
        }
      } else if($attribute->name == 'href') {
        if($element->tagName == 'a' && isset($fieldName)) {
          $content .= " href=\"" . $this->ifACFExists($fieldName, "echo get_" . $this->getSub() . "field('" . $fieldName . "')['url'];") . "\"";
          $content .= " target=\"" . $this->ifACFExists($fieldName, "echo get_" . $this->getSub() . "field('" . $fieldName . "')['target'];") . "\"";
          $content .= " alt=\"" . $this->ifACFExists($fieldName, "echo get_" . $this->getSub() . "field('" . $fieldName . "')['alt'];") . "\"";
        } else if($this->urlIsRelative($attribute->value)) {
            $content .= " " . $attribute->name . "='<?php echo get_stylesheet_directory_uri()?>/" . $attribute->value . "'";
        } else {
          $content .= " " . $attribute->name . "='" . $attribute->value . "'";
        }
      } else if($attribute->name == 'style') {
        $content .= $this->parseStyle($attribute->value, $fieldName);
      } else {
        $content .= " " . $attribute->name . "='" . $attribute->value . "'";
      }
    }
    if($this->isSingleTag($element)) {
      $content .= "/>";
    } else {
      $this->tab++;
      $content .= ">";
    }
    return $content;
  }

  private function parseInner($element, $fieldName) {
    if(isset($this->template) && isset($fieldName)) {
      $type = $this->template->getFieldType($fieldName);
      if($type == 'wysiwyg') {
        return "\n" . $this->tabs() . $this->ifACFExists($fieldName, "echo str_replace(array(\"\\r\\n\", \"\\n\", \"\\r\"), '', nl2br(get_" . $this->getSub() . "field('" . $fieldName . "', false, false)));");
      } else if($type == 'textarea' || $type == 'text') {
        return "\n" . $this->tabs() . $this->ifACFExists($fieldName, "echo str_replace(array(\"\\r\\n\", \"\\n\", \"\\r\"), '', get_" . $this->getSub() . "field('" . $fieldName . "'));");
      } else if($type == "link") {
        return "\n" . $this->tabs() . $this->ifACFExists($fieldName, "echo get_" . $this->getSub() . "field('" . $fieldName . "')['title'];");
      }
    }
    return $this->parseChildren($element);
  }

  private function parseChildren($element) {
    $content = "";
    foreach($element->childNodes as $child) {
      $content .= $this->parse($child);
    }
    return $content;
  }

  private function closeTag($element) {
    $content = "";
    if($element->tagName == 'body') { //Don't close body tag, this will happen in footer.php
      return $content;
    } else if ($element->tagName == 'head') {
      $content .= "\n" . $this->tabs() . "<?php wp_head(); ?>";
    }
    $this->tab--;
    return $content . "\n" . $this->tabs() . "</" . $element->tagName . ">";
  }

  private function tabs() {
    $ret = "";
    for($t=0; $t<$this->tab; $t++) {
      $ret .= "\t";
    }
    return $ret;
  }

  private function isSingleTag($element) {
    return in_array($element->tagName, single_tags);
  }

  private function parseStyle($style, $fieldName) {
    $content = " style='";
    $attributes = explode(";", $style);
    foreach($attributes as $attribute) {
      $pair = explode(":", $attribute);
      if(trim($pair[0]) == "background-image" || trim($pair[0]) == "background") {
        $content .= $pair[0] . ":";
        $urlStart = strpos($pair[1], "url(") + strlen("url(");
        $urlEnd = strpos(substr($pair[1], $urlStart), ")");
        $url = trim(substr($pair[1], $urlStart, $urlEnd), "'\"");
        if(isset($this->template)) {
          $bgFieldName = $this->template->addField(NULL, 'image', $url);
          if($bgFieldName) {
            $content = " bg='" . $bgFieldName . "'" . $content;
            if(!isset($fieldName)) {
              $content = " field" . $content;
            }
            $url = $this->ifACFExists($bgFieldName, "echo(wp_get_attachment_image_url(get_" . $this->getSub() . "field('" . $bgFieldName . "'), 'fullsize'));");
            $content .= substr($pair[1], 0, $urlStart) . $url . ")" . substr($pair[0], $urlEnd) . ";";
          } else {
            $content .= $pair[1] . ";";
          }
        } else if($this->urlIsRelative($url)) {
          $content .= substr($pair[1], 0, $urlStart) . "<?php echo get_stylesheet_directory_uri() ?>/" . $url . ")" . substr($pair[0], $urlEnd) . ";";
        } else {
          $content .= $pair[1] . ";";
        }
      } else if($attribute !== "") {
        $content .= $attribute . ";";
      }
    }
    return $content . "'";
  }

  private function ifACFExists($fieldName, $content) {
    return  "<?php if(get_" . $this->getSub() . "field('" . $fieldName . "') !== '') { " . $content . "}?>";
  }

  private function getSub() {
    return (isset($this->template) && isset($this->template->repeater)) ? "sub_" : "";
  }

  private function urlIsRelative($url) {
    return substr($url, 0, 4) !== "http";
  }

  private function getDOM($inputPath) {
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTMLFile($inputPath);
    libxml_use_internal_errors(false);
    $this->cleanDOM($dom);
    return $dom;
  }

  //Clean of empty text nodes
  private function cleanDOM($dom) {
    $xpath = new DOMXPath($dom);
    foreach($xpath->query('//text()') as $node) {
      if(ctype_space($node->wholeText)) {
        $node->parentNode->removeChild($node);
      }
    }
  }
}
