<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Providers\RouteServiceProvider;
use App\Services\TenantService;

use App\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Tenant\Events\TenantCreated;


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

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
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
            //   'cnpj' => ['required', 'numeric', 'digits:14', 'unique:tenants'],
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
            // Criar plano básico se não houver plano na sessão
            $plan = Plan::firstOrCreate(
                ['name' => 'Plano Básico'],
                [
                    'url' => 'plano-basico',
                    'price' => 0,
                    'description' => 'Plano básico padrão'
                ]
            );

            // Opcional: Salvar o plano na sessão para futuras referências
            session(['plan' => $plan]);
        }

        $tenantService = app(TenantService::class);
        $user = $tenantService->make($plan, $data);

        event(new TenantCreated($user));

        return $user;
    }
}
