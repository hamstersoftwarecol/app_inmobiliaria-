<?php

namespace App\Http\Requests\Search;

use App\Application\Search\PropertySearch;
use App\Domain\Search\Enums\AlertFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filters use exactly the public search rules (nested under `filters`), so a saved search can
 * always be replayed by the search endpoint and by the alert job.
 */
class SavedSearchRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $searchRules = Arr::except(PropertySearch::rules(), ['sort']);
        $nested = [];
        foreach ($searchRules as $key => $rules) {
            $nested["filters.{$key}"] = array_map(
                // Cross-field rules (gte:min_price, prohibits:polygon...) must point inside `filters`.
                fn ($rule) => is_string($rule) ? preg_replace_callback(
                    '/^(gte|required_with|prohibits):(.+)$/',
                    fn ($m) => $m[1].':'.implode(',', array_map(fn ($f) => "filters.{$f}", explode(',', $m[2]))),
                    $rule,
                ) : $rule,
                $rules,
            );
        }

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'min:2', 'max:100'],
            'filters' => [$creating ? 'required' : 'sometimes', 'array'],
            ...$nested,
            'sort' => ['nullable', Rule::in(PropertySearch::SORTS)],
            'alert_frequency' => ['sometimes', Rule::enum(AlertFrequency::class)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->has('filters') && PropertySearch::filtersFrom((array) $this->input('filters')) === []) {
                $validator->errors()->add('filters', 'Choose at least one filter to save this search.');
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForModel(): array
    {
        $data = Arr::only($this->validated(), ['name', 'sort', 'alert_frequency']);
        if ($this->has('filters')) {
            $data['filters'] = PropertySearch::filtersFrom((array) $this->validated('filters', []));
        }

        return $data;
    }
}
