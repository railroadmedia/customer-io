<?php

namespace Railroad\CustomerIo\Tests\Functional;

use Carbon\Carbon;
use Exception;
use Railroad\CustomerIo\ApiGateways\CustomerIoApiGateway;
use Railroad\CustomerIo\Events\CustomerCreated;
use Railroad\CustomerIo\Events\CustomerUpdated;
use Railroad\CustomerIo\Models\Customer;
use Railroad\CustomerIo\Services\CustomerIoService;
use Railroad\CustomerIo\Tests\CustomerIoTestCase;

class CustomerIoServiceTest extends CustomerIoTestCase
{
    /**
     * @var CustomerIoService
     */
    private $customerIoService;

    /**
     * @var CustomerIoApiGateway
     */
    private $customerIoApiGateway;

    protected function setUp()
    : void
    {
        parent::setUp();

        $this->customerIoService = app()->make(CustomerIoService::class);
        $this->customerIoApiGateway = app()->make(CustomerIoApiGateway::class);
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
        sleep(4);

        $fetchedCustomer = $this->customerIoService->getCustomerById($accountName, $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->uuid, $createdCustomer->uuid);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['id'], $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->email, $email);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['email'], $email);

        $this->assertEquals($fetchedCustomer->workspace_name, $accountConfigData['workspace_name']);
        $this->assertEquals($fetchedCustomer->workspace_id, $accountConfigData['workspace_id']);
        $this->assertEquals($fetchedCustomer->site_id, $accountConfigData['site_id']);

        $this->assertEquals(
            $fetchedCustomer->created_at,
            Carbon::now()
                ->toDateTimeString()
        );
        $this->assertEquals(
            $fetchedCustomer->updated_at,
            Carbon::now()
                ->toDateTimeString()
        );
        $this->assertEquals($fetchedCustomer->deleted_at, null);
    }

    public function test_get_customer_by_user_id()
    {
        $email = $this->faker->email;
        $userId = rand();
        $accountName = 'musora';
        $accountConfigData = $this->customerIoService->getAccountConfigData($accountName);

        $this->expectsEvents([CustomerCreated::class]);

        $createdCustomer = $this->customerIoService->createCustomer(
            $email,
            $accountName,
            [],
            null,
            $userId
        );

        // for some reason the fetch API needs some time to update otherwise we always get 404
        sleep(4);

        $fetchedCustomer = $this->customerIoService->getCustomerByUserId($accountName, $userId);

        $this->assertEquals($fetchedCustomer->uuid, $createdCustomer->uuid);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['id'], $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->email, $email);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['email'], $email);

        $this->assertEquals($fetchedCustomer->workspace_name, $accountConfigData['workspace_name']);
        $this->assertEquals($fetchedCustomer->workspace_id, $accountConfigData['workspace_id']);
        $this->assertEquals($fetchedCustomer->site_id, $accountConfigData['site_id']);

        $this->assertEquals(
            $fetchedCustomer->created_at,
            Carbon::now()
                ->toDateTimeString()
        );
        $this->assertEquals(
            $fetchedCustomer->updated_at,
            Carbon::now()
                ->toDateTimeString()
        );
        $this->assertEquals($fetchedCustomer->deleted_at, null);
    }

    public function test_get_customer_by_id_not_found_in_database()
    {
        $email = $this->faker->email;
        $accountName = 'musora';
        $accountConfigData = $this->customerIoService->getAccountConfigData($accountName);

        $this->expectExceptionMessage('No query results for model [Railroad\CustomerIo\Models\Customer]');

        $fetchedCustomer = $this->customerIoService->getCustomerById($accountName, rand() . '_404');
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
            'created_at' => Carbon::now()
                ->toDateTimeString(),
            'updated_at' => Carbon::now()
                ->toDateTimeString(),
            'deleted_at' => null,
        ];

        $this->assertDatabaseHas('customer_io_customers', $data);

        $this->assertNotEmpty(
            Customer::query()
                ->find(1)->uuid
        );

        // for some reason the fetch API needs some time to update otherwise we always get 404
        sleep(4);

        $fetchedCustomer = $this->customerIoService->getCustomerById($accountName, $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->uuid, $createdCustomer->uuid);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['id'], $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->email, $email);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['email'], $email);

        $this->assertEquals($fetchedCustomer->workspace_name, $accountConfigData['workspace_name']);
        $this->assertEquals($fetchedCustomer->workspace_id, $accountConfigData['workspace_id']);
        $this->assertEquals($fetchedCustomer->site_id, $accountConfigData['site_id']);

        $this->assertEquals(
            $fetchedCustomer->created_at,
            Carbon::now()
                ->toDateTimeString()
        );
        $this->assertEquals(
            $fetchedCustomer->updated_at,
            Carbon::now()
                ->toDateTimeString()
        );
        $this->assertEquals($fetchedCustomer->deleted_at, null);
    }

    public function test_create_customer_with_attributes_and_created_at()
    {
        $email = $this->faker->email;
        $accountName = 'musora';
        $accountConfigData = $this->customerIoService->getAccountConfigData($accountName);
        $createdAt =
            Carbon::now()
                ->subDays(1)->timestamp;

        $customAttributes = [
            'my_string_1' => $this->faker->text(),
            'my_bool_1' => true,
            'my_bool_2' => false,
            'my_integer_1' => 5,
            'my_integer_2' => 5937653,
            'my_timestamp_1' => Carbon::now()
                ->subDays(100)->timestamp,
            'my_timestamp_2' => Carbon::now()
                ->addDays(100)->timestamp,
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
            'created_at' => Carbon::createFromTimestamp($createdAt)
                ->toDateTimeString(),
            'updated_at' => Carbon::createFromTimestamp($createdAt)
                ->toDateTimeString(),
            'deleted_at' => null,
        ];

        $this->assertDatabaseHas('customer_io_customers', $data);

        $this->assertNotEmpty(
            Customer::query()
                ->find(1)->uuid
        );

        // for some reason the fetch API needs some time to update otherwise we always get 404
        sleep(4);

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

        $this->assertEquals(
            $fetchedCustomer->created_at,
            Carbon::createFromTimestamp($createdAt)
                ->toDateTimeString()
        );
        $this->assertEquals(
            $fetchedCustomer->updated_at,
            Carbon::createFromTimestamp($createdAt)
                ->toDateTimeString()
        );
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
        $createdAt =
            Carbon::now()
                ->subDays(1)->timestamp;

        $customAttributes = [
            'my_string_1' => $this->faker->text(),
            'my_bool_1' => true,
            'my_bool_2' => false,
            'my_integer_1' => 5,
            'my_integer_2' => 5937653,
            'my_timestamp_1' => Carbon::now()
                ->subDays(100)->timestamp,
            'my_timestamp_2' => Carbon::now()
                ->addDays(100)->timestamp,
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
            'created_at' => Carbon::createFromTimestamp($createdAt)
                ->toDateTimeString(),
            'updated_at' => Carbon::createFromTimestamp($createdAt)
                ->toDateTimeString(),
            'deleted_at' => null,
        ];

        $this->assertDatabaseHas('customer_io_customers', $data);

        $this->assertNotEmpty(
            Customer::query()
                ->find(1)->uuid
        );

        // for some reason the fetch API needs some time to update otherwise we always get 404
        sleep(4);

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

        $this->assertEquals(
            $fetchedCustomer->created_at,
            Carbon::createFromTimestamp($createdAt)
                ->toDateTimeString()
        );
        $this->assertEquals(
            $fetchedCustomer->updated_at,
            Carbon::createFromTimestamp($createdAt)
                ->toDateTimeString()
        );
        $this->assertEquals($fetchedCustomer->deleted_at, null);

        foreach ($customAttributes as $customAttributeName => $customAttributeValue) {
            $this->assertEquals(
                $data[$customAttributeName],
                $fetchedCustomer->getExternalAttributes()[$customAttributeName]
            );
        }
    }

    public function test_create_or_update_customer_create()
    {
        $email = $this->faker->email;
        $accountName = 'musora';
        $accountConfigData = $this->customerIoService->getAccountConfigData($accountName);
        $userId = rand();
        $createdAt =
            Carbon::now()
                ->subDays(1)->timestamp;

        $customAttributes = [
            'my_string_1' => $this->faker->text(),
            'my_bool_1' => true,
            'my_bool_2' => false,
            'my_integer_1' => 5,
            'my_integer_2' => 5937653,
            'my_timestamp_1' => Carbon::now()
                ->subDays(100)->timestamp,
            'my_timestamp_2' => Carbon::now()
                ->addDays(100)->timestamp,
        ];

        $this->expectsEvents([CustomerCreated::class]);

        $createdCustomer = $this->customerIoService->createOrUpdateCustomerByEmail(
            $email,
            $accountName,
            $customAttributes,
            $userId,
            $createdAt
        );

        $data = [
            'uuid' => $createdCustomer->uuid,
            'email' => $email,
            'user_id' => $userId,
            'workspace_name' => $accountConfigData['workspace_name'],
            'workspace_id' => $accountConfigData['workspace_id'],
            'site_id' => $accountConfigData['site_id'],
            'created_at' => Carbon::createFromTimestamp($createdAt)
                ->toDateTimeString(),
            'updated_at' => Carbon::createFromTimestamp($createdAt)
                ->toDateTimeString(),
            'deleted_at' => null,
        ];

        $this->assertDatabaseHas('customer_io_customers', $data);

        $this->assertNotEmpty(
            Customer::query()
                ->find(1)->uuid
        );

        // for some reason the fetch API needs some time to update otherwise we always get 404
        sleep(4);

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

        $this->assertEquals(
            $fetchedCustomer->created_at,
            Carbon::createFromTimestamp($createdAt)
                ->toDateTimeString()
        );
        $this->assertEquals(
            $fetchedCustomer->updated_at,
            Carbon::createFromTimestamp($createdAt)
                ->toDateTimeString()
        );
        $this->assertEquals($fetchedCustomer->deleted_at, null);

        foreach ($customAttributes as $customAttributeName => $customAttributeValue) {
            $this->assertEquals(
                $data[$customAttributeName],
                $fetchedCustomer->getExternalAttributes()[$customAttributeName]
            );
        }
    }

    public function test_create_or_update_customer_update()
    {
        $email = $this->faker->email;
        $accountName = 'musora';
        $accountConfigData = $this->customerIoService->getAccountConfigData($accountName);
        $userId = rand();
        $createdAt =
            Carbon::now()
                ->subDays(1)->timestamp;

        $customAttributes = [
            'my_string_1' => $this->faker->text(),
            'my_bool_1' => true,
            'my_bool_2' => false,
            'my_integer_1' => 5,
            'my_integer_2' => 5937653,
            'my_timestamp_1' => Carbon::now()
                ->subDays(100)->timestamp,
            'my_timestamp_2' => Carbon::now()
                ->addDays(100)->timestamp,
        ];

        $this->expectsEvents([CustomerCreated::class]);

        $createdCustomer = $this->customerIoService->createOrUpdateCustomerByEmail(
            $email,
            $accountName,
            $customAttributes,
            $userId,
            $createdAt
        );

        $data = [
            'uuid' => $createdCustomer->uuid,
            'email' => $email,
            'user_id' => $userId,
            'workspace_name' => $accountConfigData['workspace_name'],
            'workspace_id' => $accountConfigData['workspace_id'],
            'site_id' => $accountConfigData['site_id'],
            'created_at' => Carbon::createFromTimestamp($createdAt)
                ->toDateTimeString(),
            'updated_at' => Carbon::createFromTimestamp($createdAt)
                ->toDateTimeString(),
            'deleted_at' => null,
        ];

        $this->assertDatabaseHas('customer_io_customers', $data);

        $this->assertNotEmpty(
            Customer::query()
                ->find(1)->uuid
        );

        // for some reason the fetch API needs some time to update otherwise we always get 404
        sleep(4);

        // update
        $newCustomAttributes = [
            'my_string_1' => $this->faker->text(),
            'my_bool_1' => false,
            'my_bool_2' => true,
            'my_integer_1' => 5982,
            'my_integer_2' => 583,
            'my_timestamp_1' => Carbon::now()
                ->subDays(3)->timestamp,
            'my_timestamp_2' => Carbon::now()
                ->addDays(3)->timestamp,
        ];
        $newUserId = rand();
        $newCreatedAt =
            Carbon::now()
                ->subDays(1)->timestamp;

        $this->expectsEvents([CustomerUpdated::class]);

        $updatedCustomer = $this->customerIoService->createOrUpdateCustomerByEmail(
            $email,
            $accountName,
            $newCustomAttributes,
            $newUserId,
            $newCreatedAt
        );

        $data = array_merge($data, $customAttributes);

        // for some reason the fetch API needs some time to update otherwise we always get 404
        sleep(4);

        $fetchedCustomer = $this->customerIoService->getCustomerById($accountName, $updatedCustomer->uuid);

        $this->assertEquals($fetchedCustomer->uuid, $updatedCustomer->uuid);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['id'], $updatedCustomer->uuid);

        $this->assertEquals($fetchedCustomer->email, $email);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['email'], $email);

        $this->assertEquals($fetchedCustomer->user_id, $newUserId);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['user_id'], $newUserId);

        $this->assertEquals($fetchedCustomer->getExternalAttributes()['created_at'], $newCreatedAt);

        $this->assertEquals($fetchedCustomer->workspace_name, $accountConfigData['workspace_name']);
        $this->assertEquals($fetchedCustomer->workspace_id, $accountConfigData['workspace_id']);
        $this->assertEquals($fetchedCustomer->site_id, $accountConfigData['site_id']);

        $this->assertEquals($fetchedCustomer->created_at, Carbon::createFromTimestamp($newCreatedAt));
        $this->assertEquals(Carbon::now(), $fetchedCustomer->updated_at);
        $this->assertEquals($fetchedCustomer->deleted_at, null);

        foreach ($newCustomAttributes as $customAttributeName => $customAttributeValue) {
            $this->assertEquals(
                $customAttributeValue,
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
            'created_at' => Carbon::now()
                ->toDateTimeString(),
            'updated_at' => Carbon::now()
                ->toDateTimeString(),
            'deleted_at' => null,
        ];

        $this->assertDatabaseHas('customer_io_customers', $data);

        $this->assertNotEmpty(
            Customer::query()
                ->find(1)->uuid
        );

        sleep(5);

        $fetchedCustomer = $this->customerIoService->getCustomerById('musora', $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->uuid, $createdCustomer->uuid);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['id'], $createdCustomer->uuid);

        $this->assertEquals($fetchedCustomer->email, $email);
        $this->assertEquals($fetchedCustomer->getExternalAttributes()['email'], $email);

        $this->assertEquals($fetchedCustomer->workspace_name, $accountConfigData['workspace_name']);
        $this->assertEquals($fetchedCustomer->workspace_id, $accountConfigData['workspace_id']);
        $this->assertEquals($fetchedCustomer->site_id, $accountConfigData['site_id']);

        $this->assertEquals(
            $fetchedCustomer->created_at,
            Carbon::now()
                ->toDateTimeString()
        );
        $this->assertEquals(
            $fetchedCustomer->updated_at,
            Carbon::now()
                ->toDateTimeString()
        );
        $this->assertEquals($fetchedCustomer->deleted_at, null);

        $this->assertEquals(
            'my attribute value 1',
            $fetchedCustomer->getExternalAttributes()['attribute_to_sync_1']
        );

        $this->assertEquals(
            'my attribute value 2',
            $fetchedCustomer->getExternalAttributes()['attribute_to_sync_2']
        );

        $fetchedCustomerActivities = $this->customerIoApiGateway->getCustomerActivities(
            $accountConfigData['app_api_key'],
            $createdCustomer->uuid,
            'event'
        );

        $fetchedEventNames = [];

        foreach ($fetchedCustomerActivities as $fetchedCustomerActivity) {
            $fetchedEventNames[] = $fetchedCustomerActivity->name;
        }

        $this->assertContains('event_to_sync_1', $fetchedEventNames);
        $this->assertContains('event_to_sync_2', $fetchedEventNames);
    }

    public function test_create_event_all_data()
    {
        $email = $this->faker->email;
        $accountName = 'musora';
        $eventName = 'my_event_1';
        $eventType = 'my_event_type_1';
        $createdAt =
            Carbon::now()
                ->subDays(2)->timestamp;

        $createdCustomer = $this->customerIoService->createCustomer(
            $email,
            $accountName
        );

        // for some reason the fetch API needs some time to update otherwise we always get 404
        sleep(4);

        $this->customerIoService->createEvent($createdCustomer->uuid, $accountName, $eventName, $eventType, $createdAt);

        sleep(5);

        $accountConfigData = $this->customerIoService->getAccountConfigData($accountName);

        $fetchedCustomerActivities = $this->customerIoApiGateway->getCustomerActivities(
            $accountConfigData['app_api_key'],
            $createdCustomer->uuid,
            'event'
        );

        $this->assertEquals($eventName, $fetchedCustomerActivities[0]->name);
    }

    public function test_sync_user_device()
    {
        $email = $this->faker->email;
        $accountName = 'musora';
        $createdAt =
            Carbon::now()
                ->subDays(2)->timestamp;

        $createdCustomer = $this->customerIoService->createCustomer(
            $email,
            $accountName
        );

        // for some reason the fetch API needs some time to update otherwise we always get 404
        sleep(4);

        $token = '749f535671cf6b34d8e794d212d00c703b96274e07161b18b082d0d70ef1052f';
        $platform = 'ios';

        $this->customerIoService->syncDeviceForUserId(
            $createdCustomer->user_id,
            $accountName, ['id' => $token, 'platform' => $platform],
            $createdAt
        );

        sleep(5);

        $accountConfigData = $this->customerIoService->getAccountConfigData($accountName);

        $fetchedCustomer = $this->customerIoApiGateway->getCustomer(
            $accountConfigData['app_api_key'],
            $createdCustomer->uuid
        );

        $this->assertEquals(1, count($fetchedCustomer->devices));
        $this->assertEquals($token, $fetchedCustomer->devices[0]->id);
        $this->assertEquals($platform, $fetchedCustomer->devices[0]->platform);
    }

    public function test_merge_customers()
    {
        $accountName = 'musora';
        $email = $this->faker->email;
        $createdAt1 =
            Carbon::now()
                ->subDays(2)->timestamp;
        $attributes1 = ['test1' => 'value-not-overwritten', 'test2' => 'new-value', 'test3' => ''];

        $createdCustomer1 = $this->customerIoService->createCustomer(
            $email,
            $accountName,
            $attributes1
        );
        $attributes2 = ['test2' => 'value-is-overwritten', 'test3' => 'this-is-set'];

        $createdCustomer2 = $this->customerIoService->createCustomer(
            $email,
            $accountName,
            $attributes2
        );

        // for some reason the fetch API needs some time to update otherwise we always get 404
        sleep(4);

        $primaryCustomer = $this->customerIoService->mergeCustomers(
            $accountName,
            $createdCustomer1->uuid,
            $createdCustomer2->uuid
        );

        sleep(5);

        $accountConfigData = $this->customerIoService->getAccountConfigData($accountName);

        $fetchedCustomer = $this->customerIoApiGateway->getCustomer(
            $accountConfigData['app_api_key'],
            $primaryCustomer->uuid
        );

        // duplicate should now be missing
        try {
            $fetchedDuplicateCustomer = $this->customerIoApiGateway->getCustomer(
                $accountConfigData['app_api_key'],
                $createdCustomer2->uuid
            );
        } catch (Exception $exception) {
            $this->assertEquals(404, $exception->getCode());
        }

        $this->assertDatabaseMissing('customer_io_customers', ['uuid' => $createdCustomer2->uuid]);
        $this->assertDatabaseHas('customer_io_customers', ['uuid' => $primaryCustomer->uuid]);
        $this->assertEquals($primaryCustomer->email, $fetchedCustomer->attributes->email);
        $this->assertEquals($primaryCustomer->uuid, $fetchedCustomer->attributes->id);
        $this->assertEquals("value-not-overwritten", $fetchedCustomer->attributes->test1);
        $this->assertEquals("new-value", $fetchedCustomer->attributes->test2);
        $this->assertEquals("this-is-set", $fetchedCustomer->attributes->test3);
    }

    public function test_merge_customers_failed_missing_from_db()
    {
        $accountName = 'musora';
        $email = $this->faker->email;
        $createdAt1 =
            Carbon::now()
                ->subDays(2)->timestamp;
        $attributes1 = ['test1' => 'value-not-overwritten', 'test2' => 'new-value', 'test3' => ''];

        $createdCustomer1 = $this->customerIoService->createCustomer(
            $email,
            $accountName,
            $attributes1
        );

        // for some reason the fetch API needs some time to update otherwise we always get 404
        sleep(4);

        try {
            $primaryCustomer = $this->customerIoService->mergeCustomers(
                $accountName,
                $createdCustomer1->uuid,
                'test-fail-uuid'
            );
        } catch (Exception $exception) {
            $this->assertEquals(
                'Could not merge customer ids because one is missing from the database. $primaryCustomerId:' .
                $createdCustomer1->uuid . ' - $secondaryCustomerId:test-fail-uuid - $accountName: musora',
                $exception->getMessage()
            );
        }

        sleep(5);

        $accountConfigData = $this->customerIoService->getAccountConfigData($accountName);

        $fetchedCustomer = $this->customerIoApiGateway->getCustomer(
            $accountConfigData['app_api_key'],
            $createdCustomer1->uuid
        );

        $this->assertDatabaseHas('customer_io_customers', ['uuid' => $createdCustomer1->uuid]);
        $this->assertEquals($createdCustomer1->email, $fetchedCustomer->attributes->email);
        $this->assertEquals($createdCustomer1->uuid, $fetchedCustomer->attributes->id);
    }
}
