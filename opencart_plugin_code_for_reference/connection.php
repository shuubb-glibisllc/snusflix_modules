<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################

require_once 'oob_log.php';

class ModelCatalogConnection extends Model {

	public function make(){
        $userData = $this->db->query("SELECT * FROM ".DB_PREFIX."odoo_confg")->row;

        if ($userData) {
            if (!class_exists('xmlrpc_client'))
                include_once 'xmlrpc.inc';

            $client = new xmlrpc_client($userData['url'].":".$userData['port']."/xmlrpc/object");
            $sock   = new xmlrpc_client($userData['url'].":".$userData['port']."/xmlrpc/common");
            $msg    = new xmlrpcmsg('login');
            $msg->addParam(new xmlrpcval($userData['db_name'], 'string'));
            $msg->addParam(new xmlrpcval($userData['user'], 'string'));
            $msg->addParam(new xmlrpcval($userData['password'], 'string'));
            $resp = $sock->send($msg);
            if ($resp->faultCode()) {
                $error_message  = $resp->faultString();
                return array(
                    'status'=>False,
                );
            } else {
                $userId = $resp->value()->scalarval();
                if ($userId <= 0){
                    return array(
                        'status'=>false,
                    );
                } else {
                    $instance_id = $this->getInstance($userId, $client, $userData['db_name'], $userData['password']);
                    if($instance_id)
                        $this->db->query("UPDATE ".DB_PREFIX."odoo_confg set `instance_id` = ".(int)$instance_id."");
                    else
                        $this->db->query("UPDATE ".DB_PREFIX."odoo_confg set `instance_id` = ''");

                    return array(
                        'status'=>true,
                        'client'=>$client,
                        'userId'=>$userId,
                        'pwd'=>$userData['password'],
                        'url'=>$userData['url'],
                        'port'=>$userData['port'],
                        'db'=>$userData['db_name'],
                        'wkproducttype'=>$userData['wkproducttype'],
                        'instance_id' => $instance_id
                    );
                }
            }
        } else {
            return array(
                'status'=>false,
            );
        }
    }

    public function getInstance($userId, $client , $database , $password)
    {
        $url_ssl = HTTPS_CATALOG;
        $url = HTTP_CATALOG;
        $urls = array($url, $url_ssl);
        $condition = [[['user','in',$urls]]];
        $res = $this->callOdooRpc('connector.instance', 'search',[$condition], $userId , $client , $database, $password );
        if ($res[0]==1) {
            if(count($res[1])>0)
                return $res[1][0];
            else
                return false;
        } else {
            return false;
        }
    }

    public function chkConnection($userData)
    {
		if ($userData) {
            if (!class_exists('xmlrpc_client'))
                include_once 'xmlrpc.inc';

            $client = new xmlrpc_client($userData['wkurl'].":".$userData['wkport']."/xmlrpc/object");
            $sock   = new xmlrpc_client($userData['wkurl'].":".$userData['wkport']."/xmlrpc/common");
            $msg    = new xmlrpcmsg('login');
            $msg->addParam(new xmlrpcval($userData['wkdb_name'], 'string'));
            $msg->addParam(new xmlrpcval($userData['wkuser'], 'string'));
            $msg->addParam(new xmlrpcval($userData['wkpassword'], 'string'));

            function handleError($errno, $errstr, $errfile, $errline, array $errcontext) {
                if (0 === error_reporting()) {
                    return false;
                }
            }
            set_error_handler('handleError');

            try {
                $resp = $sock->send($msg);
            } catch (Exception $e) { }

            if ($resp->faultCode()) {
                $error_message  = $resp->faultString();
                return array(
                    'error'=>$error_message,
                );
            } else {
                $userId = $resp->value()->scalarval();
                if ($userId <= 0) {
                    return array(
                        'error'=>false,
                    );
                } else {
                    $instance_id = $this->getInstance($userId , $client , $userData['wkdb_name'], $userData['wkpassword']);
                    if ($instance_id) {
                        $this->db->query("UPDATE ".DB_PREFIX."odoo_confg set `instance_id` = ".(int)$instance_id."");
                        return array(
                            'success'=>' Congratulation, It\'s successfully connected with Odoo through XML-RPC. ',
                        );
                    } else {
                        $this->db->query("UPDATE ".DB_PREFIX."odoo_confg set `instance_id` = ''");
                        return array(
                            'error' => 'Connected , But Instance Not Found In Odoo end. Please Create Instance For this Opencart Server'
                        );
                    }
                }
            }
        } else {
            return array(
                'error'=>false,
            );
        }
    }

    public function getDefaultContext($userId, $client, $database, $password)
    {
        $context = ['context' =>  ['opencart' => 'opencart']];
        $instance_id = $this->db->query("SELECT `instance_id` FROM ".DB_PREFIX."odoo_confg")->row;
        if ($instance_id['instance_id']) {
            $context['context']['instance_id'] = (int)$instance_id['instance_id'];
            $condition = array('fields'=>['company_id','language']);
            $vals = array([(int)$instance_id['instance_id']],$condition);
            $response1 = $this->callOdooRpc('connector.instance', 'read', $vals, $userId, $client, $database, $password, $needContext=false);
            if ($response1[0] == 1) {
                $company_id = $response1[1][0]['company_id'][0];
                $lang = $response1[1][0]['language'];
                $context['context']['lang'] = $lang;
                $context['context']['allowed_company_ids'] = [$company_id];     
            }
        }
        return $context;
    }

    public function callOdooRpc($class, $method, $parameters, $userId, $client, $database, $password, $needContext = false)
    {
        $response = [0, 'Error'];
        if ($userId > 0) {
            $msg = new xmlrpcmsg('execute_kw');
            $msg->addParam(new xmlrpcval($database, "string"));
            $msg->addParam(new xmlrpcval($userId, "int"));
            $msg->addParam(new xmlrpcval($password, "string"));
            $msg->addParam(new xmlrpcval($class, "string"));
            $msg->addParam(new xmlrpcval($method, "string"));
            foreach($parameters as $parameter) {   
                $msg->addParam(php_xmlrpc_encode($parameter));
            }
            if ($needContext) {
                $context = $this->getDefaultContext($userId, $client, $database, $password);
                if (is_array($needContext) == "array") {
                    foreach($needContext as $key=>$value) {
                        $context['context'][$key] = $value;
                    }
                }
                $msg->addParam(php_xmlrpc_encode($context));
            }
            
            $resp = $client->send($msg);
            if ($resp->faultcode()) {
                $log = new oob_log();
                $log->logMessage(__FILE__, __LINE__, $resp->faultString(), "CRITICAL");
                $response = [0, $resp->faultString()];
            } else {
                $respVal = php_xmlrpc_decode($resp->value());
                $response = [1, $respVal];
            }
        } 
        return $response;
    }
}
?>
