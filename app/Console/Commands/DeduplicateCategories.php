<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeduplicateCategories extends Command
{
    protected $signature = 'categories:deduplicate';
    protected $description = 'Remove duplicate categories, keeping the oldest one';

    public function handle(): int
    {
        $this->info('Searching for duplicate categories...');

        // Find duplicate names
        $duplicates = DB::table('categories')
            ->select('name', 'type', DB::raw('COUNT(*) as count'))
            ->whereNull('deleted_at')
            ->groupBy('name', 'type')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicates found.');
            return Command::SUCCESS;
        }

        $deletedCount = 0;

        foreach ($duplicates as $dup) {
            $this->line("Processing: {$dup->name} ({$dup->type}) - {$dup->count} copies");

            // Get all categories with this name, ordered by preference
            $categories = Category::where('name', $dup->name)
                ->where('type', $dup->type)
                ->whereNull('deleted_at')
                ->orderByRaw('CASE WHEN is_system = 1 THEN 0 ELSE 1 END')
                ->orderBy('created_at', 'asc')
                ->get();

            if ($categories->count() <= 1) {
                continue;
            }

            // Keep the first one (system or oldest)
            $keep = $categories->first();
            $this->line("  Keeping: {$keep->id}");

            // Delete the others and reassign transactions
            foreach ($categories->skip(1) as $toDelete) {
                // Reassign transactions
                DB::table('transactions')
                    ->where('category_id', $toDelete->id)
                    ->update(['category_id' => $keep->id]);

                // Delete the duplicate
                $toDelete->forceDelete();
                $deletedCount++;
                $this->line("  Deleted: {$toDelete->id}");
            }
        }

        // Also check for similar names (normalize and compare)
        $this->info('Checking for similar names...');
        $allCategories = Category::whereNull('deleted_at')->get();

        $normalized = [];
        foreach ($allCategories as $cat) {
            $norm = $this->normalize($cat->name);
            $key = $norm . '_' . $cat->type;

            if (!isset($normalized[$key])) {
                $normalized[$key] = [];
            }
            $normalized[$key][] = $cat;
        }

        foreach ($normalized as $key => $cats) {
            if (count($cats) > 1) {
                // Sort: system first, then oldest
                usort($cats, function ($a, $b) {
                    if ($a->is_system !== $b->is_system) {
                        return $b->is_system <=> $a->is_system;
                    }
                    return $a->created_at <=> $b->created_at;
                });

                $keep = $cats[0];
                $this->line("Similar names found, keeping: {$keep->name}");

                for ($i = 1; $i < count($cats); $i++) {
                    $toDelete = $cats[$i];

                    if ($toDelete->id === $keep->id) continue;

                    DB::table('transactions')
                        ->where('category_id', $toDelete->id)
                        ->update(['category_id' => $keep->id]);

                    $toDelete->forceDelete();
                    $deletedCount++;
                    $this->line("  Deleted similar: {$toDelete->name} ({$toDelete->id})");
                }
            }
        }

        $this->info("Done! Removed {$deletedCount} duplicate(s).");
        return Command::SUCCESS;
    }

    private function normalize(string $name): string
    {
        $name = mb_strtolower($name);

        // Remove accents
        $name = str_replace(
            ['à', 'â', 'ä', 'é', 'è', 'ê', 'ë', 'ï', 'î', 'ô', 'ù', 'û', 'ü', 'ç'],
            ['a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'u', 'u', 'u', 'c'],
            $name
        );

        // Remove special chars
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);

        // Remove trailing 's' (plural)
        if (strlen($name) > 3 && str_ends_with($name, 's')) {
            $name = substr($name, 0, -1);
        }

        // Normalize spaces
        $name = preg_replace('/\s+/', ' ', trim($name));

        return $name;
    }
}
