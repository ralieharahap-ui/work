<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users',
            'password'    => ['required', 'min:8', 'confirmed', 'regex:/^(?=.*[A-Z])(?=.*\d).+$/'],
            'division_id' => 'required|uuid|exists:divisions,id',
            'employee_id' => 'nullable|string|max:50|unique:users',
            'phone'       => 'nullable|string|max:50',
            'hierarchy'   => 'required|in:staff,manager,director,stakeholder',
        ];
    }

    public function messages(): array
    {
        return ['password.regex' => 'Password harus mengandung minimal 1 huruf besar dan 1 angka.'];
    }
}
