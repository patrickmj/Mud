<?php

class MudPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $dcEls = array();

    protected $_hooks = array(
            'install',
            'after_save_item',
            );

    protected $_filters = array(
            'filterDiscipline' => array('Display', 'Item', 'MUD Elements', 'DISCIPL'),
            'filterIncomeCd'   => array('Display', 'Item', 'MUD Elements', 'INCOMECD'),
            'filterLocale4'    => array('Display', 'Item', 'MUD Elements', 'LOCALE4'),
            'filterAamreg'     => array('Display', 'Item', 'MUD Elements', 'AAMREG'),
    );
    
    public function hookInstall($args)
    {
        $this->installMudElements();
    }

    public function filterDiscipline($value, $args)
    {
        switch($value) {
            case 'ART':
                return __("Art Museums");
                break;
            case 'BOT':
                return __("Aroboretums, Botanitcal Gardends, And Nature Centers");
                break;
            case 'CMU':
                return __("Children's Museums");
                break;
            case 'GMU':
                return __("Uncategorized or General Museums");
                break;
            case 'HSC':
                return __("Historical Societies, Historic Preservation");
                break;
            case 'HST':
                return __("History Museums");
                break;
            case 'NAT':
                return __("Natural History and Natural Science Museums");
                break;
            case 'SCI':
                return __("Science and Technology Museums and Planetariums");
                break;
            case 'ZAW':
                return __("Zoos, Aquariums, and Wildlife Conservation");
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

    public function hookAfterSaveItem($args)
    {
        $item = $args['record'];
        $geoTable = get_db()->getTable('Location');
        $location = $geoTable->findLocationByItem($item, true);
        if(! $location) {
            $location = new Location();
            $location->item_id = $item->id;
            $location->latitude = metadata($item, array('MUD Elements', 'LATITUDE'));
            $location->longitude = metadata($item, array('MUD Elements', 'LONGITUDE'));
            $location->zoom_level = 3;
            $location->map_type = 'Google Maps v3.x';
            $location->address = '';
            if(! is_null($location->latitude)) {
                $location->save();
            }
        }
        $url = metadata($item, array('MUD Elements', 'WEBURL'));
        $dbpediaData = $this->dbpediaData($url);
        $picUrl = $dbpediaData['pic'];
        if($picUrl) {
            insert_files_for_item($item, 'Url', array($picUrl));
        }

        if(! empty($dbpediaData['desc'])) {
            $dcDescEl = $this->getDcEl('Description');
            $item->addTextForElement($dcDescEl, $dbpediaData['desc']);
        }
        
        $this->addDcTitles($item);
        $this->addDcIds($item);
        $this->addDcType($item);
    
        $item->saveElementTexts();
    }

    protected function addDcTitles($item)
    {
        $dcTitleEl = $this->getDcEl('Title');
        $item->addTextForElement($dcTitleEl, metadata($item, array('MUD Elements', 'NAME')));
        $item->addTextForElement($dcTitleEl, metadata($item, array('MUD Elements', 'ALTNAME')));
    }

    protected function addDcIds($item)
    {
        $dcIdEl = $this->getDcEl('Identifier');
        $item->addTextForElement($dcIdEl, 'mid_' . metadata($item, array('MUD Elements', 'MID')));
        $item->addTextForElement($dcIdEl, 'ein_' . metadata($item, array('MUD Elements', 'EIN')));
    }

    protected function addDcType($item)
    {
        $dcTypeEl = $this->getDcEl('Type');
        $item->addTextForElement($dcTypeEl, metadata($item, array('MUD Elements', 'DISCIPL')));
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
                    'name' =>'IRS990',
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
                    )
        );
        insert_element_set($elementSetMetadata, $elements);
    }
    
    protected function dbpediaData($url)
    {
        $url = rtrim($url, '/');
        //$url = "http://americanart.si.edu";
        //$url = "http://www.armyavnmuseum.org";
        //make four attempts at looking up additional data
        //first, the url without trailing slash
        $data = $this->queryDbpedia($url);
        
        //second, the url with trailing slash
        if(! $data) {
            $url = $url . '/';
        }
        usleep(100);
        $data = $this->queryDbpedia($url);
        //third, see if the url is redirected, and lookup the redirected url, sans slash
        
        /*
        if(! $data) {
            $client = new Zend_Http_Client();
            $client->setUri($url);
            $response = $client->request();
            echo $client->getUri();
            
        }
        //try the redirected url, with the slash
        
        //$this->queryDbpedia($url);
        
         */
        
        return $data;
    }
    
    protected function queryDbpedia($url)
    {
        $sparql = "
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

            SELECT DISTINCT ?pic ?desc ?s ?wikipediaUrl WHERE {
            ?s dbpedia2:website <$url> ;
               foaf:depiction ?pic ;
               foaf:isPrimaryTopicOf ?wikipediaUrl ;
               <http://dbpedia.org/ontology/abstract> ?desc 
            FILTER(langMatches(lang(?desc), 'EN'))
            }";
        $client = new Zend_Http_Client();
        $client->setUri('http://dbpedia.org/sparql');
        $client->setParameterGet('query', $sparql);
        $client->setParameterGet('output', 'json');
        $response = $client->request();
        $body = json_decode($response->getBody(), true);
        if(empty($body['results']['bindings'])) {
            return false;
        } else {
            $pic = $body['results']['bindings'][0]['pic']['value'];
            $desc = $body['results']['bindings'][0]['desc']['value'];
            return array('pic' => $pic, 'desc' => $desc);
        }
    }
    
    protected function getDcEl($elementName)
    {
        if(! isset($this->dcEls[$elementName])) {
            $this->dcEls[$elementName] = get_db()->getTable('Element')->findByElementSetNameAndElementName('Dublin Core', $elementName);
        }
        return $this->dcEls[$elementName];
    }
}