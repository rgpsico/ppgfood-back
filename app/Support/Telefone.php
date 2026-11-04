<?php

namespace App\Support;

/**
 * Celular do cliente: guardado so com digitos, sem o 55 do Brasil, para o mesmo numero
 * digitado de jeitos diferentes ("(21) 99999-9999", "+55 21 99999 9999") ser sempre o mesmo.
 */
class Telefone
{
    public static function normalizar($valor): string
    {
        $digitos = preg_replace('/\D+/', '', (string) $valor);

        // +55 DDD numero (12 ou 13 digitos): tira o 55 do pais
        if (in_array(strlen($digitos), [12, 13], true) && strpos($digitos, '55') === 0) {
            $digitos = substr($digitos, 2);
        }

        return ltrim($digitos, '0') === '' ? '' : $digitos;
    }
}
