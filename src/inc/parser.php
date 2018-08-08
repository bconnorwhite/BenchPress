<?php

const single_tags = ['meta', 'link', 'img', 'br'];
const text_tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span'];
const keyword_attributes = ['field', 'section'];

class Parser {

  var $template;

  private $tab;
  private $inRepeater;

  function __construct($tab, $template=NULL) {
    $this->tab = $tab;
    $this->template = $template;
    $this->inRepeater = false;
  }

  function getElementByTagName($inputPath, $tagName) {
    $dom = $this->getDOM($inputPath);
    $elements = $dom->getElementsByTagName($tagName);
    if($elements && $elements->length > 0) {
      return $elements->item(0);
    }
  }

  function parse($element) {
    $content = "";
    if(isset($element->tagName)) {
      if(isset($this->template)) {
        //Check if element is section
        foreach($element->attributes as $attribute) {
          if($attribute->name == 'section') {
            $this->template->createSection($element);
            return "\n" . $this->tabs() . '<?php include(get_template_directory() . "/section-templates/' . $attribute->value . '.php"); ?>';
          }
        }
      }
      $content .= $this->openTag($element);
      if(!$this->isSingleTag($element)) {
        $content .= $this->parseInner($element);
        $content .= $this->closeTag($element);
      }
    } else if(isset($element->wholeText)) {
      $content .= $element->wholeText;
    }
    return $content;
  }

  /* ----------
  * Private Functions
  ---------- */

  private function parseInner($element) {
    if(isset($this->template) && $this->getField($element) !== NULL) {
      $field = $this->getField($element);
      if($element->tagName == "p") {
        $this->template->addField($field, "p");
        //$this->template->addMeta($this->template->getFieldName($field), )
        return "\n" . $this->tabs() . $this->ifACFExists($field, "echo nl2br(get_" . $this->getSub() . "field('" . $this->template->getFieldName($field) . "', false, false));");
      } else if(in_array($element->tagName, text_tags)) {
        return $this->parseText($element, $field);
      } else if($element->tagName == "a") {
        return "\n" . $this->tabs() . $this->ifACFExists($field, "echo get_" . $this->getSub() . "field('" . $this->template->getFieldName($field) . "')['title'];");
      }
    }
    return $this->parseChildren($element);
  }

  private function parseText($element, $field) {
    $br = false;
    $content = "";
    $value = "";
    $created = false;
    foreach($element->childNodes as $child) {
      if($content == "" && !isset($child->tagName) && $child->wholeText) {
        $value .= $child->wholeText;
      } else if($content == "" && $child->tagName == 'br') {
        $value .= "<br/>";
        $br = true;
      } else {
        if(!$created) {
          $this->template->addField($field, $br ? "textarea" : "text");
          $this->template->addMeta($this->template->getFieldName($field), $value);
          $created = true;
        }
        $content .= $this->parse($child);
      }
    }
    if($value !== "") {
      if(!$created) {
        $this->template->addField($field, $br ? "textarea" : "text");
        $this->template->addMeta($this->template->getFieldName($field), $value);
      }
      $content = "\n" . $this->tabs() . $this->ifACFExists($field, "echo the_" . $this->getSub() . "field('" . $this->template->getFieldName($field) . "')['title'];") . $content;
    }
    return $content;
  }

  private function parseChildren($element) {
    $content = "";
    foreach($element->childNodes as $child) {
      $content .= $this->parse($child);
    }
    return $content;
  }

  private function openTag($element) {
    $content = "\n" . $this->tabs() . "<" . $element->tagName;
    $field = $this->getField($element);
    foreach($element->attributes as $attribute) {
      if($attribute->name == 'src') {
        if(isset($field) && $element->tagName == "img") {
          $this->template->addField($field, $element->tagName);
          $content .= " src='" . $this->ifACFExists($field, "echo(wp_get_attachment_image_url(get_" . $this->getSub() . "field('" . $this->template->getFieldName($field) . "'), 'fullsize'));") . "'";
        } else {
          $content .= " src='<?php echo get_template_directory_uri()?>/" . $attribute->value . "'";
        }
      } else if($attribute->name == 'href' && $element->tagName == "link" && $this->urlIsRelative($attribute->value)) {
        $content .= " " . $attribute->name . "='<?php echo get_template_directory_uri()?>/" . $attribute->value . "'";
      } else if($attribute->name == 'href' && $element->tagName == 'a' && isset($field)) {
        $this->template->addField($field, $element->tagName);
        $this->template->addMeta($this->template->getFieldName($field), serialize(array("title"=>$element->textContent, "url"=>$attribute->value)));
        $content .= " href=\"" . $this->ifACFExists($field, "echo get_" . $this->getSub() . "field('" . $this->template->getFieldName($field) . "')['url'];") . "\"";
        $content .= " target=\"" . $this->ifACFExists($field, "echo get_" . $this->getSub() . "field('" . $this->template->getFieldName($field) . "')['target'];") . "\"";
      } else if($attribute->name == 'style') {
        $content .= $this->parseStyle($attribute->value, $field);
      } else if(!in_array($attribute->name, keyword_attributes)) {
        $content .= " " . $attribute->name . "='" . $attribute->value . "'";
      }
    }
    if($this->isSingleTag($element)) {
      return $content . "/>";
    } else {
      $this->tab++;
      return $content . ">";
    }
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

  private function getField($element) {
    if(isset($this->template)) {
      foreach($element->attributes as $attribute) {
        if($attribute->name == 'field') {
          return $attribute->value;
        }
      }
    }
    return NULL;
  }

  private function isSingleTag($element) {
    return in_array($element->tagName, single_tags);
  }

  private function parseStyle($style, $field) {
    $content = " style='";
    $attributes = explode(";", $style);
    foreach($attributes as $attribute) {
      $pair = explode(":", $attribute);
      if(trim($pair[0]) == "background-image" || trim($pair[0]) == "background") {
        $content .= $pair[0] . ":";
        $urlStart = strpos($pair[1], "url(") + strlen("url(");
        $urlEnd = strpos(substr($pair[1], $urlStart), ")");
        $url = substr($pair[1], $urlStart, $urlEnd);
        if(isset($this->template) && isset($field)) {
          $url = $this->ifACFExists($field, "echo(wp_get_attachment_image_url(get_" . $this->getSub() . "field('" . $this->template->getFieldName($field) . "'), 'fullsize'));");
          $content .= substr($pair[1], 0, $urlStart) . $url . ")" . substr($pair[0], $urlEnd) . ";";
          $this->template->addField($field, 'img');
          //TODO: set meta for image
        } else if($this->urlIsRelative($url)) {
          $content .= substr($pair[1], 0, $urlStart) . "<?php echo get_template_directory_uri() ?>/" . $url . ")" . substr($pair[0], $urlEnd) . ";";
        } else {
          $content .= $attribute;
        }
      } else {
        $content .= $attribute;
      }
    }
    return $content . "'";
  }

  private function ifACFExists($field, $content) {
    return  "<?php if(get_" . $this->getSub() . "field('" . $this->template->getFieldName($field) . "') !== '') { " . $content . "}?>";
  }

  private function getSub() {
    return $this->inRepeater ? "sub_" : "";
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
