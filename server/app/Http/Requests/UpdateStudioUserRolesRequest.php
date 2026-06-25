<?php

namespace App\Http\Requests;

use App\Services\StudioUserManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudioUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDirector() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', Rule::in(StudioUserManagementService::MANAGEABLE_ROLE_KEYS)],
        ];
    }

    /**
     * @return list<string>
     */
    public function roleKeys(): array
    {
        /** @var list<string> $roles */
        $roles = $this->input('roles', []);

        return array_values(array_unique($roles));
    }
}
