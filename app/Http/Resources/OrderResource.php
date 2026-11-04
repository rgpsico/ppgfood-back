<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {


        return [
            'identify' => $this->identify,
            'codigo_entrega' => $this->codigo_entrega,
            'eEntregador' => $this->eEntregador,
            'total' => $this->total,
            'comment' => $this->comment,
            'status' => $this->status,
            'status_label' => $this->statusOptions[$this->status],
            'date' => Carbon::make($this->created_at)->format('Y-m-d'),
            'hour' => Carbon::make($this->created_at)->format('H:m:s'),
            'date_br' => Carbon::make($this->created_at)->format('d/m/Y H:i:s'),
            // ISO com fuso: o painel usa para saber ha quanto tempo o pedido esta esperando
            'created_at' => Carbon::make($this->created_at)->toIso8601String(),
            'price' => $this->whenPivotLoaded('order_product', function () {
                return $this->pivot->price;
            }),
            'quantity' => $this->whenPivotLoaded('order_product', function () {
                return $this->pivot->qty;
            }),



            'company' => new TenantResource($this->tenant),
            'client' => $this->client_id ? new ClientResource($this->client) : '',
            'table' => $this->table_id ? new TableResource($this->table) : '',
            'attendant' => $this->attendant_id && $this->attendant
                ? ['identify' => $this->attendant->uuid, 'name' => $this->attendant->nome]
                : null,
            'products' => ProductResource::collection($this->products),
            'evaluations' => EvaluationResource::collection($this->evaluations),
        ];
    }
}
