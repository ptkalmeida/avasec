<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessarVideoDaAula;
use App\Models\Curso;
use App\Models\Aula;
use App\Models\Usuario;
use App\Models\VersaoDeConteudo;
use App\Services\Midia\TranscodificadorDeVideo;
use App\Support\VideoProvider;
use App\Support\VideoSource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Mídia própria (ADR 023). O que importa prender: DESLIGADA não toca em nada, e
 * LIGADA só muda a aula no fim, sem apagar o original nem o link anterior.
 */
final class ProcessarVideoDaAulaTest extends TestCase
{
    use DatabaseTransactions;

    private const YOUTUBE = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    private const BASE = 'https://midia.exemplo.test/avasec';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('midia');
        config(['midia.disco' => 'midia', 'midia.url_publica' => self::BASE, 'midia.hls' => false]);
    }

    private function aulaComYoutube(): Aula
    {
        $dono = (string) Str::uuid();
        Usuario::query()->create([
            'id' => $dono,
            'nome' => 'Instrutor Mídia',
            'email' => Str::lower(Str::random(10)).'@example.com',
            'senha_hash' => password_hash('senha123456', PASSWORD_BCRYPT),
            'papel' => 'instructor',
        ]);
        $curso = Curso::query()->create([
            'id' => 'course-midia-'.uniqid(),
            'titulo' => 'Curso Da Mídia Teste',
            'descricao' => 'Descrição longa o bastante para o validador aceitar.',
            'categoria' => 'Teste',
            'miniatura' => 'sem-imagem.png',
            'instrutor_id' => $dono,
            'nome_instrutor' => 'Instrutor Mídia',
        ]);

        return Aula::query()->create([
            'id' => 'lesson-midia-'.uniqid(),
            'curso_id' => $curso->id,
            'titulo' => 'Aula com vídeo',
            'duracao' => '10 min',
            'url_video' => self::YOUTUBE,
            'conteudo' => 'Texto',
            'ordem' => 1,
        ]);
    }

    /** FFmpeg falso: grava o arquivo de saída que o real gravaria. */
    private function ffmpegFalso(): TranscodificadorDeVideo
    {
        return new TranscodificadorDeVideo('ffmpeg', 60, static function (array $cmd): void {
            $saida = (string) end($cmd);
            file_put_contents($saida, 'saida');
            if (str_ends_with($saida, 'index.m3u8')) {
                file_put_contents(dirname($saida).'/seg_0000.ts', 'ts');
            }
        });
    }

    private function executar(Aula $aula, ?TranscodificadorDeVideo $t = null): void
    {
        (new ProcessarVideoDaAula($aula->id, 'originais/'.$aula->id.'/original.mp4'))
            ->handle($t ?? $this->ffmpegFalso());
    }

    public function test_desligado_nao_toca_na_aula_nem_no_disco(): void
    {
        config(['midia.driver' => 'youtube']);
        $aula = $this->aulaComYoutube();

        $this->executar($aula, new TranscodificadorDeVideo('ffmpeg', 60, static function (): void {
            throw new RuntimeException('o FFmpeg não podia ser chamado');
        }));

        $this->assertSame(self::YOUTUBE, $aula->fresh()?->url_video);
        $this->assertSame([], Storage::disk('midia')->allFiles());
    }

    public function test_ligado_publica_o_mp4_e_so_entao_aponta_a_aula_para_ele(): void
    {
        config(['midia.driver' => 'minio']);
        $aula = $this->aulaComYoutube();
        Storage::disk('midia')->put('originais/'.$aula->id.'/original.mp4', 'video-original');

        $this->executar($aula);

        $url = (string) $aula->fresh()?->url_video;
        $this->assertStringStartsWith(self::BASE.'/aulas/'.$aula->id.'/', $url);
        $this->assertStringEndsWith('/video.mp4', $url);
        // O endereço gravado é um que o resto do sistema já sabe ler como arquivo.
        $this->assertSame(VideoProvider::File, VideoSource::tryParse($url)?->provider);

        $chave = substr($url, strlen(self::BASE) + 1);
        Storage::disk('midia')->assertExists($chave);
    }

    public function test_o_original_e_o_link_anterior_nao_sao_perdidos(): void
    {
        config(['midia.driver' => 'minio']);
        $aula = $this->aulaComYoutube();
        Storage::disk('midia')->put('originais/'.$aula->id.'/original.mp4', 'video-original');

        $this->executar($aula);

        Storage::disk('midia')->assertExists('originais/'.$aula->id.'/original.mp4');
        $this->assertSame('video-original', Storage::disk('midia')->get('originais/'.$aula->id.'/original.mp4'));
        // ADR 12: o link do YouTube que saiu da aula ficou no histórico de versões.
        $versao = VersaoDeConteudo::query()->where('referencia', $aula->id)->first();
        $this->assertNotNull($versao);
        $this->assertStringContainsString('dQw4w9WgXcQ', (string) $versao->conteudo);
    }

    public function test_duas_execucoes_nao_sobrescrevem_a_saida_uma_da_outra(): void
    {
        config(['midia.driver' => 'minio']);
        $aula = $this->aulaComYoutube();
        Storage::disk('midia')->put('originais/'.$aula->id.'/original.mp4', 'video-original');

        $this->executar($aula);
        $primeira = (string) $aula->fresh()?->url_video;
        $this->executar($aula);
        $segunda = (string) $aula->fresh()?->url_video;

        $this->assertNotSame($primeira, $segunda);
        Storage::disk('midia')->assertExists(substr($primeira, strlen(self::BASE) + 1));
        Storage::disk('midia')->assertExists(substr($segunda, strlen(self::BASE) + 1));
    }

    public function test_com_hls_ligado_envia_o_master_e_as_variantes(): void
    {
        config(['midia.driver' => 'minio', 'midia.hls' => true, 'midia.alturas' => [720, 360]]);
        $aula = $this->aulaComYoutube();
        Storage::disk('midia')->put('originais/'.$aula->id.'/original.mp4', 'video-original');

        $this->executar($aula);

        $pasta = dirname(substr((string) $aula->fresh()?->url_video, strlen(self::BASE) + 1));
        Storage::disk('midia')->assertExists($pasta.'/hls/master.m3u8');
        Storage::disk('midia')->assertExists($pasta.'/hls/720p/index.m3u8');
        Storage::disk('midia')->assertExists($pasta.'/hls/360p/seg_0000.ts');
    }

    public function test_falha_do_ffmpeg_deixa_a_aula_como_estava(): void
    {
        config(['midia.driver' => 'minio']);
        $aula = $this->aulaComYoutube();
        Storage::disk('midia')->put('originais/'.$aula->id.'/original.mp4', 'video-original');

        try {
            $this->executar($aula, new TranscodificadorDeVideo('ffmpeg', 60, static function (): void {
                throw new RuntimeException('FFmpeg falhou');
            }));
            $this->fail('Era esperada a exceção do FFmpeg.');
        } catch (RuntimeException) {
            // esperado
        }

        $this->assertSame(self::YOUTUBE, $aula->fresh()?->url_video);
        $this->assertSame(['originais/'.$aula->id.'/original.mp4'], Storage::disk('midia')->allFiles());
    }

    public function test_sem_url_publica_o_job_se_recusa_e_nao_grava_endereco_sem_dono(): void
    {
        config(['midia.driver' => 'minio', 'midia.url_publica' => '']);
        $aula = $this->aulaComYoutube();

        $this->expectExceptionMessage('MEDIA_PUBLIC_URL');
        $this->executar($aula);
    }

    public function test_original_ausente_falha_em_vez_de_fingir_sucesso(): void
    {
        config(['midia.driver' => 'minio']);
        $aula = $this->aulaComYoutube();

        $this->expectExceptionMessage('Original não encontrado');
        $this->executar($aula);
    }

    public function test_o_job_vai_para_a_fila_propria_que_o_worker_atual_nao_consome(): void
    {
        $job = new ProcessarVideoDaAula('x', 'y');

        $this->assertSame('midia', $job->queue);
        $this->assertNotSame('default', $job->queue);
    }
}
