<?php

class Mud_TestController extends Omeka_Controller_AbstractActionController
{
    
    public function testAction()
    {
        
        $url = "http://americanart.si.edu";
        $url = "http://www.armyavnmuseum.org";
        //make four attempts at looking up additional data
        //first, the url without trailing slash
        $data = $this->queryDbpedia($url);
        //$data = false;
        
        //second, the url with trailing slash
        if(! $data) {
            $url = $url . '/';
        }
        usleep(200);
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
            
            
            SELECT DISTINCT ?pic ?desc WHERE {
            ?s dbpedia2:website <$url> ;
               foaf:depiction ?pic ;
               <http://dbpedia.org/ontology/abstract> ?desc 
            FILTER(langMatches(lang(?desc), 'EN'))
            }";
        
        $client = new Zend_Http_Client();
        $client->setUri('http://dbpedia.org/sparql');
        $client->setParameterGet('query', $sparql);
        $client->setParameterGet('output', 'json');
        $response = $client->request();
        $body = json_decode($response->getBody(), true);
        print_r($response);
        if(empty($body['results']['bindings'])) {
            echo 'empty';
            return false;
        } else {
            $pic = $body['results']['bindings'][0]['pic']['value'];
            $desc = $body['results']['bindings'][0]['desc']['value'];
            return array('pic' => $pic, 'desc' => $desc);
        }
    }
}