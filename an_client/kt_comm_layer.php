<?php

/**
 * Kontagent Communications Library
 * 
 * @copyright 2008, 2009 Kontagent
 * @link http://www.kontagent.com
 */
class Kt_Comm {
    private $m_host;
    private $m_port;
    
    private $m_server = "";
    
    private $m_socket = false;
    
    /**
     * Constructor.
     * 
     * Note: port is always required, even if it is standard 80.
     * 
     * @param $host hostname of Kontagent API server.
     * @param $port port number of Kontagent API server.
     */
    public function __construct($host, $port) {
        $this->m_host = $host;
        $this->m_port = $port;
    }
    
    /**
     * Destructor.
     * 
     * Ensures that socket is closed.
     */
    public function __destruct() {
        if ($this->m_socket) {
            fclose($this->m_socket);
        }
    }
    
    /**
     * Decides on a server to use for subsequent requests.
     */
    private function select_server() {
        // If we are using Kontagent's primary servers then utilize server selection protocol.
        if ($this->m_host == "api.geo.kontagent.net") {
            $this->m_server = $this->select_ip_address($this->m_host, $this->m_port);
        } else {
            $this->m_server = $this->m_host . ":" . $this->m_port;
        }
    }
    
    /**
     * Selects a geographically-closest working IP address.
     * 
     * @param $host
     * @param $port
     * @return string "hostname:portnumber" of selected server
     */
    private function select_ip_address($host, $port) {
        // First try all servers in geographically-closest datacenter
        $ip_list = gethostbynamel($host);
        $selected_ip = "";
        
        if ($ip_list != false) {
            shuffle($ip_list);
            
            foreach ($ip_list as $ip) {
                $socket = @stream_socket_client($ip.":".$port, $errno, $errstr, 0.5, STREAM_CLIENT_CONNECT);
                if ($socket) {
                    $this->m_socket = $socket;
                	$selected_ip = $ip;
                    break;
                }
            }
        }
        
        // Looks like entire datacenter is down, so try our luck with one of global IPs
        if ($selected_ip == "") {
            $global_ip_list = gethostbynamel("api.global.kontagent.net");
            shuffle($ip_list);
            
            foreach($global_ip_list as $global_ip) {
                $socket = @stream_socket_client($global_ip.":".$port, $errno, $errstr, 0.5, STREAM_CLIENT_CONNECT);
                
                if ($socket) {
                    $this->m_socket = $socket;
                	$selected_ip = $global_ip;
                    break;
                }
            }
        }
        
        return $selected_ip.":".$port;
    }
    
    /**
     * Executes Kontagent API call.
     * 
     * @param $kt_api_url request URL prefix
     * @param $version currently, "v1"
     * @param $api_key unique identifier for application; provided by Kontagent
     * @param $secret_key secret key for application; provided by Kontagent
     * @param $api_func request type (ins for "invite sent", inr for "invite clicked", etc)
     * @param $arg_assoc_array associative array of argument list
     */
    public function api_call_method($kt_api_url, $version, $api_key, $secret_key, $api_func, $arg_assoc_array) {
    	// We delayed server selection until first API call
    	if ($this->m_server == "") {
    		$this->select_server();
    	}
    	
        if ($this->m_server != "") {
            if (!$this->m_socket) {
                $this->m_socket = @stream_socket_client($this->m_server, $errno, $errstr, 0.5, STREAM_CLIENT_CONNECT);
            }
            
            if ($this->m_socket) {
                $url_path = $this->get_call_url($kt_api_url, $version, $api_key, $secret_key, $api_func, $arg_assoc_array);
                
                fwrite($this->m_socket, "GET $url_path HTTP/1.1\r\n");
                fwrite($this->m_socket, "Host: $this->m_ip\r\n");
                fwrite($this->m_socket, "Content-type: application/x-www-form-urlencoded\r\n");
                fwrite($this->m_socket, "Accept: */*\r\n");
                fwrite($this->m_socket, "\r\n");
                fwrite($this->m_socket, "\r\n");
                
                fclose($this->m_socket);
                $this->m_socket = false;
            }
        }
    }
    
    /**
     * Computes URL (excluding http://hostname) for this API request.
     * 
     * @param $kt_api_url request URL prefix
     * @param $version currently, "v1"
     * @param $api_key unique identifier for application; provided by Kontagent
     * @param $secret_key secret key for application; provided by Kontagent
     * @param $api_func request type (ins for "invite sent", inr for "invite clicked", etc)
     * @param $arg_assoc_array associative array of argument list
     * @return string URL suffix
     */
    public function get_call_url($kt_api_url, $version, $api_key, $secret_key, $api_func, $arg_assoc_array) {
        // Get the current timestamp
        $arg_assoc_array['ts'] = gmdate("Ymd.His");
        
        // This is to get rid of null parameters in the assoc array
        parse_str(http_build_query($arg_assoc_array,'', '&'), $formatted_arg_assoc_array);
        
        // Sort parameters by key
        ksort($formatted_arg_assoc_array);
        
        // Get signature
        $formatted_arg_assoc_array['an_sig'] = $this->compute_signature($formatted_arg_assoc_array);
        
        // Build complete query parameters
        $query = http_build_query($formatted_arg_assoc_array, '', '&');
        
        $url_path = $kt_api_url."/". $version."/".$api_key."/".$api_func."/?".$query;
        
        return $url_path;
    }
    
    /**
     * Computes an_sig signature for API request parameters in $formatted_arg_assoc_array
     * 
     * @param $formatted_arg_assoc_array
     * @return string an_sig md5 hash
     */
    private function compute_signature($formatted_arg_assoc_array) {
        $sig = '';
        
        // This already happened in get_call_url() so we don't need to sort again
        // ksort($formatted_arg_assoc_array);
        
        foreach ($formatted_arg_assoc_array as $key =>$val) {
            $sig .= $key.'='.$val;
        }
        $sig .= $secret_key;
        
        return md5($sig);
    }
}

?>
