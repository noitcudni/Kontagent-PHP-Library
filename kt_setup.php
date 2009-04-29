<?php

array_shift($argv);

$use_ab_testing = false;

if( count($argv) == 0 )
{
    // Kontagent an_lib version KONTAGENT_VERSION_NUMBER
    $settings = parse_ini_file('kt_settings.ini');
}
else if( count($argv) == 1 )
{
    if ( $argv[0] == "-ab" )
    {
        $use_ab_testing = true;
        $settings = parse_ini_file('kt_settings.ini');
    }
    else
    {
        $settings = parse_ini_file($argv[0]);
        if( $settings == false )
        {
            print "Err: Failed to open $argv[0] or it's not a valid ini file\n";
            exit(0);
        }
    }
}
else if( count($argv) == 2 )
{
    // assuming that the first argument has to be ab testing related.
    if( $argv[0] == "-ab")
    {
        $use_ab_testing = true;
        $settings = parse_ini_file('kt_settings.ini');
    }
    else
    {
        print "Err: Don't understand what to do with ". $argv[0];
        exit(0);
    }

    $settings = parse_ini_file($argv[1]);
    if( $settings == false )
    {
        print "Err: Failed to open $argv[0] or it's not a valid ini file\n";
        exit(0);
    }
}


// include the facebook library
$fb_lib_path = $settings['FB_LIB_FULL_PATH']."\n";
$len = strlen($fb_lib_path);
if($fb_lib_path[$len - 1] == "/")
{
    include_once $settings['FB_LIB_FULL_PATH']."facebook.php";
}
else
{
    include_once $settings['FB_LIB_FULL_PATH']."/facebook.php";
}

$FH = fopen('kt_config.php', 'w');

// construct the facebook object.
$fb = new Facebook($settings['FB_API_KEY'], $settings['FB_SECRET_KEY']);

// get all the necessary properties.
$arr = $fb->api_client->admin_getAppProperties(array('application_name','callback_url','canvas_name', 'ip_list'));

// make sure doc root ends with a '/'
$kt_an_client_path = ltrim($settings['KT_AN_CLIENT_PATH'], "/");
//$kt_an_lib_path = ltrim($settings['KT_AN_LIB_PATH'], "/");

// write to kt_config.php
fwrite($FH, "<?php\n");
print '$doc_root = rtrim($_SERVER["DOCUMENT_ROOT"], "/"). "/";'."\n";
fwrite($FH, '$doc_root = rtrim($_SERVER["DOCUMENT_ROOT"], "/") . "/";'."\n");


print 'define("AN_CLIENT", $doc_root.'."'".$kt_an_client_path."');\n";
fwrite($FH, 'define("AN_CLIENT", $doc_root.'."'".$kt_an_client_path."');\n");

print "include_once AN_CLIENT.'/kt_facebook.php';\n";
fwrite($FH, "include_once AN_CLIENT.'/kt_facebook.php';\n");
print "include_once AN_CLIENT.'/kt_analytics.php';\n";
fwrite($FH, "include_once AN_CLIENT.'/kt_analytics.php';\n");

// canvas_url
print '$canvas_url = \'http://apps.facebook.com/'.$arr['canvas_name']."';" . "\n";
fwrite($FH, '$canvas_url = \'http://apps.facebook.com/'.$arr['canvas_name']."';" . "\n");

// default post add url. Don't need this anymore for the new facebook.
print '$kt_default_post_add_url = \'http://apps.facebook.com/'.$arr['canvas_name']."';" . "\n";
fwrite($FH, '$kt_default_post_add_url = \'http://apps.facebook.com/'.$arr['canvas_name']."';" . "\n");

$call_back_url = $arr['callback_url'];
$cb_url_arry = parse_url($call_back_url);

$call_back_host = $cb_url_arry['scheme']."://".$cb_url_arry['host'];
if(isset($cb_url_arry['port']))
{
    $call_back_host = $call_back_host.":".$cb_url_arry['port'];
}

// call_back_host
print '$call_back_host = '.$call_back_host.";"."\n";
fwrite($FH, '$call_back_host = \''.$call_back_host."';"."\n");



if(isset($cb_url_arry['path']))
{
    $call_back_req_uri = $cb_url_arry['path'];
}
else 
{
    $call_back_req_uri = "";
}



if(isset($cb_url_arry['query']))
{
    $call_back_req_uri = $call_back_req_uri."?".$cb_url_arry['query'];
}

// call_back_req_uri
print '$call_back_req_uri = '. $call_back_req_uri.";"."\n";
fwrite($FH, '$call_back_req_uri = \''. $call_back_req_uri."';"."\n");
print '$backend_api_key = '. $settings['KT_API_KEY'] .";\n";
fwrite($FH, '$backend_api_key = \''. $settings['KT_API_KEY'] ."';\n");
print '$backend_secret_key = '. $settings['KT_SECRET_KEY'] . ";\n";
fwrite($FH, '$backend_secret_key = \''. $settings['KT_SECRET_KEY'] . "';\n");

if (isset($settings['USE_TEST_SERVER']) && $settings['USE_TEST_SERVER'] == true) {
    $backend_host = "'api.test.kontagent.net'";
}
else {
    #$backend_host = "'api.kontagent.com'";
    $backend_host = "'api.geo.kontagent.net'";
}
$backend_port = "80";

print '$backend_host = ' . $backend_host . ';'."\n";
fwrite($FH, '$backend_host = ' . $backend_host . ';'."\n");
print '$backend_port = ' . $backend_port . ';'."\n";
fwrite($FH, '$backend_port = ' . $backend_port . ';'."\n");

print '$facebook_api_key = '. $settings['FB_API_KEY'] . ";\n";
fwrite($FH, '$facebook_api_key = \''. $settings['FB_API_KEY'] . "';\n");
print '$facebook_secret_key = '. $settings['FB_SECRET_KEY'] . ";\n";
fwrite($FH, '$facebook_secret_key = \''. $settings['FB_SECRET_KEY'] . "';\n");
print '$backend_url  = '."'/api';\n";
fwrite($FH, '$backend_url  = '."'/api';\n");

if ($settings['AUTO_PAGE_REQUEST_CAPTURE']) {
    $automatic_page_request_capture = "true";
}
else {
    $automatic_page_request_capture = "false";
}
print '$automatic_page_request_capture  = '. $automatic_page_request_capture .";\n";
fwrite($FH, '$automatic_page_request_capture  = '. $automatic_page_request_capture .";\n");

if ($settings['AUTO_CAPTURE_USER_INFO_AT_INSTALL']) {
    $auto_capture_user_info_at_install = "true";
}
else {
    $auto_capture_user_info_at_install = "false";
}
print '$auto_capture_user_info_at_install  = '. $auto_capture_user_info_at_install .";\n";
fwrite($FH, '$auto_capture_user_info_at_install  = '. $auto_capture_user_info_at_install .";\n");




//instantiation
print '$kt_facebook = new Kt_Facebook($facebook_api_key, $facebook_secret_key,'."\n";
fwrite($FH, '$kt_facebook = new Kt_Facebook($facebook_api_key, $facebook_secret_key,'."\n");
print '                               $backend_api_key, $backend_secret_key,'."\n";
fwrite($FH, '                               $backend_api_key, $backend_secret_key,'."\n");
print '                                   $backend_host, $backend_port, $backend_url,'."\n";
fwrite($FH, '                               $backend_host, $backend_port, $backend_url,'."\n");
print '                               $canvas_url, $call_back_req_uri);'."\n";
fwrite($FH, '                               $canvas_url, $call_back_req_uri);'."\n");

print '$an = $kt_facebook->api_client->m_an';
fwrite($FH, '$an = $kt_facebook->api_client->m_an'.";\n");


if($use_ab_testing)
{
    $ab_testing_host = 'http://www.kontagent.com';
    $ab_testing_port = '80';
    print '$ab_testing_host = \'' . $ab_testing_host . '\';'."\n";
    fwrite($FH, '$ab_testing_host = \'' . $ab_testing_host . '\';'."\n");
    print '$ab_testing_port = ' . $ab_testing_port . ';'."\n";
    fwrite($FH, '$ab_testing_port = ' . $ab_testing_port . ';'."\n");

    print 'include_once AN_CLIENT.\'/kt_ab_testing.php\';'."\n";
    fwrite($FH, 'include_once AN_CLIENT.\'/kt_ab_testing.php\';'."\n");

    print '$kt_facebook->api_client->user_ab_test($ab_testing_host, $ab_testing_port);'."\n";
    fwrite($FH, '$kt_facebook->api_client->user_ab_test($ab_testing_host, $ab_testing_port);'."\n");
}

fwrite($FH, "?>\n");
fclose($FH);

print "\n";
print "\n";
print ">> kt_config.php is generated.\n";

///////////////// RUN a simple test againist the test server ///////////////// 
print ">> Testing your kontagent setup with our test server....\n";
$index = strlen($call_back_url) - 1;
if($call_back_url[$index] == "/")
    $call_back_url = substr($call_back_url, 0, $index);
$verification_url = $call_back_url. "/kt_instrumentation_verification.php";
print $verification_url . "\n";

$sock = fopen($verification_url, 'r');

if($sock)
{
    $result = '';
    while ( !feof($sock) )
    {
        $result.=fgets($sock, 4096);
    }

    fclose($sock);
    if(trim($result) == "OK")
    {
        print "\tPASSED\n";
    }
    else
    {
        print "\tFAILED\n";
    }
}
else
{
    print "\tFAILED\n";
}


?>