<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Usuario;
use App\Support\Cep;
use App\Support\Cpf;
use App\Support\Fuso;
use App\Support\InstructorScope;
use App\Support\Jwt;
use App\Support\Sessoes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Regras de negócio de autenticação — espelha src/server/services/authService.ts.
 *
 * Hash de senha usa as funções NATIVAS do PHP (password_verify/password_hash com
 * PASSWORD_BCRYPT cost 10), não o Hash facade do Laravel: isso garante interop com
 * os hashes $2a$ gerados pelo bcryptjs do Node (o facade rejeita esse prefixo) e
 * gera hashes $2y$ que o bcryptjs do Node também consegue verificar.
 *
 * @phpstan-type PublicUser array{id: string, nome: string, email: string, papel: string, status: string, municipio?: string, uf?: string, area_interesse?: string, data_cadastro_texto?: string}
 */
final class AuthService
{
    /** Erros de senha por (identificador, rede) até travar ESSE acesso — não a conta de quem se conhece o CPF. */
    private const MAX_FAILED_ATTEMPTS = 5;

    /** Erros somados, vindos de qualquer rede, até travar a conta para todos (força bruta distribuída). */
    private const MAX_FAILED_ATTEMPTS_CONTA = 30;

    private const LOCKOUT_MINUTES = 15;

    private const BCRYPT_COST = 10;

    /**
     * @param  array{nome:string,email:string,password:string,papel?:string|null,cpf?:string|null,municipio?:string|null,uf?:string|null,area_interesse?:string|null,data_cadastro_texto?:string|null,celular?:string|null,cep?:string|null,endereco?:string|null,nome_social?:string|null,identidade?:string|null}  $input
     * @param  array{sub:string,nome:string,papel:string}|null  $requester
     * @return array{token: string|null, usuario: PublicUser, duplicado?: bool}
     */
    public function register(array $input, ?array $requester): array
    {
        $isAdminProvisioning = ($requester['papel'] ?? null) === 'admin';

        $requestedRole = $input['papel'] ?? null;
        if (! $isAdminProvisioning && $requestedRole !== null && $requestedRole !== 'student') {
            throw ApiException::forbidden('Apenas administradores podem criar contas de instrutor ou administrador.');
        }

        $emailJaExiste = Usuario::query()->withTrashed()->where('email', $input['email'])->exists();
        if ($emailJaExiste && $isAdminProvisioning) {
            throw new ApiException(409, 'CONFLICT', 'Já existe um usuário cadastrado com este e-mail.');
        }

        // CPF é o identificador de login do aluno (ADR 11) — guardado só em
        // dígitos, e conflito precisa ser 409 explícito e não erro de índice.
        $cpf = isset($input['cpf']) && $input['cpf'] !== ''
            ? Cpf::normalize($input['cpf'])
            : null;
        $cpfJaExiste = $cpf !== null && Usuario::query()->withTrashed()->where('cpf', $cpf)->exists();
        if ($cpfJaExiste && $isAdminProvisioning) {
            throw new ApiException(409, 'CONFLICT', 'Já existe um usuário cadastrado com este CPF.');
        }

        /*
         * Cadastro PÚBLICO com e-mail ou CPF que já existem: a resposta é a mesma de um
         * cadastro novo, e nada é gravado (ADR 022). O 409 confirmava a qualquer visitante
         * quem é aluno da escola. Quem já tem conta lê, na própria mensagem da tela, que
         * deve entrar com CPF e senha; a tentativa fica na trilha de auditoria (`duplicado`).
         * O hash roda mesmo assim para o tempo de resposta não denunciar o caso.
         */
        if ($emailJaExiste || $cpfJaExiste) {
            $this->hash($input['password']);

            return [
                'token' => null,
                'duplicado' => true,
                'usuario' => [
                    'id' => $this->generateId(),
                    'nome' => $input['nome'],
                    'email' => $input['email'],
                    'papel' => 'student',
                    'status' => 'pending_confirmation',
                ],
            ];
        }

        $role = 'student';
        if ($isAdminProvisioning && in_array($requestedRole, ['student', 'instructor', 'admin'], true)) {
            $role = $requestedRole;
        }
        $status = $isAdminProvisioning ? 'active' : 'pending_confirmation';

        $user = new Usuario;
        $user->id = $this->generateId();
        $user->nome = $input['nome'];
        $user->email = $input['email'];
        $user->senha_hash = $this->hash($input['password']);
        $user->papel = $role;
        $user->status = $status;
        $user->cpf = $cpf;
        $user->municipio = $input['municipio'] ?? null;
        $user->uf = $input['uf'] ?? null;
        $user->area_interesse = $input['area_interesse'] ?? null;
        // Data local: cadastro às 22h BRT registrava o dia seguinte em UTC.
        $user->data_cadastro_texto = $input['data_cadastro_texto'] ?? Fuso::agora()->format('Y-m-d');
        $user->celular = $input['celular'] ?? null;
        $user->cep = isset($input['cep']) && $input['cep'] !== ''
            ? Cep::normalize($input['cep'])
            : null;
        $user->endereco = $input['endereco'] ?? null;
        $user->nome_social = $input['nome_social'] ?? null;
        $user->identidade = $input['identidade'] ?? null;
        $user->tentativas_login_falhas = 0;
        $user->bloqueado_ate = null;
        $user->save();

        // Token só para a própria pessoa quando a conta nasce ativa (cadastro público
        // vira pending -> sem token; provisionamento por admin é conta de terceiro -> sem token).
        $token = ($status === 'active' && ! $isAdminProvisioning)
            ? Jwt::issue($user->id, $user->nome, $user->papel)
            : null;

        return ['token' => $token, 'usuario' => $this->toPublicUser($user)];
    }

    /**
     * @param  array{nome?:string|null,email?:string|null,cpf?:string|null,password:string,papel?:string|null,ip?:string|null}  $input
     * @return array{token: string, usuario: PublicUser}
     */
    public function login(array $input): array
    {
        $papel = $input['papel'] ?? null;

        /*
         * Bloqueio por (identificador, rede), e não só por conta (ADR 022). Antes, 5 senhas
         * erradas de QUALQUER lugar trancavam a conta por 15 min: quem sabe o CPF de alguém
         * a trancava à vontade. Agora cada rede tem o próprio contador, então quem erra de
         * propósito tranca só o próprio acesso. O contador conta também identificador que não
         * existe, para que o 429 não revele quais CPFs têm conta. A conta só trava para todos
         * após MAX_FAILED_ATTEMPTS_CONTA erros somados (força bruta vinda de muitas redes).
         */
        $chaveDeFalha = self::chaveDeFalha($input);
        if (RateLimiter::tooManyAttempts($chaveDeFalha, self::MAX_FAILED_ATTEMPTS)) {
            throw self::contaTravada();
        }

        // Três identificadores possíveis (ADR 11): CPF é o do aluno; e-mail é o
        // de admin/gestor; `name` permanece para as contas demo internas.
        // A busca por CPF normaliza antes, para casar com o que está gravado.
        if (! empty($input['cpf'])) {
            $user = Usuario::query()->where('cpf', Cpf::normalize($input['cpf']))->first();
        } elseif (! empty($input['email'])) {
            $user = Usuario::query()->where('email', $input['email'])->first();
        } else {
            /*
             * Nome não é único: um aluno e um gestor podem ter o mesmo. Sem o
             * papel, `first()` pegava qualquer um dos dois — e o cartão de
             * Gestão chegou a autenticar um aluno. Com o papel, a busca já
             * começa restrita a ele.
             */
            $porNome = Usuario::query()->where('nome', $input['nome'] ?? '');
            if ($papel !== null) {
                $porNome->where('papel', $papel);
            }
            $user = $porNome->first();
        }

        // Conta de outro papel que o pedido é tratada como inexistente: mesma
        // mensagem genérica, sem contar tentativa contra a conta de outra pessoa.
        if ($user !== null && $papel !== null && $user->papel !== $papel) {
            $user = null;
        }

        // Mensagem genérica sempre — nunca revela se o identificador existe.
        if ($user === null) {
            RateLimiter::hit($chaveDeFalha, self::LOCKOUT_MINUTES * 60);
            throw ApiException::unauthorized('Usuário ou senha inválidos.');
        }

        if ($user->bloqueado_ate !== null && $user->bloqueado_ate->isFuture()) {
            throw self::contaTravada();
        }

        if (! password_verify($input['password'], $user->senha_hash)) {
            RateLimiter::hit($chaveDeFalha, self::LOCKOUT_MINUTES * 60);
            $nextAttempts = $user->tentativas_login_falhas + 1;
            $shouldLock = $nextAttempts >= self::MAX_FAILED_ATTEMPTS_CONTA;
            $user->tentativas_login_falhas = $shouldLock ? 0 : $nextAttempts;
            $user->bloqueado_ate = $shouldLock
                ? CarbonImmutable::now()->addMinutes(self::LOCKOUT_MINUTES)
                : null;
            $user->save();
            throw ApiException::unauthorized('Usuário ou senha inválidos.');
        }

        RateLimiter::clear($chaveDeFalha);
        if ($user->tentativas_login_falhas > 0 || $user->bloqueado_ate !== null) {
            $user->tentativas_login_falhas = 0;
            $user->bloqueado_ate = null;
            $user->save();
        }

        // Senha correta mas conta sem acesso: NÃO emite token (distingue o motivo).
        if ($user->status === 'blocked') {
            throw ApiException::accountBlocked();
        }
        if ($user->status === 'pending_confirmation') {
            throw new ApiException(
                403,
                'ACCOUNT_PENDING_CONFIRMATION',
                'Seu cadastro ainda está aguardando confirmação da coordenação. Você será liberado para acessar a plataforma assim que for homologado.',
            );
        }

        $token = Jwt::issue($user->id, $user->nome, $user->papel);

        return ['token' => $token, 'usuario' => $this->toPublicUser($user)];
    }

    /** @return PublicUser */
    public function getCurrentUser(string $usuario_id): array
    {
        $user = Usuario::query()->find($usuario_id);
        if ($user === null) {
            throw ApiException::notFound('Usuário não encontrado.');
        }

        return $this->toPublicUser($user);
    }

    /**
     * A senha informada é a desta conta? Só leitura.
     *
     * Existe para confirmar uma alteração sensível (o nome, que sai nos
     * certificados) sem passar pelo LOGIN. O perfil do React confirmava
     * entrando de novo — e login errado conta tentativa: com cinco, a conta
     * ficava bloqueada por quinze minutos, por causa de um erro de digitação
     * numa tela de perfil. Aqui não há contador; o limite contra força bruta
     * fica no limitador da rota (`auth-password`, por usuário).
     */
    public function senhaConfere(string $usuario_id, string $senha): bool
    {
        $user = Usuario::query()->find($usuario_id);

        return $user !== null
            && is_string($user->senha_hash)
            && password_verify($senha, $user->senha_hash);
    }

    /**
     * Troca a própria senha e encerra TODAS as sessões da conta (ADR 022); devolve o token
     * da sessão atual, que continua valendo, para o controller reemitir o cookie.
     */
    public function changePassword(string $usuario_id, string $newPassword, string $currentPassword): string
    {
        $user = Usuario::query()->find($usuario_id);
        if ($user === null) {
            throw ApiException::notFound('Usuário não encontrado.');
        }

        // A senha atual é SEMPRE verificada (autosserviço). Reset sem a senha atual,
        // se necessário, deve ser um fluxo administrativo separado e auditado.
        if (! password_verify($currentPassword, $user->senha_hash)) {
            throw ApiException::unauthorized('Senha atual incorreta.');
        }

        $user->senha_hash = $this->hash($newPassword);
        $user->save();
        Sessoes::encerrarTodas($user->id);

        return Jwt::issue($user->id, $user->nome, $user->papel);
    }

    /**
     * Redefinição administrativa: troca a senha SEM a senha atual, porque quem faz não
     * a conhece. É o "fluxo administrativo separado e auditado" citado acima, que até
     * agora não existia — a interface de admin oferecia "Redefinir Senha" e só mexia em
     * estado local, então a coordenação acreditava ter revogado um acesso que continuava
     * valendo. Restrita a admin na rota; a auditoria fica no controller.
     *
     * @return array<string, mixed> usuário público (nunca o hash)
     */
    public function adminResetPassword(string $usuario_id, string $newPassword): array
    {
        $user = Usuario::query()->find($usuario_id);
        if ($user === null) {
            throw ApiException::notFound('Usuário não encontrado.');
        }

        $user->senha_hash = $this->hash($newPassword);
        $user->save();
        // Redefinir é, quase sempre, retomar uma conta em risco: quem a tinha aberta sai (ADR 022).
        Sessoes::encerrarTodas($user->id);

        return $this->toPublicUser($user);
    }

    /**
     * A coordenação troca o e-mail (o login) de um gestor. O e-mail é único entre TODAS as
     * contas, inclusive as inativadas: nada é apagado, e reusar o endereço de uma conta
     * retirada faria dois cadastros disputarem o mesmo login se ela voltasse. Quem tinha a
     * conta aberta sai (ADR 022): o login mudou, a sessão antiga não vale mais.
     *
     * @return array<string, mixed> usuário público (nunca o hash)
     */
    public function adminChangeEmail(string $usuario_id, string $email): array
    {
        $user = Usuario::query()->find($usuario_id);
        if ($user === null) {
            throw ApiException::notFound('Usuário não encontrado.');
        }

        $emUso = Usuario::query()->withTrashed()->where('email', $email)->where('id', '!=', $usuario_id)->exists();
        if ($emUso) {
            throw new ApiException(409, 'CONFLICT', 'Já existe um usuário cadastrado com este e-mail.');
        }

        $user->email = $email;
        $user->save();
        Sessoes::encerrarTodas($user->id);

        return $this->toPublicUser($user);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function listUsersByRole(?string $role, int $skip, int $take): array
    {
        $query = Usuario::query();
        if ($role !== null) {
            $query->where('papel', $role);
        }
        $total = (clone $query)->count();
        $items = $query->orderBy('criado_em')->skip($skip)->take($take)->get()
            ->map(fn (Usuario $u) => $this->toPublicUser($u))->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Escopo por perfil: instrutor só vê alunos matriculados/admitidos nos próprios
     * cursos. Cursos por FK (instrutor_id) com fallback por nome (legado); alunos por
     * FK (usuario_id) com fallback por nome. Espelha listStudentsForInstructor do Node.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function listStudentsForInstructor(string $instructorSub, string $nome_instrutor, int $skip, int $take): array
    {
        $requester = ['sub' => $instructorSub, 'nome' => $nome_instrutor, 'papel' => 'instructor'];

        // Vínculo por FK apenas (ADR 10). Fonte única do escopo: InstructorScope.
        $studentIds = InstructorScope::studentIds($requester);
        if (count($studentIds) === 0) {
            return ['items' => [], 'total' => 0];
        }

        $query = Usuario::query()->where('papel', 'student')->whereIn('id', $studentIds);

        $total = (clone $query)->count();
        $items = $query->orderBy('criado_em')->skip($skip)->take($take)->get()
            ->map(fn (Usuario $u) => $this->toPublicUser($u))->all();

        return ['items' => $items, 'total' => $total];
    }

    /** @return PublicUser */
    public function updateAccountStatus(string $usuario_id, string $status): array
    {
        $user = Usuario::query()->find($usuario_id);
        if ($user === null) {
            throw ApiException::notFound('Usuário não encontrado.');
        }
        if (! in_array($status, ['active', 'blocked', 'pending_confirmation'], true)) {
            throw ApiException::validation('Status de conta inválido.');
        }
        $estavaAtiva = $user->status === 'active';
        $user->status = $status;
        $user->save();
        // Conta bloqueada já é recusada a cada requisição, com a mensagem certa (403, e não 401).
        // O que o bloqueio não pode fazer é deixar o token de antes VOLTAR a valer quando a conta
        // for reativada: reativar encerra as sessões antigas, e a pessoa entra com login novo (ADR 022).
        if ($status === 'active' && ! $estavaAtiva) {
            Sessoes::encerrarTodas($user->id);
        }

        return $this->toPublicUser($user);
    }

    /**
     * Inativa a conta preservando o histórico acadêmico inteiro (ADR 12).
     *
     * Isto era `Usuario::query()->where('id', $usuario_id)->delete()`, e as chaves
     * estrangeiras estão em ON DELETE CASCADE: apagar UMA pessoa destruía as
     * notas de avaliação (`tentativa_avaliacao`), os trabalhos entregues e corrigidos
     * (`entrega_exercicio`), o progresso e as presenças (`progresso_aluno`), a
     * matrícula, os requerimentos e o histórico de atendimento — e o certificado
     * sobrevivia apontando para ninguém (`Certificado.usuario_id` é SET NULL).
     *
     * A pessoa sai do ar; o que ela cursou continua reconstituível.
     *
     * @param  array{sub:string,nome:string,papel:string}|null  $requester
     */
    public function deleteUser(string $usuario_id, ?array $requester = null, ?string $motivo = null): void
    {
        $user = Usuario::query()->find($usuario_id);
        if ($user === null) {
            return;
        }

        // Bloquear a conta junto: `inativado_em` tira das listagens, e o `status`
        // é o que o middleware de autenticação consulta para recusar o login.
        $user->status = 'blocked';
        $user->saveQuietly();
        $user->inativar($requester['sub'] ?? null, $motivo);
    }

    /**
     * Devolve ao ar uma conta retirada (ADR 12) — aluno ou gestor.
     *
     * `deleteUser` bloqueia a conta junto com a inativação, então reativar tem de
     * desfazer as duas: tirar do estado inativo **e** voltar o `status` para `active`.
     * Só `reativar()` deixaria a pessoa listada de novo, porém ainda barrada no login.
     *
     * O `status` anterior à retirada não é guardado (o bloqueio o sobrescreve), então
     * quem aperta "Reativar" decide: a conta volta ativa, sem passar de novo pela
     * confirmação de cadastro. É a decisão da coordenação, e fica na auditoria.
     *
     * As sessões de antes são encerradas (ADR 22): um token emitido antes da retirada
     * não pode voltar a valer só porque a conta voltou. A pessoa entra com login novo.
     * O admin não se reativa por aqui: a conta administrativa nem é retirável pela tela.
     *
     * @return PublicUser
     */
    public function reactivateUser(string $usuario_id): array
    {
        $user = Usuario::onlyTrashed()->whereIn('papel', ['student', 'instructor'])->find($usuario_id);
        if ($user === null) {
            throw ApiException::notFound('Conta retirada não encontrada.');
        }

        $user->reativar();

        $publico = $this->updateAccountStatus($usuario_id, 'active');
        // Explícito, e não só pelo que `updateAccountStatus` faz: ele só encerra as sessões
        // quando a conta NÃO estava ativa, e uma conta retirada por um caminho antigo
        // (anterior ao bloqueio junto da retirada) pode ter ficado com `status = active`.
        Sessoes::encerrarTodas($usuario_id);

        return $publico;
    }

    /**
     * Rename seguro (ADR 10): identidade é o id, então mudar o nome é atualizar
     * o Usuario e os SNAPSHOTS de exibição das satélites na mesma transação.
     * EXCEÇÃO: Certificado.nome_aluno fica intocado — é o nome impresso no PDF
     * emitido e a chave humana da verificação pública (documento histórico).
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return PublicUser
     */
    public function renameUser(string $usuario_id, string $newName, array $requester): array
    {
        if ($requester['papel'] !== 'admin' && $requester['sub'] !== $usuario_id) {
            throw ApiException::forbidden('Você só pode alterar o próprio nome.');
        }
        $user = Usuario::query()->find($usuario_id);
        if ($user === null) {
            throw ApiException::notFound('Usuário não encontrado.');
        }

        DB::transaction(function () use ($user, $newName): void {
            $user->nome = $newName;
            $user->save();

            foreach (['matricula', 'progresso_aluno', 'solicitacao_ingresso', 'solicitacao_academica', 'tentativa_avaliacao', 'entrega_exercicio'] as $table) {
                DB::table($table)->where('usuario_id', $user->id)->update(['nome_aluno' => $newName]);
            }
            DB::table('mensagem_direta')->where('aluno_usuario_id', $user->id)->update(['nome_aluno' => $newName]);
            foreach (['mensagem_direta', 'mensagem_chat', 'mensagem_forum'] as $table) {
                DB::table($table)->where('remetente_usuario_id', $user->id)->update(['nome_remetente' => $newName]);
            }
            DB::table('curso')->where('instrutor_id', $user->id)->update(['nome_instrutor' => $newName]);
        });

        return $this->toPublicUser($user);
    }

    /** @return PublicUser */
    public function toPublicUser(Usuario $user): array
    {
        $public = [
            'id' => $user->id,
            'nome' => $user->nome,
            'email' => $user->email,
            'papel' => $user->papel,
            'status' => $user->status,
        ];
        // Campos opcionais só aparecem quando presentes (mesma forma do toPublicUser do Node).
        if ($user->municipio !== null) {
            $public['municipio'] = $user->municipio;
        }
        if ($user->uf !== null) {
            $public['uf'] = $user->uf;
        }
        if ($user->area_interesse !== null) {
            $public['area_interesse'] = $user->area_interesse;
        }
        if ($user->data_cadastro_texto !== null) {
            $public['data_cadastro_texto'] = $user->data_cadastro_texto;
        }

        return $public;
    }

    /**
     * Chave do contador de erros: identificador normalizado + papel + rede.
     *
     * @param  array{nome?:string|null,email?:string|null,cpf?:string|null,password:string,papel?:string|null,ip?:string|null}  $input
     */
    private static function chaveDeFalha(array $input): string
    {
        $cpf = $input['cpf'] ?? null;
        $email = $input['email'] ?? null;
        $identificador = ! empty($cpf)
            ? 'cpf:'.Cpf::normalize((string) $cpf)
            : (! empty($email) ? 'email:'.mb_strtolower((string) $email) : 'nome:'.mb_strtolower((string) ($input['nome'] ?? '')));

        return 'login-falha:'.sha1($identificador.'|'.($input['papel'] ?? '').'|'.($input['ip'] ?? ''));
    }

    private static function contaTravada(): ApiException
    {
        return new ApiException(
            429,
            'ACCOUNT_LOCKED',
            'Conta temporariamente bloqueada após várias tentativas de login. Tente novamente em alguns minutos.',
        );
    }

    private function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    private function generateId(): string
    {
        // Id opaco e único para novas contas criadas pelo Laravel (o Prisma usa cuid();
        // o formato não importa para integridade — só precisa ser único e estável).
        return 'usr_'.bin2hex(random_bytes(12));
    }
}
