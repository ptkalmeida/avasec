import type { Response } from 'express';
import { asyncHandler } from '../utils/asyncHandler';
import * as catalogService from '../services/catalogService';
import type { AuthedRequest } from '../middlewares/auth';

export const listLibraryItems = asyncHandler(async (_req: AuthedRequest, res: Response) => {
  res.json(await catalogService.listLibraryItems());
});

export const createLibraryItem = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.status(201).json(await catalogService.createLibraryItem(req.body));
});

export const listWebinars = asyncHandler(async (_req: AuthedRequest, res: Response) => {
  res.json(await catalogService.listWebinars());
});

export const createWebinar = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.status(201).json(await catalogService.createWebinar(req.body));
});
