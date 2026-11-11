<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use Carbon\CarbonImmutable;

/** Saisie en lot : notes → lignes suggérées + totaux. N'enregistre rien (RM-6). */
final class BatchEntryService
{
    public function __construct(
        private readonly NotesParser $parser,
        private readonly EntrySuggestionService $suggestions,
    ) {}

    /** @return array{lines: list<array<string, mixed>>, totals: array{expense: int, income: int, count: int}} */
    public function preview(User $user, string $notes, CarbonImmutable $reference, ?Account $defaultAccount): array
    {
        $lines = $this->suggestions->preview($user, $this->parser->parse($notes, $reference), $defaultAccount);
        $savable = array_filter($lines, fn (array $l) => $l['status'] !== 'unparsed');

        return [
            'lines' => $lines,
            'totals' => [
                'expense' => array_sum(array_map(fn ($l) => $l['type'] === 'expense' ? $l['amount'] : 0, $savable)),
                'income' => array_sum(array_map(fn ($l) => $l['type'] === 'income' ? $l['amount'] : 0, $savable)),
                'count' => count($savable),
            ],
        ];
    }
}
