<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'account' => $this->accountRules($userId),
            'name' => $this->nameRules(),
        ];
    }

    /**
     * Get the validation rules used to validate user accounts.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function accountRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'min:2',
            'max:32',
            'regex:/^[A-Za-z][A-Za-z0-9_]+$/',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }
}
