<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Liga a flag `verificacaoPublicaCertificados` só na classe de teste que usa este trait.
 *
 * A página pública de certificados e o autenticador por QR estão INATIVADOS por padrão
 * (ADR 027), mas o código continua no projeto e ligar a flag o devolve. Estes testes
 * continuam valendo para esse dia: sem eles, o código inativo apodreceria sem ninguém
 * ver. O comportamento do estado desligado (o padrão) está em `VerificacaoPublicaDesligadaTest`.
 *
 * O nome `setUpComVerificacaoPublicaLigada` é o que o `TestCase` do Laravel chama sozinho
 * (`setUp` + nome do trait), já com a aplicação de pé.
 */
trait ComVerificacaoPublicaLigada
{
    protected function setUpComVerificacaoPublicaLigada(): void
    {
        config(['features.verificacaoPublicaCertificados' => true]);
    }
}
