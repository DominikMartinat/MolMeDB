<?php
###############################
# Dominik Martinat 2022
#  dominiki.martinat@gmail.com
#
# Library for URI dereference for MolMeDB RDF
#  https://molmedb.upol.cz/
#
# Based on library made by:
# Christopher Gutteridge 2010
#  cjg@ecs.soton.ac.uk
#  LGPL License 
#  http://graphite.ecs.soton.ac.uk/sparqllib/
#  https://github.com/cgutteridge/PHP-SPARQL-Lib
###############################


class UriDeref extends SparqllibBase
{



	function __construct()
	{
		$this->db = $this->sparql_connect( "https://idsm.elixir-czech.cz/sparql/endpoint/molmedb" );
	}

	/******* 
	 * 
	 * get HTML string from SPARQL
	 * 
	*/

	function html_sparql($uri, $out)
	{
		if( !$this->db ) { print $this->sparql_errno() . ": " . $this->sparql_error(). "\n"; exit; }

		if( $out )
		{
			$sparql = "SELECT DISTINCT * WHERE 
						{
							<$uri> ?predicate ?node
							BIND(?predicate AS ?url_predicate)
							BIND(?node AS ?url_node)
						}";
		}
		else
		{
			$sparql = "SELECT DISTINCT * WHERE
						{
							?node ?predicate <$uri>
							BIND(?node as ?url_node)
							BIND(?predicate as ?url_predicate)
						}";
		}
		$result = $this->sparql_query( $sparql ); 
		$res_array = array();
		while($row = $this->sparql_fetch_array($result))
		{
			array_push($res_array, $row);
		}
		sort($res_array);
		$last_pred = "";
		foreach($res_array as &$row)
		{
			if($last_pred === $row["predicate"])
			{
				$row["predicate"] = $row["url_predicate"] = "";

			}
			else
			{
				$last_pred = $row["predicate"];
				$row["url_predicate"] = $this->html_uri_redir($row["url_predicate"]);
				$row["predicate"] = $this->shorten_uri($row["predicate"]);
			}
			$row["url_node"] = $this->html_uri_redir($row["url_node"]);
			$row["node"] = $this->shorten_uri($row["node"]);
		}
		return $res_array;
	}

	function html_uri_redir($uri)
	{
		if(DEBUG)
		{
			return preg_replace("/rdf\.molmedb\.upol\.cz/", Url::domain() . "/rdf", "$uri");
		}
		return $uri;
	}


	/******* 
	 * 
	 * get string of rdf triples and object types from SPARQL
	 * 
	*/
	function rdf_sparql($uri, $out)
	{
		if( !$this->db ) { print $this->sparql_errno() . ": " . $this->sparql_error(). "\n"; exit; }

		if( $out )
		{
			$sparql = "SELECT DISTINCT ?subject ?predicate ?object ?type WHERE { <$uri> ?predicate ?object 
				BIND(datatype(?object) AS ?type) BIND(<$uri> AS ?subject) }";
		}
		else
		{
			$sparql = "SELECT DISTINCT ?subject ?predicate ?object WHERE { ?subject ?predicate <$uri>
				BIND(<$uri> AS ?object) }";
		}
		$result = $this->sparql_query( $sparql );
		return $result;
	}

	/******* 
	 * 
	 * transform string of rdf triples and object types into N-triples format
	 * 
	*/
	function nt_sparql($uri, $out)
	{ 
		$result = $this->rdf_sparql($uri, $out);
		if( !$result ) { print $this->sparql_errno() . ": " . $this->sparql_error(). "\n"; exit; }

		$fields = $this->sparql_field_array( $result );
		$resstring = "";
		while( $row = $this->sparql_fetch_array( $result ) )
		{
			foreach( $fields as $field )
			{
				if (array_key_exists($field, $row))
				{
					if($field == "type")
					{
						$resstring = $resstring . "^^<$row[$field]> ";
					}
					elseif (substr( $row[$field], 0, 4 ) === "http")
					{
						$resstring = $resstring . "<$row[$field]> ";
					}
					else
					{
						$resstring = $resstring . '"' . $row[$field] . '"';
					}
				}
				else
				{
					continue;
				}
			}
			$resstring = $resstring . ".\n";
		}
		return $resstring;
	}
	/**
	*takes URI and shortens inti CURI
	*/
	function shorten_uri($uri)
	{
		$namespace = array(
			"http://www.w3.org/1999/02/22-rdf-syntax-ns" => "rdf:",
			"http://www.bioassayontology.org/bao" => "bao:",
			"http://purl.org/spar/cito" => "cito:",
			"http://www.w3.org/2000/01/rdf-schema" => "rdfs:",
			"https://w3id.org/reproduceme" => "repr:",
			"http://purl.org/dc/elements/1.1" => "dc:",
			"http://purl.org/dc/terms" => "dcterms:",
			"http://semanticscience.org/resource" => "sio:",
			"http://purl.org/ontology/bibo" => "bibo:",
			"http://purl.obolibrary.org/obo" => "obo:",
			"http://www.w3.org/2004/02/skos/core" => "skos:",
			"http://rdf.molmedb.upol.cz/substance" => "mmdbsub:",
			"http://rdf.molmedb.upol.cz/interaction" => "mmdbint:",
			"http://rdf.molmedb.upol.cz/transporter" => "mmdbtra:",
			"http://rdf.molmedb.upol.cz/reference" => "mmdbref:",
			"http://rdf.molmedb.upol.cz/vocabulary" => "mmdbvoc:",
			"http://rdf.ncbi.nlm.nih.gov/pubchem/compound" => "pubchem:",
			"http://rdf.ebi.ac.uk/resource/chembl/molecule" => "ebi:",
			"http://purl.uniprot.org/uniprot/" => "uniprot:"
		);
		if (strrpos($uri,"#"))
		{
			$splituri = explode("#",$uri);
			$prefix = $splituri[0];
			$localname = $splituri[1];
		}
		else
		{
		$splituri = explode("/",$uri);
		$localname = array_pop($splituri);
		$prefix = implode('/',$splituri);
		}
		if (array_key_exists($prefix,$namespace))
		{
			return $namespace[$prefix] . $localname;
		}
		return $uri;
	}

	/******* 
	 * 
	 * transform string of rdf triples and object types into RDF/XML format
	 * 
	*/
	function xml_sparql($uri, $out)
	{ 
		$result = $this->rdf_sparql($uri, $out);
		if( !$result ) { print $this->sparql_errno() . ": " . $this->sparql_error(). "\n"; exit; }

		//$fields = sparql_field_array( $result );
		$resstring = "";
		if ($out)
		{//neumim delat XML tohle mmi pridje prasacky
			$resstring .= '<?xml version="1.0" encoding="utf-8"?>
<rdf:RDF
	xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
	xmlns:bao="http://www.bioassayontology.org/bao#"
	xmlns:cito="http://purl.org/spar/cito/"
	xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
	xmlns:repr="https://w3id.org/reproduceme#"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:dcterms="http://purl.org/dc/terms/"
	xmlns:sio="http://semanticscience.org/resource/"
	xmlns:bibo="http://purl.org/ontology/bibo/"
	xmlns:obo="http://purl.obolibrary.org/obo/"
	xmlns:skos="http://www.w3.org/2004/02/skos/core#" >
<rdf:Description rdf:about="' . $uri . '">
';
		

			while( $row = $this->sparql_fetch_array( $result ) )
			{
				$cpred = $this->shorten_uri($row["predicate"]);
				
				if (substr( $row["object"], 0, 4 ) === "http")
				{
					$resstring .= '    <' . $cpred . ' rdf:resource="' . $row["object"] . '" />
';
				}
				else
				{
					$resstring .= '    <' . $cpred;
					if (array_key_exists("type", $row))
					{
						$resstring .= ' rdf:datatype="' . $row["type"] . '"';
					}
					$resstring .= '>' . $row["object"] . '</'. $cpred .'>
';
				}
			}
			$resstring .= '  </rdf:Description>
';
		}
		else
		{
			while( $row = $this->sparql_fetch_array( $result ) )
			{
				$cpred = $this->shorten_uri($row["predicate"]);
				$resstring .= '  <rdf:Description rdf:about="' . $row["subject"] . '">
';
				$resstring .= '    <' . $cpred . ' rdf:resource="' . $uri . '" />
</rdf:Description>
';
			}
			$resstring .= "</rdf:RDF>";
		}
		return $resstring;
	}

	/******* 
	 * 
	 * create and publish rdf file in selected mime format
	 * 
	*/
	function rdf_publish($filename, $resstring, $mime)
	{

		//nejsem si jisty hlavickama na response
		header("Content-Type: " . $mime);
		header("Content-Length: " . strlen($resstring));
		header('Content-Disposition: inline; filename=' . $filename);
		echo $resstring;

	}

}





###############################
# Christopher Gutteridge 2010
#  cjg@ecs.soton.ac.uk
#  LGPL License 
#  http://graphite.ecs.soton.ac.uk/sparqllib/
#  https://github.com/cgutteridge/PHP-SPARQL-Lib
###############################

# to document: CGIParams
class SparqllibBase
{
	function sparql_connect( $endpoint ) { return new sparql_connection( $endpoint ); }

	function sparql_ns( $short, $long, $db = null ) { return $this->_sparql_a_connection( $db )->ns( $short, $long ); }
	function sparql_query( $sparql, $db = null ) { return $this->_sparql_a_connection( $db )->query( $sparql ); }
	function sparql_errno( $db = null ) { return $this->_sparql_a_connection( $db )->errno(); }
	function sparql_error( $db = null ) { return $this->_sparql_a_connection( $db )->error(); }

	function sparql_fetch_array( $result ) { return $result->fetch_array(); }
	function sparql_num_rows( $result ) { return $result->num_rows(); }
	function sparql_field_array( $result ) { return $result->field_array(); }
	function sparql_field_name( $result, $i ) { return $result->field_name( $i ); }

	function sparql_fetch_all( $result ) { return $result->fetch_all(); }

	function sparql_get( $endpoint, $sparql ) 
	{ 
		$db = $this->sparql_connect( $endpoint );
		if( !$db ) { return; }
		$result = $db->query( $sparql );
		if( !$result ) { return; }
		return $result->fetch_all(); 
	}

	function _sparql_a_connection( $db )
	{
		global $sparql_last_connection;
		if( !isset( $db ) )
		{
			if( !isset( $sparql_last_connection ) )
			{
				print( "No currect SPARQL connection (connection) in play!" );
				return;
			}
			$db = $sparql_last_connection;
		}
		return $db;
	}
}		

#	$timeout = 20;
#	$old = ini_set('default_socket_timeout', $timeout);
#	ini_set('default_socket_timeout', $old);
class sparql_connection
{
	var $db;
	var $debug = false;
	var $errno = null;
	var $error = null;
	var $ns = array();
	var $params = null;
	# capabilities are either true, false or null if not yet tested.

	function __construct( $endpoint )
	{
		$this->endpoint = $endpoint;
		global $sparql_last_connection;
		$sparql_last_connection = $this;
	}

	function ns( $short, $long )
	{
		$this->ns[$short] = $long;
	}

	function errno() { return $this->errno; }
	function error() { return $this->error; }

	function cgiParams( $params = null )
	{
		if( $params === null ) { return $this->params; }
		if( $params === "" ) { $this->params = null; return; }
		$this->params = $params;
	}

	function query( $query, $timeout=null )
	{	
		$prefixes = "";
		foreach( $this->ns as $k=>$v )
		{
			$prefixes .= "PREFIX $k: <$v>\n";
		}
		$output = $this->dispatchQuery( $prefixes.$query, $timeout );
		if( $this->errno ) { return; }
		$parser = new xx_xml($output, 'contents');
		if( $parser->error() ) 
		{ 
			$this->errno = -1; # to not clash with CURLOPT return; }
			$this->error = $parser->error();
			return;
		}
		return new sparql_result( $this, $parser->rows, $parser->fields );
	}

	function alive( $timeout=3 )
	{
		$result = $this->query( "SELECT * WHERE { ?s ?p ?o } LIMIT 1", $timeout );

		if( $this->errno ) { return false; }

		return true;
	}	

	function dispatchQuery( $sparql, $timeout=null )
	{
		$url = $this->endpoint."?query=".urlencode( $sparql );
		if( $this->params !== null )
		{
			$url .= "&".$this->params;
		}
		if( $this->debug ) { print "<div class='debug'><a href='".htmlspecialchars($url)."'>".htmlspecialchars($prefixes.$query)."</a></div>\n"; }
		$this->errno = null;
		$this->error = null;
		$ch = curl_init($url);
		#curl_setopt($ch, CURLOPT_HEADER, 1);
		if( $timeout !== null )
		{
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout );
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER,array (
			"Accept: application/sparql-results+xml"
		));

		$output = curl_exec($ch);      
		$info = curl_getinfo($ch);
		if(curl_errno($ch))
		{
			$this->errno = curl_errno( $ch );
			$this->error = 'Curl error: ' . curl_error($ch);
			return;
		}
		if( $output === '' )
		{
			$this->errno = "-1";
			$this->error = 'URL returned no data';
			return;
		}
		if( $info['http_code'] != 200) 
		{
			$this->errno = $info['http_code'];
			$this->error = 'Bad response, '.$info['http_code'].': '.$output;
			return;
		}
		curl_close($ch);

		return $output;
	}

	####################################
	# Endpoint Capability Testing
	####################################

	# This section is very limited right now. I plan, in time, to
	# caching so it can save results to a cache to save re-doing them 
	# and many more capability options (suggestions to cjg@ecs.soton.ac.uk)

	var $caps = array();
	var $caps_desc = array(
		"select"=>"Basic SELECT",
		"constant_as"=>"SELECT (\"foo\" AS ?bar)",
		"math_as"=>"SELECT (2+3 AS ?bar)",
		"count"=>"SELECT (COUNT(?a) AS ?n) ?b ... GROUP BY ?b",
		"max"=>"SELECT (MAX(?a) AS ?n) ?b ... GROUP BY ?b",
		"sample"=>"SELECT (SAMPLE(?a) AS ?n) ?b ... GROUP BY ?b",
		"load"=>"LOAD <...>",
	); 

	var $caps_cache;
	var $caps_anysubject;
	function capabilityCache( $filename, $dba_type='db4' )
	{
		$this->caps_cache = dba_open($filename, "c", $dba_type );
	}
	function capabilityCodes()
	{
		return array_keys( $this->caps_desc );
	}
	function capabilityDescription($code)
	{
		return $this->caps_desc[$code];
	}

	# return true if the endpoint supports a capability
	# nb. returns false if connecion isn't authoriased to use the feature, eg LOAD
	function supports( $code ) 
	{
		if( isset( $this->caps[$code] ) ) { return $this->caps[$code]; }
		$was_cached = false;
		if( isset( $this->caps_cache ) )
		{
			$CACHE_TIMEOUT_SECONDS = 7*24*60*60;
			$db_key = $this->endpoint.";".$code;
			$db_val = dba_fetch( $db_key, $this->caps_cache );
			if( $db_val !== false )
			{
				list( $result, $when ) = preg_split( '/;/', $db_val );
				if( $when + $CACHE_TIMEOUT_SECONDS > time() )
				{
					return $result;
				}
				$was_cached = true;
			}
		}
		$r = null;

		if( $code == "select" ) { $r = $this->test_select(); }
		elseif( $code == "constant_as" ) { $r = $this->test_constant_as(); }
		elseif( $code == "math_as" ) { $r = $this->test_math_as(); }
		elseif( $code == "count" ) { $r = $this->test_count(); }
		elseif( $code == "max" ) { $r = $this->test_max(); }
		elseif( $code == "load" ) { $r = $this->test_load(); }
		elseif( $code == "sample" ) { $r = $this->test_sample(); }
		else { print "<p>Unknown capability code: '$code'</p>"; return false; }
		$this->caps[$code] = $r;
		if( isset( $this->caps_cache ) )
		{
			$db_key = $this->endpoint.";".$code;
			$db_val = $r.";".time();
			if( $was_cached )
			{
				dba_replace( $db_key, $db_val, $this->caps_cache );
			}
			else
			{
				dba_insert( $db_key, $db_val, $this->caps_cache );
			}
		}
		return $r;
	}

	function anySubject()
	{
		if( !isset( $this->caps_anysubject ) )
		{
			$results = $this->query( 
			  "SELECT * WHERE { ?s ?p ?o } LIMIT 1" );
			if( sizeof($results)) 
			{
				$row = $results->fetch_array();
				$this->caps_anysubject = $row["s"];
			}
		}
		return $this->caps_anysubject;
	}

	# return true if the endpoint supports SELECT 
	function test_select() 
	{
		$output = $this->dispatchQuery( 
		  "SELECT ?s ?p ?o WHERE { ?s ?p ?o } LIMIT 1" );
		return !isset( $this->errno );
	}

	# return true if the endpoint supports AS
	function test_math_as() 
	{
		$output = $this->dispatchQuery( 
		  "SELECT (1+2 AS ?bar) WHERE { ?s ?p ?o } LIMIT 1" );
		return !isset( $this->errno );
	}

	# return true if the endpoint supports AS
	function test_constant_as() 
	{
		$output = $this->dispatchQuery( 
		  "SELECT (\"foo\" AS ?bar) WHERE { ?s ?p ?o } LIMIT 1" );
		return !isset( $this->errno );
	}

	# return true if the endpoint supports SELECT (COUNT(?x) as ?n) ... GROUP BY 
	function test_count() 
	{
		# assumes at least one rdf:type predicate
		$s = $this->anySubject();
		if( !isset($s) ) { return false; }
		$output = $this->dispatchQuery( 
		  "SELECT (COUNT(?p) AS ?n) ?o WHERE { <$s> ?p ?o } GROUP BY ?o" );
		return !isset( $this->errno );
	}

	function test_max() 
	{
		$s = $this->anySubject();
		if( !isset($s) ) { return false; }
		$output = $this->dispatchQuery( 
		  "SELECT (MAX(?p) AS ?max) ?o WHERE { <$s> ?p ?o } GROUP BY ?o" );
		return !isset( $this->errno );
	}

	function test_sample() 
	{
		$s = $this->anySubject();
		if( !isset($s) ) { return false; }
		$output = $this->dispatchQuery( 
		  "SELECT (SAMPLE(?p) AS ?sam) ?o WHERE { <$s> ?p ?o } GROUP BY ?o" );
		return !isset( $this->errno );
	}

	function test_load() 
	{
		$output = $this->dispatchQuery( 
		  "LOAD <http://graphite.ecs.soton.ac.uk/sparqllib/examples/loadtest.rdf>" );
		return !isset( $this->errno );
	}


}

class sparql_result
{
	var $rows;
	var $fields;
	var $db;
	var $i = 0;
	function __construct( $db, $rows, $fields )
	{
		$this->rows = $rows;
		$this->fields = $fields;
		$this->db = $db;
	}

	function fetch_array()
	{
		if( !@$this->rows[$this->i] ) { return; }
		$r = array();
		foreach( $this->rows[$this->i++]  as $k=>$v )
		{
			$r[$k] = $v["value"];
			$r["$k.type"] = $v["type"];
			if( isset( $v["language"] ) )
			{
				$r["$k.language"] = $v["language"];
			}
			if( isset( $v["datatype"] ) )
			{
				$r["$k.datatype"] = $v["datatype"];
			}
		}
		return $r;
	}

	function fetch_all()
	{
		$r = new sparql_results();
		$r->fields = $this->fields;
		foreach( $this->rows as $i=>$row )
		{
			$r []= $this->fetch_array();
		}
		return $r;
	}

	function num_rows()
	{
		return sizeof( $this->rows );
	}

	function field_array()
	{
		return $this->fields;
	}

	function field_name($i)
	{
		return $this->fields[$i];
	}
}


# class xx_xml adapted code found at http://php.net/manual/en/function.xml-parse.php
# class is cc-by 
# hello at rootsy dot co dot uk / 24-May-2008 09:30
class xx_xml {

	// XML parser variables
	var $parser;
	var $name;
	var $attr;
	var $data  = array();
	var $stack = array();
	var $keys;
	var $path;
	var $looks_legit = false;
	var $error;
  
	// either you pass url atau contents.
	// Use 'url' or 'contents' for the parameter
	var $type;

	// function with the default parameter value
	function __construct($url='http://www.opocot.com', $type='url') {
		$this->type = $type;
		$this->url  = $url;
		$this->parse();
	}
  
	function error() { return $this->error; }

	// parse XML data
	function parse()
	{
		$this->rows = array();
		$this->fields = array();
		$data = '';
		$this->parser = xml_parser_create ("UTF-8");
		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, 'startXML', 'endXML');
		xml_set_character_data_handler($this->parser, 'charXML');

		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);

		if ($this->type == 'url') {
			// if use type = 'url' now we open the XML with fopen
		  
			if (!($fp = fopen($this->url, 'rb'))) {
				$this->error("Cannot open {$this->url}");
			}

			while (($data = fread($fp, 8192))) {
				if (!xml_parse($this->parser, $data, feof($fp))) {
					$this->error = sprintf('XML error at line %d column %d',
						xml_get_current_line_number($this->parser),
						xml_get_current_column_number($this->parser));
					return;
				}
			}
	 	} else if ($this->type == 'contents') {
	  	// Now we can pass the contents, maybe if you want
			// to use CURL, SOCK or other method.
			$lines = explode("\n",$this->url);
			foreach ($lines as $val) {
				$data = $val . "\n";
				if (!xml_parse($this->parser, $data)) {
					$this->error = $data."\n".sprintf('XML error at line %d column %d',
						xml_get_current_line_number($this->parser),
				 		xml_get_current_column_number($this->parser));
					return;
				}
			}
		}
		if( !$this->looks_legit ) 
		{
			$this->error = "Didn't even see a sparql element, is this really an endpoint?";
		}
	}

	function startXML($parser, $name, $attr)	
	{
		if( $name == "sparql" ) { $this->looks_legit = true; }
		if( $name == "result" )
		{
			$this->result = array();
		}
		if( $name == "binding" )
		{
			$this->part = $attr["name"];
		}
		if( $name == "uri" || $name == "bnode" )
		{
			$this->part_type = $name;
			$this->chars = "";
		}
		if( $name == "literal" )
		{
			$this->part_type = "literal";
			if( isset( $attr["datatype"] ) )
			{
				$this->part_datatype = $attr["datatype"];
			}
			if( isset( $attr["xml:lang"] ) )
			{
				$this->part_lang = $attr["xml:lang"];
			}
			$this->chars = "";
		}
		if( $name == "variable" )
		{
			$this->fields[] = $attr["name"];
		}
	}

	function endXML($parser, $name)	{
		if( $name == "result" )
		{
			$this->rows[] = $this->result;
			$this->result = array();
		}
		if( $name == "uri" || $name == "bnode" || $name == "literal" )
		{
			$this->result[$this->part] = array( "type"=>$name, "value"=>$this->chars );
			if( isset( $this->part_lang ) )
			{
				$this->result[$this->part]["lang"] = $this->part_lang;
			}
			if( isset( $this->part_datatype ) )
			{
				$this->result[$this->part]["datatype"] = $this->part_datatype;
			}
			$this->part_datatype = null;
			$this->part_lang = null;
		}
	}

	function charXML($parser, $data)	{
		@$this->chars .= $data;
	}

}

class sparql_results extends ArrayIterator
{
	var $fields;
	function fields() { return $this->fields; }

	function render_table()
	{
		$html = "<table class='sparql-results'><tr>";
		foreach( $this->fields as $i=>$field )
		{
			$html .= "<th>?$field</th>";
		}
		$html .= "</tr>";
		foreach( $this as $row )
		{
			$html.="<tr>";
			foreach( $row as $cell )
			{
				$html .= "<td>".htmlspecialchars( $cell )."</td>";
			}
			$html.="</tr>";
		}
		$html.="</table>
<style>
table.sparql-results { border-collapse: collapse; }
table.sparql-results tr td { border: solid 1px #000 ; padding:4px;vertical-align:top}
table.sparql-results tr th { border: solid 1px #000 ; padding:4px;vertical-align:top ; background-color:#eee;}
</style>
";
		return $html;exit;
	}

}