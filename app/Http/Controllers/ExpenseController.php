<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\Expense;
use App\Support\DashboardCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $expenses = Expense::query()
            ->forFarm($this->farmId())
            ->with(['campaign.crop', 'recordedBy'])
            ->when($request->filled('campaign_id'), fn ($query) => $query->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('expense_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('expense_date', '<=', $request->date('to')))
            ->orderByDesc('expense_date')
            ->paginate(20)
            ->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'campaigns' => $this->campaigns(),
            'categories' => collect(ExpenseCategory::cases()),
            'total' => (float) $expenses->sum('amount'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Expense::class);

        return view('expenses.create', $this->formData() + [
            'expense' => new Expense([
                'category' => ExpenseCategory::Inputs,
                'expense_date' => now()->toDateString(),
                'campaign_id' => $request->integer('campaign_id') ?: null,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Expense::class);

        Expense::create($this->validated($request) + [
            'farm_id' => $this->farmId(),
            'recorded_by' => $request->user()->id,
        ]);

        DashboardCache::forget($this->farm());

        return redirect()->route('expenses.index')->with('status', 'Dépense enregistrée.');
    }

    public function edit(Expense $expense): View
    {
        Gate::authorize('update', $expense);

        return view('expenses.edit', $this->formData() + ['expense' => $expense]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('update', $expense);

        $expense->update($this->validated($request));

        DashboardCache::forget($this->farm());

        return redirect()->route('expenses.index')->with('status', 'Dépense mise à jour.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('delete', $expense);

        $expense->delete();

        DashboardCache::forget($this->farm());

        return redirect()->route('expenses.index')->with('status', 'Dépense supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'campaigns' => $this->campaigns(),
            'categories' => collect(ExpenseCategory::cases()),
        ];
    }

    private function campaigns()
    {
        return Campaign::query()->forFarm($this->farmId())->with('crop')->orderByDesc('start_date')->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('farm_id', $this->farmId())],
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'expense_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
