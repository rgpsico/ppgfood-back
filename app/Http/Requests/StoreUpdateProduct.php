<?php

namespace App\Http\Requests;

use App\Tenant\Rules\UniqueTenant;
use Illuminate\Foundation\Http\FormRequest;

class StoreUpdateProduct extends FormRequest
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
        $fromBank = $this->filled('bank_media_id'); // imagem escolhida no banco de imagens compartilhado

        $rules = [
            'title' => [
                'required',
                'min:3',
                'max:255',
                //"unique:products,title,{$id},id"
                new UniqueTenant('products', $id),
            ],
            'description' => ['required', 'min:3', 'max:500'],
            // Aceita tanto upload de arquivo quanto um link direto da imagem
            'image' => $this->hasFile('image')
                ? ['required', 'image']
                : ($fromBank ? ['nullable'] : ['required', 'string', 'url', 'max:2048']),
            'bank_media_id' => ['nullable', 'string', 'regex:/^[0-9A-Za-z]{20,40}$/'],
            'price' => "required|regex:/^\d+(\.\d{1,2})?$/",
        ];

        if ($this->method() == 'PUT') {
            $rules['image'] = $this->hasFile('image')
                ? ['nullable', 'image']
                : ($fromBank ? ['nullable'] : ['nullable', 'string', 'url', 'max:2048']);
        }

        return $rules;
    }
}
