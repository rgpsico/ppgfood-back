@extends('site.layouts.app')

@section('content')
<div class="plans-page">
    <header class="plans-header">
        <div class="wrap">
            <span class="eyebrow">PPGFood</span>
            <h1>Escolha o plano da sua barraca</h1>
            <p class="sub">Comece a receber pedidos pelo celular hoje mesmo. Sem fidelidade, cancele quando quiser.</p>
        </div>
    </header>

    <main class="wrap plans-main">
        <div class="plans-grid" style="--plan-count: {{ max($plans->count(), 1) }}">
            @forelse($plans as $plan)
                <div class="plan-card">
                    <h2 class="plan-name">{{ $plan->name }}</h2>
                    <div class="plan-price">
                        <span class="currency">R$</span>
                        <span class="amount">{{ number_format($plan->price, 2, ',', '.') }}</span>
                        <span class="period">/mês</span>
                    </div>

                    @if($plan->details->isNotEmpty())
                        <ul class="plan-features">
                            @foreach ($plan->details as $detail)
                                <li>
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5 6.5 12 13 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    {{ $detail->name }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <a href="{{ route('plan.subscription', $plan->url) }}" class="plan-cta">Assinar agora</a>
                </div>
            @empty
                <p class="plans-empty">Nenhum plano disponível no momento.</p>
            @endforelse
        </div>
    </main>
</div>
@endsection
