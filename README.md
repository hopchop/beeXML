# beeXML
 beeXML standard for bee related data
 
 More information about this project is available at http://beexml.org/ .

PHP code I used to generate the sample:

export2beexml.php

It uses a few configuration files to setup the database access and to map from internal german nomenclature to proposed beeXML terminology:

beexml.cfg
beexml_apiaries.cfg
beexml_export.xml
beexml_objectmap.cfg
beexml_typemap.cfg
beexml_units.cfg

The PHP code contains the mapping from my database to beeXML. It should be fairly easy to adapt it to other data sources.

I have validated the sample beeXML file against the beeXML.xsd file using this code:

beexml_validate.php

The elements and attributes are documented in the beeXML.xsd file and can be viewed here:

http://beexml.org/wp-content/uploads/beeXML.xsd

Obviously this is work in progress and everyones feedback would be much appreciated. It’s a quick and dirty job, but someone had to do it :-)
