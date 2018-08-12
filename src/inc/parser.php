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
        if(isset($this->template)) {
          //Check if element is section
          foreach($element->attributes as $attribute) {
            if($attribute->name == 'section') {
              $this->template->createSection($element);
              return "\n" . $this->tabs() . '<?php include(get_template_directory() . "/section-templates/' . $attribute->value . '.php"); ?>';
            }
          }
        }
        $openTag = $this->openTag($element); //array(content, fieldId);
        $content .= $openTag['content'];
        if(!$this->isSingleTag($element)) {
          $content .= $this->parseInner($element, $openTag['fieldId']);
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

  private function openTag($element) {
    $content = "\n" . $this->tabs() . "<" . $element->tagName;
    $fieldId = $this->getFieldId($element);
    foreach($element->attributes as $attribute) {
      if($attribute->name == 'src') {
        if(isset($fieldId) && $element->tagName == "img") {
          $content .= " src='" . $this->ifACFExists($fieldId, "echo(wp_get_attachment_image_url(get_" . $this->getSub() . "field('" . $this->template->getFieldName($fieldId) . "'), 'fullsize'));") . "'";
        } else {
          $content .= " src='<?php echo get_template_directory_uri()?>/" . $attribute->value . "'";
        }
      } else if($attribute->name == 'href' && $element->tagName == "link" && $this->urlIsRelative($attribute->value)) {
        $content .= " " . $attribute->name . "='<?php echo get_template_directory_uri()?>/" . $attribute->value . "'";
      } else if($attribute->name == 'href' && $element->tagName == 'a' && isset($fieldId)) {
        $this->template->addMeta($this->template->getFieldName($fieldId), serialize(array("title"=>$element->textContent, "url"=>$attribute->value)));
        $content .= " href=\"" . $this->ifACFExists($fieldId, "echo get_" . $this->getSub() . "field('" . $this->template->getFieldName($fieldId) . "')['url'];") . "\"";
        $content .= " target=\"" . $this->ifACFExists($fieldId, "echo get_" . $this->getSub() . "field('" . $this->template->getFieldName($fieldId) . "')['target'];") . "\"";
      } else if($attribute->name == 'style') {
        $content .= $this->parseStyle($attribute->value);
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
    return array('content' => $content, 'fieldId' => $fieldId);
  }

  private function parseInner($element, $fieldId) {
    if(isset($this->template) && isset($fieldId)) {
      if($element->tagName == "p") {
        $this->template->addField($fieldId, "p");
        //$this->template->addMeta($this->template->getFieldName($fieldId), )
        return "\n" . $this->tabs() . $this->ifACFExists($fieldId, "echo nl2br(get_" . $this->getSub() . "field('" . $this->template->getFieldName($fieldId) . "', false, false));");
      } else if(in_array($element->tagName, text_tags)) {
        return $this->parseText($element, $fieldId);
      } else if($element->tagName == "a") {
        return "\n" . $this->tabs() . $this->ifACFExists($fieldId, "echo get_" . $this->getSub() . "field('" . $this->template->getFieldName($fieldId) . "')['title'];");
      }
    }
    return $this->parseChildren($element);
  }

  private function parseText($element, $fieldId) {
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
          $this->template->addField($fieldId, $br ? "textarea" : "text");
          $this->template->addMeta($this->template->getFieldName($fieldId), $value);
          $created = true;
        }
        $content .= $this->parse($child);
      }
    }
    if($value !== "") {
      if(!$created) {
        $this->template->addField($fieldId, $br ? "textarea" : "text");
        $this->template->addMeta($this->template->getFieldName($fieldId), $value);
      }
      $content = "\n" . $this->tabs() . $this->ifACFExists($fieldId, "echo the_" . $this->getSub() . "field('" . $this->template->getFieldName($fieldId) . "')['title'];") . $content;
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

  private function getFieldId($element) {
    if(isset($this->template)) {
      foreach($element->childNodes as $child) {
        if(isset($child->wholeText)) {
          return $this->template->addField($element->tagName);
        }
      }
    }
    return NULL;
  }

  private function isSingleTag($element) {
    return in_array($element->tagName, single_tags);
  }

  private function parseStyle($style) {
    $content = " style='";
    $attributes = explode(";", $style);
    foreach($attributes as $attribute) {
      $pair = explode(":", $attribute);
      if(trim($pair[0]) == "background-image" || trim($pair[0]) == "background") {
        $content .= $pair[0] . ":";
        $urlStart = strpos($pair[1], "url(") + strlen("url(");
        $urlEnd = strpos(substr($pair[1], $urlStart), ")");
        $url = substr($pair[1], $urlStart, $urlEnd);
        if(isset($this->template)) {
          $fieldId = $this->template->addField('img');
          if($fieldId) {
            $url = $this->ifACFExists($fieldId, "echo(wp_get_attachment_image_url(get_" . $this->getSub() . "field('" . $this->template->getFieldName($fieldId) . "'), 'fullsize'));");
            $content .= substr($pair[1], 0, $urlStart) . $url . ")" . substr($pair[0], $urlEnd) . ";";
            //TODO: set meta for image
          } else {
            $content .= $attribute;
          }
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
