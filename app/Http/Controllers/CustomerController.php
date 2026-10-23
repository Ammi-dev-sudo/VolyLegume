<?php

namespace App\Http\Controllers;

use App\Enums\CustomerType;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $customers = Customer::query()
            ->forFarm($this->farmId())
            ->withCount('sales')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn ($query) => $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%'));
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'types' => collect(CustomerType::cases()),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Customer::class);

        return view('customers.create', [
            'customer' => new Customer(['type' => CustomerType::Individual, 'is_active' => true]),
            'types' => collect(CustomerType::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Customer::class);

        Customer::create($this->validated($request) + ['farm_id' => $this->farmId()]);

        return redirect()->route('customers.index')->with('status', 'Client enregistré.');
    }

    public function edit(Customer $customer): View
    {
        Gate::authorize('update', $customer);

        return view('customers.edit', [
            'customer' => $customer,
            'types' => collect(CustomerType::cases()),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);

        $customer->update($this->validated($request, $customer));

        return redirect()->route('customers.index')->with('status', 'Client mis à jour.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        if ($customer->sales()->exists()) {
            return back()->withErrors(['customer' => 'Ce client possède des ventes : il ne peut pas être supprimé.']);
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('status', 'Client supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Customer $customer = null): array
    {
        $farmId = $this->farmId();

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('customers', 'code')->where('farm_id', $farmId)->ignore($customer?->id)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(CustomerType::class)],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = $customer?->code ?? $this->nextCode($farmId);
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }

    private function nextCode(int $farmId): string
    {
        $sequence = Customer::query()->where('farm_id', $farmId)->count() + 1;

        do {
            $code = 'CLI-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Customer::query()->where('farm_id', $farmId)->where('code', $code)->exists());

        return $code;
    }
}
