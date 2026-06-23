<?php

namespace App\Http\Requests;

use App\Support\PortalUsername;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:50'],
        ];
    }

    public function normalizedUsername(): string
    {
        return PortalUsername::normalize((string) $this->input('username'));
    }
}
