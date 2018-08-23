<?php

const single_tags = ['meta', 'link', 'img', 'br', 'hr'];
const no_field = ['iframe', 'script'];

class Parser {

  var $template;

  private $tab;
  private $repeaterStack;

  function __construct($tab, $template=NULL) {
    $this->tab = $tab;
    $this->template = $template;
    $this->repeaterStack = array();
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
        //Start repeater?
        $cycleLength = false;
        if(!isset($element->previousSibling)) { //First child
          $cycleLength = $this->matchesForward($element);
          $cycleLength = $cycleLength ? $cycleLength : $element->getAttribute('repeater');
          array_push($this->repeaterStack, array("cycleLength"=>$cycleLength, "cycle"=>0, "counter"=>0));
        } else if(end($this->repeaterStack)["cycleLength"] == false) { //Not in same repeater as previous sibling
          $cycleLength = $this->matchesForward($element);
          $cycleLength = $cycleLength ? $cycleLength : $element->getAttribute('repeater');
          $this->repeaterStack[count($this->repeaterStack)-1] = array("cycleLength"=>$cycleLength, "cycle"=>0, "counter"=>0);
        }
        if($cycleLength) {
          $content .= $this->openRepeater();
        }
        //Content
        $fieldName = $this->getFieldName($element);
        $content .= $this->openTag($element, $fieldName);
        if(!$this->isSingleTag($element)) {
          $content .= $this->parseInner($element, $fieldName);
          $content .= $this->closeTag($element);
        }
        //End repeater?
        if(end($this->repeaterStack)["cycleLength"]) { //In a repeater
          if(end($this->repeaterStack)['cycle'] > 0) { //Not first cycle
            $content = ""; //Don't add content
          }
          if(end($this->repeaterStack)['counter'] == end($this->repeaterStack)['cycleLength']-1) { //Last of current cycle
            $this->template->loopRepeater();
            if(!$this->matchesForward($this->getFirstInCycle($element))) { //Last cycle
              $content = $this->closeRepeater();
            }
            $this->repeaterStack[count($this->repeaterStack)-1]["cycle"]++;
            $this->repeaterStack[count($this->repeaterStack)-1]["counter"] = 0;
          } else {
            $this->repeaterStack[count($this->repeaterStack)-1]["counter"]++;
          }
        }
        if(!isset($element->nextSibling)) {
          array_pop($this->repeaterStack);
        }
      }
    }
    return $content;
  }

  /* ----------
  * Private Functions
  ---------- */

  private function getFirstInCycle($element) {
    for($p = end($this->repeaterStack)['cycleLength'] - 1; $p > 0; $p--) {
      if(isset($element->previousSibling)) {
        $element = $element->previousSibling;
      } else {
        printError("ERROR: parser.php : getFirstInCycle()");
        return NULL;
      }
    }
    return $element;
  }

  //Returns # of elements per repeater cycle, or false if no cycle
  private function matchesForward($element) {
    //Build sets of adjacent elements, increasing in size, and check for match between the sets
    for($setLength=1; 1<2; $setLength++) { //TODO: Should probably start high and decrement, (look for longer repeaters first - ABABC,ABABC not AB,AB)
      $sets = [array(), array()];
      $nextElement = $element;
      for($set=0; $set<2; $set++) {
        for($e=0; $e<$setLength; $e++) {
          array_push($sets[$set], $nextElement);
          if($set==0) {
            if(isset($nextElement->nextSibling)) {
              $nextElement = $nextElement->nextSibling;
            } else { //Using two sets overlaps end of siblings
              return false;
            }
          }
        }
      }
      for($e=0; $e<$setLength; $e++) {
        $allMatching = true;
        if(!$this->matchingStructure($sets[0][$e], $sets[1][$e])) {
          $allMatching = false;
        }
        if($allMatching) {
          return $setLength;
        }
      }
    }
  }

  private function matchingStructure($element, $sibling, $debug=false) {
    if(isset($element->wholeText) || isset($sibling->wholeText)) {
      return true;
    } else if(isset($element->tagName) && isset($sibling->tagName)) {
      if($element->tagName == $sibling->tagName) {
        if($element->tagName == 'a') {
          return true;
        } else {
          foreach($element->attributes as $attribute) {
            if($attribute->name !== 'repeater' && (!isset($sibling->attributes[$attribute->name]) || $sibling->getAttribute($attribute->name) !== $attribute->value)) {
              return false;
            }
          }
          foreach($sibling->attributes as $attribute) {
            if($attribute->name !== 'repeater' && (!isset($element->attributes[$attribute->name]) || $element->getAttribute($attribute->name) !== $attribute->value)) {
              return false;
            }
          }
          if($this->getStructure($element)['hasText'] && $this->getStructure($sibling)['hasText']) {
            return true;
          } else {
            $e = $s = 0;
            $lastForward = false;
            while($e<count($element->childNodes) || $s<count($sibling->childNodes)) {
              $eChild = $e<count($element->childNodes) ? $element->childNodes[$e] : NULL;
              $sChild = $s<count($sibling->childNodes) ? $sibling->childNodes[$s] : NULL;
              if($this->matchingStructure($eChild, $sChild)) {
                $forwardE = $this->matchesForward($eChild);
                $forwardS = $this->matchesForward($sChild);
                $lastForward = max($forwardE, $forwardS);
                if($forwardE == $forwardS || $this->verifyForwardMatch($eChild, $sChild, $lastForward)) {
                  if($forwardE && !$forwardS) {
                    $sibling->childNodes[$s]->setAttribute('repeater', $forwardE);
                  } else if($forwardS && !$forwardE) {
                    $element->childNodes[$e]->setAttribute('repeater', $forwardS);
                  }
                  $e += $lastForward ? $lastForward : 1;
                  $s += $lastForward ? $lastForward : 1;
                } else {
                  return false;
                }
              } else if($this->matchingStructure($eChild, $sibling->childNodes[$s-($lastForward ? $lastForward : 1)])) {
                $e += ($lastForward ? $lastForward : 1);
              } else if($this->matchingStructure($element->childNodes[$e-($lastForward ? $lastForward : 1)], $sChild, true)) {
                $s += ($lastForward ? $lastForward : 1);
              } else {
                return false;
              }
            }
          }
          return true;
        }
      } else {
        return false;
      }
    } else {
      return false;
    }
  }

  private function verifyForwardMatch($element, $sibling, $count) {
    while($count > 0) {
      if($this->matchingStructure($element, $sibling)) {
        $count--;
        $element = $element->nextSibling;
        $sibling = $sibling->nextSibling;
      } else {
        return false;
      }
    }
    return true;
  }

  private function openRepeater() {
    $fieldName = $this->template->addField(null, 'repeater');
    $content = "\n" . $this->tabs() . "<?php $" . str_replace("-", "_", $fieldName) . "_counter = -1; while(have_rows('" . $fieldName . "')) { the_row(); $" . str_replace("-", "_", $fieldName) . "_counter++; ?>";
    $this->tab++;
    return $content;
  }

  private function closeRepeater() {
    $this->template->closeRepeater();
    $this->repeaterStack[count($this->repeaterStack)-1] = array("cycleLength"=>false, "cycle"=>0, "counter"=>0);
    $this->tab--;
    return "\n" . $this->tabs() . "<?php } ?>";
  }

  private function getFieldName($element) {
    if(isset($this->template)) {
      if($element->tagName == 'a') {
        return $this->template->addField($element, 'link');
      } else if($element->tagName == 'img') {
        if($this->urlIsRelative($element->getAttribute('src'))) {
          return $this->template->addField($element, 'image');
        }
      } else if(!in_array($element->tagName, single_tags) && !in_array($element->tagName, no_field)) {
        $structure = $this->getStructure($element);
        if($structure['hasText']) { //Immediate children include text nodes
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
      } else if(isset($child->tagName)) {
        if($child->tagName == 'br') {
          $structure['hasBR'] = true;
        } else {
          $structure['hasTags'] = true;
        }
      }
    }
    return $structure;
  }

  private function getFieldPrefix() {
    if(count($this->repeaterStack) > 0) {
      return end($this->template->repeaterStack)['key'] . "_<?php echo $" . str_replace("-", "_", end($this->template->repeaterStack)['key']) . "_counter; ?>_";
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
    return (isset($this->template) && count($this->repeaterStack) > 0) ? "sub_" : "";
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

  //Clean of empty text nodes & comments
  private function cleanDOM($dom) {
    $xpath = new DOMXPath($dom);
    foreach($xpath->query('//text()') as $node) {
      if(ctype_space($node->wholeText)) {
        $node->parentNode->removeChild($node);
      }
    }
    foreach($xpath->query('//comment()') as $node) {
      $node->parentNode->removeChild($node);
    }
  }
}
