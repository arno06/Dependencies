<?php
include_once("inc/class.Request.php");

define("NEED_SEPARATOR", ",");
define("MANIFEST", "manifest.json");

$headers = array(
	"Content-Type"=>"application/javascript"
);

$output = "";

/**
 * Check get vars
 */
$need = check("need")?explode(NEED_SEPARATOR, $_GET["need"]):array();

if(empty($need))
	output(csl("No lib to load", "warn"));

/**
 * Load manifest
 */
if(!file_exists(MANIFEST))
	output(csl("Manifest file '".MANIFEST."' not found", "error"));

$manifest = json_decode(file_get_contents(MANIFEST), true);

$config = isset($manifest["config"])?$manifest["config"]:array();
unset($manifest["config"]);

$needs = array();

retrieveNeeds($need, $needs);

$needs = array_unique($needs);

/**
 * Check Cache File (not sure this should be done now)
 */
/**
 * Get lib contents
 */
foreach($needs as $lib)
{
	if(isset($manifest[$lib]))
	{
		if(!isset($manifest[$lib]["src"])||!is_array($manifest[$lib]["src"]))
		{
			echo csl($lib." is not available", "warn");
			continue;
		}

		$files = $manifest[$lib]["src"];

		for($i = 0, $max = count($files); $i<$max;$i++)
		{
			$absolute_link = preg_match('/^http\:\/\//', $files[$i], $matches);
			if(!$absolute_link)
				$files[$i] = $config["relative"].$files[$i];
			$output .= file_get_contents($files[$i])."\r\n";
		}
	}
	else
		$output .= csl($lib." is not available");
}

/**
 * Minified / Uglyflied / gzip
 */

$accept_gzip = preg_match('/gzip/', $_SERVER['HTTP_ACCEPT_ENCODING'], $matches)&&(!isset($_GET["output"])||empty($_GET["output"]));
if($accept_gzip)
{
	$headers["Content-Encoding"] = "gzip";
	$output = gzencode($output);
}

/**
 * Generate Cache File
 */
/**
 * Define Header
 * Write body
 * End Request
 */
if(!isset($_GET["output"])||empty($_GET["output"]))
	output($output);
else
	download("package.js", $output);

/**
 * Functions
 */
function check($pName)
{
	return isset($_GET[$pName])&&!empty($_GET[$pName]);
}

function output($pContent)
{
	global $headers;

	$headers["Content-Length"] = strlen($pContent);

	foreach($headers as $n=>$v)
		header($n.": ".$v);

	echo $pContent;
	exit();
}

function csl($pText, $pType='log')
{
	return "console.".$pType."('Dependencies : ".addslashes($pText)."');\r\n";
}

function retrieveNeeds($pNeeded, &$pFinalList)
{
	global $manifest;
	global $output;

	foreach($pNeeded as $lib)
	{
		if(isset($manifest[$lib]))
		{
			array_unshift($pFinalList, $lib);
			if(!isset($manifest[$lib]["need"])||!is_array($manifest[$lib]["need"])||empty($manifest[$lib]["need"]))
				continue;
			$dep = array_reverse($manifest[$lib]["need"]);
			retrieveNeeds($dep, $pFinalList);
		}
		else
			$output .= csl($lib." is not available", "warn");
	}
}

function download($pFile, $pSource = "")
{
	switch($_GET["output"])
	{
		case "minified":
			$r = new Request("http://closure-compiler.appspot.com/compile");
			$r->setDataPost(array("js_code"=>$pSource, "compilation_level"=>"SIMPLE_OPTIMIZATIONS", "output_format"=>"json", "output_info"=>"compiled_code"));
			$pSource = json_decode($r->execute(), true);
			$pSource = $pSource["compiledCode"];
			$pFile = explode(".", $pFile);
			$pFile = array($pFile[0], "min", "js");
			$pFile = implode(".", $pFile);
			break;
	}
	if(empty($pFile))
		return;
	$fromSource = !empty($pSource);
	if(!$fromSource)
		$length = filesize($pFile);
	else
		$length = strlen($pSource);
    header("content-disposition: attachment; filename=\"".basename($pFile)."\"");
    header('Content-Type: application/force-download');
    header('Content-Transfer-Encoding: binary');
    header("Content-Length: ".$length);
    header("Pragma: no-cache");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
    header("Expires: 0");
	echo $pSource;
    exit();
}