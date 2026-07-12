<?php

namespace App\Http\Requests;

use App\Actions\Dashboard\DashboardRange;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'range' => ['sometimes', 'string', Rule::enum(DashboardRange::class)],
        ];
    }

    /**
     * The validated range, defaulting to the 24-hour window when absent.
     */
    public function range(): DashboardRange
    {
        return DashboardRange::fromRequest($this->query('range'));
    }
}
