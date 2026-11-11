<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Une ligne de relevé lue dans le texte collé (module releves-sms). Montant signé : négatif = sortie.
 * `excerpt` sert seulement à l'affichage d'une ligne non comprise ; il n'est jamais stocké.
 */
final readonly class StatementEntry
{
    public function __construct(
        public int $index,
        public ?string $date,
        public ?int $amount,
        public int $fee,
        public ?string $label,
        public ?string $reference,
        public string $status,
        public string $excerpt,
    ) {}

    public static function unparsed(int $index, string $excerpt): self
    {
        return new self($index, null, null, 0, null, null, 'unparsed', mb_substr($excerpt, 0, 160));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
