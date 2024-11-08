<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClient extends FormRequest
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
        return [
            'name' => 'required|min:3|max:60',
            'email' => 'required|email|min:3|max:60|unique:clients',
            'cpf' => 'required',
            'password' => 'required|min:6|max:15',
            'instagran' => 'required|min:5|max:60|unique:clients',
        ];
    }
}
