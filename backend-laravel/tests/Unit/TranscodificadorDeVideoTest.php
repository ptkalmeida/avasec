<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Midia\TranscodificadorDeVideo;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * O FFmpeg de verdade não roda aqui: o executor é trocado por um que grava os arquivos
 * que ele gravaria. Prende o contrato — comandos, ordem, e que falha vira exceção.
 */
final class TranscodificadorDeVideoTest extends TestCase
{
    private string $pasta;

    protected function setUp(): void
    {
        $this->pasta = sys_get_temp_dir().DIRECTORY_SEPARATOR.'avasec-test-'.bin2hex(random_bytes(6));
        mkdir($this->pasta, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->pasta.DIRECTORY_SEPARATOR.'{,*/}*', GLOB_BRACE) ?: [] as $caminho) {
            is_file($caminho) && unlink($caminho);
        }
        foreach (glob($this->pasta.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $dir) {
            @rmdir($dir);
        }
        @rmdir($this->pasta);
    }

    public function test_comando_do_mp4_usa_h264_aac_faststart_e_nao_amplia_o_video(): void
    {
        $cmd = (new TranscodificadorDeVideo('ffmpeg'))->comandoMp4('/in/a.mov', '/out/v.mp4', 720);

        $this->assertSame('ffmpeg', $cmd[0]);
        $this->assertContains('libx264', $cmd);
        $this->assertContains('aac', $cmd);
        $this->assertContains('+faststart', $cmd);
        $this->assertContains("scale=-2:'min(720,ih)'", $cmd);
        $this->assertSame('/out/v.mp4', end($cmd));
        // O áudio é opcional: vídeo sem trilha de áudio não pode derrubar o job.
        $this->assertContains('0:a:0?', $cmd);
    }

    public function test_nome_de_arquivo_com_metacaracteres_de_shell_vai_como_um_unico_argumento(): void
    {
        $nome = '/in/a; rm -rf $(whoami) `id`.mp4';
        $cmd = (new TranscodificadorDeVideo)->comandoMp4($nome, '/out/v.mp4', 480);

        $this->assertSame(1, count(array_keys($cmd, $nome, true)));
    }

    public function test_comando_do_hls_gera_playlist_vod_e_segmentos_numerados(): void
    {
        $cmd = (new TranscodificadorDeVideo)->comandoHls('/in/a.mp4', '/out/720p', 720);

        $this->assertContains('hls', $cmd);
        $this->assertContains('vod', $cmd);
        $this->assertContains('/out/720p'.DIRECTORY_SEPARATOR.'seg_%04d.ts', $cmd);
        $this->assertSame('/out/720p'.DIRECTORY_SEPARATOR.'index.m3u8', end($cmd));
    }

    public function test_gerar_mp4_executa_o_comando_com_o_timeout_configurado(): void
    {
        $recebido = null;
        $t = new TranscodificadorDeVideo('ffmpeg', 77, function (array $cmd, int $timeout) use (&$recebido): void {
            $recebido = $timeout;
            file_put_contents((string) end($cmd), 'mp4');
        });

        $t->gerarMp4('/in/a.mp4', $this->pasta.'/v.mp4', 720);

        $this->assertSame(77, $recebido);
        $this->assertFileExists($this->pasta.'/v.mp4');
    }

    public function test_gerar_mp4_falha_se_o_ffmpeg_nao_produziu_o_arquivo(): void
    {
        $t = new TranscodificadorDeVideo('ffmpeg', 60, static function (): void {});

        $this->expectException(RuntimeException::class);
        $t->gerarMp4('/in/a.mp4', $this->pasta.'/v.mp4', 720);
    }

    public function test_erro_do_ffmpeg_sobe_como_excecao(): void
    {
        $t = new TranscodificadorDeVideo('ffmpeg', 60, static function (): void {
            throw new RuntimeException('FFmpeg falhou: invalid data');
        });

        $this->expectExceptionMessage('invalid data');
        $t->gerarMp4('/in/a.mp4', $this->pasta.'/v.mp4', 720);
    }

    public function test_gerar_hls_cria_uma_variante_por_altura_e_o_master_que_as_aponta(): void
    {
        $t = new TranscodificadorDeVideo('ffmpeg', 60, static function (array $cmd): void {
            $playlist = (string) end($cmd);
            file_put_contents($playlist, "#EXTM3U\n");
            file_put_contents(dirname($playlist).'/seg_0000.ts', 'ts');
        });

        $arquivos = $t->gerarHls('/in/a.mp4', $this->pasta, [720, 360]);

        $this->assertEqualsCanonicalizing(
            ['720p/index.m3u8', '720p/seg_0000.ts', '360p/index.m3u8', '360p/seg_0000.ts', 'master.m3u8'],
            $arquivos,
        );
        $master = (string) file_get_contents($this->pasta.'/master.m3u8');
        $this->assertStringContainsString('720p/index.m3u8', $master);
        $this->assertStringContainsString('360p/index.m3u8', $master);
        $this->assertStringContainsString('#EXT-X-STREAM-INF:BANDWIDTH=2928000', $master);
    }

    public function test_gerar_hls_falha_se_uma_variante_nao_saiu(): void
    {
        $t = new TranscodificadorDeVideo('ffmpeg', 60, static function (): void {});

        $this->expectException(RuntimeException::class);
        $t->gerarHls('/in/a.mp4', $this->pasta, [720]);
    }
}
