<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Pedido sem atendimento volta a avisar no celular de 30 em 30 segundos (ate 10 min): o agendador roda
        // 1x por minuto e o comando confere 2 vezes (0s e 30s). Precisa do cron: schedule:run a cada minuto
        // Sinal de vida do agendador: a tela de super admin avisa quando o cron para
        $schedule->call(fn () => \Illuminate\Support\Facades\Cache::put('scheduler:heartbeat', now()->toIso8601String(), 3600))->everyMinute();

        $schedule->command('pedidos:lembrar --rodadas=2 --a-cada=30')->everyMinute()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
