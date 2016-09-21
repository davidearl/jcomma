<?php

include_once('jcomma.class.php');

function oops($s) { throw new Exception($s); }

try {
  $cl = ! empty($argv);
  if ($cl) {
    if (count($argv) >= 3 && $argv[1] == '-s') {
      $spec = json_decode(file_get_contents($argv[2]));
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
  } else if (! empty($_POST['spec'])) {
    $spec = json_decode($_POST['spec']);
  } else {
    oops('no spec provided');
  }
  if ($spec === FALSE) { oops('spec is not valid JSON'); }

  if (! $cl) {
    if (empty($_FILES['csv']['name'])) { oops("no file uploaded"); }
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
  }
  
  $jcomma = new jcomma($path, $spec);
  $errors = $jcomma->validate();
  if (! empty($errors)) { oops(implode("\n", $errors)); }

  $result = $jcomma->convert();

  $jcomma->output($result, $filename, $cl);
  
} catch(Exception $e) {
  if (! $cl) {
    header("HTTP/1.1 400 Bad Request");
    header('Content-type: application/json');
    echo json_encode(explode("\n", $e->getMessage()));
    exit;
  } else {    
    fwrite(STDERR, $e->getMessage());
    die(1);
  }
  // echo json_encode($spec, JSON_PRETTY_PRINT);
}
