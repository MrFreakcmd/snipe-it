<?php

namespace App\Http\Requests;

use App\Models\CheckoutAcceptance;
use App\Models\User;
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

        if (! $acceptance || ! $user) {
            return false;
        }

        if (is_string($acceptance)) {
            $acceptance = CheckoutAcceptance::find($acceptance);
            if (! $acceptance) {
                return false;
            }
        }

        if (! $user instanceof User) {
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

    protected function failedAuthorization()
    {
        $user = Auth::user();
        $acceptance = $this->route('acceptance');
        // If user is logged in and acceptance exists, treat as business logic error
        if ($user && $acceptance) {
            $redirectResponse = redirect()->route('account.accept')->with('error', trans('admin/users/message.error.incorrect_user_accepted'));
            throw new \Illuminate\Validation\ValidationException($this->getValidatorInstance(), $redirectResponse);
        }
        // Otherwise, use default 403
        parent::failedAuthorization();
    }
}
