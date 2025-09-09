<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TblCcReasonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->reasonId,
            'type' => $this->reasonType,
            'name' => $this->reasonName,
            'active' => $this->reasonActive,
            'remark' => $this->reasonRemark,
            'created_at' => $this->dtCreated?->format('Y-m-d H:i:s'),
            'updated_at' => $this->dtUpdated?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->dtDeleted?->format('Y-m-d H:i:s'),
            'audit' => [
                'created_by' => $this->personCreated,
                'updated_by' => $this->personUpdated,
                'deleted_by' => $this->personDeleted,
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'api_version' => '1.0',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}
