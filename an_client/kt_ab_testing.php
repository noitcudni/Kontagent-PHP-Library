<?php

include_once 'kt_comm_layer.php';

class AB_Testing_Manager
{
    private $m_backend_api_key;
    private $m_backend_secret_key;
    private $m_ab_backend_host;
    private $m_ab_backend_port;
    private $m_ab_backend;
    private $m_memcached_server;
    private $m_selected_msg_page_pair_dict;
    private static $url_prefix = "/abtest/campaign_info";

    public function __construct($kt_api_key,$kt_secret_key,
                                $kt_ab_backend_host,$kt_ab_backend_port=80)
    {
        $this->m_backend_api_key = $kt_api_key;
        $this->m_backend_secret_key = $kt_secret_key;
        
        $this->m_ab_backend_host = $kt_ab_backend_host;
        $this->m_ab_backend_port = $kt_ab_backend_port;

        if( $this->m_ab_backend_port != 80)
            $this->m_ab_backend = $this->m_ab_backend_host.":".$this->m_ab_backend_port;
        else
            $this->m_ab_backend = $this->m_ab_backend_host;
        

        $this->m_memcached_server = new Memcache;
        $this->m_memcached_server->connect('127.0.0.1', 11211); # TODO: don't hardcode
        $this->m_selected_msg_page_pair_dict = array();
    }
                                
    
    // if $force is set to be true, it will grab the campaign contents even if the changed flag is
    // set to be false.
    public function fetch_ab_testing_data($campaign, $force=false)
    {
        $url_str = $this->m_ab_backend.self::$url_prefix."/".$this->m_backend_api_key."/".$campaign. "/";
        
        if($force)
        {
            $url_str = $url_str."?f=1";
        }
        
        $sock = fopen( $url_str , 'r' );
        $r = null;
        
        $url_result = "";
        if ($sock){
            while (!feof($sock)) {
                $url_result .=fgets($sock, 4096);
            }
            fclose($sock);

            $json_obj = json_decode($url_result);

            if( $json_obj->changed )
            {
                // process message list
                $msg_lst = $json_obj->messages;
                $msg_weight_array = array();
                $curr_idx = 0;
                foreach($msg_lst as $msg)
                {
                    $weight = $msg[1];
                    for($i = 0; $i< $weight; $i++)
                    {
                        $msg_weight_array[] = $curr_idx;
                    }
                    $curr_idx++;
                }
                // process page list
                $page_lst = $json_obj->pages;
            
                $page_weight_array = array();
                $curr_idx = 0;
                foreach($page_lst as $page)
                {
                    $weight = $page[1];
                    for($i=0; $i<$weight; $i++)
                    {
                        $page_weight_array[] = $curr_idx;
                    }
                    $curr_idx++;
                }
                $store_dict = array();
                $store_dict['json'] = $json_obj;
                $store_dict['msg_weight'] = $msg_weight_array;
                $store_dict['page_weight'] = $page_weight_array;
            
                $r = $store_dict;                
                $this->m_memcached_server->set($this->gen_memcache_key($campaign) , serialize($store_dict), MEMCACHE_COMPRESSED, 0);
                $this->m_memcached_server->set($this->gen_memcache_fake_key($campaign), 1, MEMCACHE_COMPRESSED, 300); // 5 mins
            }
            else
            {
                $this->m_memcached_server->set($this->gen_memcache_fake_key($campaign), 1, MEMCACHE_COMPRESSED, 300); // 5 mins
            }
                
        }//if ($sock)
        return $r;
    }
    
    private function gen_memcache_fake_key($campaign)
    {
        return "kt_".$this->m_backend_api_key."_".$campaign."_fake";
    }

    private function gen_memcache_key($campaign)
    {
        return "kt_".$this->m_backend_api_key."_".$campaign;
    }
        
    private function get_ab_helper($campaign)
    {
        $fake_key_is_valid = $this->m_memcached_server->get($this->gen_memcache_fake_key($campaign));
        
        if($fake_key_is_valid == false)
        {
            // The real key should have a valid json object.
            // If not, invoke fetch_ab_testin_data with force = true
            $serialized_campaign_str = $this->m_memcached_server->get($this->gen_memcache_key($campaign));
            if( $serialized_campaign_str == false )
            {
                $dict = $this->fetch_ab_testing_data($campaign, true); // force it
            }
            else
            {
                $dict = $this->fetch_ab_testing_data($campaign);
            }
        }
        else
        {
            // Likewise, the real key should have a valid json object.
            // If not, invoke fetch_ab_testin_data with force = true
            $serialized_campaign_str = $this->m_memcached_server->get($this->gen_memcache_key($campaign));
            if( $serialized_campaign_str == false)
            {
                $dict = $this->fetch_ab_testing_data($campaign, true); // force it
            }
            else
            {
                // has a valid json object. deserialize it.
                $dict = unserialize($serialized_campaign_str);
            }
        }
        return $dict;
    }
        
    public function get_ab_testing_message($campaign)
    {
        $dict = $this->get_ab_helper($campaign);
        if($dict == null)
        {
            return null;
        }
        else{
            $json_obj = $dict['json'];
            $msg_lst = $json_obj->messages;

            $weight_array = $dict['msg_weight'];
            $index = $weight_array[rand(0, count($weight_array)-1)];

            return $msg_lst[$index];
        }
    }
    
    public function get_ab_testing_page($campaign)
    {
        $dict = $this->get_ab_helper($campaign);
        if($dict == null)
        {
            return null;
        }
        else
        {
            $json_obj = $dict['json'];
            $page_lst = $json_obj->pages;
            
            $weight_array = $dict['page_weight'];
            $index = $weight_array[rand(0, count($weight_array)-1)];
            
            return $page_lst[$index];
        }
    }

    public function cache_ab_testing_msg_and_page($campaign, $msg_info, $page_info)
    {
        $dict = array();
        $dict['page'] = $page_info;
        $dict['msg']  = $msg_info;
        $this->m_selected_msg_page_pair_dict[$campaign] = $dict;
    }

    public function get_selected_msg_info($campaign)
    {
        return  $this->m_selected_msg_page_pair_dict[$campaign]['msg'];

    }

    public function get_selected_page_info($campaign)
    {
        return  $this->m_selected_msg_page_pair_dict[$campaign]['page'];
    }
}

//testing code
//$ab_testing_mgr = new AB_Testing_Manager('ea04b006c8174440a264ab4ab5b1e4e0', '45237b3a91184c389a4c12f38e7fe755',
//                                         'http://kthq.dyndns.org', 9999);
//$ab_testing_mgr->fetch_ab_testing_data('hello');
//echo $ab_testing_mgr->get_message('hello')."\n";

//echo $ab_testing_mgr->get_page($campaign)."\n";

?>