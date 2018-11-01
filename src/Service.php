<?php
namespace Tusimo\Jushuitan;

use Carbon\Carbon;
use Psr\Http\Message\ResponseInterface;

class Service
{
    private $client;

    public function __construct( Client $client)
    {
        $this->client = $client;
    }

    public function getClient()
    {
        return $this->client;
    }


    /**
     * @param ResponseInterface $response
     * @return mixed
     * @throws RequestException
     */
    public function getFormatResponse(ResponseInterface $response)
    {
        $responseArray = json_decode($response->getBody()->getContents(), true);
        if ($responseArray['code'] != 0 || !$responseArray['issuccess']) {
            throw new RequestException($responseArray['msg'], $responseArray['code']);
        }
        return $responseArray;
    }

    public function getFormatQimenResponse(ResponseInterface $response)
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
        $response = $this->client->callRemote('shops.query', ['nicks' => $nicks]);
        return $this->getFormatResponse($response)['shops'];
    }

    public function getOrders($shopId, $modifiedBegin, $modifiedEnd, $pageIndex = 1, $pageSize = 50, $status = null, $hasLabel = true)
    {
        return $this->getFormatResponse($this->getOrdersByDate(false, $shopId, $modifiedBegin, $modifiedEnd, $pageIndex, $pageSize, $status, $hasLabel));
    }

    public function getQimenOrders($shopId, $modifiedBegin, $modifiedEnd, $pageIndex = 1, $pageSize = 50, $status = null, $haslabel = true)
    {
        return $this->getFormatQimenResponse($this->getOrdersByDate(true, $shopId, $modifiedBegin, $modifiedEnd, $pageIndex, $pageSize, $status, $haslabel));
    }

    protected function getOrdersByDate($isQimen, $shopId, $modifiedBegin, $modifiedEnd, $pageIndex = 1, $pageSize = 50, $status = null, $hasLabel = true)
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
            'has_label' => $hasLabel,
            'page_size' => max(0, min(50, $pageSize))
        ];
        !empty($status) && $parameters['status'] = $status;
        $method = $isQimen ? 'jst.orders.query' : 'orders.single.query';
        return $this->client->callRemote($method, $parameters);
    }

    public function getOrdersByIds($soIds, $isQimen = false)
    {
        $method = $isQimen ? 'jst.orders.query' : 'orders.single.query';

        $response = $this->client->callRemote($method, ['so_ids' => $soIds]);

        if ($isQimen) {
            return $this->getFormatQimenResponse($response);
        }
        return $this->getFormatResponse($response);
    }
}
