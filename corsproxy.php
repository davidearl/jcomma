<?php

/* retrieves a file from the given URL; goes via the server because of cross origin policy. Check it is a json
   file, and that it has a recipeVersion before serving it */

if (empty($_GET['recipe'])) { header("HTTP/1.1 400 Bad Request"); die('?recipe=... required'); }
$recipe = $_GET['recipe'];
if (! preg_match('~^https?://~', $recipe)) {
  header("HTTP/1.1 400 Bad Request"); die("recipe=... doesn't look like a uRL");
}

/* get the real file rather than a download page from various file storage systems */
if (preg_match('~^(https://drive\\.google\\.com)/file/d/([^/]+)/.*$~', $recipe, $m)) {
  $recipe = "{$m[1]}/uc?export=download&id={$m[2]}";
} else if (preg_match('~^(https://www\\.dropbox\\.com/s/[^/]+/[^\\?]+\\?dl).*$~', $recipe, $m)) {
  $recipe = "{$m[1]}=1";
}
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $recipe);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_USERAGENT, 'jcomma 3.0 (https://jcomma.savesnine.info)');
$r = curl_exec($ch);
if ($r === FALSE) { header("HTTP/1.1 404 Not Found"); die('cannot fetch recipe from URL'); }
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($status != 200) { header("HTTP/1.1 404 Not Found"); die("cannot fetch recipe from URL, status: {$status}"); }
$j = json_decode($r);
if ($j === FALSE) { header("HTTP/1.1 404 Not Found"); die("cannot decode recipe JSON"); }
if (empty($j->recipeVersion)) {  header("HTTP/1.1 404 Not Found"); die("no recipeVersion: not a jcomma recipe"); }
header('Content-type: application/json');
echo $r;
