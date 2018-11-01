<?php
namespace Tusimo\Jushuitan;

use GuzzleHttp\Psr7\Request;

class Client extends \GuzzleHttp\Client
{
    private $partnerId;
    private $partnerKey;
    private $token;
    private $useSandBox;
    private $useHttps;
    private $taobaoAppKey;
    private $taobaoAppsecret;

    //聚水潭API域名
    const BASE_DOMAIN = 'open.erp321.com';
    const BASE_DOMAIN_SANDBOX = 'c.sursung.com';

    const QIMEN_BASE_URL = 'http://a1q40taq0j.api.taobao.com';

    public function __construct($partnerId, $partnerKey, $token, $taobaoAppKey, $taobaoAppsecret, $useSandBox = false, $useHttps = true, array $config = [])
    {
        $this->partnerId = $partnerId;
        $this->partnerKey = $partnerKey;
        $this->token = $token;
        $this->useSandBox = $useSandBox;
        $this->useHttps = $useHttps;
        $this->taobaoAppKey = $taobaoAppKey;
        $this->taobaoAppsecret = $taobaoAppsecret;
        parent::__construct($config);
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function getBaseUrl($isQimen = false)
    {
        if ($isQimen) {
            return self::QIMEN_BASE_URL . ($this->isSandBox() ?  '/router/qmtest' : '/router/qm');
        }
        return ($this->isUseHttps() ? 'https://' : 'http://') .
            ($this->isSandBox() ? self::BASE_DOMAIN_SANDBOX : self::BASE_DOMAIN) .
            '/api/open/query.aspx';
    }

    /**
     * @return mixed
     */
    public function isSandBox()
    {
        return $this->useSandBox;
    }

    /**
     * @return mixed
     */
    public function isUseHttps()
    {
        return $this->useHttps;
    }

    /**
     * @return mixed
     */
    public function getPartnerId()
    {
        return $this->partnerId;
    }

    /**
     * @return mixed
     */
    public function getPartnerKey()
    {
        return $this->partnerKey;
    }

    /**
     * @return mixed
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return mixed
     */
    public function getTaobaoAppKey()
    {
        return $this->taobaoAppKey;
    }

    /**
     * @return mixed
     */
    public function getTaobaoAppsecret()
    {
        return $this->taobaoAppsecret;
    }

    private function getSystemParameters($method, $params)
    {
        # 默认系统参数
        $systemParams = array(
            'partnerid' => $this->getPartnerId(),
            'token' => $this->getToken(),
            'method' => $method,
            'ts' => time()
        );
        //是否包含jst
        if (strstr($method, 'jst')) {
            $systemParams['sign_method'] = 'md5';
            $systemParams['format'] = 'json';
            $systemParams['app_key'] = $this->getTaobaoAppKey();
            $systemParams['timestamp'] = date("Y-m-d H:i:s", $systemParams['ts']);
            $systemParams['target_app_key'] = '23060081';
        }
        return $this->generateSignature($systemParams, $params);
    }
    private function generateSignature($system_params, $params = null)
    {
        $sign_str = '';
        ksort($system_params);
        //奇门接口
        if(strstr($system_params['method'], 'jst'))
        {
            $method = str_replace('jst.','',$system_params['method']);
            $jstsign = $method.$this->getPartnerId()."token".$this->getToken()."ts".$system_params['ts'].$this->getPartnerKey();

            $system_params['jstsign'] = md5($jstsign);

            //如果有业务参数则合并
            if($params!=null)
            {
                $system_params = array_merge($system_params,$params);
                ksort($system_params);

                foreach($system_params as $key=>$value) {
                    if(is_array($value))
                    {
                        $sign_str.= $key.join(',',$value);
                        continue;
                    }
                    $sign_str .=$key.strval($value);
                }
            }
            $system_params['sign'] = strtoupper(md5($this->getTaobaoAppsecret().$sign_str.$this->getTaobaoAppsecret()));
        }
        else  //普通接口
        {
            $no_exists_array = array('method','sign','partnerid','partnerkey');

            $sign_str = $system_params['method'].$system_params['partnerid'];
            foreach($system_params as $key=>$value) {

                if(in_array($key,$no_exists_array)) {
                    continue;
                }
                $sign_str.=$key.strval($value);
            }

            $sign_str.=$this->getPartnerKey();
            $system_params['sign'] = md5($sign_str);
        }

        return $system_params;
    }

    protected function getParameters($method, $parameters)
    {
        $urlParameters = $this->getSystemParameters($method, $parameters);

        if($this->isQimen($method)) {
            foreach($parameters as $key=>$value) {
                if(is_array($value)) {
                    $urlParameters[$key] = join(',',$value);
                    continue;
                }
                $urlParameters[$key]=$value;
            }
        }
        return $urlParameters;
    }

    public function isQimen($method)
    {
        if (strstr($method, 'jst')) {
            return true;
        }
        return false;
    }

    public function callRemote($method, $parameters)
    {
        return $this->post($this->getBaseUrl($this->isQimen($method)) . '?' . http_build_query($this->getParameters($method, $parameters)), [
            'json' => $parameters
        ]);
    }

    public function makeRequest($method, $parameters)
    {
        return new Request(
            'POST',
            $this->getBaseUrl() . '?' . http_build_query($this->getParameters($method, $parameters)),
            ['json' => $parameters]
        );
    }
}
