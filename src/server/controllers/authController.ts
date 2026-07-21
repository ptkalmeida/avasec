import type { Response } from 'express';
import { asyncHandler } from '../utils/asyncHandler';
import { getPageParams, paginatedResponse } from '../utils/pagination';
import { logAudit } from '../services/auditService';
import * as authService from '../services/authService';
import type { AuthedRequest } from '../middlewares/auth';
import { Errors } from '../utils/ApiError';

export const register = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const result = await authService.registerUser(req.body, req.user);
  await logAudit(req, 'Cadastro de Usuário', `Nova conta criada: ${result.user.name} (${result.user.role}, status ${result.user.status}).`);
  res.status(201).json(result);
});

export const login = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const identifier = req.body.email || req.body.name;
  try {
    const result = await authService.loginUser(req.body);
    await logAudit(req, 'Autenticação no Sistema', `Login efetuado com sucesso.`, 'SUCCESS', {
      name: result.user.name,
      role: result.user.role,
    });
    res.json(result);
  } catch (err) {
    await logAudit(req, 'Tentativa Fracassada', `Falha de login para o identificador: ${identifier}.`, 'FAILED');
    throw err;
  }
});

export const me = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const user = await authService.getCurrentUser(req.user!.sub);
  res.json(user);
});

export const changePassword = asyncHandler(async (req: AuthedRequest, res: Response) => {
  await authService.changePassword(req.user!.sub, req.body.newPassword, req.body.currentPassword);
  await logAudit(req, 'Alteração de Senha', 'Senha alterada pelo próprio usuário.');
  res.json({ success: true });
});

export const listUsers = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const { role } = req.query as { role?: string };
  const pageParams = getPageParams(req);

  // Escopo por perfil: aluno não lista outros alunos; instrutor só vê os alunos dos
  // próprios cursos; administrador vê a base completa.
  if (role === 'student') {
    if (req.user!.role === 'student') {
      throw Errors.forbidden('Alunos não podem listar dados de outros alunos.');
    }
    if (req.user!.role === 'instructor') {
      const { items, total } = await authService.listStudentsForInstructor(req.user!.name, pageParams.skip, pageParams.take);
      res.json(paginatedResponse(items, total, pageParams));
      return;
    }
  }

  const { items, total } = await authService.listUsersByRole(role, pageParams.skip, pageParams.take);
  res.json(paginatedResponse(items, total, pageParams));
});

export const updateStatus = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const { id } = req.params;
  const { status } = req.body;
  const updated = await authService.updateAccountStatus(id, status);
  await logAudit(req, 'Alteração de Status de Conta', `Status de "${updated.name}" alterado para "${status}".`);
  res.json(updated);
});

export const removeUser = asyncHandler(async (req: AuthedRequest, res: Response) => {
  if (req.params.id === req.user!.sub) {
    throw Errors.badRequest('Você não pode remover a própria conta administrativa.');
  }
  await authService.deleteUser(req.params.id);
  await logAudit(req, 'Exclusão de Usuário', `Usuário ${req.params.id} removido.`);
  res.json({ success: true });
});
