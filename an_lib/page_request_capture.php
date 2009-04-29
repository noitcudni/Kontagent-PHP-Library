<?php
$dir_name = dirname(__FILE__);
include_once $dir_name . '/../kt_config.php';

$url_str = $an->get_page_tracking_url();

if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
{
    //ajax call, so don't include the image tracking tag.
}
else
{
    echo '<img src="'.$url_str.'" width="1px" height="1px" style="display:none;"/>';
}

?>



