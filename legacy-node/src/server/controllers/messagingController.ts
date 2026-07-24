import type { Response } from 'express';
import { asyncHandler } from '../utils/asyncHandler';
import * as messagingService from '../services/messagingService';
import type { AuthedRequest } from '../middlewares/auth';

export const listChatMessages = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.json(await messagingService.listChatMessages(req.query.sessionId as string | undefined));
});

export const createChatMessage = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.status(201).json(await messagingService.createChatMessage(req.body, req.user!));
});

export const listDirectMessages = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.json(await messagingService.listDirectMessages(req.user!, req.query.studentName as string | undefined));
});

export const createDirectMessage = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.status(201).json(await messagingService.createDirectMessage(req.body, req.user!));
});
