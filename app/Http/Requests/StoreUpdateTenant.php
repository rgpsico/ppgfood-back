<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUpdateTenant extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->segment(3);

        $rules = [
            'name' => ['required', 'min:3', 'max:255', "unique:tenants,name,{$id},id"],
            'email' => ['required', 'min:3', 'max:255', "unique:tenants,email,{$id},id"],
            'cnpj' => ['required', 'digits:14', "unique:tenants,cnpj,{$id},id"],
            // Aceita tanto upload de arquivo quanto um link direto da imagem
            'logo' => ['nullable', 'image'],
            'logo_url' => ['nullable', 'string', 'url', 'max:2048'],
            'cover_image' => ['nullable', 'image'],
            'cover_image_url' => ['nullable', 'string', 'url', 'max:2048'],
            'location_label' => ['nullable', 'string', 'max:255'],
            'active' => ['required', 'in:Y,N'],

            // subscription
            'subscription' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'subscription_id' => ['nullable', 'max:255'],
            'subscription_active' => ['nullable', 'boolean'],
            'subscription_suspended' => ['nullable', 'boolean'],
        ];

        return $rules;
    }
}
