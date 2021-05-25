<?php

namespace Railroad\CustomerIo\Tests\Functional;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Railroad\CustomerIo\Services\CustomerIoService;
use Railroad\CustomerIo\Tests\CustomerIoTestCase;

class CustomerIoServiceTest extends CustomerIoTestCase
{
    /**
     * @var CustomerIoService
     */
    private $customerIoService;

    protected function setUp()
    {
        parent::setUp();

        $this->customerIoService = app()->make(CustomerIoService::class);
    }

    public function test_track_lead_success()
    {
        $brand = $this->faker->word;
        config()->set('lead-tracker.brand', $brand);

        $data =
            [
                'email' => $this->faker->email,
                'maropost_tag_name' => $this->faker->words(2, true),
                'form_name' => $this->faker->words(2, true),
                'form_page_url' => $this->faker->url,
                'utm_source' => $this->faker->word . rand(),
                'utm_medium' => $this->faker->word . rand(),
                'utm_campaign' => $this->faker->word . rand(),
                'utm_term' => $this->faker->words(2, true),
            ];

        $this->expectsEvents([LeadTracked::class]);

        $inserted = $this->customerIoService->trackLead(
            $data['email'],
            $data['maropost_tag_name'],
            $data['form_name'],
            $data['form_page_url'],
            $data['utm_source'],
            $data['utm_medium'],
            $data['utm_campaign'],
            $data['utm_term']
        );

        $this->assertEquals(
            array_merge(['id' => 1, 'submitted_at' => Carbon::now()->toDateTimeString(), 'brand' => $brand], $data),
            $inserted
        );

        $this->assertDatabaseHas('leadtracker_leads', $data);
    }

    public function test_track_lead_success_with_null()
    {
        $data =
            [
                'email' => $this->faker->email,
                'maropost_tag_name' => $this->faker->words(2, true),
                'form_name' => $this->faker->words(2, true),
                'form_page_url' => $this->faker->url,
                'utm_source' => null,
                'utm_medium' => null,
                'utm_campaign' => null,
                'utm_term' => null,
            ];

        $this->expectsEvents([LeadTracked::class]);

        $inserted = $this->customerIoService->trackLead(
            $data['email'],
            $data['maropost_tag_name'],
            $data['form_name'],
            $data['form_page_url'],
            $data['utm_source'],
            $data['utm_medium'],
            $data['utm_campaign'],
            $data['utm_term']
        );

        $this->assertEquals(
            array_merge(
                [
                    'id' => 1,
                    'submitted_at' => Carbon::now()->toDateTimeString(),
                    'brand' => null,
                ],
                $data
            ),
            $inserted
        );

        $this->assertDatabaseHas('leadtracker_leads', $data);
    }

    public function test_track_lead_no_duplicates()
    {
        $brand = $this->faker->word;
        config()->set('lead-tracker.brand', $brand);

        $data =
            [
                'email' => $this->faker->email,
                'maropost_tag_name' => $this->faker->words(2, true),
                'form_name' => $this->faker->words(2, true),
                'form_page_url' => $this->faker->url,
                'utm_source' => $this->faker->word . rand(),
                'utm_medium' => $this->faker->word . rand(),
                'utm_campaign' => $this->faker->word . rand(),
                'utm_term' => $this->faker->words(2, true),
            ];

        $this->expectsEvents([LeadTracked::class]);

        $inserted = $this->customerIoService->trackLead(
            $data['email'],
            $data['maropost_tag_name'],
            $data['form_name'],
            $data['form_page_url'],
            $data['utm_source'],
            $data['utm_medium'],
            $data['utm_campaign'],
            $data['utm_term']
        );

        $inserted = $this->customerIoService->trackLead(
            $data['email'],
            $data['maropost_tag_name'],
            $data['form_name'],
            $data['form_page_url'],
            $data['utm_source'],
            $data['utm_medium'],
            $data['utm_campaign'],
            $data['utm_term']
        );

        $this->assertEquals(
            array_merge(['id' => 1, 'submitted_at' => Carbon::now()->toDateTimeString(), 'brand' => $brand], $data),
            $inserted
        );

        $this->assertDatabaseHas('leadtracker_leads', $data);
        $this->assertDatabaseMissing('leadtracker_leads', ['id' => 2]);
    }

    public function test_get_input_array_for_request_tracking_form()
    {
        $formPath = '/test-path';
        $formMethod = 'post';
        $formName = 'my lead form';
        $maropostTagName = 'my-tag';

        config()->set(
            'lead-tracker.requests_to_capture',
            [
                [
                    'path' => $formPath,
                    'method' => $formMethod,
                    'form_name' => 'other-form-name-not-to-track',
                    'input_data_map' => [
                        'email' => 'other_my_email_input_name',
                        'maropost_tag_name' => 'other_my_maropost_tag_name_input_name',
                        'form_name' => 'other_my_form_name_input_name',
                        'utm_source' => 'other_my_utm_source_input_name',
                        'utm_medium' => 'other_my_utm_medium_input_name',
                        'utm_campaign' => 'other_my_utm_campaign_input_name',
                        'utm_term' => 'other_my_utm_term_input_name',
                    ],
                ],
                [
                    'path' => $formPath,
                    'method' => $formMethod,
                    'form_name' => $formName,
                    'input_data_map' => [
                        'email' => 'my_email_input_name',
                        'maropost_tag_name' => 'my_maropost_tag_name_input_name',
                        'form_name' => 'my_form_name_input_name',
                        'utm_source' => 'my_utm_source_input_name',
                        'utm_medium' => 'my_utm_medium_input_name',
                        'utm_campaign' => 'my_utm_campaign_input_name',
                        'utm_term' => 'my_utm_term_input_name',
                    ],
                ],
            ]
        );

        $data =
            [
                'utm_source' => $this->faker->word . rand(),
                'utm_medium' => $this->faker->word . rand(),
                'utm_campaign' => $this->faker->word . rand(),
                'utm_term' => $this->faker->words(2, true),
            ];

        $request = Request::create('https://www.leadtracker.com/my-lead-page', 'get', $data);

        app()->bind(
            'request',
            function () use ($request) {
                return $request;
            }
        );

        $inputArray = CustomerIoService::getRequestTrackingInputArrayFromRequest(
            $formPath,
            $formMethod,
            $maropostTagName,
            $formName
        );

        $this->assertEquals(
            [
                'my_maropost_tag_name_input_name' => 'my-tag',
                'my_form_name_input_name' => 'my lead form',
                'my_utm_source_input_name' => $data['utm_source'],
                'my_utm_medium_input_name' => $data['utm_medium'],
                'my_utm_campaign_input_name' => $data['utm_campaign'],
                'my_utm_term_input_name' => $data['utm_term'],
            ],
            $inputArray
        );
    }

    public function test_get_input_array_for_request_tracking_form_with_nulls()
    {
        $formPath = '/test-path';
        $formMethod = 'post';
        $formName = 'my lead form';
        $maropostTagName = 'my-tag';

        config()->set(
            'lead-tracker.requests_to_capture',
            [
                [
                    'path' => $formPath,
                    'method' => $formMethod,
                    'form_name' => $formName,
                    'input_data_map' => [
                        'email' => 'my_email_input_name',
                        'maropost_tag_name' => 'my_maropost_tag_name_input_name',
                        'form_name' => 'my_form_name_input_name',
                        'utm_source' => 'my_utm_source_input_name',
                        'utm_medium' => 'my_utm_medium_input_name',
                        'utm_campaign' => 'my_utm_campaign_input_name',
                        'utm_term' => 'my_utm_term_input_name',
                    ],
                ],
            ]
        );

        $data = [];

        $request = Request::create('https://www.leadtracker.com/my-lead-page', 'get', $data);

        app()->bind(
            'request',
            function () use ($request) {
                return $request;
            }
        );

        $inputArray = CustomerIoService::getRequestTrackingInputArrayFromRequest(
            $formPath,
            $formMethod,
            $maropostTagName,
            $formName
        );

        $this->assertEquals(
            [
                'my_maropost_tag_name_input_name' => 'my-tag',
                'my_form_name_input_name' => 'my lead form',
                'my_utm_source_input_name' => $data['utm_source'] ?? null,
                'my_utm_medium_input_name' => $data['utm_medium'] ?? null,
                'my_utm_campaign_input_name' => $data['utm_campaign'] ?? null,
                'my_utm_term_input_name' => $data['utm_term'] ?? null,
            ],
            $inputArray
        );
    }

    public function test_get_inputs_html_for_request_tracking_form()
    {
        $formPath = '/test-path';
        $formMethod = 'post';
        $formName = 'my lead form';
        $maropostTagName = 'my-tag';

        config()->set(
            'lead-tracker.requests_to_capture',
            [
                [
                    'path' => $formPath,
                    'method' => $formMethod,
                    'form_name' => $formName,
                    'input_data_map' => [
                        'email' => 'my_email_input_name',
                        'maropost_tag_name' => 'my_maropost_tag_name_input_name',
                        'form_name' => 'my_form_name_input_name',
                        'utm_source' => 'my_utm_source_input_name',
                        'utm_medium' => 'my_utm_medium_input_name',
                        'utm_campaign' => 'my_utm_campaign_input_name',
                        'utm_term' => 'my_utm_term_input_name',
                    ],
                ],
            ]
        );

        $data =
            [
                'utm_source' => $this->faker->word . rand(),
                'utm_medium' => $this->faker->word . rand(),
                'utm_campaign' => $this->faker->word . rand(),
                'utm_term' => $this->faker->words(2, true),
            ];

        $request = Request::create('https://www.leadtracker.com/my-lead-page', 'get', $data);

        app()->bind(
            'request',
            function () use ($request) {
                return $request;
            }
        );

        $inputArray = CustomerIoService::getRequestTrackingInputsHtmlFromRequest(
            $formPath,
            $formMethod,
            $maropostTagName,
            $formName
        );

        $this->assertEquals(
            "<input type='hidden' name='my_maropost_tag_name_input_name' value='my-tag'>\n" .
            "<input type='hidden' name='my_form_name_input_name' value='my lead form'>\n" .
            "<input type='hidden' name='my_utm_source_input_name' value='" . $data['utm_source'] . "'>\n" .
            "<input type='hidden' name='my_utm_medium_input_name' value='" . $data['utm_medium'] . "'>\n" .
            "<input type='hidden' name='my_utm_campaign_input_name' value='" . $data['utm_campaign'] . "'>\n" .
            "<input type='hidden' name='my_utm_term_input_name' value='" . $data['utm_term'] . "'>\n",
            $inputArray
        );
    }

    public function test_get_inputs_html_for_request_tracking_form_with_nulls()
    {
        $formPath = '/test-path';
        $formMethod = 'post';
        $formName = 'my lead form';
        $maropostTagName = 'my-tag';

        config()->set(
            'lead-tracker.requests_to_capture',
            [
                [
                    'path' => $formPath,
                    'method' => $formMethod,
                    'form_name' => $formName,
                    'input_data_map' => [
                        'email' => 'my_email_input_name',
                        'maropost_tag_name' => 'my_maropost_tag_name_input_name',
                        'form_name' => 'my_form_name_input_name',
                        'utm_source' => 'my_utm_source_input_name',
                        'utm_medium' => 'my_utm_medium_input_name',
                        'utm_campaign' => 'my_utm_campaign_input_name',
                        'utm_term' => 'my_utm_term_input_name',
                    ],
                ],
            ]
        );

        $data = [];

        $request = Request::create('https://www.leadtracker.com/my-lead-page', 'get', $data);

        app()->bind(
            'request',
            function () use ($request) {
                return $request;
            }
        );

        $inputArray = CustomerIoService::getRequestTrackingInputsHtmlFromRequest(
            $formPath,
            $formMethod,
            $maropostTagName,
            $formName
        );

        $this->assertEquals(
            "<input type='hidden' name='my_maropost_tag_name_input_name' value='my-tag'>\n" .
            "<input type='hidden' name='my_form_name_input_name' value='my lead form'>\n",
            $inputArray
        );
    }

}
