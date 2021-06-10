<?php

namespace Railroad\CustomerIo\Events;

use Railroad\CustomerIo\Models\Customer;

class CustomerUpdated
{
    /**
     * @var Customer
     */
    public $oldCustomer;

    /**
     * @var Customer
     */
    public $newCustomer;

    /**
     * CustomerUpdated constructor.
     *
     * @param  Customer  $oldCustomer
     * @param  Customer  $newCustomer
     */
    public function __construct(Customer $oldCustomer, Customer $newCustomer)
    {
        $this->oldCustomer = $oldCustomer;
        $this->newCustomer = $newCustomer;
    }
}