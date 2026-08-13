<?php

namespace App\Agent\Data;

/**
 * Deskripsi tool yang dibaca perencana (dan LLM) untuk memilih tindakan.
 */
class ToolDefinition
{
    /**
     * @param  array<string, array{type?: string, required?: bool, description?: string, enum?: array<int, string>, default?: mixed}>  $inputSchema
     * @param  array<int, string>  $outputKeys
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $description,
        public readonly array $inputSchema = [],
        public readonly array $outputKeys = [],
        public readonly string $riskLevel = 'low',        // low | medium | high
        public readonly ?string $permission = null,        // izin aplikasi yang harus dimiliki pemilik task
        public readonly ?string $integration = null,       // kunci akses luar yang dibutuhkan
        public readonly bool $readOnly = true,
        public readonly bool $idempotent = true,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name'         => $this->name,
            'title'        => $this->title,
            'description'  => $this->description,
            'input_schema' => $this->inputSchema,
            'output_keys'  => $this->outputKeys,
            'risk_level'   => $this->riskLevel,
            'permission'   => $this->permission,
            'integration'  => $this->integration,
            'read_only'    => $this->readOnly,
            'idempotent'   => $this->idempotent,
        ];
    }
}
