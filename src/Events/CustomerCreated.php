<?php

namespace Railroad\CustomerIo\Events;

use Railroad\CustomerIo\Models\Customer;

class CustomerCreated
{
    /**
     * @var Customer
     */
    public $customer;

    /**
     * CustomerCreated constructor.
     * @param  Customer  $customer
     */
    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
    }
}