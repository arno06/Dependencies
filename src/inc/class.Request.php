<?php
/**
 * Class Request - permet de gérer une surcouche nécessaire à CURL pour se simplifier les traitements
 *
 * @author Arnaud NICOLAS - arno06@gmail.com
 * @version .1
 * @package tools
 * @subpackage net
 */
class Request
{
	/**
	 * @var resource
	 */
	private $curlRessource;

	/**
	 * @var string
	 */
	private $url;

	
	/**
	 * Constructor
	 * @param  string   $pUrl
	 */
	public function __construct($pUrl)
	{
		$this->curlRessource = curl_init();
		$this->setUrl($pUrl);
		$this->setOption(CURLOPT_HEADER, 0);
	}


	/**
	 * Méthode de définition de l'url cible de la requête
	 * @param  string   $pUrl
	 * @return void
	 */
	public function setUrl($pUrl)
	{
		$this->url = $pUrl;
		curl_setopt($this->curlRessource, CURLOPT_URL, $pUrl);
	}


	/**
	 * @param  $pData
	 * @return void
	 */
	public function setDataPost($pData)
	{
		if(is_array($pData))
		{
			$params = array();
			foreach($pData as $n=>$v)
			{
				$params[] = $n."=".rawurlencode($v);
			}
			$pData = implode("&", $params);
		}
		$this->setOption(CURLOPT_POST, true);
		$this->setOption(CURLOPT_POSTFIELDS, $pData);
	}


	/**
	 * Méthode de définition d'une option liée à la requête en cours
	 * @param Number $pCode
	 * @param string $pValue
	 * @return void
	 */
	public function setOption($pCode, $pValue)
	{
		curl_setopt($this->curlRessource, $pCode, $pValue);
	}

	/**
	 * Méthode d'éxecution de la requête - renvoi le résultat du traitement
	 * @throws Exception
	 * @return string
	 */
	public function execute()
	{
		ob_start();
		$return = curl_exec($this->curlRessource);
		$datas = ob_get_contents();
		ob_end_clean();
		curl_close($this->curlRessource);
		if(!$return)
			throw new Exception("Impossible d'accéder à l'url : <b>".$this->url."</b>");
		return $datas;
	}

	/**
	 * Méthode d'exécution d'une requête http
	 * @throws Exception
	 * @param  string   $pUrl
	 * @return string
	 */
	static public function load($pUrl)
	{
		$r = new Request($pUrl);
		try
		{
			$d = $r->execute();
		}
		catch(Exception $e)
		{
			throw $e;
		}
		return $d;
	}
}