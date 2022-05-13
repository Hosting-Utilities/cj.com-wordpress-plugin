<?php
global $cj_proxy_path;

$url = 'https://www.mczbf.com/' . $cj_proxy_path;
// At this point we could have something malicous ie a URL of https://www.mczbf.com/@evildomain.com/

if (strpos($url, '@') !== false){
    throw new Exception('Invalid URL. The proxy cannot handle URLs with an @ symbol');
}
// On PHP 7.0.7+ we will also be using CURLOPT_CONNECT_TO later on to prevent the domain from being spoofed

// If $cj_proxy_path was an absolute URL, fix $url
$relative_proxy_url = parse_url(plugin_dir_url(CJ_TRACKING_PLUGIN_PATH.'/placeholder'), PHP_URL_PATH).'proxy/';
$matches = null;
$pattern = '@^' . preg_quote($relative_proxy_url) . '(.*)@';
if (preg_match( $pattern, $cj_proxy_path, $matches) && isset($matches[1])){
    $url = 'https://www.mczbf.com/' . $matches[1];
}


if (! function_exists('cj_get_server_ip_addr')){
    function cj_get_server_ip_addr(){
        // According to https://stackoverflow.com/a/29733041/786593 both methods of finding the
        // IP address have their shortcomings, so we'll try using $_SERVER first and then a DNS lookup of the hostname
        $ip = $_SERVER['SERVER_NAME'];
        if (
            ! $ip
            || in_array($ip, array('127.0.0.1', '::1', 'localhost', ''))
            || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE |  FILTER_FLAG_NO_RES_RANGE)
        ){
            $host= gethostname();
            $ip = gethostbyname($host);
        }
        return $ip;
    }
}

$tag_id = get_cj_settings()['action_tracker_id'];

if (! $tag_id)
    exit('console.error("No cj.com Action tracker ID")');

$ch = curl_init();

// // Ignore Bad SSL Certificates
// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

// Support compressed responses
curl_setopt($ch, CURLOPT_ENCODING , "");
curl_setopt($ch, CURLOPT_ACCEPT_ENCODING , "");

// Use the same headers
$headers = apache_request_headers();

$parse_url = parse_url(home_url($_SERVER['REQUEST_URI']));

// with the CJ stuff added to it
$headers = array_merge($headers,
    array(
        'X-Forwarded-For'  => cj_get_server_ip_addr(),
        'X-Forwarded-Host'  => $parse_url['host'],
        'X-Forwarded-Server'  => gethostname() . ' utilizing a WordPress plugin',
        'X-Forwarded-Request-Host'  => $parse_url['host'],
        'X-Forwarded-Request-Path'  => $parse_url['path'],
    )
);

// force HTTP 1.1
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

// Make sure the connection header does not try to upgrade us to HTTP/2
if ( isset($headers['connection']) && strpos(strtolower($headers['connection']), 'upgrade') !== false ||
		 isset($headers['Connection']) && strpos(strtolower($headers['Connection']), 'upgrade') !== false ){

		 unset($headers['connection']);
		 unset($headers['Connection']);
		 $headers["Connection"] = 'keep-alive';
}

// For some reason it doesn't work if we don't unset this
if (isset($headers['Host']))
    unset($headers['Host']);
if (isset($headers['Content-Length'])) /* with different compressions it can be wrong */
    unset($headers['Content-Length']);

array_walk($headers, function(&$v, $k){$v = "$k: $v";});
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Use the same URL, except replace the domain with the new ip address
//$url = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $ip_address . $_SERVER['REQUEST_URI'];
curl_setopt($ch, CURLOPT_URL, $url);

if ( version_compare( PHP_VERSION, '7.0.7' ) === 1 ){
    curl_setopt($ch, CURLOPT_CONNECT_TO, array('www.mczbf.com')); // Extra protection to prevent malicous URLs that take us to other domains
}

// Tell Curl to save the response headers so we can retreive them later
//curl_setopt($ch, CURLINFO_HEADER_OUT, true);
curl_setopt($ch, CURLOPT_HEADER, true);

// Return the response instead of displaying it, so we can have a chance to set the response headers first
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// pass along POST requests. Note: This doesn't handle other requests methods.
// You may consider just using the htaccess ProxyPass instead
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	curl_setopt($ch, CURLOPT_POST, true );
	curl_setopt($ch, CURLOPT_POSTFIELDS, $_POST);
}

// Execute our Curl request, and display the result
$result = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$response_headers = substr($result, 0, $header_size);
$response_body = substr($result, $header_size);

// Set our response headers to be the same as the headers returned from Curl
// This could potentially run into problems if the current server does not support the HTTP/2 protocal, but I think
// the protocal is standard enough that this shouldn't be an issue
//$response_headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
$response_headers = explode("\n", $response_headers);

// It's not necessarry, but we're going to set the alternative service header
//$response_headers['Alt-Svc'] = "Alt-Svc: http/1.1=\"{$ip_address}\"; ma=2000";

// remove first line of requests string ie "GET /bar HTTP/1.1" or "GET /bar HTTP/2"
if (preg_match('/HTTP\/\d/', $response_headers[0])) {
	unset($response_headers[0]);
}

if (strpos($_SERVER['REQUEST_URI'], 'pageInfo')){
    // foreach($response_headers as $k => $header){
    //     if (preg_match('/^Host:/', $header)) {
    //     	$response_headers[$k] = 'Host: ' . $parse_url['host'];
    //         unset($response_headers[$k]);
    //     }
    // }
    foreach($response_headers as $k => $header){
        if (preg_match('/^Content-Length/', $header)){
            unset($response_headers[$k]);
        }
    }
}
// if (strpos($_SERVER['REQUEST_URI'], 'seteventid')){
//     var_dump($result);
// }
$response_headers = array_filter($response_headers, function($header){
    return (
        trim($header)
        //&& ! preg_match('/^Accept-Encoding:/', $header)
        // CURL already took care of decompressing, and Apache/Nginx will handle the encoding headers from here, setting it ourselves will mess things up
        /* They are also not allowed in HTTP/2 */
        && ! preg_match('/^Transfer-Encoding:/', $header)
        && ! preg_match('/^Content-Encoding:/', $header)
        /* Assuming HTTP/2 is being used
        The following are not allowed in HTTP/2
        (along with any headers nominated by the connection header, whatever that means)
        (I think some of these or all of these might be request headers instead of response headers) */
        && ! preg_match('/^Connection:/', $header)
        && ! preg_match('/^Keep-Alive:/', $header)
        && ! preg_match('/^Proxy-Connection:/', $header)
        && ! preg_match('/^Upgrade:/', $header)
    );
});
foreach($response_headers as $header){
	header($header, false);
}

if (defined('WP_DEBUG') && WP_DEBUG){
    header("X-Proxy-Requested: $url");
    header("X-Curl-Version: " . json_encode(curl_version()));
    header("X-PHP-Version: " . json_encode(phpversion()));
    header("X-Request-Size: " . (string)strlen((string)$response_body));
}

echo $response_body;

exit;
