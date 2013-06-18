
<?php

/*
 * Helper function to get downstream JSON data.
 * Returns array of http code and results/error message.
 */
function CurlJson($url, $debug=false)
{
    $ch = curl_init($url);
    curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 2 );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $ch, CURLOPT_USERAGENT, "DU-Topics/1.0");
    $data = curl_exec($ch);
    $retries=0;
    while((empty($data)|| curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404||curl_errno($ch) > 0) && ++$retries<=3){
        if($retries==3) break;
        $data = curl_exec($ch);
    }   

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    switch($http_code) {
    case 404:
        return array(404=>'Not found.' . ($debug? ' '.$url: ''));
    case 200:
        if ($debug) {
            return array(200=>array(
                'results'=>array(
                        json_decode($data, TRUE)),
                'debug'=>$url
                        )); 
        }   
        else {
            return array(200=>array(
                'results'=>array(
                        json_decode($data, TRUE))));
        }   
    default:
        return array(502=>'Error on downstream service.' . ($debug? ' '.$url: ''));
    }   
}


var_export(CurlJson("http://search-add-api.prd.use1.nytimes.com/svc/add/v1/lookup.json?_showQuery=true&fq=book%20review&sort=newest&type=article%2Cblogpost&offset=0"));
?>

