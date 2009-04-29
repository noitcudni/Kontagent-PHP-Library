<?php
// Kontagent an_client lib version KONTAGENT_VERSION_NUMBER

class Kt_Comm
{
   private $m_host;
   private $m_ip;
   //private $m_socket;
   
   // NOTE: host has to include a port number even if it's port 80!
   public function __construct($host, $port){
       $this->m_host = $host;
       if($host == "api.geo.kontagent.net")
           $this->fetch_ip($port);
//       else if($host == "api.test.kontagent.net")
//           $this->m_ip = $host . ":80";
       else
           $this->m_ip = $host.":".$port;
   }

   private function fetch_ip($port){
       // First try all servers in geographically-closest datacenter
       $ip_list = gethostbynamel($this->m_host);
       shuffle($ip_list);
       $selected_ip = "";
       foreach ($ip_list as $ip) {
           $socket = stream_socket_client($ip.":".$port, $errno, $errstr, 0.5, STREAM_CLIENT_CONNECT);
           if($socket)
           {
               fclose($socket);           
               $selected_ip = $ip;
               break;
           }
       }// for
       

       // Looks like entire datacenter is down, so try our luck with one of global IPs.
       if($selected_ip == "")
       {
           $global_ip_list = gethostbynamel("api.global.kontagent.net");
           shuffle($ip_list);

           foreach($global_ip_list as $global_ip)
           {
               $socket = stream_socket_client($global_ip.":".$port, $errno, $errstr, 0.5, STREAM_CLIENT_CONNECT);
               if($socket)
               {
                   fclose($socket);
                   $selected_ip = $global_ip;
                   break;
               }
           }// for
       }

       $this->m_ip = $selected_ip.":".$port;
   }
   
   // $kt_api_url : excludes the host name.
   // $version_num : example, v1, v2, etc.
   // $api_key : is used to uniquely identify the user.
   // $api_func : example, ins for "invite sent", inr for "invite clicked", etc
   // $arg_assoc_array : an associative array of argument list. 
   public function api_call_method($kt_api_url, $version, $api_key, $secret_key, $api_func,
                                   $arg_assoc_array){
       
       if($this->m_ip != "")
       {
           $socket = @stream_socket_client($this->m_ip, $errno, $errstr, 0.5, STREAM_CLIENT_CONNECT);
           
           if($socket){
               $url_path = $this->get_call_url($kt_api_url, $version, $api_key, $secret_key, $api_func,
                                               $arg_assoc_array);
               fwrite($socket, "GET $url_path HTTP/1.1\r\n");
               fwrite($socket, "Host: $this->m_ip\r\n");
               fwrite($socket, "Content-type: application/x-www-form-urlencoded\r\n");
               fwrite($socket, "Accept: */*\r\n");
               fwrite($socket, "\r\n");
               fwrite($socket, "\r\n");
               fclose($socket);
           }
       }
   }

   public function get_call_url($kt_api_url, $version, $api_key, $secret_key, $api_func,
                                $arg_assoc_array)
   {
       $sig = '';
       // Get the current time stamp
       $arg_assoc_array['ts'] = gmdate("M-d-YTH:i:s");
       
       // This is to get rid of null parameters. in the assoc array
       parse_str(http_build_query($arg_assoc_array,'', '&'), $formatted_arg_assoc_array);
       
       ksort($formatted_arg_assoc_array); // sort by key first
       foreach ($formatted_arg_assoc_array as $key =>$val){
           $sig .= $key.'='.$val;
       }
       $sig .= $secret_key;

       $formatted_arg_assoc_array['an_sig'] = md5($sig);

       $query = http_build_query($formatted_arg_assoc_array, '', '&');

       $url_path = $kt_api_url."/". $version."/".$api_key."/".$api_func."/?$query";
       
       return $url_path;
   }
}
 
?>
