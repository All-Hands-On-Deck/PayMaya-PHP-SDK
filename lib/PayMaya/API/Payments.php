<?php

namespace PayMaya\API;

use PayMaya\Model\Payment\Amount;
use PayMaya\Model\Payment\Buyer;

class Payments
{
    const TOKEN_URL_SANDBOX = 'https://pg-sandbox.paymaya.com/payments/v1/payment-tokens';
    const PAY_URL_SANDBOX = 'https://pg-sandbox.paymaya.com/payments/v1/payments';
    const STATUS_URL_SANDBOX = 'https://pg-sandbox.paymaya.com/payments/v1/payments/%s';

    const TOKEN_URL = 'https://pg.paymaya.com/payments/v1/payment-tokens';
    const PAY_URL = 'https://pg.paymaya.com/payments/v1/payments';
    const STATUS_URL = 'https://pg.paymaya.com/payments/v1/payments/%s';

    protected $key = null;
    protected $secret = null;
    protected $env = false; //default is sandbox
    protected $token = array();
    protected $redirectUrls = array();

    public function __construct($key, $secret, $env = false)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->env = $env; // false = sandbox, true = production
    }

    public function createToken($card = array())
    {

        // card is required
        if (empty($card) || !is_object($card)) {
            throw new \Exception('Card information missing!');
        }

        // request parameters
        $tokenParams = array('card' => array(
            'number' => $card->number,
            'cvc' => $card->cvc,
            'expMonth' => $card->expM,
            'expYear' => $card->expY, ));

        $auth = $this->useKey();

        $headers = array(
            'Content-Type:  application/json',
            'Authorization: Basic '.$auth, );

        $url = self::TOKEN_URL_SANDBOX;
        if ($this->env) {
            $url = self::TOKEN_URL;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tokenParams));
        $res = curl_exec($ch);
        curl_close($ch);

        $resArray = json_decode($res, true);

        if (!isset($resArray['paymentTokenId'])) {
            //throw json data
            if ($res) {
                throw new \Exception($res);
            }

            // no response
            // throw custom message
            throw new \Exception(json_encode(
                array(
                    'message' => 'Something went wrong.',
                )
            ));
        }

        // decode
        $token = json_decode($res, true);
        $this->setToken($token);

        return $this;
    }

    public function getTokenId()
    {
        if (isset($this->token['paymentTokenId']) && $this->token['paymentTokenId']) {
            return $this->token['paymentTokenId'];
        }

        return false;
    }

    public function setTokenId($tokenId)
    {
        // initialize token array
        if (!(isset($this->token) && !empty($this->token))) {
            $this->token = array();
        }

        $this->token['paymentTokenId'] = $tokenId;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function getRedirectUrls()
    {
        return $this->redirectUrls;
    }

    public function setRedirectUrls($redirectUrls)
    {
        $this->redirectUrls = $redirectUrls;
    }

    public function pay(Buyer $buyer, Amount $amount)
    {
        // check if token is set
        if (empty($this->token)) {
            throw new \Exception(json_encode(
                array(
                    'Message' => 'Cannot pay without token',
                )
            ));
        }

        $auth = $this->useSecret();

        $headers = array(
            'Content-Type:  application/json',
            'Authorization: Basic '.$auth, );

        $payParams = array(
            'paymentTokenId' => $this->getTokenId(),
            'totalAmount' => array(
                'amount' => $amount->total,
                'currency' => $amount->code,
            ),
            'buyer' => array(
                'firstName' => $buyer->firstname,
                'middleName' => $buyer->middlename,
                'lastName' => $buyer->lastname,
                'contact' => array(
                    'phone' => $buyer->phone,
                    'email' => $buyer->email,
                ),
                'billingAddress' => array(
                    'line1' => $buyer->address1,
                    'line2' => $buyer->address2,
                    'city' => $buyer->city,
                    'state' => $buyer->state,
                    'zipCode' => $buyer->zip,
                    'countryCode' => $buyer->country,
                ),
            ),
        );

        // set redirectUrls
        $redirectUrls = $this->getRedirectUrls();
        if (isset($redirectUrls) && !empty($redirectUrls)) {
            // success
            if (isset($redirectUrls['success']) && $redirectUrls['success']) {
                $payParams['redirectUrl']['success'] = $redirectUrls['success'];
            }

            // failure
            if (isset($redirectUrls['failure']) && $redirectUrls['failure']) {
                $payParams['redirectUrl']['failure'] = $redirectUrls['failure'];
            }

            // cancel
            if (isset($redirectUrls['cancel']) && $redirectUrls['cancel']) {
                $payParams['redirectUrl']['cancel'] = $redirectUrls['cancel'];
            }
        }

        $url = self::PAY_URL_SANDBOX;
        if ($this->env) {
            $url = self::PAY_URL;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payParams));
        $res = curl_exec($ch);

        curl_close($ch);

        return $res;
    }

    public function getPaymentStatus($id)
    {
        $auth = $this->useSecret();

        $headers = array(
            'Content-Type:  application/json',
            'Authorization: Basic '.$auth, );

        $url = sprintf(self::STATUS_URL_SANDBOX, $id);
        if ($this->env) {
            $url = sprintf(self::STATUS_URL, $id);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $res = curl_exec($ch);

        curl_close($ch);

        return $res;
    }

    protected function useKey()
    {
        return base64_encode($this->key.':');
    }

    protected function useSecret()
    {
        return base64_encode($this->secret.':');
    }
}
