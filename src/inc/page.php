<?php

class Page {

  var $path;
  var $template;

  function __construct($path, $template) {
    $this->path = $path;
    $this->template = $template;
  }

  function getName() {
    return toWords(basename($this->path, ".html"));
  }

}
