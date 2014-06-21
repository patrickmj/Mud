<?php
class Mud_SearchController extends Omeka_Controller_AbstractActionController
{
    public function indexAction()
    {
        $params = $this->getAllParams();
        $advanced = array();
        if (!empty($params['type'])) {
            $advanced[] = array('element_id' => 51, 'type' => 'is exactly', 'terms' => $params['type']);
        }
        $paramArray = array('search' => '', 'advanced' => $advanced);
        if (!empty($params['zip'])) {
            //dig up lat/long for the zipcode
            $zip = $params['zip'];
            $client = new Zend_Http_Client;
            $client->setUri("http://maps.googleapis.com/maps/api/geocode/json?address=$zip&sensor=false");
            $response = $client->request();
            if ($response->getStatus() == 200) {
                $body = $response->getBody();
                $json = json_decode($body, true);
                $lat = $json['results'][0]['geometry']['location']['lat'];
                $lng = $json['results'][0]['geometry']['location']['lng'];
                $paramArray['geolocation-address'] = $zip;
                $paramArray['geolocation-latitude'] = $lat;
                $paramArray['geolocation-longitude'] = $lng;
                if (isset($params['geolocation-radius'])) {
                    $paramArray['geolocation-radius'] = $params['geolocation-radius'];
                }
            }
        }
        //make a mobile location take precedent
        if ($params['mobile-located'] == 1) {
            $session = new Zend_Session_Namespace();
            $session->geolocationLatitude = $paramArray['geolocation_latitude'];
            $session->geolocationLongitude = $paramArray['geolocation_longitude']; 
            $session->mobileLocated = $params['mobile-located'];
            $paramArray = array('search' => '', 'advanced' => $advanced);
            $paramArray['geolocation-address'] = 'Where you are'; //geolocation needs this to not be empty
            $paramArray['geolocation-latitude'] = $params['geolocation-latitude'];
            $paramArray['geolocation-longitude'] = $params['geolocation-longitude'];
            if (isset($params['geolocation-radius'])) {
                $paramArray['geolocation-radius'] = $params['geolocation-radius'];
            }
        }
        $searchParams = http_build_query($paramArray);
        $this->_helper->redirector->gotoUrl('items/browse?' . $searchParams );
    }
}