// Erro de API padronizado. O errorHandler central converte instâncias desta classe em
// { error: true, code, message } — nunca expõe stack trace ou detalhes internos ao cliente.
export class ApiError extends Error {
  constructor(
    public status: number,
    public code: string,
    message: string
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

export const Errors = {
  unauthorized: (message = 'Autenticação necessária para acessar este recurso.') =>
    new ApiError(401, 'UNAUTHORIZED', message),
  forbidden: (message = 'Acesso não permitido.') => new ApiError(403, 'FORBIDDEN', message),
  accountBlocked: () =>
    new ApiError(
      403,
      'ACCOUNT_BLOCKED',
      'Seu acesso à Escola Estadual da Cultura foi suspenso. Entre em contato com a coordenação para mais informações.'
    ),
  accountPending: () =>
    new ApiError(
      403,
      'ACCOUNT_PENDING_CONFIRMATION',
      'Seu cadastro ainda está aguardando confirmação. Você poderá acessar as áreas internas assim que for homologado pela coordenação.'
    ),
  notFound: (message = 'Recurso não encontrado.') => new ApiError(404, 'NOT_FOUND', message),
  conflict: (message: string) => new ApiError(409, 'CONFLICT', message),
  badRequest: (message = 'Requisição inválida.') => new ApiError(400, 'BAD_REQUEST', message),
  validation: (message: string) => new ApiError(400, 'VALIDATION_ERROR', message),
  tooManyRequests: (message = 'Muitas tentativas em um curto período. Tente novamente mais tarde.') =>
    new ApiError(429, 'RATE_LIMITED', message),
  internal: (message = 'Erro interno do servidor.') => new ApiError(500, 'INTERNAL_ERROR', message),
};
