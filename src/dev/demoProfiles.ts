/**
 * Perfis de demonstração — SOMENTE desenvolvimento.
 *
 * Por que este arquivo existe: os PINs estavam escritos direto no código, num array
 * em escopo de módulo (`ProfileView.tsx`) e em texto no JSX da tela de login
 * (`App.tsx`). O guard `import.meta.env.DEV` esconde a interface, não remove o dado:
 * num build gerado por engano em modo desenvolvimento — o que já aconteceu neste
 * projeto — as senhas iam para o pacote e ficavam legíveis para qualquer visitante,
 * e o login é por nome de perfil, então PIN vazado é conta tomada.
 *
 * A lista agora vem de VITE_DEMO_PINS, que só existe na máquina de quem desenvolve.
 * Sem a variável (qualquer build de produção), a lista nasce vazia e a interface
 * desaparece por consequência, não por lembrança de quem escreveu o componente.
 *
 * Formato: "Nome:PIN:Rótulo,Nome:PIN:Rótulo"
 * Exemplo no .env local: VITE_DEMO_PINS="João Silva:1234:Aluno,Admin Superior:9999:Admin"
 *
 * Isto NÃO é o lugar de credencial real: os PINs de demonstração devem ser trocados
 * em produção (ver relatório em docs/security-audit/), e nenhum valor padrão é
 * embutido aqui de propósito — default silencioso é o que transforma senha de
 * exemplo em senha de produção.
 */

export interface DemoProfile {
  name: string;
  pin: string;
  label: string;
}

function parseDemoProfiles(raw: unknown): DemoProfile[] {
  // Fora de desenvolvimento a lista é vazia mesmo que a variável exista, para que
  // um .env mal copiado no servidor de build não reative o atalho.
  if (!import.meta.env.DEV) return [];
  if (typeof raw !== 'string' || raw.trim() === '') return [];

  return raw
    .split(',')
    .map((entrada) => entrada.split(':').map((parte) => parte.trim()))
    .filter((partes): partes is [string, string, string] =>
      partes.length === 3 && partes.every((parte) => parte !== '')
    )
    .map(([name, pin, label]) => ({ name, pin, label }));
}

export const demoProfiles: DemoProfile[] = parseDemoProfiles(
  import.meta.env.VITE_DEMO_PINS
);
