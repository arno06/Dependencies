<?php
include_once("inc/class.Dependencies.php");

$d = new Dependencies(Dependencies::TYPE_JS);
$d->retrieve();

/**
 * @not_implemented_yet
 * @param $pFile
 * @param string $pSource
 * @throws Exception
 */
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