<?php

namespace Stone009\HmpayAgent;

use Hanson\Foundation\AbstractAPI;
use Stone009\HmpayAgent\Exceptions\HmpayAgentException;
use Stone009\HmpayAgent\Exceptions\HttpException;

class Api extends AbstractAPI
{
    private $appId;
    private $privateKey;

    const URL = 'https://hmpay.sandpay.com.cn/agent-api/api';
    const UPLOAD_URL = 'https://hmpay.sandpay.com.cn/agent-api/api/upload/pic';

    public function __construct(string $appId, string $privateKey)
    {
        $this->appId = $appId;
        $this->privateKey = $privateKey;
    }


    /**
     * @throws HttpException
     * @throws HmpayAgentException
     */
    public function request(string $method, array $params)
    {
        $params = array_merge($params, [
            'app_id' => $this->appId,
            'biz_content' => json_encode($params, JSON_UNESCAPED_UNICODE),
            'charset' => 'UTF-8',
            'format' => 'JSON',
            'method' => $method,
            'nonce' => $this->getRandomStr(8),
            'sign_type' => 'RSA',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
        ]);
        $params['sign'] = $this->rsaSign($params, $this->privateKey);

        $http = $this->getHttp();
        try {
            $response = $http->post(self::URL, $params);
            $result = json_decode(strval($response->getBody()), true);
            $this->checkErrorAndThrow($result);
            return $result;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws HmpayAgentException
     */
    public function uploadPic(string $pic_type, string $pic_file)
    {
        $params = [
            'app_id' => $this->appId,
            'pic_type' => $pic_type,
            'sign_type' => 'RSA',
            'nonce' => $this->getRandomStr(8),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        $params['sign'] = $this->rsaSign($params, $this->privateKey);
        $params['pic_file'] = $pic_file;

        $http = $this->getHttp();
        try {
            $response = $http->upload(self::UPLOAD_URL, $params);
            $result = json_decode(strval($response->getBody()), true);
            $this->checkErrorAndThrow($result);
            return $result;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * ??????A-Za-z0-9?????????
     * @param int $length ????????????
     * @return string
     */
    public function getRandomStr(int $length): string
    {
        // ???????????????????????????????????????????????????
        $chars = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
            'a', 'b', 'c', 'd', 'e', 'f', 'g',
            'h', 'i', 'j', 'k', 'l', 'm', 'n',
            'o', 'p', 'q', 'r', 's', 't',
            'u', 'v', 'w', 'x', 'y', 'z',
            'A', 'B', 'C', 'D', 'E', 'F', 'G',
            'H', 'I', 'J', 'K', 'L', 'M', 'N',
            'O', 'P', 'Q', 'R', 'S', 'T',
            'U', 'V', 'W', 'X', 'Y', 'Z');
        // ??? $chars ???????????? $length ?????????????????????
        $keys = array_rand($chars, $length);
        $randStr = '';
        for ($i = 0; $i < $length; $i++) {
            // ??? $length ?????????????????????????????????
            $randStr .= $chars[$keys[$i]];
        }
        return $randStr;
    }

    /**
     * @throws HmpayAgentException
     */
    public function rsaSign($params, $privateKey, $signType = "RSA"): string
    {
        return $this->sign($this->getSignContent($params), $privateKey, $signType);
    }

    /**
     * @throws HmpayAgentException
     */
    protected function sign($data, $privateKey, $signType = "RSA"): string
    {
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($privateKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        if (!$res) {
            throw new HmpayAgentException('Please check private key');
        }

        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($data, $sign, $res);
        }

        return base64_encode($sign);
    }

    public function getSignContent($params): string
    {
        ksort($params);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
                // ????????????????????????
                //$v = $this->characet($v, $this->charset);
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }
        unset ($k, $v);
        return $stringToBeSigned;
    }

    /**
     * ??????$value????????????
     * if not set ,return true;
     * if is null , return true;
     **/
    protected function checkEmpty($value): bool
    {
        if (!isset($value))
            return true;
        if (trim($value) === "")
            return true;

        return false;
    }

    /**
     * @param $result
     * @throws HmpayAgentException
     */
    private function checkErrorAndThrow($result)
    {
        if (!$result || $result['code'] != 0) {
            throw new HmpayAgentException($result['message'], $result['code']);
        }
    }
}