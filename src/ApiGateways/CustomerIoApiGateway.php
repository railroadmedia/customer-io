<?php


namespace Railroad\CustomerIo\ApiGateways;


use Exception;

class CustomerIoApiGateway
{
    /**
     * If the email is passed, the customers with the given ID will have their email updated to the passed value.
     * Attributes are set and unset using a value or empty value. If you pass an empty array no attributes will be added
     * or removed. If you want to unset an existing attribute it must be passed with a null value.
     *
     * @param  string  $customerIoSiteId
     * @param  string  $customerIoTrackApiKey
     * @param  string  $customerId
     * @param  string|null  $emailAddress
     * @param  array  $attributes
     * @param  null  $createdAtTimestamp
     * @throws Exception
     */
    public function addOrUpdateCustomer(
        $customerIoSiteId,
        $customerIoTrackApiKey,
        $customerId,
        $emailAddress = null,
        $attributes = [],
        $createdAtTimestamp = null
    ) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://track.customer.io/api/v1/customers/'.$customerId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');

        $dataArray = $attributes;

        if (!empty($emailAddress)) {
            $dataArray['email'] = $emailAddress;
        }

        if (!empty($createdAtTimestamp)) {
            $dataArray['created_at'] = $createdAtTimestamp;
        }

        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode($dataArray)
        );

        $authHeaderKey = base64_encode($customerIoSiteId.':'.$customerIoTrackApiKey);

        $headers = [];
        $headers[] = 'Authorization: Basic '.$authHeaderKey;
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = json_decode(curl_exec($ch), true);

        if (curl_errno($ch)) {
            throw new Exception('Customer.io addOrUpdateCustomer api call failed: '.curl_error($ch));
        }

        // empty result means success for some reason...
        if ($result !== []) {
            throw new Exception('Customer.io addOrUpdateCustomer api call failed: '.curl_error($ch));
        }

        curl_close($ch);
    }

    /**
     * @param  string  $customerIoAppApiKey
     * @param  string  $customerId
     */
    public function getCustomer(
        $customerIoAppApiKey,
        $customerId
    ) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://beta-api.customer.io/v1/api/customers/'.$customerId.'/attributes');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $headers = [];
        $headers[] = 'Authorization: Bearer '.$customerIoAppApiKey;
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $apiResponse = curl_exec($ch);

        $result = json_decode($apiResponse);

        if (curl_errno($ch)) {
            throw new Exception('Customer.io api call failed: '.curl_error($ch));
        }

        // empty result means success for some reason...
        if (!empty($result->errors) || empty($result->customer)) {
            throw new Exception('Customer.io api call failed: '.curl_error($ch).' - '.var_export($result, true), 404);
        }

        curl_close($ch);

        return $result->customer;
    }

    /**
     * @param $customerIoSiteId
     * @param  string  $customerIoTrackApiKey
     * @param  string  $customerId
     * @param  string  $eventName
     * @param  array  $eventData // key value pairs
     * @param  null  $eventType
     * @param  null  $createdAtTimestamp
     * @return bool
     * @throws Exception
     */
    public function createEvent(
        $customerIoSiteId,
        $customerIoTrackApiKey,
        $customerId,
        $eventName,
        $eventData = [],
        $eventType = null,
        $createdAtTimestamp = null
    ) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://track.customer.io/api/v1/customers/'.$customerId.'/events');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

        $dataArray = [
            'name' => $eventName,
        ];

        if (!empty($eventData)) {
            $dataArray['data'] = $eventData;
        }

        if (!empty($eventType)) {
            $dataArray['type'] = $eventType;
        }

        if (!empty($createdAtTimestamp)) {
            $dataArray['timestamp'] = $createdAtTimestamp;
        }

        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode($dataArray)
        );

        $authHeaderKey = base64_encode($customerIoSiteId.':'.$customerIoTrackApiKey);

        $headers = [];
        $headers[] = 'Authorization: Basic '.$authHeaderKey;
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $rawResult = curl_exec($ch);
        $jsonResult = json_decode($rawResult, true);

        if (curl_errno($ch)) {
            throw new Exception(
                'Customer.io createEvent api call failed: '.curl_error($ch).' - Result: '.$rawResult
            );
        }

        // empty result means success for some reason...
        if ($jsonResult !== []) {
            throw new Exception(
                'Customer.io createEvent api call failed: '.curl_error($ch).' - Result: '.$rawResult
            );
        }

        curl_close($ch);

        return true;
    }

    /**
     * https://customer.io/docs/api/#operation/getPersonActivities
     *
     * @param  string  $customerIoAppApiKey
     * @param  string  $customerId
     */
    public function getCustomerActivities(
        $customerIoAppApiKey,
        $customerId,
        $type = null,
        $name = null,
        $limit = 10,
        $startToken = null
    ) {
        $ch = curl_init();

        $params = [];

        if (!empty($type)) {
            $params['type'] = $type;
        }
        if (!empty($name)) {
            $params['name'] = $name;
        }
        if (!empty($limit)) {
            $params['limit'] = $limit;
        }
        if (!empty($startToken)) {
            $params['start'] = $startToken;
        }

        $paramsString = http_build_query($params);

        curl_setopt(
            $ch,
            CURLOPT_URL,
            'https://beta-api.customer.io/v1/api/customers/'.$customerId.'/activities?'.$paramsString
        );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $headers = [];
        $headers[] = 'Authorization: Bearer '.$customerIoAppApiKey;
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $apiResponse = curl_exec($ch);

        $result = json_decode($apiResponse);

        if (curl_errno($ch)) {
            throw new Exception('Customer.io getCustomerActivities api call failed: '.curl_error($ch));
        }

        // empty result means success for some reason...
        if (!empty($result->errors)) {
            throw new Exception(
                'Customer.io getCustomerActivities api call failed: '.curl_error($ch).' - '.var_export($result, true),
                404
            );
        }

        curl_close($ch);

        return $result->activities;
    }

    /**
     * @param  string  $customerIoAppApiKey,
     * @param  string  $customerIoTransactionalMessageId
     * @param  string  $customerEmail
     * @param  string  $customerId
     * @param  array  $messageDataArray
     * @return bool
     * @throws Exception
     */
    public function sendTransactionalEmail(
        $customerIoAppApiKey,
        $customerIoTransactionalMessageId,
        $customerEmail,
        $customerId,
        $messageDataArray = []
    ) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.customer.io/v1/send/email');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

        $jonDataArray = [
            'to' => $customerEmail,
            'transactional_message_id' => $customerIoTransactionalMessageId,
            'message_data' => $messageDataArray,
            'identifiers' => [
                'id' => $customerId,
            ]
        ];

        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode($jonDataArray)
        );

        $headers = [];
        $headers[] = 'Authorization: Bearer '.$customerIoAppApiKey;
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $rawResult = curl_exec($ch);
        $jsonResult = json_decode($rawResult, true);

        if (curl_errno($ch)) {
            throw new Exception(
                'Customer.io sendTransactionalEmail api call failed: '.curl_error($ch).' - Result: '.$rawResult
            );
        }

        // empty result means success for some reason...
        if (empty($jsonResult['delivery_id'])) {
            throw new Exception(
                'Customer.io sendTransactionalEmail api call failed: '.curl_error($ch).' - Result: '.$rawResult
            );
        }

        curl_close($ch);

        return true;
    }
}