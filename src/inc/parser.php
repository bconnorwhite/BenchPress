<?php

const single_tags = ['meta', 'link', 'img', 'br'];
const text_tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span'];

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
    } else {
      return NULL;
    }
  }

  function parse($element) {
    $content = "";
    if(isset($element)) {
      if(isset($element->tagName)) {
        $fieldId = $this->getFieldId($element);
        $content .= $this->openTag($element, $fieldId);
        if(!$this->isSingleTag($element)) {
          $content .= $this->parseInner($element, $fieldId);
          $content .= $this->closeTag($element);
        }
      } else if(isset($element->wholeText)) {
        $content .= $element->wholeText;
      }
    }
    return $content;
  }

  /* ----------
  * Private Functions
  ---------- */

  private function getFieldId($element) {
    if(isset($this->template)) {
      if($element->tagName == 'a') {
        return $this->template->addField($element, 'link');
      } else if($element->tagName == 'img') {
        return $this->template->addField($element, 'image');
      } else {
        $hasText = false;
        $hasBR = false;
        $hasTags = false;
        foreach($element->childNodes as $child) {
          if(isset($child->wholeText)) { //Has a text node
            $hasText = true;
          } else if($child->tagName == 'br') {
            $br = true;
          } else {
            $hasTags = true;
          }
        }
        if($hasText) {
          if($hasTags) {
            return $this->template->addField($element, 'wysiwyg');
          } else if($hasBR || strlen($element->textContent) > 40) {
            return $this->template->addField($element, 'textarea');
          } else {
            return $this->template->addField($element, 'text');
          }
        }
      }
    }
    return NULL;
  }

  private function openTag($element, $fieldId) {
    $content = "\n" . $this->tabs() . "<" . $element->tagName;
    if(isset($fieldId) && isset($this->template)) {
      $content .= " field='" . $this->template->getFieldName($fieldId) . "'";
    }
    foreach($element->attributes as $attribute) {
      if($attribute->name == 'src') {
        if(isset($fieldId) && $element->tagName == "img") {
          $content .= " src='" . $this->ifACFExists($fieldId, "echo(wp_get_attachment_image_url(get_" . $this->getSub() . "field('" . $this->template->getFieldName($fieldId) . "'), 'fullsize'));") . "'";
        } else {
          $content .= " src='<?php echo get_template_directory_uri()?>/" . $attribute->value . "'";
        }
      } else if($attribute->name == 'href') {
        if($element->tagName == 'a' && isset($fieldId)) {
          $content .= " href=\"" . $this->ifACFExists($fieldId, "echo get_" . $this->getSub() . "field('" . $this->template->getFieldName($fieldId) . "')['url'];") . "\"";
          $content .= " target=\"" . $this->ifACFExists($fieldId, "echo get_" . $this->getSub() . "field('" . $this->template->getFieldName($fieldId) . "')['target'];") . "\"";
          $content .= " alt=\"" . $this->ifACFExists($fieldId, "echo get_" . $this->getSub() . "field('" . $this->template->getFieldName($fieldId) . "')['alt'];") . "\"";
        } else if($element->tagName == "link" && $this->urlIsRelative($attribute->value)) {
          $content .= " " . $attribute->name . "='<?php echo get_template_directory_uri()?>/" . $attribute->value . "'";
        }
      } else if($attribute->name == 'style') {
        $content .= $this->parseStyle($attribute->value, $fieldId);
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

  private function parseInner($element, $fieldId) {
    if(isset($this->template) && isset($fieldId)) {
      $type = $this->template->getFieldType($fieldId);
      if($type == 'wysiwyg') {
        return "\n" . $this->tabs() . $this->ifACFExists($fieldId, "echo str_replace(array(\"\\r\\n\", \"\\n\", \"\\r\"), '', nl2br(get_" . $this->getSub() . "field('" . $this->template->getFieldName($fieldId) . "', false, false)));");
      } else if($type == 'textarea' || $type == 'text') {
        return "\n" . $this->tabs() . $this->ifACFExists($fieldId, "echo str_replace(array(\"\\r\\n\", \"\\n\", \"\\r\"), '', get_" . $this->getSub() . "field('" . $this->template->getFieldName($fieldId) . "'));");
      } else if($type == "a") {
        return "\n" . $this->tabs() . $this->ifACFExists($fieldId, "echo get_" . $this->getSub() . "field('" . $this->template->getFieldName($fieldId) . "')['title'];");
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

  private function parseStyle($style, $fieldId) {
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
          $bgFieldId = $this->template->addField(NULL, 'image', $url);
          if($bgFieldId) {
            $content = " bg='" . $this->template->getFieldName($bgFieldId) . "'" . $content;
            if(!isset($fieldId)) {
              $content = " field" . $content;
            }
            $url = $this->ifACFExists($bgFieldId, "echo(wp_get_attachment_image_url(get_" . $this->getSub() . "field('" . $this->template->getFieldName($bgFieldId) . "'), 'fullsize'));");
            $content .= substr($pair[1], 0, $urlStart) . $url . ")" . substr($pair[0], $urlEnd);
          } else {
            $content .= $attribute;
          }
        } else if($this->urlIsRelative($url)) {
          $content .= substr($pair[1], 0, $urlStart) . "<?php echo get_template_directory_uri() ?>/" . $url . ")" . substr($pair[0], $urlEnd) . ";";
        } else {
          $content .= $attribute . ";";
        }
      } else {
        $content .= $attribute . ";";
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
