<?php
if(!class_exists('clsXMLParser')) {
  class clsXMLParser {
    var $strCacheDir = '';
    var $nCacheTimeout = 3600;
    var $nConnectionTimeout = 20;
    var $arrOutput = array();
    var $resParser;
    var $strXmlData;
    
    function parse($strInputXML) {
      $this->resParser = xml_parser_create ();
      xml_set_object($this->resParser,$this);
      xml_set_element_handler($this->resParser, "tagOpen", "tagClosed");
      xml_set_character_data_handler($this->resParser, "tagData");
      $this->strXmlData = xml_parse($this->resParser,$strInputXML );
      if(!$this->strXmlData) {
        return false;
      }
      xml_parser_free($this->resParser);
      return $this->arrOutput;
    }

    function tagOpen($parser, $name, $attrs) {
      $tag=array("name"=>$name,"attrs"=>$attrs);
      array_push($this->arrOutput,$tag);
    }
    
    function tagData($parser, $tagData) {
      if(trim($tagData)) {
        if(isset($this->arrOutput[count($this->arrOutput)-1]['tagData'])) {
          $this->arrOutput[count($this->arrOutput)-1]['tagData'] .= $tagData;
        } else {
          $this->arrOutput[count($this->arrOutput)-1]['tagData'] = $tagData;
        }
      }
    }

    function tagClosed($parser, $name) {
      $this->arrOutput[count($this->arrOutput)-2]['children'][] = $this->arrOutput[count($this->arrOutput)-1];
      array_pop($this->arrOutput);
    }
    
    function getXML($strXMLUrl) {
  		$urlParts =	parse_url($strXMLUrl);
  		$host = $urlParts['host'];
  		$uri  = $urlParts['path'];
  
  		if (strcmp($urlParts['query'], '') != 0) {
  			$uri .= '?' . $urlParts['query'];
  		}
  
  		if(strcmp($urlParts['fragment'],'') !=0){
  			$fragment = $urlParts['fragment'];
  			$fragment = substr($fragment,4,strlen($fragment)-3);
  			$uri = $uri . $fragment;
  		}
  
  		if ($f = fsockopen($host, 80, $errno, $errstr, $this->nConnectionTimeout)) {
  			$strXMLData = '';
  			fputs($f, "GET $uri HTTP/1.0\r\nHost: $host\r\n\r\n");
  			while (!feof($f)) {
  				$strXMLData .= fgets($f, 128);
  			}
  			fclose ($f);
  			$strXMLData = strstr($strXMLData,'<?xml');
  			return $strXMLData;
  		} else {
  			return false;
  		}
    }
    
    function get($strXMLUrl) {
  		// If CACHE ENABLED
  		if ($this->strCacheDir != '') {
  			$cache_file = $this->strCacheDir . '/rsscache_' . md5($strXMLUrl);
  			$timedif = @(time() - filemtime($cache_file));
  			if ($timedif < $this->nCacheTimeout) {
  				// cached file is fresh enough, return cached array
  				$result = unserialize(file_get_contents($cache_file));
  			} else {
  				// cached file is too old, create new
          $strXML = $this->getXML($strXMLUrl);
          if ($strXML) {
            $result = $this->parse($strXML);
            $serialized = serialize($result);
    				if ($f = @fopen($cache_file, 'w')) {
    					fwrite ($f, $serialized, strlen($serialized));
    					fclose($f);
    				}
          } else {
            $result = false;
          }
  			}
  		}
  		// If CACHE DISABLED >> load and parse the file directly
  		else {
        $strXML = $this->getXML($strXMLUrl);
        if ($strXML) {
          $result = $this->parse($strXML);
        } else {
          $result = false;
        }
  		}
  		// return result
  		return $result;
    }
  }
}
?>