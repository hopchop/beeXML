<?php
/**
 * export2beexml.php
 *
 * @package default
 */

$sample = True;

$minLat = 90;
$minLon = 90;
$maxLat = -90;
$maxLon = -90;

$config = array_column(array_map('str_getcsv', file('beexml.cfg')), 1, 0);
print_r($config);

$types = array_column(array_map('str_getcsv', file('beexml_typemap.cfg')), 1, 0);
print_r($types);

$objects = array_column(array_map('str_getcsv', file('beexml_objectmap.cfg')), 1, 0);
print_r($objects);

$units = array_column(array_map('str_getcsv', file('beexml_units.cfg')), 1, 0);
print_r($units);

$apiaries = array_column(array_map('str_getcsv', file('beexml_apiaries.cfg')), 1, 0);
print_r($apiaries);


setlocale (LC_ALL, $config['locale']);

ini_set('auto_detect_line_endings', true);
$xsdstring = file_get_contents($config['schemaXSDFile']);
// $xsdstring = file_get_contents('http://beexml.org/wp-content/uploads/beeXML.xsd');

$schema = new DOMDocument();
$schema->preserveWhiteSpace = false;
$schema->loadXML(mb_convert_encoding($xsdstring, 'utf-8', mb_detect_encoding($xsdstring)));
$fieldsList = $schema->getElementsByTagName('element'); //get all elements

print_r($fieldsList);

foreach ($fieldsList as $element) {
	$key = $element->getAttribute('name');
	$type = $element->getAttribute('type');
	printf("Key: %s %s\n", $key, $type);
}

$objDateTime = new DateTime('NOW');
$objDateTime->setTimezone(new DateTimeZone("UTC"));

$beexml = new DOMDocument();
$beexml->encoding = 'utf-8';
$beexml->xmlVersion = '1.0';
$beexml->formatOutput = true;

$beexml_file_name = 'beexml_export.xml';

$root = $beexml->createElement('beeXML');
$root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
$root->setAttribute('xmlns', 'http://beexml.org');
$root->setAttribute('xsi:schemaLocation', 'http://beexml.org/ http://beexml.org/wp-content/uploads/beeXML.xsd');
$root->setAttribute('creator', 'export2beexml.php');
$root->setAttribute('version', '1.0');

$node = $beexml->createElement('metadata');
$subnode = $beexml->createElement('name', 'beeXML Export');
$node->appendChild($subnode);
$root->appendChild($node);
$subnode = $beexml->createElement('author');
$subsubnode = $beexml->createElement('name', $config['author']);
$subnode->appendChild($subsubnode);
$node->appendChild($subnode);
$root->appendChild($node);
$subnode = $beexml->createElement('time', $objDateTime->format('Y-m-d\TH:i:s\Z'));
$node->appendChild($subnode);
$root->appendChild($node);
$subnode = $beexml->createElement('bounds');
$attr = new DOMAttr('minlat',  $config['minLat']);
$subnode->setAttributeNode($attr);
$attr = new DOMAttr('minlon',  $config['minLon']);
$subnode->setAttributeNode($attr);
$attr = new DOMAttr('maxlat',  $config['maxLat']);
$subnode->setAttributeNode($attr);
$attr = new DOMAttr('maxlon',  $config['maxLon']);
$subnode->setAttributeNode($attr);
$node->appendChild($subnode);
$root->appendChild($node);

// Create connection
$conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['database'], $config['port']);
// Check connection
if ($conn->connect_error) {
	die("Connection failed: " . $conn->connect_error);
}
printf("Systemstatus: %s\n", $conn->stat());

// Perform query
if ($result = $conn -> query("SELECT * FROM aktivitaeten LEFT JOIN Standorte ON aktivitaeten.Standort=Standorte.Standort WHERE aktivitaeten.Zeitpunkt >= '2019-01-01' ORDER BY `Zeitpunkt` ASC LIMIT 1")) {
	echo "Returned rows are: " . $result -> num_rows;
	$values = $result->fetch_all(MYSQLI_ASSOC);
	$columns = array();

	if (!empty($values)) {
		$columns = array_keys($values[0]);
		print_r($columns);
	}
	// Free result set
	$result -> free_result();
}

if ($result = $conn -> query("SELECT * FROM aktivitaeten LEFT JOIN Standorte ON aktivitaeten.Standort=Standorte.Standort WHERE aktivitaeten.Zeitpunkt >= '2019-01-01' ORDER BY `Zeitpunkt` ASC")) {
	while($obj = $result->fetch_object()){ 
	    $beexmlCmt = "";
	    $node = $beexml->createElement('event');

	    if ( strlen($obj->GPS) > 7 ) {
		list($beexmlLat, $beexmlLon) = explode(" ", $obj->GPS);
		if ($beexmlLon > $beexmlLat ) {
			list($beexmlLat, $beexmlLon) = array($beexmlLon, $beexmlLat);	// swap switched GPS coordiantes
			$beexmlCmt .= "swapped coordinates in $obj->GPS ";
		}
		if ($beexmlLat < $config['minLat'] or $beexmlLon < $config['minLon'] ) {
			$beexmlLat = $config['defaultLat'];
			$beexmlLon = $config['defaultLon'];
			$beexmlCmt .= "using default coordinates instead of $obj->GPS ";
		}
		if ($beexmlLat > $maxLat ) {
			$maxLat = $beexmlLat;
		}
		if ($beexmlLon > $maxLon ) {
			$maxLon = $beexmlLon;
		}
		if ($beexmlLat < $minLat ) {
			$minLat = $beexmlLat;
		}
		if ($beexmlLon < $minLon ) {
			$minLon = $beexmlLon;
		}
	    } else {
			$beexmlLat = $config['defaultLat'];
			$beexmlLon = $config['defaultLon'];
			$beexmlCmt .= "using default coordinates instead of $obj->GPS ";
	    }

	   $beexmlLat = str_replace(",", "", $beexmlLat);	// just in case the database entry was not clean
	   $beexmlLon = str_replace(",", "", $beexmlLon);
	   if ( $sample ) {
	   	$beexmlLat = $beexmlLat + (mt_rand() / mt_getrandmax()); 
	   	$beexmlLon = $beexmlLon + (mt_rand() / mt_getrandmax());
	   	$beexmlLat = str_replace(",", ".", $beexmlLat);	// calculation introduces comma in certain locales
	    	$beexmlLon = str_replace(",", ".", $beexmlLon);
	   }
	   $attr = new DOMAttr('lat', $beexmlLat);
	   $node->setAttributeNode($attr);
	   $attr = new DOMAttr('lon', $beexmlLon);
	   $node->setAttributeNode($attr);

	    $beexml_timestamp = DateTime::createFromFormat('Y-m-d H:i:s', $obj->Zeitpunkt, new DateTimeZone($config['timezone']));
	    $beexml_timestamp->setTimezone(new DateTimeZone('UTC'));
	    $subnode = $beexml->createElement('time', $beexml_timestamp->format('Y-m-d\TH:i:s\Z'));
	    $node->appendChild($subnode);
	    $standort = rtrim($obj->Standort);
	    if (array_key_exists($standort, $apiaries)) {
		    $beexmlApiary = $apiaries[$standort];
	    } else {
		    if ( strpos($standort, 'Warngau') > 0 ) {
		    	$beexmlApiary = "SiteWRN";	// special case encoding issue 
                    } else {
		    	$beexmlApiary = $standort;	// use untranslated type
		    }
	    }

	    $subnode = $beexml->createElement('hiveID', $obj->Beute);
	    $node->appendChild($subnode);

	    $subnode = $beexml->createElement('apiaryID', rtrim($beexmlApiary));
	    $node->appendChild($subnode);

	    if ($obj->Standort2 != "-") {
	    	$subnode = $beexml->createElement('srcApiaryID', rtrim($obj->Standort2));
	    	$node->appendChild($subnode);
	    }

	    if ( strlen($obj->Beschreibung) > 1 ) {
	   	if ( $sample ) {
	    		$subnode = $beexml->createElement('desc', 'Lorem ipsum dolor sit amet, consectetur adipisici elit, …');
		} else {
	    		$subnode = $beexml->createElement('desc', $obj->Beschreibung);
		}
	    	$node->appendChild($subnode);
            }

	    $subnode = $beexml->createElement('src', $obj->UserID);
	    $node->appendChild($subnode);

	    if (array_key_exists($obj->TasksID, $types)) {
		    $beexmlType = $types[$obj->TasksID];
	    } else {
		    $beexmlType = $obj->TasksID;	// use untranslated type
	    }

	    $subnode = $beexml->createElement('type', $beexmlType);
	    $node->appendChild($subnode);

	    if (array_key_exists($obj->Objekt, $objects)) {
		    $beexmlObject = $objects[$obj->Objekt];
	    } else {
		    $beexmlObject = $obj->Objekt;	// use untranslated object
	    }
	    $subnode = $beexml->createElement('object', $beexmlObject);
	    $node->appendChild($subnode);

	    if ( $obj->Anzahl > 0 ) {
                    $beexmlCount = $obj->Anzahl;
            } else {
                    $beexmlCount = 1; 	// event with zero object makes no sense
            }
	    $subnode = $beexml->createElement('count', $beexmlCount);
	    $node->appendChild($subnode);

	    if (array_key_exists($obj->Objekt, $units)) {
	    	$subnode = $beexml->createElement('unit', $units[$obj->Objekt]);
	    	$node->appendChild($subnode);
	    }

	    $subnode = $beexml->createElement('timePrecision', $config['timePrecision']);
	    $node->appendChild($subnode);

	    $subnode = $beexml->createElement('geoPrecision', $config['geoPrecision']);
	    $node->appendChild($subnode);

	    if ( $obj->Altitude != 0 ) {
                    $beexmlEle = $obj->Altitude;
	    	    $subnode = $beexml->createElement('ele', $beexmlEle);
	            $node->appendChild($subnode);
	    }


    	    if ( $beexmlCmt != "" ) {
                $subnode = $beexml->createElement('cmt', rtrim($beexmlCmt));
                $node->appendChild($subnode);
            }

	    $root->appendChild($node);
        } 
	// Free result set
	$result -> free_result();
}

$conn->close();

$beexml->appendChild($root);
$beexml->save($beexml_file_name);
echo "$beexml_file_name has been successfully created\n";
echo "$maxLat $maxLon\n";
echo "$minLat $minLon\n";

?>
