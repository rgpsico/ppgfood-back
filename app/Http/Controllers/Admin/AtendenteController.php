<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atendente;
use App\Models\Order;
use App\Models\User;
use App\Services\AtendenteReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Cadastro e acompanhamento dos atendentes (funcionarios que atendem clientes) da empresa logada.
 * Tudo filtrado por tenant: Atendente e Order usam o TenantScope do usuario autenticado.
 */
class AtendenteController extends Controller
{
    public function __construct()
    {
        $this->middleware(['can:users']);
    }

    public function index(Request $request, AtendenteReportService $report)
    {
        [$de, $ate] = $this->periodo($request);

        $linhas = $report->resumo(auth()->user()->tenant_id, $de, $ate);

        return view('admin.pages.atendentes.index', [
            'linhas' => $linhas,
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
            'habilitado' => app(\App\Services\AtendimentoService::class)->habilitado(auth()->user()->tenant_id),
        ]);
    }

    public function create()
    {
        return view('admin.pages.atendentes.create');
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => 'required|string|max:100',
            'ativo' => 'nullable|boolean',
            'email' => 'nullable|email|max:255|unique:users,email|required_with:password',
            'password' => 'nullable|string|min:6|required_with:email',
        ]);

        DB::transaction(function () use ($dados) {
            $user = null;

            if (!empty($dados['email'])) {
                $user = User::create([
                    'name' => $dados['nome'],
                    'email' => $dados['email'],
                    'password' => Hash::make($dados['password']),
                    'tenant_id' => auth()->user()->tenant_id,
                ]);
            }

            Atendente::create([
                'nome' => $dados['nome'],
                'ativo' => $dados['ativo'] ?? true,
                'user_id' => $user ? $user->id : null,
            ]);
        });

        return redirect()->route('atendentes.index')->with('message', 'Atendente cadastrado.');
    }

    public function edit($id)
    {
        return view('admin.pages.atendentes.edit', ['atendente' => Atendente::with('user')->findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $atendente = Atendente::findOrFail($id);

        $dados = $request->validate([
            'nome' => 'required|string|max:100',
            'ativo' => 'nullable|boolean',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($atendente->user_id), 'required_with:password'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        DB::transaction(function () use ($dados, $atendente) {
            $user = $atendente->user;

            if ($user) {
                $user->name = $dados['nome'];
                if (!empty($dados['email'])) {
                    $user->email = $dados['email'];
                }
                if (!empty($dados['password'])) {
                    $user->password = Hash::make($dados['password']);
                }
                $user->save();
            } elseif (!empty($dados['email']) && !empty($dados['password'])) {
                $user = User::create([
                    'name' => $dados['nome'],
                    'email' => $dados['email'],
                    'password' => Hash::make($dados['password']),
                    'tenant_id' => auth()->user()->tenant_id,
                ]);
            }

            $atendente->update([
                'nome' => $dados['nome'],
                'ativo' => $dados['ativo'] ?? false,
                'user_id' => $user ? $user->id : null,
            ]);
        });

        return redirect()->route('atendentes.index')->with('message', 'Atendente atualizado.');
    }

    /** Ativa/desativa sem apagar: pedidos antigos continuam ligados ao atendente. */
    public function toggle($id)
    {
        $atendente = Atendente::findOrFail($id);
        $atendente->update(['ativo' => !$atendente->ativo]);

        return redirect()->route('atendentes.index')
            ->with('message', $atendente->ativo ? 'Atendente ativado.' : 'Atendente desativado.');
    }

    public function show(Request $request, $id)
    {
        $atendente = Atendente::findOrFail($id);
        [$de, $ate] = $this->periodo($request);

        $pedidos = Order::with('products', 'table', 'client')
            ->where('attendant_id', $atendente->id)
            ->whereBetween('created_at', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
            ->latest()
            ->paginate(30)
            ->appends(['de' => $de->toDateString(), 'ate' => $ate->toDateString()]);

        return view('admin.pages.atendentes.show', [
            'atendente' => $atendente,
            'pedidos' => $pedidos,
            'atendentes' => Atendente::orderBy('nome')->get(),
            'statusOptions' => (new Order)->statusOptions,
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
        ]);
    }

    /** Corrige quem atendeu o pedido. Destino precisa ser atendente da mesma empresa (o escopo garante). */
    public function reatribuir(Request $request, $orderId)
    {
        $request->validate(['attendant_id' => 'nullable|integer']);

        $order = Order::findOrFail($orderId);
        $novo = $request->attendant_id ? Atendente::findOrFail($request->attendant_id) : null;

        $order->update(['attendant_id' => $novo ? $novo->id : null]);

        return back()->with('message', 'Atendente do pedido atualizado.');
    }

    private function periodo(Request $request): array
    {
        try {
            $de = Carbon::parse($request->query('de', now()->subDays(30)->toDateString()));
            $ate = Carbon::parse($request->query('ate', now()->toDateString()));
        } catch (\Exception $e) {
            $de = now()->subDays(30);
            $ate = now();
        }

        return [$de, $ate];
    }
}
