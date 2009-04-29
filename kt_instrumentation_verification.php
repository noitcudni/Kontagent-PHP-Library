<?php
include_once 'kt_config.php'; 

$notification_str = "Kontagent Instrumentation Verification: http://kontagent.instrumentation.com/verification"; 
$sender_id = "12345";
$rev_id = "67890";
$template_id = 10;
$subtype1 = "instrumentation verification";
$subtype2 = "notification type";

$an->override_backend_host("api.test.kontagent.net", 80);
$uuid = $an->gen_notifications_link($notification_str, $template_id, $subtype1, $subtype2); 
// send a fake notification message to the testing server.

$an->kt_notifications_send($sender_id, $rev_id, $uuid, $template_id, $subtype1, $subtype2);

$arg_assoc_array = Array();
$arg_assoc_array['kt_api_key'] = $backend_api_key;
$arg_assoc_array['uuid'] = $uuid;
$arg_assoc_array['t'] = $template_id;
$arg_assoc_array['st1'] = $subtype1;
$arg_assoc_array['st2'] = $subtype2;
$query_str = http_build_query($arg_assoc_array , '' , '&');

//$verification_ui_url = "http://192.168.1.102:9999/dashboard/instrumentation_verification/?$query_str";
$verification_ui_url = "http://www.kontagent.com/dashboard/instrumentation_verification/?$query_str";

$sock = fopen($verification_ui_url, 'r');
if($sock){
    $result = '';
    while (!feof($sock) )
    {
        $result.=fgets($sock, 4096);
    }
    echo $result;
    fclose($sock);
}


?>