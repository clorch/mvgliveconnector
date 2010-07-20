<?php
header("Content-Type: text/plain; charset=utf8");

require_once('MVGLiveConnector.php');

$myMVG = new MVGLiveConnector();
print_r($myMVG->getLiveData("Hauptbahnhof"));

