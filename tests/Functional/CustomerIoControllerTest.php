<?php

namespace Railroad\CustomerIo\Tests\Functional;

use Carbon\Carbon;
use Railroad\CustomerIo\Events\CustomerCreated;
use Railroad\CustomerIo\Models\Customer;
use Railroad\CustomerIo\Services\CustomerIoService;
use Railroad\CustomerIo\Tests\CustomerIoTestCase;

class CustomerIoControllerTest extends CustomerIoTestCase
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

    public function test_submit_email_form_failed_validation()
    {
        $response = $this->post('customer-io/submit-email-form');

        $this->assertEquals("The email field is required.", session()->get('errors')->get('email')[0]);
        $this->assertEquals("The form name field is required.", session()->get('errors')->get('form_name')[0]);
    }

    public function test_submit_email_form_success_form_redirect()
    {
        $email = $this->faker->email;
        $formName = 'Example Form Name';
        $redirectUrl = $this->faker->url;

        $response = $this->post('customer-io/submit-email-form', ['email' => $email, 'form_name' => $formName, 'success_redirect' => $redirectUrl]);

        $this->assertEquals(302, $response->getStatusCode());
        $response->assertRedirect($redirectUrl);
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
        sleep(3);

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
        sleep(3);

        $data = array_merge($data, $customAttributes);

        $fetchedCustomer = $this->customerIoService->getCustomerById($accountName, $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->uuid, $createdCustomer->uuid);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['id'], $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->email, $email);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['email'], $email);

        $this->assertEquals($fetchedCustomer->user_id, null);

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

    public function test_create_customer_with_user_id_and_attributes_and_created_at()
    {
        $email = $this->faker->email;
        $accountName = 'musora';
        $accountConfigData = $this->customerIoService->getAccountConfigData($accountName);
        $userId = rand();
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
            $userId,
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

        $this->assertEquals($fetchedCustomer->user_id, $userId);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['user_id'], $userId);

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
}
