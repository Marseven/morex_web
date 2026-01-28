<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = Category::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)
              ->orWhere('is_system', true);
        })
        ->withSum(['transactions as spent_this_month' => function ($q) {
            $q->where('type', 'expense')
              ->whereMonth('date', now()->month)
              ->whereYear('date', now()->year);
        }], 'amount')
        ->orderBy('type')
        ->orderBy('order_index')
        ->get();

        return Inertia::render('Budgets/Index', [
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Budgets/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:expense,income'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'budget_limit' => ['nullable', 'integer', 'min:0'],
        ]);

        $maxOrder = $request->user()->categories()->max('order_index') ?? -1;

        $request->user()->categories()->create([
            ...$validated,
            'order_index' => $maxOrder + 1,
        ]);

        return redirect()->route('budgets.index')
            ->with('success', 'Catégorie créée avec succès.');
    }

    public function edit(Category $category): Response
    {
        $this->authorize('update', $category);

        return Inertia::render('Budgets/Edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:expense,income'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'budget_limit' => ['nullable', 'integer', 'min:0'],
        ]);

        $category->update($validated);

        return redirect()->route('budgets.index')
            ->with('success', 'Catégorie mise à jour avec succès.');
    }

    public function destroy(Category $category)
    {
        $this->authorize('delete', $category);

        $category->delete();

        return redirect()->route('budgets.index')
            ->with('success', 'Catégorie supprimée avec succès.');
    }
}
