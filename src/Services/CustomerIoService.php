<?php

namespace Railroad\CustomerIo\Services;

use Carbon\Carbon;
use Exception;
use Railroad\CustomerIo\Models\Customer;
use Throwable;

class CustomerIoService
{
    /**
     * If no ID is passed, one will be generated automatically.
     * If no $createdAtTimestamp is passed it will use the current time.
     *
     * @param $email
     * @param $accountName
     * @param  array  $customAttributes
     * @param  string|null  $id
     * @param  integer|null  $createdAtTimestamp
     * @throws Exception
     * @throws Throwable
     */
    public function createCustomer(
        $email,
        $accountName,
        $customAttributes = [],
        $id = null,
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

        if (!empty($createdAtTimestamp)) {
            $customer->setCreatedAt(Carbon::createFromTimestamp($createdAtTimestamp));
            $customer->setUpdatedAt(Carbon::createFromTimestamp($createdAtTimestamp));
        }

        // save to the database
        $customer->saveOrFail();

        // sync to customer.io using their API
        // todo: sync via api, $customAttributes
        // delete from database if sync failed?

        return $customer;
    }

    /**
     * @param $accountName
     * @return array
     * @throws Exception
     */
    private function getAccountConfigData($accountName)
    {
        $accountConfig = config('customer-io.accounts')[$accountName] ?? [];

        if (empty($accountConfig) ||
            empty($accountConfig['workspace_name']) ||
            empty($accountConfig['workspace_id']) ||
            empty($accountConfig['site_id'])) {
            // incorrect config, error
            throw new Exception('Failed to create customer, no config exists for account name: '.$accountName);
        }

        return $accountConfig;
    }

    /**
     * Looks up the customer based on the $uuid and $accountName config data. Can update their email, custom fields,
     * or created at time.
     *
     * To delete a customer attribute value in Customer.IO you must pass it as a null value in the array.
     *
     * @param $uuid
     * @param $accountName
     * @param  array  $customAttributes
     * @param  null  $email
     * @param  integer|null  $createdAtTimestamp
     * @return mixed
     * @throws Exception
     */
    public function updateCustomer(
        $uuid,
        $accountName,
        $customAttributes = [],
        $email = null,
        $createdAtTimestamp = null
    ) {
        $accountConfigData = $this->getAccountConfigData($accountName);

        $customer = Customer::query()->where(
            [
                'uuid' => $uuid,
                'workspace_name' => $accountConfigData['workspace_name'],
                'workspace_id' => $accountConfigData['workspace_id'],
                'site_id' => $accountConfigData['site_id'],
            ]
        )->firstOrFail();

        if (!empty($email)) {
            $customer->email = $email;
        }

        if (!empty($createdAtTimestamp)) {
            $customer->setCreatedAt(Carbon::createFromTimestamp($createdAtTimestamp));
        }

        // save to the database
        $customer->saveOrFail();

        // sync to customer.io using their API
        // todo: sync via api, $customAttributes
        // delete from database if sync failed?

        return $customer;
    }

    /**
     * Looks up the customer based on the $email and $accountName config data. Can update their email, custom fields,
     * or created at time.
     *
     * @param $lookupEmail
     * @param $accountName
     * @param  array  $customAttributes
     * @param  null  $newEmail
     * @param  integer|null  $createdAtTimestamp
     * @return mixed
     * @throws Exception
     */
    public function createOrUpdateCustomerByEmail(
        $lookupEmail,
        $accountName,
        $customAttributes = [],
        $newEmail = null,
        $createdAtTimestamp = null
    ) {
        $accountConfigData = $this->getAccountConfigData($accountName);

        $customer = Customer::query()->where(
            [
                'email' => $lookupEmail,
                'workspace_name' => $accountConfigData['workspace_name'],
                'workspace_id' => $accountConfigData['workspace_id'],
                'site_id' => $accountConfigData['site_id'],
            ]
        )->firstOrFail();

        if (!empty($email)) {
            $customer->email = $email;
        }

        if (!empty($createdAtTimestamp)) {
            $customer->setCreatedAt(Carbon::createFromTimestamp($createdAtTimestamp));
        }

        // save to the database
        $customer->saveOrFail();

        // sync to customer.io using their API
        // todo: sync via api, $customAttributes
        // delete from database if sync failed?

        return $customer;
    }

    /**
     * Deletes the customer based on the $uuid and $accountName config data.
     *
     * @param $uuid
     * @param $accountName
     * @param  array  $customAttributes
     * @param  null  $email
     * @param  integer|null  $createdAtTimestamp
     * @return mixed
     * @throws Exception
     */
    public function deleteCustomer($uuid, $accountName)
    {
        $accountConfigData = $this->getAccountConfigData($accountName);

        $customer = Customer::query()->where(
            [
                'uuid' => $uuid,
                'workspace_name' => $accountConfigData['workspace_name'],
                'workspace_id' => $accountConfigData['workspace_id'],
                'site_id' => $accountConfigData['site_id'],
            ]
        )->firstOrFail();

        // save to the database
        $customer->delete();

        // delete customer.io using their API
        // todo: delete

        return $customer;
    }

    public function processForm($email, $formName)
    {
        $allConfiguredForms = config('customer-io.forms', []);

        foreach ($allConfiguredForms as $formName => $formConfig) {
            if ($formName === $allConfiguredForms) {
                foreach ($formConfig['accounts_to_sync'] as $accountName) {
                    $accountConfigData = $this->getAccountConfigData($accountName);

                    /**
                     * @var $customer Customer
                     */
                    $customer = Customer::query()
                        ->where(
                            [
                                'email' => $email,
                                'workspace_name' => $accountConfigData['workspace_name'],
                                'workspace_id' => $accountConfigData['workspace_id'],
                                'site_id' => $accountConfigData['site_id'],
                            ]
                        )
                        ->first();

                    if (empty($customer)) {
                        $customer = $this->createCustomer($email, $accountName, $formConfig['custom_attributes']);
                    } else {
                        $this->updateCustomer($customer->uuid, $accountName, $formConfig['custom_attributes']);
                    }
                }
            }
        }
    }
}