<?php

// Kontagent an_client lib version KONTAGENT_VERSION_NUMBER
include_once 'kt_comm_layer.php';

class Analytics_Utils
{
    private static $s_undirected_types = array('pr'=>'pr', 'fdp'=>'fdp', 'ad'=>'ad', 'ap'=>'ap');
    private static $s_directed_types = array('in'=>'in', 'nt'=>'nt', 'nte'=>'nte');

    private static $s_kt_args = array('kt_uid'=>1,
                                      'kt_d'=>1,
                                      'kt_type'=>1,
                                      'kt_ut'=>1,
                                      'kt_t'=>1,
                                      'kt_st1'=>1,
                                      'kt_st2'=>1,
                                      'kt_st3'=>1);
    
    private static $s_install_args = array('d'=>1,
                                           'ut'=>1,
                                           'installed'=>1,
                                           'sut'=>1);
       

    const directed_val = 'd';
    const undirected_val = 'u';    
    const URL_REGEX_STR_NO_HREF = '/https?:\/\/[^\s>\'"]+/';
    const URL_REGEX_STR = '/(href\s*=.*?)(https?:\/\/[^\s>\'"]+)/';
    const VO_PARAM_REGEX_STR = '/\{\*KT_AB_MSG\*\}/';
    const ESC_URL_UT_REGEX_STR = '/(ut%.*?)%/';
    const ESC_URL_SUT_REGEX_STR = '/(sut%.*?)%/';

    
    public $m_backend_api_key;
    private $m_backend_secret_key;
    private $m_backend_url;
    private $m_backend_host;
    private $m_local_req_uri;
    private $m_canvas_url;
    private $m_aggregator;
    private $m_invite_uuid;
    private $m_invite_message_info;
    
    // temporary variables for feed_publishUserAction to pass values to replace_kt_comm_link_helper
    private $m_template_bundle_id_tmp;
    private $m_st1_tmp;
    private $m_st2_tmp;
    private $m_st3_tmp;
    private $m_query_str_tmp;
    private $m_msg_text_tmp;
    
    public $m_ab_testing_mgr;
    
    
    private function __construct($kt_api_key,$kt_secret_key,
                                 $kt_backend_host,$kt_backend_port,$kt_backend_url, 
                                 $canvas_url,
                                 $local_req_uri){
        $this->m_backend_api_key = $kt_api_key;
        $this->m_backend_secret_key = $kt_secret_key;
        $this->m_backend_url = $kt_backend_url;
        $this->m_local_req_uri = $local_req_uri;
        $this->m_canvas_url = $canvas_url;
        $this->m_aggregator = new Kt_Comm($kt_backend_host, $kt_backend_port);        
        $this->m_invite_uuid = 0;
        $this->m_invite_message_info = null;
        $this->m_backend_host = $kt_backend_host;
        $this->m_backend_port = $kt_backend_port;
    }

    public function set_ab_testing_mgr($ab_testing_mgr)
    {
        $this->m_ab_testing_mgr = $ab_testing_mgr;
    }
    
    public function override_backend_host($kt_backend_host, $kt_backend_port)
    {
        $this->m_backend_host = $kt_backend_host;
        $this->m_aggregator = new Kt_Comm($kt_backend_host, $kt_backend_port);
    }
    
    public static function &instance($kt_api_key,$kt_secret_key,
                                     $kt_backend_host,$kt_backend_port,$kt_backend_url,
                                     $canvas_url,
                                     $local_req_uri){
        static $instance;
        
        if(!isset($instance))
        {
            $instance = new Analytics_Utils($kt_api_key,$kt_secret_key,
                                            $kt_backend_host,$kt_backend_port,$kt_backend_url,
                                            $canvas_url,
                                            $local_req_uri);
        }
        return $instance;
    }    
    
    private function is_directed_type($type){
        if (isset(self::$s_directed_types[$type]))
            return true;
        return false;
    }

    public function is_undirected_type($type){
        if (isset(self::$s_undirected_types[$type]))
            return true;
        return false;
    }    

    
    // Invoke this function with the fb_sig_* but excludes the fb_sig_ prefix.
    // For example, for fb_sig_user, pass in "user" as the $param_name argument.
    // If it's an iframe application, it will switch to use FACEBOOK_API_KEY . "_user"
    public function get_fb_param($param_name){
        $r = 0;
        global $kt_facebook;

        if( isset($_REQUEST['fb_sig_'.$param_name]) )
        {
            $r = $_REQUEST['fb_sig_'.$param_name];
        }
        else if( isset($_REQUEST[$kt_facebook->api_key."_".$param_name]) )
        {
            $r = $_REQUEST[$kt_facebook->api_key."_".$param_name];
        }
        else
        {
            if($param_name == 'user')
            {
                if ( isset($_REQUEST['fb_sig_canvas_user']) )
                {
                    $r = $_REQUEST['fb_sig_canvas_user'];
                }
                else
                {
                    // No way of getting to the uid with an unauthorized iframe app.
                    // So, check to make sure that KT_USER is set.
                    if(isset($_COOKIE["KT_USER"]))
                        $r = $_COOKIE["KT_USER"];
                }
            }
        }
        
        return $r;
    }

    private function gen_ut_cookie_key(){
        return $this->m_backend_api_key."_ut";
    }

    private function gen_sut_cookie_key(){
        return $this->m_backend_api_key."_sut";
    }

    private function gen_kt_comm_query_str($comm_type, $template_id, $subtype1, $subtype2, $subtype3, &$ret_str){
        $param_array = array();
        $dir_val;       
        $uuid = 0;
       
        if($comm_type != null){
            if ($this->is_directed_type($comm_type)){
                $dir_val = Analytics_Utils::directed_val;
            }
            else if($this->is_undirected_type($comm_type)){
                $dir_val = Analytics_Utils::undirected_val;
            }
         
            if($comm_type == 'pr'){ //profile
                //$sender = $this->m_an->m_kt_facebook->require_login();
                //$param_array['kt_uid'] = $sender;
            }
        }       
        
        $param_array['kt_d'] = $dir_val;
        $param_array['kt_type'] = $comm_type;

        if($dir_val == Analytics_Utils::directed_val){
            $uuid = $this->gen_long_uuid();
            $param_array['kt_ut'] = $uuid;
        }
       
        if($template_id != null){
            $param_array['kt_t'] = $template_id;
        }
      
        if($subtype1 != null){
            $param_array['kt_st1'] = $subtype1;
        }
        if($subtype2 != null){
            $param_array['kt_st2'] = $subtype2;
        }
        if($subtype3 != null){
            $param_array['kt_st3'] = $subtype3;
        }
        
        $ret_str = http_build_query($param_array, '', '&');
        return $uuid;
    }
    
    private function gen_kt_comm_link(&$input_txt, $comm_type, $template_id, $subtype1, $subtype2)
    {
        // This is here so it knows the fb namespace. Plus, it turns into a well formed XML.
        //$input_txt = '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:fb="http://apps.facebook.com/ns/1.0" targetNamespace="http://apps.facebook.com/ns/1.0" elementFormDefault="qualified" attributeFormDefault="unqualified">'.$this->xmlentities($input_txt).'</xs:schema>';

        $query_str;
        $uuid = $this->gen_kt_comm_query_str($comm_type, $template_id, $subtype1, $subtype2, null, $query_str);
        $this->m_query_str_tmp = $query_str;

        $input_txt = preg_replace_callback(self::URL_REGEX_STR,
                                           array($this, 'replace_kt_comm_link_helper_directed'),
                                           $input_txt);
        
        return $uuid;
    }

    private function gen_kt_comm_link_vo(&$input_txt, $comm_type, $subtype1, $subtype2, $subtype3)
    {
        $query_str;
        $uuid = $this->gen_kt_comm_query_str($comm_type, null, $subtype1, $subtype2, $subtype3, $query_str);
        $this->m_query_str_tmp = $query_str;
        
        $input_txt = preg_replace_callback(self::URL_REGEX_STR,
                                           array($this, 'replace_kt_comm_link_helper_directed'),
                                           $input_txt);
        
        return $uuid;
        
    }
        
    private function xmlentities ( $string )
    {
        $arry =  split('&', $string);
        $len = sizeof($arry);
        $r_str = null;

        if($len == 0)
        {
            // no & characters.
            $r_str = $string;
        }
        else
        {
            $r_str = $arry[0];
            for($i = 1; $i < $len; $i++)
            {
                $curr_str = $arry[$i];
               
                $str_len = strlen($curr_str);
                if($str_len > 0)
                {
                    if($str_len < 3)
                    {
                        $r_str.='&amp;';
                    }
                    else
                    {
                                              
                        $comp_str0 = substr($curr_str,0,3);
                        $comp_str1 = null;
                        $comp_str2 = null;
                       
                        if($str_len >= 4)
                        {
                            $comp_str1 = substr($curr_str,0,4);
                        }
                        if($str_len >= 5)
                        {
                            $comp_str2 = substr($curr_str,0,5);
                        }
                       
                        if($comp_str0 == 'lt;' || $comp_str0 == 'gt;')
                        {
                            $r_str.='&'.$curr_str;
                        }
                        else
                        {
                            if($comp_str1 != null)
                            {
                                if($comp_str1 == 'amp;')
                                {
                                    $r_str.='&'.$curr_str;
                                }
                                else
                                {
                                    if($comp_str2 != null)
                                    {
                                        if($comp_str2 == 'apos;' || $comp_str2 == 'quot;')
                                        {
                                            $r_str.='&'.$curr_str;
                                        }
                                        else
                                        {
                                            $r_str.='&amp;'.$curr_str;
                                        }
                                    }
                                    else
                                    {
                                        $r_str.='&amp;'.$curr_str;
                                    }
                                }
                            }
                            else
                            {
                                $r_str.='&amp;'.$curr_str;
                            }
                        }
                    }
                }
                else
                {
                    $r_str.='&amp;';
                }
            }
        }
       
        return $r_str;
    }

    private function replace_kt_comm_link_helper_directed($matches)
    {
        return $matches[1].$this->append_kt_query_str($matches[2], $this->m_query_str_tmp);
    }
    
    
    private function replace_kt_comm_link_helper_undirected($matches)
    {
        return $this->replace_kt_comm_link_helper_undirected_impl('fdp', $matches[0]);
    }

    private function replace_kt_comm_link_helper_undirected_impl($kt_type, $input_str)
    {
        $query_str;
        $this->gen_kt_comm_query_str($kt_type,
                                     $this->m_template_bundle_id_tmp,
                                     $this->m_st1_tmp,
                                     $this->m_st2_tmp,
                                     $this->m_st3_tmp,
                                     $query_str);

        return $this->append_kt_query_str($input_str, $query_str);
    }
    
    private function fill_message_with_ab_message($matches)
    {
        return $this->m_msg_text_tmp;
    }
        
    private function gen_kt_comm_link_templatized_data(&$input_txt, $comm_type, $template_id, $subtype1, $subtype2)
    {
        $data_arry = json_decode($input_txt, true);
       
        if($data_arry != null)
        {
            foreach( $data_arry as $key => $value)
            {
                $new_value = preg_replace_callback(self::URL_REGEX_STR_NO_HREF,
                                                   array($this, 'replace_kt_comm_link_helper_undirected'),
                                                   $value);
                $data_arry[$key] = $new_value;
            }
            $input_txt = json_encode($data_arry);
        }
    }
    
    private function append_kt_query_str($original_url, $query_str)
    {
        $position = strpos($original_url, '?');
        
        /* There are no query params, just append the new one */
        if ($position === false) {
            return $original_url.'?'.$query_str;
        }
        
        /* Prefix the params with the reference parameter */
        $noParams                   = substr($original_url, 0, $position + 1);
        $params                     = substr($original_url, $position + 1);
        return $noParams.$query_str.'&'.$params;
    }
        
    private function an_send_user_data($user_id, $birthday=null, $gender=null, $cur_city = null,
                                       $cur_state = null, $cur_country = null, $cur_zip = null,
                                       $home_city = null, $home_state = null, $home_country = null, $home_zip = null,
                                       $num_of_friends = null){

        $user_data = array();
        $user_data['s'] = $user_id;
      
        if (isset($birthday) && $birthday != ''){
            $tmp_array = split(',',$birthday);
            if(count($tmp_array) == 2)
                $user_data['b'] = urlencode(trim($tmp_array[1]));
            else
                $user_data['b'] = urlencode('');
        }
        if (isset($gender)){
            $user_data['g'] = urlencode(strtoupper($gender));
        }

        // Only allow a single entry for city, state, country, and zip in the Capture User Info message,
        // not separate ones for each of them for hometown and current. When fetching data from facebook,
        // get both sets of values and, when available, use the current ones, but use the hometown ones
        // if the current values are blank.
        $use_hometown_info = true;
      
        if (isset($cur_city)){
            $user_data['ly'] = $cur_city;
            $use_hometown_info = false;
        }
        if (isset($cur_state)){
            $user_data['ls'] = $cur_state;
            $use_hometown_info = false;
        }
        if (isset($cur_country)){
            $user_data['lc'] = $cur_country;
            $use_hometown_info = false;
        }
        if (isset($cur_zip)){
            $user_data['lp'] = $cur_zip;
            $use_hometown_info = false;
        }

        if($use_hometown_info == true){
            if (isset($home_city)){
                $user_data['ly'] = $home_city;
            }
            if (isset($home_state)){
                $user_data['ls'] = $home_state;
            }
            if (isset($home_country)){
                $user_data['lc'] = $home_country;
            }
            if (isset($home_zip)){
                $user_data['lp'] = $home_zip;
            }
        }
      
        if (isset($num_of_friends)){
            $user_data['f'] = $num_of_friends;
        }

        $this->m_aggregator->api_call_method($this->m_backend_url, "v1", $this->m_backend_api_key,
                                             $this->m_backend_secret_key,
                                             'cpu',
                                             $user_data); //cpu stands for capture user
    }

    private function an_app_remove($uid){
        $this->m_aggregator->api_call_method($this->m_backend_url, "v1", $this->m_backend_api_key,
                                             $this->m_backend_secret_key,
                                             "apr",
                                             array('s'=>$uid));
    }
    
    private function an_app_added_directed($uid, $long_uuid){
        $this->m_aggregator->api_call_method($this->m_backend_url, "v1", $this->m_backend_api_key,
                                             $this->m_backend_secret_key,
                                             "apa",
                                             array('s'=>$uid,
                                                   'u'=>$long_uuid));
    }

    private function an_app_added_undirected($uid, $short_uuid){
        $this->m_aggregator->api_call_method($this->m_backend_url, "v1", $this->m_backend_api_key,
                                             $this->m_backend_secret_key,
                                             "apa",
                                             array('s'=>$uid,
                                                   'su'=>$short_uuid));
    }


    private function an_app_added_nonviral($uid){
        $this->m_aggregator->api_call_method($this->m_backend_url, "v1", $this->m_backend_api_key,
                                             $this->m_backend_api_key,
                                             "apa",
                                             array('s'=>$uid));
    }

    private function an_notification_click($has_been_added, $uuid, $template_id, $subtype1, $subtype2, $subtype3, $recipient_uid = null)
    {
        $this->m_aggregator->api_call_method($this->m_backend_url, "v1",
                                             $this->m_backend_api_key, $this->m_backend_secret_key,
                                             "ntr",
                                             array('r' => $recipient_uid,
                                                   'i' => $has_been_added,
                                                   'u' => $uuid,
                                                   'tu' => 'ntr',
                                                   't' => $template_id,
                                                   'st1' => $subtype1,
                                                   'st2' => $subtype2,
                                                   'st3' => $subtype3));
    }
    
    private function an_notification_email_click($has_been_added, $uuid, $template_id, $subtype1, $subtype2, $subtype3, $recipient_uid = null){
        $this->m_aggregator->api_call_method($this->m_backend_url, "v1",
                                             $this->m_backend_api_key, $this->m_backend_secret_key,
                                             "nei",
                                             array('r' => $recipient_uid,
                                                   'i' => $has_been_added,
                                                   'u' => $uuid,
                                                   'tu' => 'nei',
                                                   't' => $template_id,
                                                   'st1' => $subtype1,
                                                   'st2' => $subtype2,
                                                   'st3' => $subtype3));
    }

    private function an_invite_send($sender_uid, $recipient_uid_arry, $uuid, $invite_template_id = null, $subtype1 = null, $subtype2 = null, $subtype3 = null){
       $param_array = array('s' => $sender_uid,
                            'u' => $uuid);

       if(is_array($recipient_uid_arry))
           $param_array['r'] = join(',',$recipient_uid_arry);

       if(isset($invite_template_id))
           $param_array['t'] = $invite_template_id;
       if(isset($subtype1))
           $param_array['st1'] = $subtype1;
       if(isset($subtype2))
           $param_array['st2'] = $subtype2;
       if(isset($subtype3))
           $param_array['st3'] = $subtype3;
           
       $this->m_aggregator->api_call_method($this->m_backend_url, "v1",
                                            $this->m_backend_api_key, $this->m_backend_secret_key,
                                            "ins",
                                            $param_array);
   }

    private function an_invite_click($has_been_added, $uuid, $template_id=null, $subtype1=null, $subtype2=null, $subtype3=null, $recipient_uid = null){
       
       $this->m_aggregator->api_call_method($this->m_backend_url, "v1",
                                            $this->m_backend_api_key, $this->m_backend_secret_key,
                                            "inr",
                                            array('r' => $recipient_uid,
                                                  'i' => $has_been_added,
                                                  'u' => $uuid,
                                                  'tu' => 'inr',
                                                  't' => $template_id,
                                                  'st1' => $subtype1,
                                                  'st2' => $subtype2,
                                                  'st3' => $subtype3));
   }

    private function an_app_undirected_comm_click($uid, $type, $template_id, $subtype1, $subtype2, $subtype3, $has_added, $short_tag){
       $this->m_aggregator->api_call_method($this->m_backend_url, "v1",
                                            $this->m_backend_api_key, $this->m_backend_secret_key,
                                            "ucc",
                                            array('s'=>$uid,
                                                  'tu'=>$type,
                                                  't'=>$template_id,
                                                  'st1'=>$subtype1,
                                                  'st2'=>$subtype2,
                                                  'st3'=>$subtype3,
                                                  'i'=>$has_added,
                                                  'su'=>$short_tag));
   }

   private function an_goal_count_increment($uid, $goal_counts){
       $param_array = array();
       foreach ($goal_counts as $key => $value)
           $param_array['gc'.$key] = $value;
       if(is_array($uid))
           $param_array['s'] = join(',',$uid);
       else
           $param_array['s'] = $uid;

       $this->m_aggregator->api_call_method($this->m_backend_url, "v1",
                                            $this->m_backend_api_key, $this->m_backend_secret_key,
                                            "gci",
                                            $param_array);
   }
   
   
   public function get_stripped_installed_arg_url()
   {
       $param_array = array();
       foreach($_GET as $arg => $val)
       {
           if(!isset(self::$s_install_args[$arg]))
           {
               $param_array[$arg] = $val;
           }
       }       
       return $this->build_stripped_url($param_array);
   }
   
   // After done processing the kt_params (see konttagent.php), this function will be invoked to stripped all the
   // kt_* parameters. Why bother? First, it prevents erroneous processing after authorization. Second, prettier url.
   // Third, no need to deal with refreshing the url with kt_params in it.
   // Pass ids along. Since we are doing a redirect after done handling kt params,
   // we need to forward the ids array for invite_send event.   
   // 
   // Saves kt_ut or sut as <kt_api_key>_ut and <kt_api_key>_sut, respectively, in the cookie, so that we can
   // handle apps that don't force its users to install the apps immediately.
   private function get_stripped_kt_args_url($short_tag=null, $ids_array=null)
   {
       $param_array = array();
       foreach($_GET as $arg => $val)
       {
           if (!isset(self::$s_kt_args[$arg]))
           {
               $param_array[$arg] = $val;
           }
           else
           {
               if($arg == 'kt_d')
               {
                   $param_array['d'] = $val;
               }
               else if($arg == 'kt_ut')
               {
                   $param_array['ut'] = $val;
                   setcookie($this->gen_ut_cookie_key(), $val, time()+600); // 10 minutes
               }
           }
       }

       if($short_tag != null)
       {
           $param_array['sut'] = $short_tag;
           setcookie($this->gen_sut_cookie_key(), $val, time()+600);
       }

       if($ids_array != null)
       {
           $param_array['ids'] = $ids_array;
       }

       return $this->build_stripped_url($param_array);
   }

   public function prolong_ut_cookie_if_set()
   {
       if(!empty($_COOKIE[$this->gen_ut_cookie_key()]))
       {
           $val = $_COOKIE[$this->gen_ut_cookie_key()];
           setcookie($this->gen_ut_cookie_key(), $val, time()+600); // 10 minutes
       }       
   }

   public function prolong_sut_cookie_if_set()
   {
       if(!empty($_COOKIE[$this->gen_sut_cookie_key()]))
       {
           $val = $_COOKIE[$this->gen_sut_cookie_key()];
           setcookie($this->gen_sut_cookie_key(), $val, time()+600); // 10 minutes
       }       
   }
   
   private function build_stripped_url($param_array)
   {
       // get the script name only minus the call_back_uri
       $script_uri = null;
       if(isset($_SERVER['SCRIPT_URI']))
           $script_uri = $_SERVER['SCRIPT_URI'];
       else if(isset($_SERVER['PHP_SELF']))
           $script_uri = $_SERVER['PHP_SELF'];
           
       if($script_uri != null)
       {
           if(preg_match("@".$this->m_local_req_uri."(.*)@", $script_uri, $matches))
           {
               $script_name = $matches[1];
           }
           else
           {
               $script_name = $script_uri;
           }

           // if there are slashes around the script_name (/index.php/), strip the one in the front.
           if($script_name[0] == "/")
               $script_name = substr($script_name, 1);
       
       
           $len = strlen($this->m_canvas_url);
                        
           if( $this->m_canvas_url[$len-1] == "/")
           {
               return $this->m_canvas_url.$script_name."?".http_build_query($param_array, '', '&');
           }
           else
           {
               return $this->m_canvas_url."/".$script_name."?".http_build_query($param_array, '', '&');
           }
       }
       else
           return null;
   }
   
   public function get_page_tracking_url() {
       if( $this->m_backend_port != 80 )
           $url = "http://" . $this->m_backend_host.":".$this->m_backend_port;
       else
           $url = "http://" . $this->m_backend_host;

       global $kt_facebook;
       $uid = $this->get_fb_param('user');
       
       $url .= $this->m_aggregator->get_call_url(
           $this->m_backend_url, 
           "v1", 
           $this->m_backend_api_key,
           $this->m_backend_secret_key,
           "pgr",
           array('s' => $uid)
                                                 );
       
       return $url;
   }

   public function gen_long_uuid(){
        return substr(uniqid(rand()),  -16);
    }

    public function gen_short_uuid(){
        $t=explode(" ",microtime());
        $a = $t[1];
        $b = round($t[0]*mt_rand(0,0xfffff));
        
        $c = mt_rand(0,0xfffffff);
        $tmp_binary = base_convert($c, 10, 2);
        $c = $c << (8 - strlen($tmp_binary));
      
        return dechex($a ^ $b ^ $c);
    }

    public function gen_notifications_link_vo(&$notification, $msg_text, $subtype1, $subtype2, $subtype3)
    {
        $this->m_msg_text_tmp = $msg_text;
        $notification = preg_replace_callback(self::VO_PARAM_REGEX_STR,
                                              array($this,  'fill_message_with_ab_message'),
                                              $notification);
        return $this->gen_kt_comm_link_vo($notification, 'nt', $subtype1, $subtype2, $subtype3);
    }
    
    public function gen_notifications_link(&$notification, $template_id = null, $subtype1 = null, $subtype2 = null)
    {
        return $this->gen_kt_comm_link($notification, 'nt', $template_id, $subtype1, $subtype2); 
    }

    public function gen_email_link(&$email_fbml, $template_id = null, $subtype1 = null, $subtype2 = null)
    {
        return $this->gen_kt_comm_link($email_fbml, 'nte', $template_id, $subtype1, $subtype2); 
    }

    public function gen_email_link_vo(&$email_fbml, $msg_text, $subtype1 = null, $subtype2 = null, $subtype3=null)
    {
        $this->m_msg_text_tmp = $msg_text;
        $email_fbml = preg_replace_callback(self::VO_PARAM_REGEX_STR,
                                            array($this,  'fill_message_with_ab_message'),
                                            $email_fbml);
        return $this->gen_kt_comm_link_vo($email_fbml, 'nte', $subtype1, $subtype2, $subtype3);
    }
        
    public function gen_feed_link(&$template, $template_id = null, $subtype1 = null, $subtype2 = null)
    {
        return $this->gen_kt_comm_link($email_fbml, 'fdp', $template_id, $subtype1, $subtype2); 
    }

    public function gen_feed_link_templatized_data(&$data, $template_id = null, $subtype1 = null, $subtype2 = null)
    {
        $this->gen_kt_comm_link_templatized_data($data, 'fdp', $template_id, $subtype1, $subtype2);
    }

    // $template_bundle_id: bundle_id from registerTemplateBundle.
    public function gen_feed_publishUserAction(&$data, $template_bundle_id,
                                               $subtype1 = null, $subtype2 = null, $subtype3 = null,
                                               $msg_text = null)
    {
        $this->m_template_bundle_id_tmp = $template_bundle_id;
        $this->m_st1_tmp = $subtype1;
        $this->m_st2_tmp = $subtype2;
        $this->m_st3_tmp = $subtype3;
        
        if(is_array($data))
            $data_arry = $data;
        else
            $data_arry = json_decode($data, true);
        
        if($data_arry != null)
        {        
            foreach($data_arry as $key => $value)
            {
                // read http://uk.php.net/manual/en/language.pseudo-types.php#language.types.callback:
                // for why I'm doing array($this, 'replace_kt_comm_link_helper')
                // ASSUMPTION: All urls begin with http or https. No relative urls.                
                if($key == "images")
                {
                    $len = sizeof($value);
                    for($i = 0 ; $i < $len ; $i++)
                    {
                        $value[$i]['href'] = $this->replace_kt_comm_link_helper_undirected_impl('fdp', $value[$i]['href']);
                    }
                    $data_arry[$key] = $value;
                }
                else if($key == "flash")
                {
                    //TODO: 
                }
                else if($key == "mp3")
                {
                    //TODO: 
                }
                else if($key == "video")
                {
                    //TODO: 
                }
                else
                {
                    $new_value = preg_replace_callback(self::URL_REGEX_STR_NO_HREF,
                                                       array($this, 'replace_kt_comm_link_helper_undirected'),
                                                       $value);
                    $data_arry[$key] = $new_value;
                }                
            }// foreach

            if( $msg_text != null )
            {
                $data_arry['KT_AB_MSG'] = $msg_text;
            }
            $data = json_encode($data_arry);
        }
    }
    
    public function format_kt_st1($st1_str)
    {
        return "aB_".$st1_str;
    }

    public function format_kt_st2($st2_str)
    {
        return "m".$st2_str;
    }

    public function format_kt_st3($st3_str)
    {
        return "p".$st3_str;
    }
    
    public function kt_get_invite_post_link_vo($invite_post_link, $campaign)
    {
        if ($this->m_invite_message_info == null)
        {
            $msg_info_array = $this->m_ab_testing_mgr->get_ab_testing_message($campaign);
            $this->m_invite_message_info = $msg_info_array;
        }
        else
        {
            $msg_info_array = $this->m_invite_message_info;
            $this->m_invite_message_info = null;
        }
        
        $param_array = array();        

        if ($this->m_invite_uuid == 0)
        {
            $this->m_invite_uuid = $this->gen_long_uuid();
            $uuid = $this->m_invite_uuid;
        }
        else
        {
            $uuid = $this->m_invite_uuid;
            $this->m_invite_uuid = 0;
        }
            
        $param_array['kt_ut'] = $uuid;
        $param_array['kt_uid'] = $this->get_fb_param('user');
        $param_array['kt_type'] = 'ins'; 

        $param_array['kt_st1'] = $this->format_kt_st1($campaign);
        
        //$message_id = $this->m_ab_testing_mgr->get_message_id();
        $message_id = $msg_info_array[0];
        $param_array['kt_st2'] = $this->format_kt_st2($message_id);
        
        $page_info = $this->m_ab_testing_mgr->get_selected_page_info($campaign);
        $param_array['kt_st3'] = $this->format_kt_st3($page_info[0]);
        
        $r = array();
        $r['url']=$this->append_kt_query_str($invite_post_link, http_build_query($param_array,'', '&'));
        $r['message_id'] = $msg_info_array[0];
        $r['message'] = $msg_info_array[2];
        
        return $r;
    }
    
    //OG
    public function kt_get_invite_post_link($invite_post_link,
                                            $template_id = null, $subtype1 = null, $subtype2 = null)
    {
        $param_array = array();        

        if ($this->m_invite_uuid == 0)
        {
            $this->m_invite_uuid = $this->gen_long_uuid();
            $uuid = $this->m_invite_uuid;
        }
        else
        {
            $uuid = $this->m_invite_uuid;
            $this->m_invite_uuid = 0;
        }
            
        $param_array['kt_ut'] = $uuid;
        $param_array['kt_uid'] = $this->get_fb_param('user');
        $param_array['kt_type'] = 'ins'; 
        if(isset($template_id))        
            $param_array['kt_t'] = $template_id;
        if(isset($subtype1))        
            $param_array['kt_st1'] = $subtype1;
        if(isset($subtype2))        
            $param_array['kt_st2'] = $subtype2;

        return $this->append_kt_query_str($invite_post_link, http_build_query($param_array,'', '&'));
    }
    
    public function kt_get_invite_content_link($invite_content_link,
                                               $template_id = null, $subtype1 = null , $subtype2 = null)
    {
        $param_array['kt_uid'] = $this->get_fb_param('user');
      
        if ($this->m_invite_uuid == 0)
        {
            $this->m_invite_uuid = $this->gen_long_uuid();
            $uuid = $this->m_invite_uuid;
        }
        else
        {
            $uuid = $this->m_invite_uuid;
            $this->m_invite_uuid = 0;
        }
        
        $param_array['kt_d'] = Analytics_Utils::directed_val;
        $param_array['kt_ut'] = $uuid;
        $param_array['kt_uid'] = $this->get_fb_param('user');
        $param_array['kt_type'] = 'in';
        if(isset($template_id))
            $param_array['kt_t'] = $template_id;
        if(isset($subtype1))        
            $param_array['kt_st1'] = $subtype1;
        if(isset($subtype2))        
            $param_array['kt_st2'] = $subtype2;

        return $this->append_kt_query_str($invite_content_link, http_build_query($param_array,'', '&'));
    }
    
    public function kt_get_invite_content_link_vo($invite_content_link, $campaign)
    {
        if ($this->m_invite_message_info == null)
        {
            $msg_info_array = $this->m_ab_testing_mgr->get_ab_testing_message($campaign);
            $this->m_invite_message_info = $msg_info_array;
        }
        else
        {
            $msg_info_array = $this->m_invite_message_info;
            $this->m_invite_message_info = null;
        }

        if ($this->m_invite_uuid == 0)
        {
            $this->m_invite_uuid = $this->gen_long_uuid();
            $uuid = $this->m_invite_uuid;
        }
        else
        {
            $uuid = $this->m_invite_uuid;
            $this->m_invite_uuid = 0;
        }
        
        $param_array['kt_uid'] = $this->get_fb_param('user');

        $param_array['kt_d'] = Analytics_Utils::directed_val;
        $param_array['kt_ut'] = $uuid;
        $param_array['kt_uid'] = $this->get_fb_param('user');
        $param_array['kt_type'] = 'in';

        $param_array['kt_st1'] = $this->format_kt_st1($campaign);
        $param_array['kt_st2'] = $this->format_kt_st2($msg_info_array[0]);        

        $page_info = $this->m_ab_testing_mgr->get_selected_page_info($campaign);
        $param_array['kt_st3'] = $this->format_kt_st3($page_info[0]);

        $r = array();
        $r['url'] = $this->append_kt_query_str($invite_content_link, http_build_query($param_array, '', '&'));
        $r['message_id'] = $msg_info_array[0];
        $r['message'] = $msg_info_array[2];
        
        return $r;
    }
        
    public function kt_notifications_send($uid, $to_ids, $uuid, $template_id=null, $subtype1=null, $subtype2=null, $subtype3=null)
    {
        if(is_array($to_ids)){
            $to_ids_arg = join(",",$to_ids);
        }
        else
        {
            $to_ids_arg=$to_ids;
        }

        $arg_array = array('s' => $uid,
                           'r' => $to_ids_arg,
                           'u' => $uuid);
        if(isset($template_id))
            $arg_array['t'] = $template_id;

        if(isset($subtype1))
            $arg_array['st1'] = $subtype1;

        if(isset($subtype2))
            $arg_array['st2'] = $subtype2;

        if(isset($subtype3))
            $arg_array['st3'] = $subtype3;
            
        $this->m_aggregator->api_call_method($this->m_backend_url, 'v1',
                                             $this->m_backend_api_key, $this->m_backend_secret_key,
                                             'nts',
                                             $arg_array);
    }

    public function kt_annoucements_send($to_ids, $uuid, $template_id, $subtype1=null, $subtype2=null, $subtype3=null)
    {
        $this->kt_notifications_send(0, $to_ids, $uuid, $template_id, $subtype1, $subtype2, $subtype3);
    }
    
    public function kt_email_send($uid, $to_ids, $uuid, $template_id, $subtype1=null, $subtype2=null, $subtype3=null)
    {
        if(is_array($to_ids)){
            $to_ids_arg = join(",",$to_ids);
        }
        else
        {
            $to_ids_arg=$to_ids;
        }

        $arg_array = array('s' => $uid,
                           'r' => $to_ids_arg,
                           'u' => $uuid);
        if(isset($template_id))
            $arg_array['t'] = $template_id;

        if(isset($subtype1))
            $arg_array['st1'] = $subtype1;

        if(isset($subtype2))
            $arg_array['st2'] = $subtype2;
        
        if(isset($subtype3))
            $arg_array['st3'] = $subtype3;

        $this->m_aggregator->api_call_method($this->m_backend_url, 'v1',
                                             $this->m_backend_api_key, $this->m_backend_secret_key,
                                             'nes',
                                             $arg_array);
    }

    
    public function kt_templatized_feed_send($uid, $template_id, $subtype1=null, $subtype2=null)
    {
        $arg_array = array('pt' => 3,
                           's' => $uid);

        if(isset($template_id))
            $arg_array['t'] = $template_id;

        if(isset($subtype1))
            $arg_array['st1'] = $subtype1;

        if(isset($subtype2))
            $arg_array['st2'] = $subtype2;
        
        $this->m_aggregator->api_call_method($this->m_backend_url, 'v1',
                                             $this->m_backend_api_key, $this->m_backend_secret_key,
                                             'fdp',
                                             $arg_array);        
    }

    // $bundle_template_id : from registerTemplateBundle.
    public function kt_user_action_feed_send($uid, $bundle_template_id, $subtype1=null, $subtype2=null, $subtype3=null)
    {
        $arg_array = array('pt' => 4,
                           's' => $uid);

        if(isset($bundle_template_id))
            $arg_array['t'] = $bundle_template_id;

        if(isset($subtype1))
            $arg_array['st1'] = $subtype1;

        if(isset($subtype2))
            $arg_array['st2'] = $subtype2;
        
        if(isset($subtype3))
            $arg_array['st3'] = $subtype3;
            
        $this->m_aggregator->api_call_method($this->m_backend_url, 'v1',
                                             $this->m_backend_api_key, $this->m_backend_secret_key,
                                             'fdp',
                                             $arg_array);
    }
    
    public function save_app_added()
    {
        $has_direction = isset($_GET['d']);
        $uid = $this->get_fb_param('user');

        if($has_direction && $_GET['d'] == self::directed_val)
        {
            $this->an_app_added_directed($uid, $_GET['ut']);
        }
        else if($has_direction && $_GET['d'] == self::undirected_val)
        {
            $this->an_app_added_undirected($uid, $_GET['sut']);
        }
        else if(!empty($_COOKIE[$this->gen_ut_cookie_key()]))
        {
            $this->an_app_added_directed($uid, $_COOKIE[$this->gen_ut_cookie_key()]);
            setcookie($this->gen_ut_cookie_key(), "", time()-600); //remove cookie
        }
        else if(!empty($_COOKIE[$this->gen_sut_cookie_key()]))
        {
            $this->an_app_added_undirected($uid, $_COOKIE[$this->gen_sut_cookie_key()]);
            setcookie($this->gen_sut_cookie_key(), "", time()-600); //remove cookie
        }
        else
        {
            // If the app's settings on facebook has a specifed post-authorized redirect URL,
            // then all kt_* parameters will be escaped out. To work around this problem, it'll
            // grab ut or sut. If there's kt_ut or kt_d, that means that they have require_once before
            // include_once kontagent.php
            
            if(preg_match(Analytics_Utils::ESC_URL_UT_REGEX_STR, $_SERVER['QUERY_STRING'], $matches))
            {
                $tmp_str = urldecode($matches[1]);
                $tmp_arry = split("=", $tmp_str);
                if(sizeof($tmp_arry) != 2)
                {
                    $this->an_app_added_nonviral($uid);
                }
                else
                {
                    $this->an_app_added_directed($uid, $tmp_arry[1]);
                }
            }
            else if(preg_match(Analytics_Utils::ESC_URL_SUT_REGEX_STR, $_SERVER['QUERY_STRING'], $matches))
            {
                $tmp_str = urldecode($matches[1]);
                $tmp_arry = split("=", $tmp_str);
                if(sizeof($tmp_arry) != 2)
                {
                    $this->an_app_added_nonviral($uid);
                }
                else
                {
                    $this->an_app_added_undirected($uid, $tmp_arry[1]);
                }
            }
            else
            {
                $this->an_app_added_nonviral($uid);
            }
        }
    }
    
    public function save_notification_click($added)
    {
        $ut = $_GET['kt_ut'];

        $template_id = null;
        if(isset($_GET['kt_t']))
            $template_id = $_GET['kt_t'];
        $subtype1 = null;
        if(isset($_GET['kt_st1']))
            $subtype1 = $_GET['kt_st1'];
        $subtype2 = null;
        if(isset($_GET['kt_st2']))
            $subtype2 = $_GET['kt_st2'];
        $subtype3 = null;
        if(isset($_GET['kt_st3']))
            $subtype3 = $_GET['kt_st3'];
        
        $uid = $this->get_fb_param('user');
        
        $this->an_notification_click($added, $ut, $template_id, $subtype1, $subtype2, $subtype3, $uid);
        return $this->get_stripped_kt_args_url();
    }
    
    public function save_notification_email_click($added)
    {
        $ut = $_GET['kt_ut'];
        
        $template_id = null;
        if(isset($_GET['kt_t']))
            $template_id = $_GET['kt_t'];
        $subtype1 = null;
        if(isset($_GET['kt_st1']))
            $subtype1 = $_GET['kt_st1'];
        $subtype2 = null;
        if(isset($_GET['kt_st2']))
            $subtype2 = $_GET['kt_st2'];        
        $subtype3 = null;
        if(isset($_GET['kt_st3']))
            $subtype3 = $_GET['kt_st3'];
            
        $uid = $this->get_fb_param('user');
        
        $this->an_notification_email_click($added, $ut, $template_id, $subtype1, $subtype2, $subtype3, $uid);
        return $this->get_stripped_kt_args_url();
    }
    
    public function save_invite_send()
    {
        $template_id = null;
        if(isset($_GET['kt_t']))
            $template_id = $_GET['kt_t'];
        $subtype1 = null;
        if(isset($_GET['kt_st1']))
            $subtype1 = $_GET['kt_st1'];
        $subtype2 = null;
        if(isset($_GET['kt_st2']))
            $subtype2 = $_GET['kt_st2'];        
        $subtype3 = null;
        if(isset($_GET['kt_st3']))
            $subtype3 = $_GET['kt_st3'];
        
        if(isset($_POST['ids']))
            $recipient_arry = $_POST['ids'];
        else
            $recipient_arry = '';

        $this->an_invite_send($_GET['kt_uid'], $recipient_arry, $_GET['kt_ut'], $template_id, $subtype1, $subtype2, $subtype3);
        return $this->get_stripped_kt_args_url();
    }

    public function save_invite_click($added)
    {
        $ut = $_GET['kt_ut'];

        if(isset($_GET['kt_t']))
            $template_id = $_GET['kt_t'];
        else
            $template_id = null;        
      
        if(isset($_GET['kt_st1']))
            $subtype1 = $_GET['kt_st1'];
        else
            $subtype1 = null;

        if(isset($_GET['kt_st2']))
            $subtype2 = $_GET['kt_st2'];
        else
            $subtype2 = null;        

        if(isset($_GET['kt_st3']))
            $subtype3 = $_GET['kt_st3'];
        else
            $subtype3 = null;
        
        $uid = $this->get_fb_param('user');
        
        $this->an_invite_click($added, $ut, $template_id, $subtype1, $subtype2, $subtype3, $uid); 
        return $this->get_stripped_kt_args_url();
    }

    // returns the short_tag;
    public function save_undirected_comm_click($added)
    {
        $uid = $this->get_fb_param('user');
        
        $type = $_GET['kt_type'];
        
        if(isset($_GET['kt_t']))
            $template_id = $_GET['kt_t'];
        else
            $template_id = null;        
      
        if(isset($_GET['kt_st1']))
            $subtype1 = $_GET['kt_st1'];
        else
            $subtype1 = null;

        if(isset($_GET['kt_st2']))
            $subtype2 = $_GET['kt_st2'];
        else
            $subtype2 = null;        

        if(isset($_GET['kt_st3']))
            $subtype3 = $_GET['kt_st3'];
        else
            $subtype3 = null;
        
        $short_tag = $this->gen_short_uuid();
        $this->an_app_undirected_comm_click($uid, $type, $template_id, $subtype1, $subtype2, $subtype3, $added, $short_tag);

        return $this->get_stripped_kt_args_url($short_tag);
    }

    public function save_app_removed()
    {
        global $kt_facebook;

        $post_arry = $_POST;
        ksort($post_arry);
        $sig = '';
        
        foreach ($post_arry as $key => $val) {
            if ($key == 'fb_sig') {
                continue;
            }

            $sig .= substr($key, 7) . '=' . $val;
        }

        $sig .= $kt_facebook->secret;
        $verify =  md5($sig);

        if ($verify == $post_arry['fb_sig']) {
            // Update your database to note that fb_sig_user has removed your application
            $this->an_app_remove($post_arry['fb_sig_user']);
        }else{
            // TODO: log this somehow?
        }
    }

    public function increment_goal_count($uid, $goal_id, $inc)
    {
        $this->an_goal_count_increment($uid, array($goal_id => $inc));
    }


    public function increment_multiple_goal_counts($uid, $goal_counts)
    {
        $this->an_goal_count_increment($uid, $goal_counts);
    }

    // Should use cookie to avoid sending repeated information to kontagent.
    // Example:
    // $key = $an->$m_backend_api_key."_".$uid;
    // if(!empty($_COOKIE[$key]))
    // {
    //    $an->kt_capture_user_data($uid, $info_array);
    // }
    // setcookie($key, 1, time()+1209600); // two weeks.
    
    public function kt_capture_user_data($uid, $info)
    {
        global $kt_facebook;

        if(is_array($info))
        {
            $birthday = null;
            if( isset($info[0]['birthday']) && $info[0]['birthday'] != '')
                $birthday = $info[0]['birthday'];

            $gender = null;
            if( isset($info[0]['sex']) && $info[0]['sex'] != '')
                $gender = substr($info[0]['sex'], 0, 1);
               
            $cur_city = null;
            if( isset($info[0]['current_location']['city']) &&
                $info[0]['current_location']['city'] != '' )
                $cur_city = $info[0]['current_location']['city'];
            $cur_state = null;
            if( isset($info[0]['current_location']['state']) &&
                $info[0]['current_location']['state'] != '')
                $cur_state = $info[0]['current_location']['state'];
            $cur_country = null;
            if( isset($info[0]['current_location']['country']) &&
                $info[0]['current_location']['country'] != '')
                $cur_country = $info[0]['current_location']['country'];
            $cur_zip = null;
            if( isset($info[0]['current_location']['zip']) &&
                $info[0]['current_location']['zip'] != '')
                $cur_zip = $info[0]['current_location']['zip'];

            $home_city=null;
            if( isset($info[0]['hometown_location']['city']) &&
                $info[0]['hometown_location']['city'] != '')
                $home_city = $info[0]['hometown_location']['city'];
            $home_state=null;
            if( isset($info[0]['hometown_location']['state']) &&
                $info[0]['hometown_location']['state'] != '')
                $home_state = $info[0]['hometown_location']['state'];
            $home_country=null;
            if( isset($info[0]['hometown_location']['country']) &&
                $info[0]['hometown_location']['country'] !='')
                $home_country = $info[0]['hometown_location']['country'];
            $home_zip = null;
            if( isset($info[0]['hometown_location']['zip']) &&
                $info[0]['hometown_location']['zip'] != '')
                $home_zip = $info[0]['hometown_location']['zip'];
               
            //$friends_count = count(split(',',$kt_facebook->fb_params['friends']));
            $friend_count = $info[0]['friend_count'];
            
            $this->an_send_user_data($uid, $birthday, $gender,
                                     $cur_city, $cur_state, $cur_country, $cur_zip,
                                     $home_city, $home_state, $home_country, $home_zip,
                                     $friend_count);
        }
    }
}


?>