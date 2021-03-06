<?php

class MudPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $dcEls = array();

    protected $mudEls = array();

    private $dbpediaData = false;
    
    
    protected $_hooks = array(
            'install',
            'after_save_item',
            'after_delete_item'
            );

    protected $_filters = array(
            'filterDiscipline' => array('Display', 'Item', 'MUD Elements', 'DISCIPL'),
            'filterIncomeCd'   => array('Display', 'Item', 'MUD Elements', 'INCOMECD'),
            'filterLocale4'    => array('Display', 'Item', 'MUD Elements', 'LOCALE4'),
            'filterAamreg'     => array('Display', 'Item', 'MUD Elements', 'AAMREG'),
            'filterPhone'      => array('Display', 'Item', 'MUD Elements', 'PHONE'),
            'api_resources'
    );

    const PREFIXES = "
            PREFIX owl: <http://www.w3.org/2002/07/owl#>
            PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
            PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
            PREFIX foaf: <http://xmlns.com/foaf/0.1/>
            PREFIX dc: <http://purl.org/dc/elements/1.1/>
            PREFIX : <http://dbpedia.org/resource/>
            PREFIX dbpedia2: <http://dbpedia.org/property/>
            PREFIX dbpedia: <http://dbpedia.org/>
            PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
    ";
    
    public function hookInstall($args)
    {
        $db = $this->_db;
        $sql = "
            CREATE TABLE IF NOT EXISTS `$db->MudIdsMap` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `mid` bigint(20) unsigned NOT NULL,
              `item_id` int(10) unsigned NOT NULL,
              `dbpedia_uri` text COLLATE utf8_unicode_ci,
              `messages` text COLLATE utf8_unicode_ci,
              PRIMARY KEY (`id`),
              UNIQUE KEY `mid` (`mid`,`item_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;
            ";
        $db->queryBlock($sql);
        $this->installMudElements();
    }

    public function filterPhone($value, $args)
    {
        return "(".substr($value, 0, 3).") ".substr($value, 3, 3)."-".substr($value,6);
    }
    
    public function filterApiResources($apiResources)
    {
        $apiResources['muditems'] = array(
                'record_type' => 'MudIdsMap',
                'actions'      => array('get', 'index'),
                'index_params' => array('mid')
                );
        return $apiResources;
    }

    public function filterDiscipline($value, $args)
    {
        switch($value) {
            case 'ART':
                return "Art Museums";
                break;
            case 'BOT':
                return "Arboretums, Botanitcal Gardens, And Nature Centers";
                break;
            case 'CMU':
                return "Children's Museums";
                break;
            case 'GMU':
                return "Uncategorized or General Museums";
                break;
            case 'HSC':
                return "Historical Societies, Historic Preservation";
                break;
            case 'HST':
                return "History Museums";
                break;
            case 'NAT':
                return "Natural History and Natural Science Museums";
                break;
            case 'SCI':
                return "Science and Technology Museums and Planetariums";
                break;
            case 'ZAW':
                return "Zoos, Aquariums, and Wildlife Conservation";
                break;
        }
    }

    public function filterIncomeCd($value, $args)
    {
        switch($value) {
            case '0':
                return '$0';
                break;
            case '1':
                return '$1 to $9,000';
                break;
            case '2':
                return '$10,000 to $24,999';
                break;
            case '3':
                return '$25,000 to $99,999';
                break;
            case '4':
                return '$100,000 to $499,999';
                break;
            case '5':
                return '$500,000 to $999,999';
                break;
            case '6':
                return '$1,000,000 to $4,999,999';
                break;
            case '7':
                return '$5,000,000 to $9,999,999';
                break;
            case '8':
                return '$10,000,000 to $49,999,999';
                break;
            case '9';
                return '$50,000,000 to greater';
                break;
            default:
                return 'Unknown';
                break;
        }
    }

    public function filterLocale4($value, $args)
    {
        switch($value) {
            case '1':
                return 'City';
                break;
            case '2':
                return 'Suburb';
                break;
            case '3':
                return 'Town';
                break;
            case '4':
                return 'Rural';
                break;
            default:
                return 'Unknown';
                break;
        }
    }
    
    public function filterAamreg($value, $args) 
    {
        switch($value) {
            case '1':
                return 'New England';
                break;
            case '2':
                return 'Mid-Atlantic';
                break;
            case '3':
                return 'Southeastern';
                break;
            case '4':
                return 'Midwest';
                break;
            case '5':
                return 'Mount Plains';
                break;
            case '6':
                return 'Western';
                break;
            default:
                return 'Unknown';
                break;
        }
    }
    
    public function hookAfterDeleteItem($args)
    {
        $item = $args['record'];
        $mudMaps = $this->_db->getTable('MudIdsMap')->findBy(array('item_id' => $item->id));
        foreach ($mudMaps as $map) {
            $map->delete();
        }
    }
    
    /**
     * Add the data that requires an item id
     * @param array $args
     */
    public function hookAfterSaveItem($args)
    {
        $item = $args['record'];
        $geoTable = get_db()->getTable('Location');
        $location = $geoTable->findLocationByItem($item, true);
        if (! $location) {
            $location = new Location();
            $location->item_id = $item->id;
            $location->latitude = metadata($item, array('MUD Elements', 'LATITUDE'));
            $location->longitude = metadata($item, array('MUD Elements', 'LONGITUDE'));
            $location->zoom_level = '12';
            $location->map_type = 'Google Maps v3.x';
            $location->address = '';
            if (! is_null($location->latitude)) {
                $location->save();
            }
        }
        $this->addDcTitles($item);
        $this->addDcIds($item);
        $this->addDcType($item);

        
        //validate the url. if not, leave a message on the MudIdMap
        //check on initial import
        $this->dbpediaData = false;
        $this->fetchDbpediaData($item);


        //check on saving with new data
        
        
        if ($this->dbpediaData) {
            $dcDescription = metadata($item, array('Dublin Core', 'Description'));
            if (! empty($this->dbpediaData['desc']) && empty($dcDescription)) {
                $dcDescEl = $this->getDcEl('Description');
                $item->addTextForElement($dcDescEl, $this->dbpediaData['desc']);
            }
            $dbpediaUri = metadata($item, array('MUD Elements', 'DBpedia Uri'));
            if (! empty($this->dbpediaData['dbpediaUri']) && empty($dbpediaUri)) {
                $mudDbpediaEl = $this->getMudEl('DBpedia Uri');
                $item->addTextForElement($mudDbpediaEl, $this->dbpediaData['dbpediaUri']);
            }
            $wikipediaUrl = metadata($item, array('MUD Elements', 'Wikipedia Url'));
            if (! empty($this->dbpediaData['wikipediaUrl']) && empty($wikipediaUrl)) {
                $mudWikipediaEl = $this->getMudEl('Wikipedia Url');
                $item->addTextForElement($mudWikipediaEl, $this->dbpediaData['wikipediaUrl']);
            }
            $picUrl = $this->dbpediaData['pic'];
            //@TODO: maneuvering around pic url variations will have to be abstracted out someplace
            if ($picUrl) {
                $fileFail = false;
                try {
                    insert_files_for_item($item, 'Url', array($picUrl));
                } catch(Exception $e) {
                    _log($e->getMessage());
                    $fileFail = true;
                }
            }
            if ($fileFail) {
                $picUrl = str_replace('commons', 'en', $picUrl);
            }
            if ($picUrl) {
                $fileFail = false;
                try {
                    insert_files_for_item($item, 'Url', array($picUrl));
                } catch(Exception $e) {
                    _log($e->getMessage());
                    $fileFail = true;
                }
            }            
            
        }
        $item->saveElementTexts();
        $searchTitle = metadata($item, array('Dublin Core', 'Title'));
        $searchText = metadata($item, array('Dublin Core', 'Description'));
        Mixin_Search::saveSearchText('Item', $item->id, $searchText, $searchTitle);
        $this->createMudIdsMap($item);
    }

    protected function addDcTitles($item)
    {
        $dcTitleEl = $this->getDcEl('Title');
        $name = metadata($item, array('MUD Elements', 'NAME'));
        $altName = metadata($item, array('MUD Elements', 'ALTNAME'));
        $dcTitle = metadata($item, array('Dublin Core', 'Title'));
        if (empty($dcTitle)) {
            if (!empty($name)) {
                $item->addTextForElement($dcTitleEl, $name);
            }
            if (!empty($altName)) {
                $item->addTextForElement($dcTitleEl, $altName);
            }
        }
    }

    protected function addDcIds($item)
    {
        $dcIdEl = $this->getDcEl('Identifier');
        $mid = metadata($item, array('MUD Elements', 'MID'));
        $ein = metadata($item, array('MUD Elements', 'EIN'));
        $dcId = metadata($item, array('Dublin Core', 'Identifier'));
        if (empty($dcId)) {
            if (!empty($ein)) {
                $item->addTextForElement($dcIdEl, 'ein_' . $ein);    
            }
            $item->addTextForElement($dcIdEl, 'mid_' . $mid);            
        }
    }

    protected function addDcType($item)
    {
        $disc = metadata($item, array('MUD Elements', 'DISCIPL'));
        $dcType = metadata($item, array('Dublin Core', 'Type'));
        if (empty($dcType)) {
            if (! empty($disc)) {
                $dcTypeEl = $this->getDcEl('Type');
                $item->addTextForElement($dcTypeEl, $disc);
            }
        }
    }

    protected function installMudElements()
    {
        $elementSetMetadata = array('name' => 'MUD Elements', 'description' => 'IMLS Museum Universe Metadata');
        $elements = array(
                array(
                    'name' => 'MID',
                    'description' => 'Unique mueseum identifier'
                    ),
                array(
                    'name' => 'NAME',
                    'description' => 'Name of institution'
                    ),
                array(
                    'name' => 'ALTNAME',
                    'description' => 'Alternative name of institution'
                    ),
                array(
                    'name' => 'ADDRESS',
                    'description' => 'Address institution, Street Address'
                    ),
                array(
                    'name' => 'CITY',
                    'description' => ''
                    ),
                array(
                    'name' => 'STATE',
                    'description' => ''
                    ),
                array(
                    'name' => 'ZIP',
                    'description' => ''
                    ),
                array(
                    'name' => 'ZIP5',
                    'description' => ''
                    ),
                array(
                    'name' => 'ZIP4',
                    'description' => ''
                    ),
                array(
                    'name' => 'PHONE',
                    'description' => ''
                    ),
                array(
                    'name' => 'WEBURL',
                    'description' => ''
                    ),
                array(
                    'name' => 'DISCIPL',
                    'description' => 'Museum discipline or type'
                    ),
                array(
                    'name' => 'EIN',
                    'description' => 'Federal Employer Idenfication Number'
                    ),
                array(
                    'name' => 'NTEECC',
                    'description' => ''
                    ),
                array(
                    'name' => 'TAXPER',
                    'description' => ''
                    ),
                array(
                    'name' => 'INCOME',
                    'description' => ''
                    ),
                array(
                    'name' => 'REVENUE',
                    'description' => ''
                    ),
                array(
                    'name' => 'INCOMECD',
                    'description' => ''
                    ),
                array(
                    'name' => 'LOCALE4',
                    'description' => ''
                    ),
                array(
                    'name' => 'AAMREG',
                    'description' => ''
                    ),
                array(
                    'name' => 'LATITUDE',
                    'description' => ''
                    ),
                array(
                    'name' => 'LONGITUDE',
                    'description' => ''
                    ),
                array(
                    'name' => 'FIPSST',
                    'description' => ''
                    ),
                array(
                    'name' => 'FIPSCO',
                    'description' => ''
                    ),
                array(
                    'name' => 'TRACT',
                    'description' => ''
                    ),
                array(
                    'name' => 'BLOCK',
                    'description' => ''
                    ),
                array(
                    'name' => 'FIPSMIN',
                    'description' => ''
                    ),
                array(
                    'name' => 'FIPSPLAC',
                    'description' => ''
                    ),
                array(
                    'name' => 'CBSACODE',
                    'description' => ''
                    ),
                array(
                    'name' =>'METRODIV',
                    'description' => ''
                    ),
                array(
                    'name' =>'MICROF',
                    'description' => ''
                    ),
                array(
                    'name' =>'CNTRYCD',
                    'description' => ''
                    ),
                array(
                    'name' =>'IRS990_F',
                    'description' => ''
                    ),
                array(
                    'name' =>'IMLSAD_F',
                    'description' => ''
                    ),
                array(
                    'name' =>'FCT3P_F',
                    'description' => ''
                    ),
                array(
                    'name' =>'PFND_F',
                    'description' => ''
                    ),
                array(
                    'name' => 'Wikipedia Url',
                    'description' => ''
                    ),
                array(
                    'name' => 'DBpedia Uri',
                    'description' => ''
                    )
        );
        insert_element_set($elementSetMetadata, $elements);
    }
    
    protected function fetchDbpediaData($item)
    {

        $url = metadata($item, array('MUD Elements', 'WEBURL'));
        $url = rtrim($url, '/');
        if( (! empty($url)) && Zend_Uri::check($url)) {
            $data = $this->queryDbpedia($url);
        }        
        
        //second, the url with trailing slash
        if(! $data) {
            $url = $url . '/';
            sleep(1);
            $data = $this->queryDbpedia($url);
        }
        //try via a stored wikipedia url
        $wikipediaUrl = metadata($item, array('MUD Elements', 'Wikipedia Url'));
        if (! $data) {
            $data = $this->queryDbpediaByWikipediaUrl($wikipediaUrl);
        }
        //insert new variations, like digging up a redirected / updataed url, here
        
        
        $this->dbpediaData = $data;
    }
    
    protected function sparqlDbpedia($sparql) 
    {
        $client = new Zend_Http_Client();
        $client->setUri('http://dbpedia.org/sparql');
        $client->setParameterGet('query', $sparql);
        $client->setParameterGet('output', 'json');
        $response = $client->request();
        $body = json_decode($response->getBody(), true);
        debug(print_r($body['results']['bindings'], true));
        if(empty($body['results']['bindings'])) {
            return false;
        } else {
            $pic = $body['results']['bindings'][0]['pic']['value'];
            $desc = $body['results']['bindings'][0]['desc']['value'];
            $dbpediaUri = $body['results']['bindings'][0]['s']['value'];
            $wikipediaUrl = $body['results']['bindings'][0]['wikipediaUrl']['value'];
            return array('pic' => $pic, 
                         'desc' => $desc, 
                         'dbpediaUri' => $dbpediaUri, 
                         'wikipediaUrl' => $wikipediaUrl
                        );
        }
    }
    
    protected function queryDbpediaByWikipediaUrl($wikipediaUrl)
    {
        $sparql =  self::PREFIXES . "
            SELECT DISTINCT ?pic ?desc ?s ?wikipediaUrl WHERE {
            ?s ?p ?wikipediaUrl ;
               foaf:isPrimaryTopicOf <{$wikipediaUrl}> ;
               foaf:isPrimaryTopicOf ?wikipediaUrl;
               <http://dbpedia.org/ontology/abstract> ?desc .
               
               OPTIONAL {
                  ?s foaf:depiction ?pic .
               }               
            FILTER(langMatches(lang(?desc), 'EN'))
            }";
        
        debug($sparql);
        return $this->sparqlDbpedia($sparql);
    }
    
    protected function queryDbpedia($url)
    {
        $sparql = self::PREFIXES .  "
            SELECT DISTINCT ?pic ?desc ?s ?wikipediaUrl WHERE {
            ?s dbpedia2:website <$url> ;
               foaf:depiction ?pic ;
               foaf:isPrimaryTopicOf ?wikipediaUrl ;
               <http://dbpedia.org/ontology/abstract> ?desc 
            FILTER(langMatches(lang(?desc), 'EN'))
            }";
        return $this->sparqlDbpedia($sparql);

    }

    protected function getDcEl($elementName)
    {
        if(! isset($this->dcEls[$elementName])) {
            $this->dcEls[$elementName] = get_db()->getTable('Element')->findByElementSetNameAndElementName('Dublin Core', $elementName);
        }
        return $this->dcEls[$elementName];
    }

    protected function getMudEl($elementName)
    {
        if(! isset($this->mudEls[$elementName])) {
            $this->mudEls[$elementName] = get_db()->getTable('Element')->findByElementSetNameAndElementName('MUD Elements', $elementName);
        }
        return $this->mudEls[$elementName];
    }
    
    protected function createMudIdsMap($item)
    {
        $mid = metadata($item, array('MUD Elements', 'MID'));
        $table = $this->_db->getTable('MudIdsMap');
        $select = $table->getSelect();
        $select->where('mid = ?', $mid);
        
        $record = $table->fetchObject($select);
        if(! $record) {
            $record = new MudIdsMap;
            $record->item_id = $item->id;
            $record->mid = $mid;
        }
        if ($this->dbpediaData) {
            $record->dbpedia_uri = $this->dbpediaData['dbpediaUri'];
        }

        $url = metadata($item, array('MUD Elements', 'WEBURL'));
        $validUrl = Zend_Uri::check($url);
        if (! empty($url) && ! $validUrl) {
            $record->messages = json_encode(array('url' => $url));
        }
        $record->save();
    }
}