<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrder extends FormRequest
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
            'token_company' => [
                'required',
                'exists:tenants,uuid'
            ],
            'table' => [
                'nullable',
                'exists:tables,uuid',
            ],
            // Validado de verdade no AtendimentoService (mesma empresa, ativo, modulo ligado)
            'attendant' => ['nullable', 'string', 'max:64'],
            'comment' => [
                'nullable',
                'max:1000',
            ],
            'products' => ['required'],
            // So produtos da barraca do pedido (antes aceitava uuid de outra barraca) e quantidade > 0
            // (qty negativa aumentava o estoque e zerava/negativava o total)
            'products.*.identify' => [
                'required',
                \Illuminate\Validation\Rule::exists('products', 'uuid')->where(
                    fn ($query) => $query->where('tenant_id', \App\Models\Tenant::where('uuid', $this->input('token_company'))->value('id'))
                ),
            ],
            'products.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }
}
