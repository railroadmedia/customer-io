<?php

namespace Railroad\CustomerIo\Jobs;

use Exception;
use Railroad\CustomerIo\Services\CustomerIoService;

class CustomerIoSyncUserByEmail extends CustomerIoBaseJob
{

    /**
     * @var string
     */
    private $lookupEmail;

    /**
     * @var string
     */
    private $accountName;

    /**
     * @var array
     */
    private $customAttributes;

    /**
     * @var integer|null
     */
    private $userId;

    /**
     * @var integer|null
     */
    private $createdAtTimestamp;

    public function __construct(
        $lookupEmail,
        $accountName,
        $customAttributes = [],
        $userId = null,
        $createdAtTimestamp = null
    ) {
        $this->lookupEmail = $lookupEmail;
        $this->accountName = $accountName;
        $this->customAttributes = $customAttributes;
        $this->userId = $userId;
        $this->createdAtTimestamp = $createdAtTimestamp;
    }

    public function handle(CustomerIoService $customerIoService)
    {
        try {
            $customerIoService->createOrUpdateCustomerByEmail(
                $this->lookupEmail,
                $this->accountName,
                $this->customAttributes,
                $this->userId,
                $this->createdAtTimestamp
            );
        } catch (Exception $exception) {
            $this->failed($exception);
        }
    }

    /**
     * The job failed to process.
     *
     * @param  Exception  $exception
     */
    public function failed(Exception $exception)
    {
        error_log(
            'Error on SyncUserByEmail job trying to sync user to customer.io. User ID: '.
            $this->userId.' - Attributes: '.print_r(
                $this->customAttributes,
                true
            ).' - lookupEmail: '.$this->lookupEmail
        );

        parent::failed($exception);
    }
}