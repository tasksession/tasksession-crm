<?php

namespace Stripe;

/**
 * PaymentMethod API resource.
 */
class PaymentMethod extends ApiResource
{
    const OBJECT_NAME = 'payment_method';

    use ApiOperations\Create;
    use ApiOperations\Retrieve;
    use ApiOperations\Update;

    /**
     * @param array|null $params
     * @param array|string|null $options
     *
     * @return PaymentMethod
     */
    public function attach($params = null, $options = null)
    {
        $url = $this->instanceUrl() . '/attach';
        list($response, $opts) = $this->_request('post', $url, $params, $options);
        $this->refreshFrom($response, $opts);

        return $this;
    }

    /**
     * @param array|null $params
     * @param array|string|null $options
     *
     * @return PaymentMethod
     */
    public function detach($params = null, $options = null)
    {
        $url = $this->instanceUrl() . '/detach';
        list($response, $opts) = $this->_request('post', $url, $params, $options);
        $this->refreshFrom($response, $opts);

        return $this;
    }
}
