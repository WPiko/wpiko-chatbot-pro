<?php
if (PHP_SAPI !== 'cli' || defined('ABSPATH')) { exit; }
// Isolated regression harness. All HTTP and WooCommerce storage are simulated.
error_reporting(E_ALL);
set_error_handler(function($level,$message,$file,$line){throw new ErrorException($message,0,$level,$file,$line);});
define('ABSPATH','/tmp/'); define('DAY_IN_SECONDS',86400); define('HOUR_IN_SECONDS',3600);
$options=$filters=$transients=$scheduled=$http_calls=$orders=array(); $user=0; $ip='192.0.2.1'; $wc=$license=true; $count=0;
class WP_Error {public $code; function __construct($c,$m){$this->code=$c;$this->message=$m;} public $message; function get_error_message(){return $this->message;}}
function is_wp_error($v){return $v instanceof WP_Error;}
function add_action(...$a){} function register_deactivation_hook(...$a){}
function add_filter($name,$fn,...$rest){$GLOBALS['filters'][$name][]=$fn;}
function apply_filters($name,$v,...$args){foreach($GLOBALS['filters'][$name]??array() as $fn){$v=$fn($v,...$args);}return $v;}
function do_action(...$a){}
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value,...$a){$GLOBALS['options'][$key]=$value;return true;}
function add_option($key,$value,...$a){if(isset($GLOBALS['options'][$key]))return false;return update_option($key,$value);}
function delete_option($key){unset($GLOBALS['options'][$key]);}
function get_transient($key){return $GLOBALS['transients'][$key]??false;}
function set_transient($key,$v,$ttl){$GLOBALS['transients'][$key]=$v;}
function delete_transient($key){unset($GLOBALS['transients'][$key]);}
function wp_clear_scheduled_hook($key){unset($GLOBALS['scheduled'][$key]);}
function wp_next_scheduled($key){return $GLOBALS['scheduled'][$key]??false;}
function wp_schedule_event($time,$recurrence,$key){$GLOBALS['scheduled'][$key]=$time;}
function wp_schedule_single_event($time,$key){$GLOBALS['scheduled'][$key]=$time;}
function wpiko_chatbot_is_woocommerce_active(){return $GLOBALS['wc'];}
function wpiko_chatbot_is_license_active(){return $GLOBALS['license'];}
function get_current_user_id(){return $GLOBALS['user'];}
function wpiko_chatbot_get_client_ip(){return $GLOBALS['ip'];}
function wp_salt($scheme){return 'test-salt';}
function wp_generate_password($len,...$a){return substr(bin2hex(random_bytes($len)),0,$len);}
function is_ssl(){return false;}
function is_email($s){return filter_var($s,FILTER_VALIDATE_EMAIL);}
function wp_strip_all_tags($s){return strip_tags($s);}
function wp_html_excerpt($s,$len,$more){return mb_substr($s,0,$len);}
function esc_url_raw($s){return $s;}
function wp_parse_args($v,$defaults){return array_merge($defaults,$v);}
function wp_json_encode($v){return json_encode($v);}
function wpiko_chatbot_log(...$a){}
function wpiko_chatbot_decrypt_api_key($k){return $k;}
function wp_remote_retrieve_response_code($r){return $r['response']['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function response($body,$code=200){return array('response'=>array('code'=>$code),'body'=>json_encode($body));}
function wp_remote_request($url,$args){$GLOBALS['http_calls'][]=array($url,$args);return ($GLOBALS['http_handler'])($url,$args);}
function wpiko_chatbot_api_call_with_retry($url,$args){return wp_remote_request($url,$args);}
class DB {
 public $options='wp_options'; public $counts=array(); public $broken=false;
 function prepare($sql,...$args){return array($sql,$args);}
 function query($q){if($this->broken)return false;if(strpos($q[0],'INSERT')===0){$k=$q[1][0];$this->counts[$k]=($this->counts[$k]??0)+1;}return 1;}
 function get_var($q){return $this->counts[$q[1][0]]??null;}
 function esc_like($s){return $s;}
}
$wpdb=new DB;
class WC_Order {
 public $id=101;public $number='101'; public $customer=7;public $email='buyer@example.test';
 function get_id(){return $this->id;} function get_order_number(){return $this->number;}
 function get_customer_id(){return $this->customer;} function get_billing_email(){return $this->email;}
 function get_status(){return 'processing';} function get_total(){return '35.50';} function get_currency(){return 'EUR';}
 function get_date_created(){return new class {function date($fmt){return '2026-09-29T10:00:00+00:00';}};}
 function get_date_paid(){return null;} function get_date_completed(){return null;}
 function get_items(){return array_fill(0,22,new class {function get_name(){return '<b>Product</b>';}function get_quantity(){return 2;}});}
 function get_meta($key){return $key==='tracking_number'?'TRACK101':'';}
}
class WC_Order_Refund extends WC_Order{}
function wc_get_order($id){return $GLOBALS['orders'][$id]??false;}
function wc_get_order_status_name($s){return ucfirst($s);}
$root=dirname(__DIR__, 2) . '/';
require $root.'wpiko-chatbot/includes/responses-tools.php';
require $root.'wpiko-chatbot/includes/responses-api.php';
require $root.'wpiko-chatbot-pro/includes/woocommerce-integration.php';
require $root.'wpiko-chatbot-pro/includes/order-lookup.php';
require $root.'wpiko-chatbot-pro/includes/order-file-cleanup.php';
function check($value,$label){if(!$value)throw new Exception('FAIL: '.$label);$GLOBALS['count']++;echo "PASS $label\n";}
function lookup($number='101',$email='buyer@example.test'){$GLOBALS['wpdb']->counts=array();return wpiko_chatbot_order_lookup_result(null,'lookup_order_status',array('order_number'=>$number,'billing_email'=>$email));}
$options['wpiko_chatbot_woocommerce_integration_enabled']=true;
$options['wpiko_chatbot_order_lookup_enabled']=true;
$orders[101]=new WC_Order;
check(count(wpiko_chatbot_order_lookup_tools(array()))===1,'Tool registered when enabled');
$license=false;check(wpiko_chatbot_order_lookup_tools(array())===array()&&isset(lookup()['error']),'Inactive license prevents registration and execution');$license=true;
$wc=false;check(isset(lookup()['error']),'WooCommerce required');$wc=true;
$options['wpiko_chatbot_woocommerce_integration_enabled']=false;check(isset(lookup()['error']),'Integration required');$options['wpiko_chatbot_woocommerce_integration_enabled']=true;
$options['wpiko_chatbot_order_lookup_enabled']=false;check(isset(lookup()['error']),'Lookup switch enforced');$options['wpiko_chatbot_order_lookup_enabled']=true;
$r=lookup();check($r===array('access'=>'basic_status','order_number'=>'101','status'=>'Processing'),'Guest receives only number and status');
check(lookup('#101',' BUYER@example.test ') === $r,'Number and email normalization');
check(lookup('101','wrong@example.test')===lookup('999','buyer@example.test'),'Mismatch does not reveal order existence');
check(isset(lookup('101','')['error']),'Guests need checkout email');
check(isset(lookup("101 OR 1=1")['error']),'Invalid identifiers fail closed');
$user=7;$r=lookup('101','');check($r['access']==='account_owner'&&$r['total']==='35.50'&&$r['currency']==='EUR','Owner identified by WordPress account without email');
check(count($r['items'])===20&&$r['items_truncated']&&$r['items'][0]['name']==='Product','Owner items bounded and sanitized');
check(!isset($r['billing_email'],$r['customer_id'],$r['order_note'],$r['payment_method'])&&count($r)===12,'Only allowed owner fields returned');
$options['wpiko_chatbot_order_fields_options']=array('total'=>false,'items'=>false,'tracking_link'=>false);$r=lookup('101','');check(!isset($r['total'])&&!isset($r['items'])&&!isset($r['tracking_link']),'Owner field preferences enforced');
$user=8;check(isset(lookup('101','')['error']),'Other signed-in account cannot act as owner');check(lookup()['access']==='basic_status','Other account with matching email limited to guest status');
check(isset(wpiko_chatbot_order_lookup_result(null,'lookup_order_status',array('order_number'=>'101','billing_email'=>'','customer_id'=>7))['error']),'Client-supplied customer ID rejected');
$user=0;$orders[102]=new WC_Order_Refund;$orders[102]->number='102';check(isset(lookup('102')['error']),'Refund objects excluded');
$orders[101]->number='SHOP-101';add_filter('wpiko_chatbot_lookup_order_id',function($id,$n){return $n==='SHOP-101'?101:$id;});check(lookup('SHOP-101')['order_number']==='SHOP-101','Custom order numbering resolver supported');$orders[101]->number='101';
$wpdb->counts=array();for($i=0;$i<10;$i++)check(wpiko_chatbot_order_lookup_rate_allowed('101'),'Allowed visitor attempt '.($i+1));check(!wpiko_chatbot_order_lookup_rate_allowed('101'),'Eleventh visitor attempt blocked');
$wpdb->counts=array();for($i=0;$i<20;$i++){$ip='192.0.2.'.($i+1);wpiko_chatbot_order_lookup_rate_allowed('101');}$ip='192.0.2.30';check(!wpiko_chatbot_order_lookup_rate_allowed('101'),'Per-order cap across actors');$wpdb->broken=true;check(!wpiko_chatbot_order_lookup_rate_allowed('101'),'Rate-store failure denies access');$wpdb->broken=false;
$options['wpiko_chatbot_private_orders_retired']=true;$_COOKIE['wpiko_chat_session']=str_repeat('a',48);
$conv=wpiko_chatbot_bind_chat_session('resp_legacy');check($conv!=='resp_legacy','Legacy/foreign conversation ID reset');check(wpiko_chatbot_bind_chat_session($conv)===$conv,'Same browser/account session continues');
wpiko_chatbot_record_private_response($conv,'resp_trusted');$body=wpiko_chatbot_prepare_response_tools(array('previous_response_id'=>'resp_foreign','tools'=>array(array('type'=>'file_search','vector_store_ids'=>array('vs_test')))),$conv);
check($body['previous_response_id']==='resp_trusted','Frontend response ID overridden by bound state');check($body['tools'][0]['filters']===array('type'=>'eq','key'=>'wpiko_scope','value'=>'public_v1'),'Only classified public files searchable');check($body['parallel_tool_calls']===false,'Parallel lookups disabled');
$user=7;check(wpiko_chatbot_bind_chat_session($conv)!==$conv,'Account switch resets private history');$user=0;
check(wpiko_chatbot_vector_file_attachment('f','woocommerce_orders.json')['attributes']['wpiko_scope']==='private_orders','Reserved order filename never public');check(wpiko_chatbot_vector_file_attachment('f','knowledge.pdf')['attributes']['wpiko_scope']==='public_v1','New knowledge files classified');
$events=array();$state=array('event_name'=>'response.function_call_arguments.delta','data_lines'=>array('{"delta":"buyer@example.test"}'),'assistant_message'=>'','citation_pending'=>'','response_id'=>null);
$callbacks=array('delta'=>function($d)use(&$events){$events[]=$d;});wpiko_chatbot_dispatch_openai_stream_event($state,$callbacks);check($events===array()&&$state['assistant_message']==='','Function argument deltas never stream');
$state['event_name']='response.output_text.delta';$state['data_lines']=array('{"delta":"Hello"}');wpiko_chatbot_dispatch_openai_stream_event($state,$callbacks);check($events===array('Hello'),'Normal text still streams');
$tool=array('type'=>'function_call','name'=>'lookup_order_status','arguments'=>'{"order_number":"101","billing_email":"buyer@example.test"}','call_id'=>'call1');$body=array('model'=>'test','tools'=>wpiko_chatbot_order_lookup_tools(array()),'stream'=>true);
$wpdb->counts=array();$http_calls=array();$http_handler=function($url,$args){return response(array('id'=>'resp_final','output'=>array(array('type'=>'message','content'=>array(array('type'=>'output_text','text'=>'Processing'))))));};
$r=wpiko_chatbot_run_response_tools(response(array('id'=>'resp_tool','output'=>array($tool))),$body,array('Authorization'=>'Bearer test'));
$sent=json_decode($http_calls[0][1]['body'],true);check($sent['previous_response_id']==='resp_tool'&&$sent['tool_choice']==='none'&&!isset($sent['stream']),'Tool round continues correct response and prohibits more tools');
check(json_decode($sent['input'][0]['output'],true)['access']==='basic_status','Backend result sent through function_call_output');
$http_calls=array();$two=$tool;$two['call_id']='call2';wpiko_chatbot_run_response_tools(response(array('id'=>'resp_tool','output'=>array($tool,$two))),$body,array());$sent=json_decode($http_calls[0][1]['body'],true);check(isset(json_decode($sent['input'][1]['output'],true)['error']),'Second lookup in a round denied');
$http_calls=array();$bad=$tool;$bad['name']='system';wpiko_chatbot_run_response_tools(response(array('id'=>'resp_tool','output'=>array($bad))),$body,array());$sent=json_decode($http_calls[0][1]['body'],true);check(isset(json_decode($sent['input'][0]['output'],true)['error']),'Unregistered tool cannot execute');
$http_handler=function()use($tool){return response(array('id'=>'again','output'=>array($tool)));};check(is_wp_error(wpiko_chatbot_run_response_tools(response(array('id'=>'resp_tool','output'=>array($tool))),$body,array())),'Repeated tool rounds rejected');check(is_wp_error(wpiko_chatbot_run_response_tools(response(array('output'=>array($tool))),$body,array())),'Missing response identifier rejected');
// Migration, classification, retry and deletion all use mocked HTTP.
unset($options['wpiko_chatbot_private_orders_retired'],$options['wpiko_chatbot_order_lookup_enabled']);$options['wpiko_chatbot_orders_auto_sync']='100';$options['wpiko_chatbot_responses_vector_store_id']='vs_test';$options['wpiko_chatbot_responses_orders_file_id']='f_old';$options['wpiko_chatbot_api_key']='test';$http_calls=array();
wpiko_chatbot_initialize_order_lookup();check($options['wpiko_chatbot_order_lookup_enabled']===true&&$options['wpiko_chatbot_orders_auto_sync']==='disabled','Existing order assistance migrates to controlled lookup');check(count($http_calls)===0,'Initialization has no network calls');
$http_handler=function($url,$args){
 if(strpos($url,'?limit=3')!==false)return response(array('data'=>array(array('id'=>'f_public','attributes'=>array('custom'=>'preserved')),array('id'=>'f_order','attributes'=>array())),'has_more'=>false));
 if($args['method']==='GET')return response(array('filename'=>strpos($url,'f_public')!==false?'knowledge.pdf':'woocommerce_orders.json'));
 return response(array('id'=>'ok'));
};wpiko_chatbot_cleanup_order_files();$state=$options['wpiko_chatbot_order_file_cleanup'];check($state['scanned']&&isset($state['pending']['f_order'],$state['pending']['f_old'])&&!$state['done'],'Scan finds exported and tracked private files before deletion');
$posts=array_values(array_filter($http_calls,function($c){return $c[1]['method']==='POST';}));check(count($posts)===1&&json_decode($posts[0][1]['body'],true)['attributes']===array('custom'=>'preserved','wpiko_scope'=>'public_v1'),'Only public files tagged; existing attributes retained');
$http_handler=function($url,$args){return response(array('error'=>'network'),500);};wpiko_chatbot_cleanup_order_files();check($options['wpiko_chatbot_order_file_cleanup']['error']&&count($options['wpiko_chatbot_order_file_cleanup']['pending'])===2,'Failed deletion retains retry state');
$http_calls=array();$http_handler=function($url,$args){return response(array(),404);};wpiko_chatbot_cleanup_order_files();check($options['wpiko_chatbot_order_file_cleanup']['done']&&empty($options['wpiko_chatbot_order_file_cleanup']['pending']),'Already-deleted files handled idempotently');check(count($http_calls)===4&&!get_option('wpiko_chatbot_responses_orders_file_id'),'Each private file detached and deleted from storage');
check(wpiko_chatbot_sync_orders()===false&&wpiko_chatbot_run_background_orders_sync()===false,'Retired sync entry points inert');
$r=wpiko_chatbot_upload_file_to_responses(array('name'=>'woocommerce_orders.json'));check(empty($r['success']),'Reserved order upload rejected');

// Full chat paths with network transport replaced; no live API requests.
function wpiko_chatbot_combine_responses_instructions($files=true){return 'Test managed instructions';}
function wpiko_chatbot_save_message(...$args){$GLOBALS['saved_messages'][]=$args;}
function wpiko_chatbot_process_message($s){return $s;}
function wpiko_chatbot_clear_openai_health(){}
function wpiko_chatbot_classify_openai_error(...$args){return array('account_problem'=>false,'code'=>'server_error');}
function wpiko_chatbot_build_api_error_response(...$args){return array('visitor_message'=>'Unavailable','data'=>array('message'=>'Unavailable'));}
function wpiko_chatbot_get_user_friendly_error_message(...$args){return 'Unavailable';}
function wp_remote_post(...$args){throw new Exception('Unexpected fallback request');}
$options['wpiko_chatbot_responses_model']='gpt-4.1';$options['wpiko_chatbot_responses_vector_store_id']='vs_test';$options['wpiko_chatbot_order_lookup_enabled']=true;$wpdb->counts=array();$user=0;
set_transient('wpiko_chatbot_vs_has_files_'.md5('vs_test'),'1',600);
$http_calls=array();$saved_messages=array();$http_handler=function($url,$args)use($tool){$body=json_decode($args['body'],true);return isset($body['tool_choice'])?response(array('id'=>'resp_final','output_text'=>'Your order is processing.')):response(array('id'=>'resp_tool','output'=>array($tool)));};
$r=wpiko_chatbot_responses_api_call('Order 101, buyer@example.test','resp_untrusted','','resp_foreign');
check($r['response']==='Your order is processing.'&&count($http_calls)===2,'Complete non-streamed lookup returns final answer');
$initial=json_decode($http_calls[0][1]['body'],true);check(!isset($initial['previous_response_id'])&&$initial['tools'][0]['filters']['value']==='public_v1','Non-streamed request rejects foreign history and filters files');
check(get_transient(wpiko_chatbot_chat_state_key($r['conversation_id']))['response_id']==='resp_final','Non-streamed final response saved in bound state');
$http_calls=array();$http_handler=function($url,$args){return response(array('error'=>array('message'=>'Failure')),500);};$r=wpiko_chatbot_responses_api_call('Order 101','resp_untrusted');check($r['success']===false&&count($http_calls)===1,'Lookup HTTP failures never retry without tools');
check(!wpiko_chatbot_chat_session_is_bound('foreign'),'Unknown session cannot poll or send heartbeat');
$guest_scope=wpiko_chatbot_private_chat_script_data(array())['private_history_scope'];$user=7;check(wpiko_chatbot_private_chat_script_data(array())['private_history_scope']!==$guest_scope,'Frontend history marker changes between guest and owner');$user=0;
if (!function_exists('curl_init')) {
 function curl_init($url){return (object)array('options'=>array());}
 function curl_setopt($ch,$key,$value){$ch->options[$key]=$value;return true;}
 function curl_exec($ch){$GLOBALS['curl_body']=json_decode($ch->options[CURLOPT_POSTFIELDS],true);$wire=$GLOBALS['sse'];foreach(str_split($wire,17) as $chunk){$ch->options[CURLOPT_WRITEFUNCTION]($ch,$chunk);}return true;}
 function curl_error($ch){return '';}
 function curl_getinfo($ch,$key){return 200;}
 function curl_close($ch){}
 $sse='event: response.function_call_arguments.delta'."\n".'data: {"delta":"buyer@example.test"}'."\n\n".'event: response.completed'."\n".'data: '.json_encode(array('response'=>array('id'=>'resp_streamtool','output'=>array($tool))))."\n\n";
 $http_calls=$events=$saved_messages=array();$wpdb->counts=array();$http_handler=function($url,$args){return response(array('id'=>'resp_streamfinal','output_text'=>'Order processing.'));};
 $r=wpiko_chatbot_responses_api_stream('Order 101, buyer@example.test','resp_foreign','','resp_foreign',array('delta'=>function($d)use(&$events){$events[]=$d;},'done'=>function($r){$GLOBALS['done']=$r;}));
 check($r['response']==='Order processing.'&&$events===array()&&$done===$r,'Complete streamed lookup delivers final answer without argument leakage');
 check(count($http_calls)===1&&$http_calls[0][1]['headers']['Authorization']==='Bearer test','Streaming continuation carries correct authorization');
 check(get_transient(wpiko_chatbot_chat_state_key($r['conversation_id']))['response_id']==='resp_streamfinal','Streamed final response saved in bound state');
 check(!isset($curl_body['previous_response_id'])&&$curl_body['tools'][0]['filters']['value']==='public_v1','Streaming request also enforces history binding and file filter');
 $http_handler=function(){return response(array('error'=>array('message'=>'no')),500);};$r=wpiko_chatbot_responses_api_stream('Order 101');check($r['success']===false,'Streaming continuation failure returns a safe error');
} else {throw new Exception('Run this test with curl functions disabled to install the mock transport.');}
echo "FINAL TOTAL: $count checks passed\n";
