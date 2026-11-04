<?php

namespace App\Http\Requests;

use App\Support\Telefone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cadastro de cliente: so nome, celular e senha (cliente de praia nao tem paciencia).
 * E-mail, CPF e Instagram continuam aceitos, mas ninguem e obrigado a informar.
 */
class StoreClient extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('telefone')) {
            $this->merge(['telefone' => Telefone::normalizar($this->input('telefone'))]);
        }
    }

    public function rules()
    {
        return [
            'name' => 'required|string|min:2|max:60',
            'telefone' => 'required|digits_between:10,15|unique:clients,telefone',
            'password' => 'required|string|min:4|max:30',
            'email' => 'nullable|email|max:60|unique:clients',
            'cpf' => 'nullable|string|max:20',
            'instagran' => 'nullable|string|max:60|unique:clients',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Digite seu nome.',
            'telefone.required' => 'Digite seu celular com DDD.',
            'telefone.digits_between' => 'Digite o celular com DDD, ex: 21 99999-9999.',
            'telefone.unique' => 'Este celular já tem cadastro. Toque em "Entrar" e use sua senha.',
            'password.required' => 'Escolha uma senha.',
            'password.min' => 'A senha precisa de pelo menos 4 caracteres.',
        ];
    }
}
