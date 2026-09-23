<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $dpto = $request->query('dpto');

        $customers = Customer::withCount('orders')
            ->when($q !== '', fn ($x) => $x->where(fn ($y) => $y->where('code', 'like', "%$q%")->orWhere('name', 'like', "%$q%")->orWhere('tax_id', 'like', "%$q%")->orWhere('city', 'like', "%$q%")))
            ->when($dpto, fn ($x) => $x->where('department', $dpto))
            ->orderBy('name')->paginate(40)->withQueryString();

        return view('customers.index', [
            'title' => 'Clientes', 'customers' => $customers, 'q' => $q, 'dpto' => $dpto,
            'departments' => Customer::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'editing' => $request->query('editar') ? Customer::find($request->query('editar')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $c = Customer::create($data + ['is_active' => true]);

        return redirect()->route('customers.index', ['q' => $c->code])->with('toast', "Cliente {$c->name} creado.");
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $this->validated($request, $customer->id);
        $customer->fill($data + ['is_active' => $request->boolean('is_active', true)])->save();

        return redirect()->route('customers.index', ['q' => $customer->code])->with('toast', 'Cliente actualizado.');
    }

    private function validated(Request $request, ?int $ignore = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:customers,code'.($ignore ? ",$ignore" : '')],
            'name' => ['required', 'string', 'max:160'],
            'tax_id' => ['nullable', 'string', 'max:30'], 'contact_name' => ['nullable', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:200'], 'department' => ['nullable', 'string', 'max:60'], 'province' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:80'], 'zone' => ['nullable', 'string', 'max:60'], 'channel' => ['nullable', 'string', 'max:40'],
        ], [], ['code' => 'código', 'name' => 'razón social']);
    }
}
