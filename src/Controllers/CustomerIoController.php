<?php

namespace Railroad\Maropost\Controllers;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Railroad\CustomerIo\Services\CustomerIoService;

class CustomerIoController extends Controller
{
    use ValidatesRequests;

    /**
     * @var CustomerIoService
     */
    private $customerIoService;

    public function __construct(CustomerIoService $customerIoService)
    {
        $this->customerIoService = $customerIoService;
    }

    public function submitEmailForm(Request $request)
    {
        $allConfiguredFormNames = array_keys(config('customer-io.forms', []));

        $this->validate(
            $request,
            [
                'email' => 'email',
                'form_name' => 'in:'.implode(',', $allConfiguredFormNames),
            ]
        );

        $this->customerIoService->processForm($request->get('email'), $request->get('form_name'));

        if (request()->expectsJson()) {
            return response('', 201); // Just a successfully created response
        }

        return back()->with('successMessage', 'Your payment request registered successfully.');
    }
}
