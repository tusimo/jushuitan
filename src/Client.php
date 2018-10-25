<?php
namespace Tusimo\Jushuitan;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;

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


    private function getJSTSign($method, $time)
    {
        return md5($method . $this->getPartnerId() . 'token'. $this->getToken() . 'ts' . $time . $this->getPartnerKey());
    }

    private function getSystemParameters($method)
    {
        $time = time();
        return [
            'partnerid' => $this->getPartnerId(),
            'token' => $this->getToken(),
            'method' => $method,
            'ts' => $time,
            'sign' => $this->getJSTSign($method, $time)
        ];
    }

    private function getQimenParameters($method, $parameters)
    {
        $now = Carbon::now();
        $params = [
            'method' => $method,
            'app_key' => $this->getTaobaoAppKey(),
            'timestamp' => $now->toDateTimeString(),
            'format' => 'json',
            'v' => '2.0',
            'partnerid' => $this->getPartnerId(),
            'target_app_key' => '23060081',
            'sign_method' => 'md5',
            'token' => $this->getToken(),
            'ts' => $now->getTimestamp(),
            'jstsign' => $this->getJSTSign(ltrim($method, 'jst.'), $now->getTimestamp()),
        ];
        $params = array_merge($parameters, $params);
        ksort($params);
        $stringToBeSigned = $this->getTaobaoAppsecret();
        foreach ($params as $k => $v) {
            $stringToBeSigned .= "$k$v";
        }
        $stringToBeSigned .= $this->getTaobaoAppsecret();
        $sign = strtoupper(md5($stringToBeSigned));
        unset($k, $v, $stringToBeSigned);
        return array_merge($params, ['sign' => $sign]);
    }

    public function callRemote($method, $parameters)
    {
        return $this->post($this->getBaseUrl() . '?' . http_build_query($this->getSystemParameters($method)), [
            'json' => $parameters
        ]);
    }

    public function callQimenRemote($method, $parameters)
    {
        return $this->get($this->getBaseUrl(true) . '?' . http_build_query($this->getQimenParameters($method, $parameters)));
    }

    /**
     * @param Response $response
     * @return mixed
     * @throws RequestException
     */
    public function getFormatResponse(Response $response)
    {
        $responseArray = json_decode($response->getBody()->getContents(), true);
        if ($responseArray['code'] != 0 || !$responseArray['issuccess']) {
            throw new RequestException($responseArray['msg'], $responseArray['code']);
        }
        return $responseArray;
    }

    public function getFormatQimenResponse(Response $response)
    {
        $responseArray = json_decode($response->getBody()->getContents(), true);
        if (isset($responseArray['response']) &&
            isset($responseArray['response']['flag']) &&
            $responseArray['response']['flag'] == 'failure'
        ) {
            throw new RequestException($responseArray['response']['message'], $responseArray['response']['code']);
        }
        return $responseArray['response'];
    }

    public function getShop($nick)
    {
        return $this->getShops([$nick])[0] ?? null;
    }

    public function getShops($nicks = [])
    {
        $response = $this->callRemote('shops.query', ['nicks' => $nicks]);
        return $this->getFormatResponse($response)['shops'];
    }

    public function getOrders($shopId, $modifiedBegin, $modifiedEnd, $pageIndex = 1, $pageSize = 50, $status = null, $soIds = [], $hasLabel = true)
    {
        $modifiedEnd = new Carbon($modifiedEnd);
        $modifiedBegin = new Carbon($modifiedBegin);
        if ($modifiedBegin >= $modifiedEnd) {
            throw new \InvalidArgumentException("开始时间不能大于结束时间");
        }
        if ($modifiedEnd->diffInDays($modifiedBegin) > 7) {
            throw new \InvalidArgumentException("日期不能相差超过7天");
        }
        $args = [
            'shop_id' => $shopId,
            'modified_begin' => $modifiedBegin->toDateTimeString(),
            'modified_end' => $modifiedEnd->toDateTimeString(),
            'status' => $status,
            'so_ids' => $soIds,
            'has_label' => $hasLabel,
            'page_index' => max(1, $pageIndex),
            'page_size' => max(0, min(50, $pageSize))
        ];
        $response = $this->callRemote('orders.single.query', $args);
        return $this->getFormatResponse($response);
    }

    public function getQimenOrders($shopId, $modifiedBegin, $modifiedEnd, $pageIndex = 1, $pageSize = 50, $status = null, $soIds = [])
    {
        $modifiedEnd = new Carbon($modifiedEnd);
        $modifiedBegin = new Carbon($modifiedBegin);
        if ($modifiedBegin >= $modifiedEnd) {
            throw new \InvalidArgumentException("开始时间不能大于结束时间");
        }
        if ($modifiedEnd->diffInDays($modifiedBegin) > 7) {
            throw new \InvalidArgumentException("日期不能相差超过7天");
        }
        $parameters = [
            'shop_id' => $shopId,
            'modified_begin' => $modifiedBegin->toDateTimeString(),
            'modified_end' => $modifiedEnd->toDateTimeString(),
            'page_index' => max(1, $pageIndex),
            'page_size' => max(0, min(50, $pageSize))
        ];
        !empty($status) && $parameters['status'] = $status;
        !empty($soIds) && $pageSize['so_ids'] = json_encode($soIds);
        $response = $this->callQimenRemote('jst.orders.query', $parameters);
        return $this->getFormatQimenResponse($response);
    }
}