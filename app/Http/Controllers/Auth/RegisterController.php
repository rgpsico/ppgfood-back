<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShop;
use App\Models\Plan;
use App\Providers\RouteServiceProvider;
use App\Services\AsaasService;
use App\Services\TenantService;

use App\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Tenant\Events\TenantCreated;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */

    protected $redirectTo = '/admin';
    protected $assasService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(AsaasService $assasService)
    {
        $this->assasService = $assasService; // Corrige o problema
        $this->middleware(['guest', 'check.selected.plan']);
    }


    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {

        return Validator::make($data, [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'min:3', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 'max:16', 'confirmed'],
            'empresa' => ['required', 'string', 'min:3', 'max:255', 'unique:tenants,name'],
            'cnpj' => ['required', 'numeric', 'digits:14', 'unique:tenants'],
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */

    protected function create(array $data)
    {

        $plan = session('plan');

        if (!$plan) {

            $plan = Plan::firstOrCreate(
                ['name' => 'Plano Básico'],
                [
                    'url' => 'plano-basico',
                    'price' => 0,
                    'description' => 'Plano básico padrão'
                ]
            );

            session(['plan' => $plan]);
        }

        $tenantService = app(TenantService::class);
        $user = $tenantService->make($plan, $data);

        event(new TenantCreated($user));

        return $user;
    }

    public function createEmpresa(Request $data)
    {
        try {



            $validatedData = $data->validate([
                'name' => ['required', 'string', 'min:3', 'max:255'],
                'email' => ['required', 'string', 'email', 'min:3', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:6', 'max:16', 'confirmed'],
                'empresa' => ['required', 'string', 'min:3', 'max:255', 'unique:tenants,name'],
                'cnpj' => ['required', 'numeric', 'unique:tenants']
            ]);
            $id_assas = $this->assasService->createCustomerLoja($data);

            $validatedData['asaas_key'] = $id_assas;


            $plan = session('plan');

            if (!$plan) {
                // Criar plano básico se não houver plano na sessão
                $plan = Plan::firstOrCreate(
                    ['name' => 'Plano Básico'],
                    [
                        'url' => 'plano-basico',
                        'price' => 0,
                        'description' => 'Plano básico padrão'
                    ]
                );
                session(['plan' => $plan]);
            }

            $tenantService = app(TenantService::class);
            $user = $tenantService->make($plan, $validatedData);

            event(new TenantCreated($user));



            return redirect()->route('admin.index')->with('success', 'Empresa registrada com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Ocorreu um erro inesperado',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
