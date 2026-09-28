<?php

namespace App\Http\Requests;

use App\Models\Habit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HabitRequest extends FormRequest
{
    public const COLORS = ['#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#f43f5e', '#f97316', '#eab308', '#64748b'];

    public function authorize(): bool
    {
        $habit = $this->route('habit');

        return ! $habit instanceof Habit || $this->user()->can('manage', $habit);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'frequency_type' => ['required', Rule::in([Habit::DAILY, Habit::WEEKLY])],
            'weekly_target' => ['nullable', 'required_if:frequency_type,weekly', 'integer', 'between:1,6'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'start_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'frequency_type' => 'frecuencia',
            'weekly_target' => 'veces por semana',
            'start_date' => 'fecha de inicio',
        ];
    }

    public function messages(): array
    {
        return ['weekly_target.required_if' => 'Indica cuántas veces por semana.'];
    }

    /** Datos listos para guardar. */
    public function habitData(): array
    {
        $data = $this->validated();
        $data['color'] = strtolower($data['color']);
        if ($data['frequency_type'] === Habit::DAILY) {
            $data['weekly_target'] = null;
        }

        return $data;
    }
}
