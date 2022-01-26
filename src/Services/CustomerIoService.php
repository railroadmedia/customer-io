<?php

namespace Railroad\CustomerIo\Services;

use Carbon\Carbon;
use Exception;
use Railroad\CustomerIo\ApiGateways\CustomerIoApiGateway;
use Railroad\CustomerIo\Events\CustomerCreated;
use Railroad\CustomerIo\Events\CustomerUpdated;
use Railroad\CustomerIo\Models\Customer;
use Throwable;

class CustomerIoService
{
    /**
     * @var CustomerIoApiGateway
     */
    private $customerIoApiGateway;

    /**
     * @var string
     */
    private $userIdCustomFieldName;

    /**
     * CustomerIoService constructor.
     * @param  CustomerIoApiGateway  $customerIoApiGateway
     */
    public function __construct(CustomerIoApiGateway $customerIoApiGateway)
    {
        $this->customerIoApiGateway = $customerIoApiGateway;
        $this->userIdCustomFieldName = config('customer-io.customer_attribute_name_for_user_id', 'user_id');
    }

    /**
     * @param string $accountName
     * @param string $id
     * @returns Customer
     * @throws Exception
     */
    public function getCustomerById($accountName, $id)
    {
        // customer.io account/workspace details
        $accountConfigData = $this->getAccountConfigData($accountName);

        /**
         * @var $customer Customer
         */
        $customer =
            Customer::query()
                ->where([
                        'uuid' => $id,
                        'workspace_name' => $accountConfigData['workspace_name'],
                        'workspace_id' => $accountConfigData['workspace_id'],
                        'site_id' => $accountConfigData['site_id'],
                    ])
                ->firstOrFail();

        $externalCustomerData = $this->customerIoApiGateway->getCustomer(
            $accountConfigData['app_api_key'],
            $customer->uuid
        );

        $customer->setExternalAttributes((array)$externalCustomerData->attributes);

        return $customer;
    }

    /**
     * @param string $accountName
     * @param string $userId
     * @returns Customer
     * @throws Exception
     */
    public function getCustomerByUserId($accountName, $userId)
    {
        // customer.io account/workspace details
        $accountConfigData = $this->getAccountConfigData($accountName);

        /**
         * @var $customer Customer
         */
        $customer =
            Customer::query()
                ->where([
                        'user_id' => $userId,
                        'workspace_name' => $accountConfigData['workspace_name'],
                        'workspace_id' => $accountConfigData['workspace_id'],
                        'site_id' => $accountConfigData['site_id'],
                    ])
                ->firstOrFail();

        $externalCustomerData = $this->customerIoApiGateway->getCustomer(
            $accountConfigData['app_api_key'],
            $customer->uuid
        );

        $customer->setExternalAttributes((array)$externalCustomerData->attributes);

        return $customer;
    }

    /**
     * @param string $accountName
     * @param string $userId
     * @param int $limit
     * @param int $amountToSkip
     * @return Customer
     * @throws Exception
     */
    public function getCustomerEventsByUserId($accountName, $userId, $limit = 25, $amountToSkip = 0)
    {
        // customer.io account/workspace details
        $accountConfigData = $this->getAccountConfigData($accountName);

        /**
         * @var $customer Customer
         */
        $customer =
            Customer::query()
                ->where([
                        'user_id' => $userId,
                        'workspace_name' => $accountConfigData['workspace_name'],
                        'workspace_id' => $accountConfigData['workspace_id'],
                        'site_id' => $accountConfigData['site_id'],
                    ])
                ->firstOrFail();

        $customerActivities = $this->customerIoApiGateway->getCustomerActivities(
            $accountConfigData['app_api_key'],
            $customer->uuid,
            'event',
            null,
            $limit,
            $amountToSkip
        );

        return $customerActivities;
    }

    /**
     * If no ID is passed, one will be generated automatically.
     * If no $createdAtTimestamp is passed it will use the current time.
     *
     * @param $email
     * @param $accountName
     * @param array $customAttributes
     * @param string|null $id
     * @param integer|null $userId
     * @param integer|null $createdAtTimestamp
     * @throws Exception
     * @throws Throwable
     */
    public function createCustomer(
        $email,
        $accountName,
        $customAttributes = [],
        $id = null,
        $userId = null,
        $createdAtTimestamp = null
    ) {
        $customer = new Customer();

        // uuid (this is what is used inside customer.io
        if (empty($id)) {
            $customer->generateUUID();
        }

        // customer.io account/workspace details
        $accountConfigData = $this->getAccountConfigData($accountName);

        $customer->workspace_name = $accountConfigData['workspace_name'];
        $customer->workspace_id = $accountConfigData['workspace_id'];
        $customer->site_id = $accountConfigData['site_id'];

        // email & other misc
        $customer->email = $email;
        $customer->user_id = $userId;

        if (empty($createdAtTimestamp)) {
            $createdAtTimestamp = Carbon::now()->timestamp;
        }

        $customer->setCreatedAt(Carbon::createFromTimestamp($createdAtTimestamp));
        $customer->setUpdatedAt(Carbon::createFromTimestamp($createdAtTimestamp));

        // save to the database
        $customer->saveOrFail();

        // set the user id custom attribute if its not empty
        if (!empty($userId)) {
            $customAttributes[$this->userIdCustomFieldName] = $userId;
        }

        // sync to customer.io using their API
        $this->customerIoApiGateway->addOrUpdateCustomer(
            $accountConfigData['site_id'],
            $accountConfigData['track_api_key'],
            $customer->uuid,
            $customer->email,
            $customAttributes,
            $createdAtTimestamp
        );

        event(new CustomerCreated($customer));

        return $customer;
    }

    /**
     * Looks up the customer based on the $uuid and $accountName config data. Can update their email, custom fields,
     * or created at time.
     *
     * To delete a customer attribute value in Customer.IO you must pass it as a null value in the array.
     *
     * @param $uuid
     * @param $accountName
     * @param array $customAttributes
     * @param null $email
     * @param integer|null $userId
     * @param integer|null $createdAtTimestamp
     * @return mixed
     * @throws Exception
     */
    public function updateCustomer(
        $uuid,
        $accountName,
        $customAttributes = [],
        $email = null,
        $userId = null,
        $createdAtTimestamp = null
    ) {
        $accountConfigData = $this->getAccountConfigData($accountName);

        /**
         * @var $customer Customer
         */
        $customer =
            Customer::query()
                ->where([
                        'uuid' => $uuid,
                        'workspace_name' => $accountConfigData['workspace_name'],
                        'workspace_id' => $accountConfigData['workspace_id'],
                        'site_id' => $accountConfigData['site_id'],
                    ])
                ->firstOrFail();

        $oldCustomer = clone $customer;

        if (!empty($email)) {
            $customer->email = $email;
        }

        if (!empty($userId)) {
            $customer->user_id = $userId;
        }

        if (!empty($createdAtTimestamp)) {
            $customer->setCreatedAt(Carbon::createFromTimestamp($createdAtTimestamp));
        }

        $customer->setUpdatedAt(Carbon::now());

        // save to the database
        $customer->saveOrFail();

        // set the user id custom attribute if its not empty
        if (!empty($userId)) {
            $customAttributes[$this->userIdCustomFieldName] = $userId;
        }

        // sync to customer.io using their API
        $this->customerIoApiGateway->addOrUpdateCustomer(
            $accountConfigData['site_id'],
            $accountConfigData['track_api_key'],
            $customer->uuid,
            $customer->email,
            $customAttributes,
            $createdAtTimestamp
        );

        event(new CustomerUpdated($oldCustomer, $customer));

        return $customer;
    }

    /**
     * Looks up the customer based on the $email and $accountName config data. If none exists, this creates a new one,
     * otherwise it updates the existing customer in the database and via the API.
     *
     * @param $lookupEmail
     * @param $accountName
     * @param array $customAttributes
     * @param integer|null $userId
     * @param integer|null $createdAtTimestamp
     * @return mixed
     * @throws Exception
     * @throws Throwable
     */
    public function createOrUpdateCustomerByEmail(
        $lookupEmail,
        $accountName,
        $customAttributes = [],
        $userId = null,
        $createdAtTimestamp = null
    ) {
        $accountConfigData = $this->getAccountConfigData($accountName);

        /**
         * @var $customer Customer
         */
        $customer =
            Customer::query()
                ->where([
                        'email' => $lookupEmail,
                        'workspace_name' => $accountConfigData['workspace_name'],
                        'workspace_id' => $accountConfigData['workspace_id'],
                        'site_id' => $accountConfigData['site_id'],
                    ])
                ->first();

        if (empty($customer)) {
            $customer = $this->createCustomer(
                $lookupEmail,
                $accountName,
                $customAttributes,
                null,
                $userId,
                $createdAtTimestamp
            );
        } else {
            $customer = $this->updateCustomer(
                $customer->uuid,
                $accountName,
                $customAttributes,
                null,
                $userId,
                $createdAtTimestamp
            );
        }

        return $customer;
    }

    /**
     * Looks up the customer based on the user id and $accountName config data. If none exists, this creates a new one,
     * otherwise it updates the existing customer in the database and via the API.
     *
     * If a new email is passed it will be updated in customer.io via the api
     *
     * @param integer|null $userId
     * @param $accountName
     * @param string $userEmail
     * @param array $customAttributes
     * @param integer|null $createdAtTimestamp
     * @return mixed
     * @throws Exception
     * @throws Throwable
     */
    public function createOrUpdateCustomerByUserId(
        $userId,
        $accountName,
        $userEmail = null,
        $customAttributes = [],
        $createdAtTimestamp = null
    ) {
        $accountConfigData = $this->getAccountConfigData($accountName);

        /**
         * @var $customer Customer
         */
        $customer =
            Customer::query()
                ->where([
                        'user_id' => $userId,
                        'workspace_name' => $accountConfigData['workspace_name'],
                        'workspace_id' => $accountConfigData['workspace_id'],
                        'site_id' => $accountConfigData['site_id'],
                    ])
                ->first();

        if (empty($customer)) {
            $customer = $this->createCustomer(
                $userEmail,
                $accountName,
                $customAttributes,
                null,
                $userId,
                $createdAtTimestamp
            );
        } else {
            $customer = $this->updateCustomer(
                $customer->uuid,
                $accountName,
                $customAttributes,
                $userEmail,
                $userId,
                $createdAtTimestamp
            );
        }

        return $customer;
    }

    /**
     * Deletes the customer based on the $uuid and $accountName config data.
     *
     * @param $uuid
     * @param $accountName
     * @param array $customAttributes
     * @param null $email
     * @param integer|null $createdAtTimestamp
     * @return mixed
     * @throws Exception
     */
    public function deleteCustomer($uuid, $accountName)
    {
        $accountConfigData = $this->getAccountConfigData($accountName);

        $customer =
            Customer::query()
                ->where([
                        'uuid' => $uuid,
                        'workspace_name' => $accountConfigData['workspace_name'],
                        'workspace_id' => $accountConfigData['workspace_id'],
                        'site_id' => $accountConfigData['site_id'],
                    ])
                ->firstOrFail();

        // save to the database
        $customer->delete();

        // delete customer.io using their API
        // todo: delete

        return $customer;
    }

    /**
     * @param $email
     * @param $formNameToProcess
     * @param $requestParams
     * @return array
     * @throws Throwable
     */
    public function processForm($email, $formNameToProcess, $requestParams)
    {
        $allConfiguredForms = config('customer-io.forms', []);

        $customers = [];

        foreach ($allConfiguredForms as $formName => $formConfig) {
            if ($formName === $formNameToProcess) {
                foreach ($formConfig['accounts_to_sync'] as $accountName) {
                    $accountConfigData = $this->getAccountConfigData($accountName);

                    /**
                     * @var $customer Customer
                     */
                    $customer =
                        Customer::query()
                            ->where([
                                    'email' => $email,
                                    'workspace_name' => $accountConfigData['workspace_name'],
                                    'workspace_id' => $accountConfigData['workspace_id'],
                                    'site_id' => $accountConfigData['site_id'],
                                ])
                            ->first();

                    if (empty($customer)) {
                        $customer = $this->createCustomer($email, $accountName, $formConfig['custom_attributes']);
                    } else {
                        $customer = $this->updateCustomer(
                            $customer->uuid,
                            $accountName,
                            $formConfig['custom_attributes']
                        );
                    }

                    sleep(1);

                    foreach ($formConfig['events'] as $eventName) {
                        $eventData = [];
                        foreach (config('customer-io.forms_events_UTM_parameters',[]) as $param => $dataKey) {
                                $eventData[$dataKey] = $requestParams[$param] ?? null;
                        }

                        $this->createEvent($customer->uuid, $accountName, $eventName, array_filter($eventData));
                    }

                    $customers[] = $customer;
                }
            }
        }

        if (!empty($customers)) {
            return $customers;
        }

        throw new Exception('Failed to process form: ' . $formNameToProcess . ' for email address: ' . $email);
    }

    /**
     * @param $uuid
     * @param $accountName
     * @param $eventName
     * @param array $eventData
     * @param null $eventType
     * @param null $createdAtTimestamp
     * @return bool
     * @throws Exception
     */
    public function createEvent(
        $uuid,
        $accountName,
        $eventName,
        $eventData = [],
        $eventType = null,
        $createdAtTimestamp = null
    ) {
        $accountConfigData = $this->getAccountConfigData($accountName);

        $this->customerIoApiGateway->createEvent(
            $accountConfigData['site_id'],
            $accountConfigData['track_api_key'],
            $uuid,
            $eventName,
            $eventData,
            $eventType,
            $createdAtTimestamp
        );

        return true;
    }

    /**
     * @param string|null $email
     * @param string|null $uuid
     * @param string $accountName
     * @param string $eventName
     * @param null $eventType
     * @param null $createdAtTimestamp
     * @return Customer
     * @throws Exception
     */
    public function createEventForEmailOrId(
        $email,
        $uuid,
        $accountName,
        $eventName,
        $eventType = null,
        $createdAtTimestamp = null
    ) {
        $accountConfigData = $this->getAccountConfigData($accountName);

        if (!empty($uuid)) {
            /**
             * @var $customer Customer
             */
            $customer =
                Customer::query()
                    ->where([
                            'uuid' => $uuid,
                            'workspace_name' => $accountConfigData['workspace_name'],
                            'workspace_id' => $accountConfigData['workspace_id'],
                            'site_id' => $accountConfigData['site_id'],
                        ])
                    ->first();
        } else {
            /**
             * @var $customer Customer
             */
            $customer =
                Customer::query()
                    ->where([
                            'email' => $email,
                            'workspace_name' => $accountConfigData['workspace_name'],
                            'workspace_id' => $accountConfigData['workspace_id'],
                            'site_id' => $accountConfigData['site_id'],
                        ])
                    ->first();

            if (empty($customer)) {
                $customer = $this->createCustomer($email, $accountName, []);

                sleep(5);
            }
        }

        if (!empty($customer)) {
            $this->customerIoApiGateway->createEvent(
                $accountConfigData['site_id'],
                $accountConfigData['track_api_key'],
                $customer->uuid,
                $eventName,
                $eventType,
                $createdAtTimestamp
            );

            return $customer;
        }

        throw new Exception(
            'Customer not found when trying to trigger event. Args: ' . var_export(func_get_args(), true)
        );
    }

    /**
     * @param integer $userId
     * @param string $accountName
     * @param string $eventName
     * @param array $eventData
     * @param string|null $eventType
     * @param integer|null $createdAtTimestamp
     * @return Customer
     * @throws Exception
     */
    public function createEventForUserId(
        $userId,
        $accountName,
        $eventName,
        $eventData = [],
        $eventType = null,
        $createdAtTimestamp = null
    ) {
        $accountConfigData = $this->getAccountConfigData($accountName);

        /**
         * @var $customer Customer
         */
        $customer =
            Customer::query()
                ->where([
                        'user_id' => $userId,
                        'workspace_name' => $accountConfigData['workspace_name'],
                        'workspace_id' => $accountConfigData['workspace_id'],
                        'site_id' => $accountConfigData['site_id'],
                    ])
                ->first();

        if (!empty($customer)) {
            $this->customerIoApiGateway->createEvent(
                $accountConfigData['site_id'],
                $accountConfigData['track_api_key'],
                $customer->uuid,
                $eventName,
                $eventData,
                $eventType,
                $createdAtTimestamp
            );

            return $customer;
        }

        throw new Exception(
            'Customer not found when trying to trigger event with user id. Args: ' . var_export(func_get_args(), true)
        );
    }

    /**
     * @param string $uuid
     * @param string $accountName
     * @param string $eventName
     * @param $customerIoTransactionalMessageId
     * @param $customerEmail
     * @param $customerId
     * @param null $eventType
     * @param null $createdAtTimestamp
     * @return bool
     * @throws Exception
     */
    public function sendTransactionalEmail(
        $accountName,
        $customerIoTransactionalMessageId,
        $customerEmail,
        $messageDataArray = []
    ) {
        $accountConfigData = $this->getAccountConfigData($accountName);

        /**
         * @var $customer Customer
         */
        $customer =
            Customer::query()
                ->where([
                        'email' => $customerEmail,
                        'workspace_name' => $accountConfigData['workspace_name'],
                        'workspace_id' => $accountConfigData['workspace_id'],
                        'site_id' => $accountConfigData['site_id'],
                    ])
                ->first();

        if (empty($customer)) {
            $customer = $this->createCustomer($customerEmail, $accountName);

            // we must sleep because there is a delay until when the customer can be used in the API after its created
            sleep(5);
        }

        $this->customerIoApiGateway->sendTransactionalEmail(
            $accountConfigData['app_api_key'],
            $customerIoTransactionalMessageId,
            $customerEmail,
            $customer->uuid,
            $messageDataArray
        );

        return true;
    }

    /**
     * @param $accountName
     * @return array
     * @throws Exception
     */
    public function getAccountConfigData($accountName)
    {
        $accountConfig = config('customer-io.accounts')[$accountName] ?? [];

        if (empty($accountConfig) ||
            empty($accountConfig['workspace_name']) ||
            empty($accountConfig['workspace_id']) ||
            empty($accountConfig['site_id'])) {
            // incorrect config, error
            throw new Exception(
                'Failed to connect to customer.io account, no config exists for account name: ' . $accountName
            );
        }

        return $accountConfig;
    }
}
