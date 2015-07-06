<?php
include_once("class.Request.php");
include_once("class.Stack.php");
/**
 * Class Dependencies
 * Gère deux types de dépendences JS & CSS
 * @author Arnaud NICOLAS <arno06@gmail.com>
 * @version 1.3
 * @todo minified
 */
class Dependencies
{
    /**
     * Chemin du fichier manifest
     */
    const MANIFEST = "./manifest.json";

    /**
     * Séparateur des librairies dans l'url
     */
    const NEED_SEPARATOR = ',';

    /**
     * Type javascript
     */
    const TYPE_JS = "javascript";

    /**
     * Type CSS
     */
    const TYPE_CSS = "css";

    /**
     * @var string
     */
    static private $current_folder;

    /**
     * @var array
     */
    private $headers;

    /**
     * @var string
     */
    private $output = "";

    /**
     * @var array
     */
    private $manifest = array();

    /**
     * @var string
     */
    private $type;

    /**
     * @var array
     */
    private $configuration = array();

    /**
     * Constructor
     * @param string $pType
     * @throws \Exception
     */
    public function __construct($pType = self::TYPE_JS)
    {
        $this->type = $pType;
        switch($this->type)
        {
            case self::TYPE_JS:
                $this->headers = array("Content-Type"=>"application/javascript");
                break;
            case self::TYPE_CSS:
                $this->headers = array("Content-Type"=>"text/css");
                break;
        }

        /**
         * Load manifest
         */
        if(!file_exists(self::MANIFEST))
            $this->output($this->log("Manifest file '".self::MANIFEST."' not found", "error"));

        $this->manifest = json_decode(file_get_contents(self::MANIFEST), true);

        $this->configuration = isset($this->manifest["config"])?$this->manifest["config"]:array();
        unset($this->manifest["config"]);

        /**
         * Cache
         */
        $cacheDuration = Stack::get("cache.duration", $this->configuration);
        if(!empty($cacheDuration))
        {
            $eTag = '"'.md5($_GET["need"]).'"';

            $this->headers["Cache-Control"] = "max-age=".$cacheDuration.", public";
            $this->headers["ETag"] = $eTag;


            if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && isset($_SERVER['HTTP_IF_NONE_MATCH']))
            {
                $if_modified_since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
                $if_none_match = $_SERVER['HTTP_IF_NONE_MATCH'];
                $expires = $if_modified_since+$cacheDuration;

                if($if_none_match == $eTag && (time() < $expires))
                {
                    header('HTTP/1.1 304 Not Modified');
                    $this->headers["Expires"] = gmdate("D, d M Y H:i:s", $expires)." GMT";
                    $this->writeHeaders();
                    exit();
                }
            }

            $this->headers["Last-Modified"] = gmdate("D, d M Y H:i:s", time())." GMT";
            $this->headers["Expires"] = gmdate("D, d M Y H:i:s", time() + $cacheDuration)." GMT";
        }
    }

    /**
     * @throws \Exception
     */
    public function retrieve()
    {
        /**
         * Check get vars
         */
        $need = isset($_GET["need"])&&!empty($_GET['need'])?explode(self::NEED_SEPARATOR, $_GET["need"]):array();

        if(empty($need))
            $this->output($this->log("No lib to load", "warn"));

        $needs = array();

        $this->calculateNeeds($need, $needs);

        $needs = array_unique($needs);

        /**
         * Get lib contents
         */
        foreach($needs as $lib)
        {
            if(isset($this->manifest[$lib]))
            {
                if(!isset($this->manifest[$lib][$this->type])
                    ||!is_array($this->manifest[$lib][$this->type]))
                {
                    $this->output .= $this->log($lib." is not available", "warn");
                    continue;
                }

                $files = $this->manifest[$lib][$this->type];

                for($i = 0, $max = count($files); $i<$max;$i++)
                {
                    $absolute_link = preg_match('/^http\:\/\//', $files[$i], $matches);
                    if(!$absolute_link)
                    {
                        $files[$i] = dirname(self::MANIFEST)."/".$this->configuration["relative"].$files[$i];
                        $content = file_get_contents($files[$i]);
                        self::$current_folder = dirname($files[$i]);
                        if($this->type == self::TYPE_CSS)
                        {
                            $content = preg_replace_callback('/(url\(\")([^\"]+)/', 'core\tools\Dependencies::correctUrls', $content);
                        }
                        $this->output .= $content."\r\n";
                    }
                    else
                        $this->output .= Request::load($files[$i]);
                }
            }
            else
                $this->output .= $this->log($lib." is not available", "warn");
        }


        /**
         * Minified / Uglyflied / gzip
         */

        $accept_gzip = preg_match('/gzip/', $_SERVER['HTTP_ACCEPT_ENCODING'], $matches)&&(!isset($_GET['output'])||empty($_GET['output']));
        if($accept_gzip)
        {
            $this->headers["Content-Encoding"] = "gzip";
            $this->output = gzencode($this->output);
        }

        $this->output($this->output);
    }

    /**
     * @param array $pNeeded
     * @param array $pFinalList
     */
    private function calculateNeeds($pNeeded, &$pFinalList)
    {

        foreach($pNeeded as $lib)
        {
            if(isset($this->manifest[$lib]))
            {
                array_unshift($pFinalList, $lib);
                if(!isset($this->manifest[$lib]["need"])
                    ||!is_array($this->manifest[$lib]["need"])
                    ||empty($this->manifest[$lib]["need"]))
                    continue;
                $dep = array_reverse($this->manifest[$lib]["need"]);
                $this->calculateNeeds($dep, $pFinalList);
            }
            else
                $this->output .= $this->log($lib." is not available", "warn");
        }
    }

    /**
     * @param string $pText
     * @param string $pLevel
     * @return string
     */
    private function log($pText, $pLevel='log')
    {
        switch($this->type)
        {
            case self::TYPE_JS:
                return "console.".$pLevel."('Dependencies : ".addslashes($pText)."');".PHP_EOL;
                break;
            case self::TYPE_CSS:
                return "/* Dependencies -".$pLevel."- : ".$pText." */".PHP_EOL;
                break;
        }
        return "";
    }

    /**
     * @param string $pContent
     */
    private function output($pContent)
    {
        $this->headers["Content-Length"] = strlen($pContent);
        $this->writeHeaders();
        echo $pContent;
        exit();
    }

    /**
     * Méthode d'écriture des headers
     */
    private function writeHeaders()
    {
        foreach($this->headers as $n=>$v)
        {
            header($n.": ".$v);
        }
    }

    /**
     * Méthode de correction des urls des assets utilisés dans les CSS
     * @param array $pMatches
     * @return string
     */
    static private function correctUrls($pMatches)
    {
        if(strpos($pMatches[2], 'data:image')>-1)
        {
            return $pMatches[0];
        }
        return $pMatches[1].'../../'.self::$current_folder.'/'.$pMatches[2];
    }
}