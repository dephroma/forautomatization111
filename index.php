<?php

$error = ''; // error message 
$show_mode = 0; 
$tokens = [];
$request = array_merge($_GET,$_POST); // request or form
$system = array('password'=>'');

// get setiings files content 
$config = parse_ini_file('config.ini');
$tokens_data = file('tokens.ini',FILE_SKIP_EMPTY_LINES);
$system['current'] = file_get_contents('current.ini');

if (isset($config['show_mode'])) {
	$show_mode = (bool)$config['show_mode'];
}

// pretify tokens array
foreach ($tokens_data as $number => $token) {
	$token = trim($token);
	if (!empty($token)) {
		$tokens[] = $token;
	}
}

// get actual token
if (!isset($tokens[$system['current']])) $system['current'] = 0; 
if (isset($tokens[$system['current']]) && $tokens[$system['current']]) {
	$config['access_token'] = trim($tokens[$system['current']]);
}
else{
	$system['current']++;
	errorFinihs('Token №'.$system['current'].' not found');
}

// error if token not found
if (!isset($config['access_token']) || !$config['access_token']) {
	errorFinihs('Token file is empty: <br>');
}

$system['next']  = $system['current']+1; 
$system['next'] = isset($tokens[$system['next']]) ? $system['next'] : 0;
file_put_contents('current.ini',$system['next']);

if ($config['password'] && (!isset($request['password']) || !isset($config['password']) || $config['password']!=$request['password'])) {
	errorFinihs('Wrong password');
}

// set system params from config
foreach (['allowed_methods','allowed_clubs'] as $param) {
	$system[$param] = array_map('trim', explode(',', $config[$param]));
}

if ($system['allowed_clubs'] && isset($request['group_id']) && !in_array($request['group_id'], $system['allowed_clubs'])) {
	errorFinihs('This club is not allowed');
}

if ($system['allowed_methods'] && isset($system['method']) && !in_array($system['method'], $system['allowed_methods'])) {
	errorFinihs('This method is not allowed');
}

// compose data array 
$data = array_replace_recursive($config, $request);

// set system params from data
foreach (['method','request_url'] as $param) {
	if (isset($data[$param]) && $data[$param]) {
		$system[$param]=$data[$param];
	}
	else {
		$error = 'Param '.$param.' not found';
	}
}

// compose request params array
$params = array_diff_key($data, $system);

if ($show_mode) {
	echo "<pre>"; print_r($tokens); echo "</pre><br><br><pre>"; print_r($system); echo "</pre><br><br><pre>"; print_r($params); echo "</pre>"; exit();
}

if ($error) {
	errorFinihs($error);
}

// ACTION ! 
$response = skyRequest($system['request_url'].$system['method'], $params);
echoExit($response);


// library start

function errorFinihs($message){
	$message_array = array('success'=>false, 'error_type'=>'Setup error', 'error_msg'=>$message);
	echoExit($message_array);
}

function echoExit($message){
	$output = is_string($message) ? $message : json_encode($message,JSON_UNESCAPED_UNICODE);
	header('Content-type: application/json; charset=utf-8'); // after any text !!!
	exit($output);
}

function skyRequest($link='', $params = array()){
    if ($link) {
        $curl_opt = array(
          CURLOPT_URL => $link,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_CONNECTTIMEOUT => 10,
          CURLOPT_TIMEOUT => 60,
          CURLOPT_POST => true,
          CURLOPT_FOLLOWLOCATION =>true,
          CURLOPT_POSTFIELDS => http_build_query($params)
        );

        $skyCurl = curl_init();
        curl_setopt_array($skyCurl, $curl_opt);
        $response = curl_exec($skyCurl);
        curl_close($skyCurl); 
    }
    else{
        $response = '{"success":false,"error_type":"Setup error","error_msg":"URL not found"}';
    }
    return $response;           
}

function GetAutopilotHash($params, $secret){
    $values = "";
    foreach ($params as $value) { $values .= (is_array($value) ? implode("", $value) : $value); }
    return md5($values . $secret);
}