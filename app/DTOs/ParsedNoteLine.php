<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Une opération lue dans les notes de saisie en lot (module saisie-lot).
 *
 * status : `ok` (prête), `incomplete` (montant sans libellé), `unparsed` (ligne non comprise).
 */
final readonly class ParsedNoteLine
{
    public function __construct(
        public int $lineNumber,
        public string $raw,
        public ?string $date,
        public ?string $type,
        public ?int $amount,
        public ?string $label,
        public string $status,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'line' => $this->lineNumber,
            'raw' => $this->raw,
            'date' => $this->date,
            'type' => $this->type,
            'amount' => $this->amount,
            'label' => $this->label,
            'status' => $this->status,
        ];
    }
}
