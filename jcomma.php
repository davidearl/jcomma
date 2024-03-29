<?php

ini_set('default_charset', 'utf-8');

include_once(__DIR__.'/vendor/autoload.php');

function oops($s) { throw new Exception($s); }

try {
  $cl = ! empty($argv);
  if ($cl) {
    if (count($argv) >= 3 && $argv[1] == '-s') {
      $recipe = json_decode(file_get_contents($argv[2]));
      array_splice($argv, 1, 2);
    }
    if (count($argv) > 1) {
      $path = $argv[1];
      if ($path == '-') { $path = 'php:://stdin'; } /* can be '-' for stdin */
      $filename = $path;
      $slash = strrpos($filename, '/');
      if ($slash !== FALSE) { $filename = substr($path, $slash+1); }
    } else {
      $path = 'php:://stdin';
      $filename = 'stdin.csv';
    }
  } else if (! empty($_POST['recipe'])) {
    $recipe = json_decode($_POST['recipe']);
  } else {
    oops('no recipe provided');
  }
  if ($recipe === FALSE) { oops('recipe is not valid JSON'); }

  if (! $cl) {
    if (! empty($_FILES['csv']['name'])) {
      $filename = $_FILES['csv']['name'];
      if (! preg_match('~\\.(csv)$~i', $filename, $m)) { oops("only .csv files allowed"); }

      switch ($_FILES['csv']['error']) {
      case UPLOAD_ERR_OK:
        break;
      case UPLOAD_ERR_NO_FILE:
        oops('No file sent');
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
        oops('Exceeded filesize limit');
      default:
        oops('Unknown error');
      }
      $path = $_FILES['csv']['tmp_name'];
    } else if (! empty($_POST['csvpaste'])) {
      $path = fopen('data://text/plain,'.$_POST['csvpaste'], 'r');
      $filename = 'pasted.csv';
    } else {
      oops("no file uploaded");
    }

  }
  
  $jcomma = new \DavidEarl\JComma\JComma($path, $recipe);
  $errors = $jcomma->validate();
  if (! empty($errors)) { oops(implode("\n", $errors)); }

  $result = $jcomma->convert();

  $jcomma->output($result, $filename, $cl);
  
} catch(Exception $e) {
  if (! $cl) {
    header("HTTP/1.1 400 Bad Request");
    header('Content-type: application/json');
    echo json_encode(explode("\n", $e->getMessage()));
    if (! empty($_FILES['csv'])) { unlink($_FILES['csv']['tmp_name']); }
    exit;
  } else {    
    fwrite(STDERR, $e->getMessage());
    die(1);
  }
  // echo json_encode($recipe, JSON_PRETTY_PRINT);
}
