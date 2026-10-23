<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Exceptions\InsufficientProduceStockException;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Customer;
use App\Models\ProduceLot;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SaleController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $sales = Sale::query()
            ->forFarm($this->farmId())
            ->with(['customer', 'recordedBy'])
            ->withCount('items')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('sale_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('sale_date', '<=', $request->date('to')))
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $confirmed = Sale::query()->forFarm($this->farmId())->confirmed();

        return view('sales.index', [
            'sales' => $sales,
            'customers' => Customer::query()->forFarm($this->farmId())->orderBy('name')->get(),
            'statuses' => collect(SaleStatus::cases()),
            'paymentStatuses' => collect(PaymentStatus::cases()),
            'total' => (float) (clone $confirmed)->sum('total_amount'),
            'outstanding' => (float) (clone $confirmed)->sum('total_amount') - (float) (clone $confirmed)->sum('amount_paid'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Sale::class);

        $lots = ProduceLot::query()
            ->forFarm($this->farmId())
            ->available()
            ->with(['campaign.crop'])
            ->orderByDesc('harvested_at')
            ->get();

        $selectedLot = $request->integer('produce_lot_id') ?: $lots->first()?->id;

        return view('sales.create', [
            'customers' => Customer::query()->forFarm($this->farmId())->active()->orderBy('name')->get(),
            'lots' => $lots,
            'selectedLot' => $selectedLot,
        ]);
    }

    public function store(Request $request, SaleService $service): RedirectResponse
    {
        Gate::authorize('create', Sale::class);

        $farmId = $this->farmId();

        $validated = $request->validate([
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('farm_id', $farmId)],
            'sale_date' => ['required', 'date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produce_lot_id' => ['required', 'distinct', Rule::exists('produce_lots', 'id')->where('farm_id', $farmId)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $sale = $service->create($farmId, $validated, $validated['items'], $request->user());
        } catch (InsufficientProduceStockException $exception) {
            return back()->withInput()->withErrors(['items' => $exception->getMessage()]);
        }

        return redirect()->route('sales.show', $sale)->with('status', 'Vente enregistrée et stock mis à jour.');
    }

    public function show(Sale $sale): View
    {
        $sale->load([
            'customer',
            'items.produceLot.campaign.crop',
            'items.produceLot.harvest',
            'payments.recordedBy',
            'recordedBy',
            'revenue',
        ]);

        return view('sales.show', [
            'sale' => $sale,
            'paymentMethods' => collect(PaymentMethod::cases()),
        ]);
    }

    public function cancel(Request $request, Sale $sale, SaleService $service): RedirectResponse
    {
        Gate::authorize('cancel', $sale);

        try {
            $service->cancel($sale, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('sales.show', $sale)->with('status', 'Vente annulée, stock restauré et recette supprimée.');
    }

    public function storePayment(Request $request, Sale $sale, SaleService $service): RedirectResponse
    {
        Gate::authorize('recordPayment', $sale);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'paid_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $service->recordPayment($sale, $validated, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('sales.show', $sale)->with('status', 'Paiement enregistré.');
    }
}
