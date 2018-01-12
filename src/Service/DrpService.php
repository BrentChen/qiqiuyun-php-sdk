<?php

namespace QiQiuYun\SDK\Service;

use QiQiuYun\SDK\SignUtil;
use QiQiuYun\SDK\Helper\MarketingHelper;
use QiQiuYun\SDK\Exception\DrpException;
use QiQiuYun\SDK\Exception\SDKException;


class DrpService extends BaseService
{
    private $loginPath = '/merchant/login';
    private $postDataPath = '/post_merchant_data';

    /**
     * 生成登陆的表单
     *
     * @param array $user 当前登陆的ES用户
     * user信息如下：
     * * user_source_id 用户在ES的Id
     * * nickname 用户在ES的昵称
     * * avatar 用户头像
     * @param array $site 网校信息
     * site网校信息
     * * domain 网校网址
     * * name 网校名称
     * * logo 网校logo
     * * about 网校介绍
     * * wechat 网校微信客服
     * * qq 网校qq客服
     * * telephone 网校电话客服
     *
     * @return string form表单
     */
    public function generateLoginForm($user, $site)
    {
        $jsonStr = SignUtil::serialize(array('user' => $user, 'site' => $site));
        $sign = SignUtil::sign($this->auth, $jsonStr);
        $action = $this->baseUri.$this->loginPath;

        return MarketingHelper::generateLoginForm($action, $user, $site, $sign);
    }

    /**
     *  解析token，返回token的组成部分
     *
     * @param string $token
     *
     * @return array 内容如下:
     *               - coupon_price 奖励优惠券金额
     *               - coupon_expiry_day 奖励优惠券的有效天数
     *               - time 链接生成时间
     *               - nonce 参与签名计算的随机字符串
     *
     * @throws DrpException 签名不通过
     */
    public function parseToken($token)
    {
        $data = explode(':', $token);
        if (7 !== count($data)) {
            throw new DrpException('非法请求:sign格式不合法');
        }

        list($merchantId, $agencyId, $couponPrice, $couponExpiryDay, $time, $nonce, $signature) = $data;

        $json = SignUtil::serialize(array('merchant_id' => $merchantId, 'agency_id' => $agencyId, 'coupon_price' => $couponPrice, 'coupon_expiry_day' => $couponExpiryDay));
        $signText = implode('\n', array($time, $nonce, $json));
        $sign = $this->auth->sign($signText);
        if ($sign != $signature) {
            throw new DrpException('非法请求:sign值不一致');
        }

        return array('couponPrice' => $couponPrice, 'couponExpiryDay' => $couponExpiryDay, 'time' => $time, 'nonce' => $nonce);
    }

    /**
     * 上报通过分销平台注册的用户,或者他们的订单信息
     *
     * 
     * @param array $data, 数组,形如[{$user},...]
     * user 内容如下:
     *  * user_source_id: 用户的Id
     *  * nickname: 用户名的用户名
     *  * mobile: 用户的手机号
     *  * registered_time: 当前记录的创建时间（用户注册时间）
     *  * token: 用户注册时用的token
     * order 内容如下：
     *  * user_source_id 订单的用户Id
     *  * source_id 订单的Id
     *  * product_type 商品类型
     *  * product_id 商品Id
     *  * title  订单title
     *  * sn 订单编号
     *  * created_time 订单创建时间
     *  * payment_time 支付时间
     *  * refund_expiry_day 退款有效期（X天）
     *  * refund_deadline 退款截止时间
     *  * price 订单价格（分）
     *  * pay_amount 订单支付金额（分）
     *  * deduction [{'type'=>'adjust_price','detail'=>'修改价格','amount'=>1(分)},...]
     *  * status 订单状态
     * @param string $type 数据类型，user，order
     * 
     * @return array 内容如下:
     *               -code success | error
     *               -msg 如果code为error，这里是错误原因   
     */
    public function postData($data,$type)
    {
        if(empty($data) || empty($type)){
            throw new SDKException("Required 'data' and 'type'");
        }
        if(!is_array($data)){
            throw new SDKException("'data' must be instanceof Array");
        }
        return $this->doPost($data, $type);
    }

    private function doPost($data, $type)
    {
        $jsonStr = SignUtil::serialize(array('data'=>$data,'type'=>$type));
        $jsonStr = SignUtil::cut($jsonStr);
        $sign = SignUtil::sign($this->auth, $jsonStr);
        
        return $this->client->request(
            'POST',
            $this->baseUri.$this->postDataPath,
            array(
                'data' => $data,
                'sign' => $sign,
            )
        );
    }
}