<?php

namespace Railroad\CustomerIo\Tests\Functional;

use Carbon\Carbon;
use Railroad\CustomerIo\Events\CustomerCreated;
use Railroad\CustomerIo\Models\Customer;
use Railroad\CustomerIo\Services\CustomerIoService;
use Railroad\CustomerIo\Tests\CustomerIoTestCase;

class CustomerIoServiceTest extends CustomerIoTestCase
{
    /**
     * @var CustomerIoService
     */
    private $customerIoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerIoService = app()->make(CustomerIoService::class);
    }

    public function test_get_customer_by_id()
    {
        $email = $this->faker->email;
        $accountName = 'musora';
        $accountConfigData = $this->customerIoService->getAccountConfigData($accountName);

        $this->expectsEvents([CustomerCreated::class]);

        $createdCustomer = $this->customerIoService->createCustomer(
            $email,
            $accountName
        );

        // for some reason the fetch API needs some time to update otherwise we always get 404
        sleep(2);

        $fetchedCustomer = $this->customerIoService->getCustomerById($accountName, $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->uuid, $createdCustomer->uuid);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['id'], $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->email, $email);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['email'], $email);

        $this->assertEquals($fetchedCustomer->workspace_name, $accountConfigData['workspace_name']);
        $this->assertEquals($fetchedCustomer->workspace_id, $accountConfigData['workspace_id']);
        $this->assertEquals($fetchedCustomer->site_id, $accountConfigData['site_id']);

        $this->assertEquals($fetchedCustomer->created_at, Carbon::now()->toDateTimeString());
        $this->assertEquals($fetchedCustomer->updated_at, Carbon::now()->toDateTimeString());
        $this->assertEquals($fetchedCustomer->deleted_at, null);
    }

    public function test_get_customer_by_id_not_found_in_database()
    {
        $email = $this->faker->email;
        $accountName = 'musora';
        $accountConfigData = $this->customerIoService->getAccountConfigData($accountName);

        $this->expectExceptionMessage('No query results for model [Railroad\CustomerIo\Models\Customer]');

        $fetchedCustomer = $this->customerIoService->getCustomerById($accountName, rand().'_404');
    }

    public function test_get_customer_by_id_found_in_database_but_not_from_api()
    {
        $email = $this->faker->email;
        $accountName = 'musora';
        $accountConfigData = $this->customerIoService->getAccountConfigData($accountName);

        $customer = new Customer();
        $customer->generateUUID();
        $customer->email = $this->faker->email;
        $customer->workspace_name = $accountConfigData['workspace_name'];
        $customer->workspace_id = $accountConfigData['workspace_id'];
        $customer->site_id = $accountConfigData['site_id'];

        $customer->save();

        $this->expectExceptionCode(404);

        $fetchedCustomer = $this->customerIoService->getCustomerById($accountName, $customer->uuid);
    }

    public function test_create_customer_without_attributes_or_existing_id_or_created_at()
    {
        $email = $this->faker->email;
        $accountName = 'musora';
        $accountConfigData = $this->customerIoService->getAccountConfigData($accountName);

        $this->expectsEvents([CustomerCreated::class]);

        $createdCustomer = $this->customerIoService->createCustomer(
            $email,
            $accountName
        );

        $data = [
            'email' => $email,
            'workspace_name' => $accountConfigData['workspace_name'],
            'workspace_id' => $accountConfigData['workspace_id'],
            'site_id' => $accountConfigData['site_id'],
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
            'deleted_at' => null,
        ];

        $this->assertDatabaseHas('customer_io_customers', $data);

        $this->assertNotEmpty(Customer::query()->find(1)->uuid);

        // for some reason the fetch API needs some time to update otherwise we always get 404
        sleep(2);

        $fetchedCustomer = $this->customerIoService->getCustomerById($accountName, $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->uuid, $createdCustomer->uuid);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['id'], $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->email, $email);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['email'], $email);

        $this->assertEquals($fetchedCustomer->workspace_name, $accountConfigData['workspace_name']);
        $this->assertEquals($fetchedCustomer->workspace_id, $accountConfigData['workspace_id']);
        $this->assertEquals($fetchedCustomer->site_id, $accountConfigData['site_id']);

        $this->assertEquals($fetchedCustomer->created_at, Carbon::now()->toDateTimeString());
        $this->assertEquals($fetchedCustomer->updated_at, Carbon::now()->toDateTimeString());
        $this->assertEquals($fetchedCustomer->deleted_at, null);
    }

    public function test_create_customer_with_attributes_and_created_at()
    {
        $email = $this->faker->email;
        $accountName = 'musora';
        $accountConfigData = $this->customerIoService->getAccountConfigData($accountName);
        $createdAt = Carbon::now()->subDays(1)->timestamp;

        $customAttributes = [
            'my_string_1' => $this->faker->text(),
            'my_bool_1' => true,
            'my_bool_2' => false,
            'my_integer_1' => 5,
            'my_integer_2' => 5937653,
            'my_timestamp_1' => Carbon::now()->subDays(100)->timestamp,
            'my_timestamp_2' => Carbon::now()->addDays(100)->timestamp,
        ];

        $this->expectsEvents([CustomerCreated::class]);

        $createdCustomer = $this->customerIoService->createCustomer(
            $email,
            $accountName,
            $customAttributes,
            null,
            $createdAt
        );

        $data = [
            'uuid' => $createdCustomer->uuid,
            'email' => $email,
            'workspace_name' => $accountConfigData['workspace_name'],
            'workspace_id' => $accountConfigData['workspace_id'],
            'site_id' => $accountConfigData['site_id'],
            'created_at' => Carbon::createFromTimestamp($createdAt)->toDateTimeString(),
            'updated_at' => Carbon::createFromTimestamp($createdAt)->toDateTimeString(),
            'deleted_at' => null,
        ];

        $this->assertDatabaseHas('customer_io_customers', $data);

        $this->assertNotEmpty(Customer::query()->find(1)->uuid);

        // for some reason the fetch API needs some time to update otherwise we always get 404
        sleep(2);

        $data = array_merge($data, $customAttributes);

        $fetchedCustomer = $this->customerIoService->getCustomerById($accountName, $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->uuid, $createdCustomer->uuid);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['id'], $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->email, $email);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['email'], $email);

        $this->assertEquals($fetchedCustomer->getExternalAttributes()['created_at'], $createdAt);

        $this->assertEquals($fetchedCustomer->workspace_name, $accountConfigData['workspace_name']);
        $this->assertEquals($fetchedCustomer->workspace_id, $accountConfigData['workspace_id']);
        $this->assertEquals($fetchedCustomer->site_id, $accountConfigData['site_id']);

        $this->assertEquals($fetchedCustomer->created_at, Carbon::createFromTimestamp($createdAt)->toDateTimeString());
        $this->assertEquals($fetchedCustomer->updated_at, Carbon::createFromTimestamp($createdAt)->toDateTimeString());
        $this->assertEquals($fetchedCustomer->deleted_at, null);

        foreach ($customAttributes as $customAttributeName => $customAttributeValue) {
            $this->assertEquals(
                $data[$customAttributeName],
                $fetchedCustomer->getExternalAttributes()[$customAttributeName]
            );
        }
    }

    public function test_process_form()
    {
        $email = $this->faker->email;
        $formName = 'Example Form Name';
        $accountConfigData = $this->customerIoService->getAccountConfigData('musora');

        $customers = $this->customerIoService->processForm($email, $formName);
        $createdCustomer = $customers[0];

        $data = [
            'uuid' => $createdCustomer->uuid,
            'email' => $email,
            'workspace_name' => $accountConfigData['workspace_name'],
            'workspace_id' => $accountConfigData['workspace_id'],
            'site_id' => $accountConfigData['site_id'],
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
            'deleted_at' => null,
        ];

        $this->assertDatabaseHas('customer_io_customers', $data);

        $this->assertNotEmpty(Customer::query()->find(1)->uuid);

        sleep(2);

        $fetchedCustomer = $this->customerIoService->getCustomerById('musora', $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->uuid, $createdCustomer->uuid);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['id'], $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->email, $email);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['email'], $email);

        $this->assertEquals($fetchedCustomer->workspace_name, $accountConfigData['workspace_name']);
        $this->assertEquals($fetchedCustomer->workspace_id, $accountConfigData['workspace_id']);
        $this->assertEquals($fetchedCustomer->site_id, $accountConfigData['site_id']);

        $this->assertEquals($fetchedCustomer->created_at, Carbon::now()->toDateTimeString());
        $this->assertEquals($fetchedCustomer->updated_at, Carbon::now()->toDateTimeString());
        $this->assertEquals($fetchedCustomer->deleted_at, null);

        $this->assertEquals(
            'my attribute value 1',
            $fetchedCustomer->getExternalAttributes()['attribute_to_sync_1']
        );

        $this->assertEquals(
            'my attribute value 2',
            $fetchedCustomer->getExternalAttributes()['attribute_to_sync_2']
        );
    }
}
