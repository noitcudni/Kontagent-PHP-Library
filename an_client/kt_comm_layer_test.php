<?php
include_once 'kt_comm_layer.php';

$pb = new Kt_Comm('api.geo.kontagent.net');
$pb->api_call_method('/api', 'v1', 2345, 2345, 'test_func', array());
echo "done\n";
 
?>
