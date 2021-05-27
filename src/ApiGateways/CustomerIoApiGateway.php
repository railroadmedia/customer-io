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
            throw new Exception('Customer.io api call failed: '.curl_error($ch));
        }

        // empty result means success for some reason...
        if ($result !== []) {
            throw new Exception('Customer.io api call failed: '.curl_error($ch));
        }

        curl_close($ch);
    }

    /**
     * @param  string  $customerIoTrackApiKey
     * @param  string  $customerId
     */
    public function getCustomer(
        $customerIoTrackApiKey,
        $customerId
    ) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://beta-api.customer.io/v1/api/customers/'.$customerId.'/attributes');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $headers = [];
        $headers[] = 'Authorization: Bearer '.$customerIoTrackApiKey;
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
}