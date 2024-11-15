<?php

namespace App\Http\Controllers;

use App\Enums\AddressType;
use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileRequest;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Class ProfileController manages user profile related actions.
 *
 * @package App\Http\Controllers
 */
class ProfileController extends Controller
{
    /**
     * Display the user's profile information.
     *
     * @param Request $request
     * @return View|Application|Factory|\Illuminate\Contracts\Foundation\Application
     */
    public function view(Request $request): View|Application|Factory|\Illuminate\Contracts\Foundation\Application
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Customer $customer */
        $customer = $user->customer;
        $shippingAddress = $customer->shippingAddress ?? new CustomerAddress(['type' => AddressType::Shipping]);
        $billingAddress = $customer->billingAddress ?? new CustomerAddress(['type' => AddressType::Billing]);

        $countries = Country::query()->orderBy('name')->get();
        return view('profile.view', compact('customer', 'user', 'shippingAddress', 'billingAddress', 'countries'));
    }

    /**
     * Store the updated profile information.
     *
     * @param ProfileRequest $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function store(ProfileRequest $request): RedirectResponse
    {
        $customerData = $request->validated();
        $shippingData = $customerData['shipping'];
        $billingData = $customerData['billing'];

        /** @var User $user */
        $user = $request->user();
        /** @var Customer|null $customer */
        $customer = $user->customer;

        // Ensure a customer record exists for the user
        if ($customer) {
            $customer->update($customerData);
        } else {
            $customerData['user_id'] = $user->id;
            $customer = Customer::create($customerData);
            $customer->refresh(); // Ensure $customer has the ID after creation
        }

        // Ensure a shipping address exists or create a new one
        if ($customer->shippingAddress) {
            $customer->shippingAddress->update($shippingData);
        } else {
            $shippingData['customer_id'] = $customer->user_id;
            $shippingData['type'] = AddressType::Shipping->value;
            CustomerAddress::create($shippingData);
        }

        // Ensure a billing address exists or create a new one
        if ($customer->billingAddress) {
            $customer->billingAddress->update($billingData);
        } else {
            $billingData['customer_id'] = $customer->user_id;
            $billingData['type'] = AddressType::Billing->value;
            CustomerAddress::create($billingData);
        }

        $request->session()->flash('flash_message', 'Profile was successfully updated.');

        return redirect()->route('profile');
    }


    /**
     * Update the user's password.
     *
     * @param PasswordUpdateRequest $request
     * @return void
     */
    public function passwordUpdate(PasswordUpdateRequest $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $passwordData = $request->validated();

        $user->password = Hash::make($passwordData['new_password']);
        $user->save();

        $request->session()->flash('flash_message', 'Your password was successfully updated.');
    }
}
