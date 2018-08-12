<?php

const default_color = "\e[39m";
const primary_color = "\e[96m";
const secondary_color = "\e[95m";
const prompt_color = "\e[93m";
const success_color = "\e[92m";
const failure_color = "\e[91m";

const checkmark = "\xE2\x9C\x94";

function colorString($string, $color) {
  return $color . $string . "\e[39m";
}

function printError($string) {
  printLine(colorString($string, failure_color));
}

function printLine($string) {
  echo $string . "\n";
}

function toWords($string) {
  return ucwords(str_replace("-", " ", str_replace("_", " ", $string)));
}

function getPrefix($string) {
  return explode('-', $string)[0];
}

function getSuffix($string) {
  $split = explode('-', $string);
  return count($split) > 1 ? $split[1] : "";
}
