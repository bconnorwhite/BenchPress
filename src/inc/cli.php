<?php

const default_color = "\e[39m";
const primary_color = "\e[96m";
const secondary_color = "\e[95m";
const prompt_color = "\e[93m";
const success_color = "\e[92m";
const failure_color = "\e[91m";

const checkmark = "\xE2\x9C\x94";

class CLI {
  
  function colorString($string, $color) {
    return $color . $string . "\e[39m";
  }

  function printLine($string) {
    echo $string . "\n";
  }

}
