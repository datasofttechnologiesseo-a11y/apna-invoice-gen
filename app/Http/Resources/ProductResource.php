<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'kind' => $this->kind,
            'kind_label' => $this->kindLabel(),
            'hsn_sac' => $this->hsn_sac,
            'unit' => $this->unit,
            'rate' => (float) $this->rate,
            'gst_rate' => (float) $this->gst_rate,
            'is_active' => (bool) $this->is_active,
            'description' => $this->description,
        ];
    }
}
