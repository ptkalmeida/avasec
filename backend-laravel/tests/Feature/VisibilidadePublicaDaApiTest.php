<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LibraryItem;
use App\Models\WebinarEvent;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * `GET /api/library` e `GET /api/webinars` são públicos, mas a escada de
 * visibilidade vale para eles (achado da varredura de segurança de 23/10/2026):
 * antes devolviam TUDO que não estava inativado, e um visitante anônimo lia item
 * restrito (gestor e admin) e rascunho (só admin).
 */
final class VisibilidadePublicaDaApiTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function itemDaBiblioteca(string $titulo, int $status): void
    {
        LibraryItem::query()->create([
            'id' => 'lib-vis-'.uniqid(), 'title' => $titulo, 'type' => 'link', 'category' => 'Teste',
            'description' => 'x', 'url' => 'https://example.com/x',
        ]);
        LibraryItem::query()->where('title', $titulo)->update(['status' => $status]);
    }

    private function webinar(string $titulo, int $status): void
    {
        WebinarEvent::query()->create([
            'id' => 'web-vis-'.uniqid(), 'title' => $titulo, 'date' => '01/12/2030', 'time' => '10:00',
            'description' => 'x', 'link' => 'https://example.com/sala', 'image' => null,
        ]);
        WebinarEvent::query()->where('title', $titulo)->update(['status' => $status]);
    }

    /** @return list<string> */
    private function titulos(mixed $json): array
    {
        return array_values(array_map(static fn (array $i): string => (string) $i['title'], is_array($json) ? $json : []));
    }

    public function test_visitante_so_ve_a_biblioteca_publicada(): void
    {
        $this->itemDaBiblioteca('Lib Publicada Vis', Visibilidade::PUBLICADO);
        $this->itemDaBiblioteca('Lib Restrita Vis', Visibilidade::RESTRITO);
        $this->itemDaBiblioteca('Lib Rascunho Vis', Visibilidade::RASCUNHO);

        $titulos = $this->titulos($this->getJson('/api/library')->assertOk()->json());

        $this->assertContains('Lib Publicada Vis', $titulos);
        $this->assertNotContains('Lib Restrita Vis', $titulos);
        $this->assertNotContains('Lib Rascunho Vis', $titulos);
    }

    public function test_cada_papel_ve_ate_o_seu_nivel_na_biblioteca(): void
    {
        $this->itemDaBiblioteca('Lib Restrita Papel', Visibilidade::RESTRITO);
        $this->itemDaBiblioteca('Lib Rascunho Papel', Visibilidade::RASCUNHO);
        $aluno = $this->makeStudent('Aluno Lib Visibilidade');

        $comoAluno = $this->titulos($this->getJson('/api/library', ['Authorization' => 'Bearer '.$aluno['token']])->assertOk()->json());
        $this->assertNotContains('Lib Restrita Papel', $comoAluno);

        $comoGestor = $this->titulos($this->getJson('/api/library', ['Authorization' => 'Bearer '.$this->staffToken('instructor')])->assertOk()->json());
        $this->assertContains('Lib Restrita Papel', $comoGestor);
        $this->assertNotContains('Lib Rascunho Papel', $comoGestor);

        $comoAdmin = $this->titulos($this->getJson('/api/library', ['Authorization' => 'Bearer '.$this->staffToken('admin')])->assertOk()->json());
        $this->assertContains('Lib Rascunho Papel', $comoAdmin);
    }

    public function test_visitante_so_ve_webinar_publicado(): void
    {
        config(['features.eventosWebinars' => true]);

        $this->webinar('Webinar Publicado Vis', Visibilidade::PUBLICADO);
        $this->webinar('Webinar Restrito Vis', Visibilidade::RESTRITO);
        $this->webinar('Webinar Rascunho Vis', Visibilidade::RASCUNHO);

        $titulos = $this->titulos($this->getJson('/api/webinars')->assertOk()->json());

        $this->assertContains('Webinar Publicado Vis', $titulos);
        $this->assertNotContains('Webinar Restrito Vis', $titulos);
        $this->assertNotContains('Webinar Rascunho Vis', $titulos);

        $comoAdmin = $this->titulos($this->getJson('/api/webinars', ['Authorization' => 'Bearer '.$this->staffToken('admin')])->assertOk()->json());
        $this->assertContains('Webinar Rascunho Vis', $comoAdmin);
    }
}
