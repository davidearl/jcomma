<?php

/* Note: the README.md file can be conveniently edited at
   http://dillinger.io/ and exported as HTML to /jcomma.help.md.html
   where it is further processed below. */

?><!doctype html>
<html>
<head>
<meta charset="UTF-8">
<title>jcomma - help: a CSV converter</title>
<link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,400i,700,700i" rel="stylesheet">
<link rel='stylesheet' href='jcomma.css'>
<link rel='shortcut icon' href='/logo.png'>
<script src='vendor/components/jquery/jquery.min.js'></script>
<style>
ul {
	list-style: disc;
}
ul ul {
	list-style: circle;
}
li {
	margin: 2px 0 2px 20px;
}
h1, h2, h3 { font-weight: bold; }
h2 { margin-top: 14px; }
h3 { margin-top: 8px; }
p { margin-bottom: 7px; }
.canchortop {
	margin-top: 10px;
}
code {
	background: #eee;
	padding: 2px;
}
pre {
	background: #eee;
}
</style>
<script>
function getParameterByName(name) {
    name = name.replace(/[\[\]]/g, "\\$&");
    var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
        results = regex.exec(window.location.href);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, " "));
}
$(function(){
	var anchor = getParameterByName('a');
	if (anchor) {
		var ji = $("[name=\""+anchor+"\"]");
		if (ji) {
			$("body,html").animate({scrollTop: ji.offset().top}, 250); 
		}
	}
});
</script>
</head>
<?php
$help = file_get_contents('jcomma.help.md.html');
$help = preg_replace('~^!DOCTYPE.*</head>~', '', $help);
$help = preg_replace('~\(#([a-z0-9]+)\)~', '<div class="canchortop"><a name="$1" href="#">top</a></div>', $help);
$help = preg_replace('~<a href="http~', '<a target="blank" href="http', $help);
echo $help;
