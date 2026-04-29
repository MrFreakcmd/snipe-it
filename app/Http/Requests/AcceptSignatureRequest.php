<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AcceptSignatureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $acceptance = $this->route('acceptance');
        $user = Auth::user();

        if (!$acceptance || !$user) {
            return false;
        }

        if (is_string($acceptance)) {
            $acceptance = \App\Models\CheckoutAcceptance::find($acceptance);
            if (!$acceptance) {
                return false;
            }
        }

        if (!$user instanceof \App\Models\User) {
            return false;
        }

        // Only allow if the user is the assigned user or sign-in-place admin
        $assignedToId = $acceptance->assigned_to_id ?? null;
        $isSignInPlaceAdmin = session('sign_in_place_acceptance_id') === $acceptance->id && $user->can('checkout', $acceptance->checkoutable);
        return $user->id === $assignedToId || $isSignInPlaceAdmin;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // ...existing validation rules...
        ];
    }
}
